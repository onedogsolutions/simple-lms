# Implementation Blueprint: PMPro Registration Sync Phase Reorder

## Problem Statement

The current migration pipeline runs PMPro Registration Sync as **Phase 4** (after student progress and certificates). This causes failures because Phases 2 and 3 attempt to validate user "ownership" of courses before users have actually been enrolled via PMPro. The fix is to move PMPro sync immediately after Phase 1 so that users own their courses before any progress or certificate data is migrated.

### Current Phase Order (Broken)

| Phase | Name | Method | REST Route |
|-------|------|--------|------------|
| 1 | Content Migration | `migrate_cpt_batch()` | `/migration/cpts` |
| 2 | Student Progress | `migrate_progress_batch()` | `/migration/progress` |
| 3 | Historical Certificates | `migrate_history_batch()` | `/migration/history` |
| 4 | PMPro Membership Sync | `migrate_pmpro_batch()` | `/migration/pmpro` |

### Target Phase Order (Fixed)

| Phase | Name | Method | REST Route |
|-------|------|--------|------------|
| 1 | Content Migration | `migrate_cpt_batch()` | `/migration/cpts` |
| **2** | **PMPro Registration Sync** | **`migrate_pmpro_batch()`** | **`/migration/pmpro`** |
| 3 | Student Progress | `migrate_progress_batch()` | `/migration/progress` |
| 4 | Historical Certificates | `migrate_history_batch()` | `/migration/history` |

---

## Files to Modify

| File | What Changes |
|------|-------------|
| `includes/class-migration.php` | Rewrite `migrate_pmpro_batch()` with 90-day rule, offset pagination, and updated log labels |
| `includes/class-rest.php` | No route changes needed (routes are independent), but update comments |
| `src/admin/components/MigrationTool.js` | Reorder the UI panels, update phase numbering, update state machine gating logic |

---

## Part 1: PHP Backend Changes (`includes/class-migration.php`)

### Step 1.1 — Rewrite `migrate_pmpro_batch()` with Offset Pagination and 90-Day Rule

**Location:** `includes/class-migration.php`, lines 993-1212

Replace the **entire** `migrate_pmpro_batch()` method with a new implementation. The method signature MUST change to accept an `$offset` parameter:

```php
public static function migrate_pmpro_batch($limit = 10, $offset = 0)
```

#### 1.1.1 — Method Signature and Opening Guards

```
function migrate_pmpro_batch($limit = 10, $offset = 0)
  $limit  = absint($limit)
  $offset = absint($offset)
  Log: "Phase 2: Starting PMPro registration sync (limit=$limit, offset=$offset)."
  Start timer.

  Guard: if !class_exists('GFAPI') → return error payload with log.
  Guard: if !function_exists('pmpro_changeMembershipLevel') → return error payload with log.
```

**CRITICAL:** Change the log prefix from `"Phase 4"` to `"Phase 2"` everywhere in this method.

#### 1.1.2 — Query GF Form 2 Entries with Strict Offset Pagination

Remove the current "self-advancing offset with max_scans" loop (lines 1032-1060). Replace it with a single, deterministic GFAPI query using the caller-provided `$offset`:

```
$gf_form_id      = 2;
$course_field_id  = 20;

$search_criteria = ['status' => 'active'];
$sorting         = ['key' => 'id', 'direction' => 'ASC'];
$paging          = ['offset' => $offset, 'page_size' => $limit];

$entries = GFAPI::get_entries($gf_form_id, $search_criteria, $sorting, $paging);
```

This is the key architectural fix. The old code tried to skip already-migrated entries internally, leading to an infinite scan loop. The new code fetches a deterministic page and always advances `$offset` forward by the number of entries fetched, regardless of whether individual entries were already migrated.

#### 1.1.3 — Process Each Entry with the 90-Day Rule

For each `$entry` in the fetched page:

```
1. Check if already migrated:
   $migrated = gform_get_meta($entry['id'], '_slms_pmpro_migrated');
   if ($migrated) { $skipped++; continue; }

2. Resolve user:
   $user_id = $entry['created_by'] (int) or 0
   If $user_id === 0:
     Loop entry fields, find any value that is_email()
     $wp_user = get_user_by('email', $email_value)
     $user_id = $wp_user->ID
   If still no user: log warning, mark entry as _slms_pmpro_migrated, $count++, continue.

3. Extract legacy course IDs from checkbox field 20:
   Loop $i = 1..20:
     $val = rgar($entry, "20.$i")
     if (!empty($val) && is_numeric($val)): $legacy_course_ids[] = (int)$val
   If empty: log debug, mark as migrated, $count++, continue.

4. Build (or reuse cached) legacy course map:
   $legacy_map = self::build_legacy_course_map()  // same as current

5. Calculate days elapsed since purchase:
   $entry_date      = $entry['date_created']
   $entry_timestamp = strtotime($entry_date)
   $now             = current_time('timestamp')
   $days_elapsed    = floor(($now - $entry_timestamp) / DAY_IN_SECONDS)

6. THE 90-DAY RULE — For each $legacy_id in $legacy_course_ids:
   Look up $legacy_map[$legacy_id] → { new_course_id, level_ids }
   If not found: log warning, continue.

   IF $days_elapsed > 90:
     // EXPIRED PATH — create a historical order but NO active access.
     a. Create a PMPro order record for audit trail:
        $order = new \MemberOrder()
        $order->user_id         = $user_id
        $order->membership_id   = $level_id  (first from level_ids)
        $order->subtotal        = <price from level>
        $order->total           = <price from level>
        $order->status          = 'success'
        $order->gateway         = 'free'          // historical import
        $order->gateway_environment = 'sandbox'
        $order->payment_type    = 'Migration Import'
        $order->datetime        = $entry_date      // preserve original date
        $order->notes           = "Migrated from GF entry #{$entry_id}. Access expired (purchased {$days_elapsed} days ago)."
        $order->saveOrder()

     b. Do NOT call pmpro_changeMembershipLevel() — no active level granted.
     c. DO enroll in SimpleLMS for record-keeping (read-only historical):
        Relationships::enroll_user($user_id, $new_course_id, 'pmpro_migration_expired')
     d. Log: "{$user_label}: historical order created for level {$level_id} (expired, {$days_elapsed} days old). No active access granted."

   ELSE ($days_elapsed <= 90):
     // ACTIVE PATH — grant remaining days.
     a. $remaining_days = 90 - $days_elapsed
        $enddate = gmdate('Y-m-d H:i:s', $now + ($remaining_days * DAY_IN_SECONDS))

     b. Call pmpro_changeMembershipLevel():
        $level_params = [
          'user_id'       => $user_id,
          'membership_id' => $level_id,
          'enddate'       => $enddate,
        ]
        pmpro_changeMembershipLevel($level_params, $user_id)

     c. Enroll in SimpleLMS:
        Relationships::enroll_user($user_id, $new_course_id, 'pmpro_migration')

     d. Log: "{$user_label}: enrolled in PMPro level {$level_id} with {$remaining_days} days remaining (enddate={$enddate})."

7. Mark entry as migrated:
   gform_update_meta($entry_id, '_slms_pmpro_migrated', time())
   $count++
```

#### 1.1.4 — Return Payload with Next Offset

The return payload MUST include the `offset` key so the frontend can advance pagination:

```php
$next_offset = $offset + count($entries);
$pending     = self::get_pending_pmpro_count();
$duration    = round(microtime(true) - $start_time, 2);

self::log(sprintf(
    'Phase 2 batch complete: processed=%d, skipped=%d, enrolled=%d, expired=%d, duration=%ss.',
    $count, $skipped, $enrolled_active, $enrolled_expired, $duration
));

return [
    'processed' => $count,
    'pending'   => $pending,
    'total'     => $count + $pending,
    'enrolled'  => $enrolled_active,
    'expired'   => $enrolled_expired,
    'offset'    => $next_offset,
    'duration'  => $duration,
    'success'   => true,
    'status'    => (empty($entries) || $pending === 0) ? 'complete' : 'processing',
    'log'       => self::flush_log(),
];
```

**IMPORTANT:** When `$entries` comes back empty from GFAPI (no more rows), return `'status' => 'complete'` to stop the frontend loop.

### Step 1.2 — Update `reset_pmpro_migration()` Log Label

**Location:** `includes/class-migration.php`, line 1555

Change:
```php
self::log('Phase 4 reset: removed ' . (int)$deleted . ' migration markers.', 'info');
```
To:
```php
self::log('Phase 2 reset: removed ' . (int)$deleted . ' migration markers.', 'info');
```

### Step 1.3 — Update `migrate_progress_batch()` Log Label

**Location:** `includes/class-migration.php`, line 316

Change:
```php
self::log('Phase 2: Starting student progress migration (limit=' . $limit . ').');
```
To:
```php
self::log('Phase 3: Starting student progress migration (limit=' . $limit . ').');
```

Also update the completion log line (around line 443-447 area) from `"Phase 2 complete"` to `"Phase 3 complete"`.

### Step 1.4 — Update `migrate_history_batch()` Log Label

**Location:** `includes/class-migration.php`, around line 800

Change:
```php
self::log('Phase 3: Starting historical certificate migration ...');
```
To:
```php
self::log('Phase 4: Starting historical certificate migration ...');
```

Also update its completion log line from `"Phase 3 complete"` to `"Phase 4 complete"`.

---

## Part 2: REST API Route Changes (`includes/class-rest.php`)

### Step 2.1 — Add `offset` Parameter to PMPro Endpoint

**Location:** `includes/class-rest.php`, lines 263-279

The PMPro migration endpoint currently does NOT accept an `offset` parameter. Add it:

```php
// PMPro Migration endpoint (Phase 2)
register_rest_route(self::NAMESPACE, '/migration/pmpro', array(
    'methods'  => 'POST',
    'callback' => function ($request) {
        $limit  = $request->get_param('limit') ?? 10;
        $offset = $request->get_param('offset') ?? 0;
        return rest_ensure_response(Migration::migrate_pmpro_batch($limit, $offset));
    },
    'args' => array(
        'limit' => array(
            'sanitize_callback' => 'absint',
            'default' => 10,
        ),
        'offset' => array(
            'sanitize_callback' => 'absint',
            'default' => 0,
        ),
    ),
    'permission_callback' => function () {
        return current_user_can('manage_options');
    }
));
```

### Step 2.2 — Update Route Comments

Update the comment above the PMPro route from `// Phase 4` to `// Phase 2`.
Update the comment above the progress route from its current label to `// Phase 3`.
Update the comment above the history route from its current label to `// Phase 4`.

No route paths change. The React frontend already references these routes by name, not by number.

---

## Part 3: React Frontend Changes (`src/admin/components/MigrationTool.js`)

### Step 3.1 — Update the File Header Comment

**Location:** Lines 1-10

Change the phase listing in the JSDoc comment to:
```
Phase 1: CPT Migration (Legacy Pods -> New CPTs)
Phase 2: PMPro Registration Sync (GF Form 2 -> PMPro Levels)
Phase 3: Student Progress Migration (WPComplete -> New DB)
Phase 4: Historical Certificate Migration (Gravity Forms)
```

### Step 3.2 — Update the Phase State Comment

**Location:** Line 28

Change:
```js
const [phase, setPhase] = useState(0); // 0 = idle, 1 = content, 2 = progress, 3 = history, 4 = pmpro
```
To:
```js
const [phase, setPhase] = useState(0); // 0 = idle, 1 = content, 2 = pmpro, 3 = progress, 4 = history
```

### Step 3.3 — Update `runMigration()` Phase Number Mapping

**Location:** Lines 136-169

Update the `activePhase` mapping so the internal phase numbers match the new order:

```js
const runMigration = async (type) => {
    setMigrating(true);
    setError(null);
    setNotice(null);

    let activePhase = 0;
    if (type === 'content')  activePhase = 1;
    if (type === 'pmpro')    activePhase = 2;  // was 4, now 2
    if (type === 'progress') activePhase = 3;  // was 2, now 3
    if (type === 'history')  activePhase = 4;  // was 3, now 4

    setPhase(activePhase);

    let endpoint = '';
    if (type === 'content')  endpoint = '/simple-lms/v1/migration/cpts';
    if (type === 'pmpro')    endpoint = '/simple-lms/v1/migration/pmpro';
    if (type === 'progress') endpoint = '/simple-lms/v1/migration/progress';
    if (type === 'history')  endpoint = '/simple-lms/v1/migration/history';
    // ... rest unchanged
```

### Step 3.4 — Update Completion Notice Messages

**Location:** Lines 262-278

Update the notice text to match new phase numbers:

```js
if (type === 'content')  noticeMessage = 'Phase 1: Content migration completed successfully.';
if (type === 'pmpro')    noticeMessage = 'Phase 2: PMPro Registration Sync completed successfully.';
if (type === 'progress') noticeMessage = 'Phase 3: Student Progress migration completed successfully.';
if (type === 'history')  noticeMessage = 'Phase 4: Historical Certificates migration completed successfully.';
```

### Step 3.5 — Update Phase Completion Gating Logic

**Location:** Lines 299-331

This is the critical change that controls which buttons are enabled. Update the derived state:

```js
const contentPending  = status?.content?.pending || 0;
const pmproPending    = status?.pmpro?.pending || 0;      // moved up
const progressPending = status?.progress?.pending || 0;    // moved down
const historyPending  = status?.history?.pending || 0;

// Phase 1 complete check (unchanged)
const isPhase1Complete = contentPending === 0 && totals.content >= 0;

// NEW Phase 2: PMPro Sync — unlocked when Phase 1 completes
let isPhase2Complete = false;
if (isPhase1Complete && pmproPending === 0 && totals.pmpro >= 0) {
    isPhase2Complete = true;
}

// Phase 3: Student Progress — unlocked when Phase 2 (PMPro) completes
let isPhase3Complete = false;
if (isPhase2Complete && progressPending === 0 && totals.progress >= 0) {
    isPhase3Complete = true;
}

// Phase 4: Historical Certificates — unlocked when Phase 3 completes
let isPhase4Complete = false;
if (isPhase3Complete && historyPending === 0) {
    isPhase4Complete = true;
}
```

### Step 3.6 — Reorder the JSX Phase Panels

The four phase `<div>` blocks in the JSX return must be reordered. The current order in the JSX is:

1. Phase 1 (Content) — lines 375-458
2. Phase 2 (Progress) — lines 460-576
3. Phase 3 (History) — lines 578-693
4. Phase 4 (PMPro) — lines 696-817

The new JSX order must be:

1. **Phase 1 (Content)** — keep as-is, no changes
2. **Phase 2 (PMPro Registration Sync)** — move old Phase 4 block here, then update:
   - Circle badge number: `4` → `2`
   - Title: `"PMPro Membership Migration"` → `"PMPro Registration Sync"`
   - Description: `"Migrate historical access from Gravity Forms..."` → `"Sync course purchases from GF Registration (Form 2) into PMPro membership levels. Users who purchased within 90 days get active access; older purchases get a historical receipt only."`
   - Gating: `disabled={migrating || !isPhase1Complete}` (unlocked right after Phase 1)
   - `isPhase3Complete` references → `isPhase1Complete` (this panel unlocks after Phase 1 now)
   - Completion badge: gate on `isPhase2Complete` (new variable)
   - Button busy state: `phase === 4` → `phase === 2`
   - Processing text: `"Migrating Memberships…"` → `"Syncing Registrations…"`
   - Button text: `"Start PMPro Migration"` → `"Start Registration Sync"`
   - Reset button: keep functionality, update label to `"Reset Phase 2"`
   - `resetPhase4` function: rename to `resetPhase2` (update the function and all references)
   - Purple color scheme: keep it or change to blue — developer's choice
3. **Phase 3 (Student Progress)** — move old Phase 2 block here, then update:
   - Circle badge number: `2` → `3`
   - Description: update `"Requires Phase 1 to be finished."` → `"Requires Phase 2 (Registration Sync) to be finished."`
   - Gating: `disabled={migrating || !isPhase1Complete}` → `disabled={migrating || !isPhase2Complete}`
   - Completion badge: gate on `isPhase3Complete` (new variable)
   - Button busy state: `phase === 2` → `phase === 3`
   - Locked state: check `!isPhase2Complete` instead of `!isPhase1Complete`
   - Waiting text: `"Waiting for Phase 1"` → `"Waiting for Phase 2"`
4. **Phase 4 (Historical Certificates)** — move old Phase 3 block here, then update:
   - Circle badge number: `3` → `4`
   - Description: update `"Requires Phase 2."` → `"Requires Phase 3 (Student Progress) to be finished."`
   - Gating: `disabled={migrating || !isPhase2Complete}` → `disabled={migrating || !isPhase3Complete}`
   - Completion badge: gate on `isPhase4Complete`
   - Button busy state: `phase === 3` → `phase === 4`
   - Locked state: check `!isPhase3Complete` instead of `!isPhase2Complete`
   - Waiting text: `"Waiting for Phase 2"` → `"Waiting for Phase 3"`

### Step 3.7 — Update `resetPhase4` → `resetPhase2`

**Location:** Lines 97-134

Rename the function from `resetPhase4` to `resetPhase2`. Update:

- The `window.confirm` message: `"Reset Phase 4?"` → `"Reset Phase 2? This clears all PMPro sync markers so entries can be re-processed."`
- The notice message: `"Phase 4 reset: %d markers cleared..."` → `"Phase 2 reset: %d markers cleared..."`
- All JSX references to `resetPhase4` → `resetPhase2`

---

## Part 4: Pagination Safety Checklist

The developer MUST verify the following to prevent infinite loops:

### 4.1 — PHP `migrate_pmpro_batch()` Must Always Advance

- The method MUST accept `$offset` as a parameter (not compute it internally).
- The return payload MUST include `'offset' => $next_offset` where `$next_offset = $offset + count($entries)`.
- When `GFAPI::get_entries()` returns an empty array, the method MUST return `'status' => 'complete'`.

### 4.2 — Frontend Must Pass `currentOffset` to the Endpoint

**Location:** `MigrationTool.js`, inside the `while (pending > 0)` loop (line 201-205)

The existing code already sends `offset: currentOffset` in the request data. Verify that `currentOffset` is updated from the response:

```js
currentOffset = res.offset || 0;
```

This line already exists at line 213. It will pick up the `offset` key from the new return payload. **No change needed here** — just verify it works.

### 4.3 — Stall Detection Remains Active

The existing stall detection (3 consecutive zero-progress batches) at lines 221-235 will continue to work. No changes needed.

---

## Part 5: PMPro MemberOrder Object Reference

When creating historical (expired) order records, the developer needs to use the PMPro `MemberOrder` class. Here is the correct usage pattern:

```php
if (class_exists('MemberOrder')) {
    $order = new \MemberOrder();
    $order->user_id              = $user_id;
    $order->membership_id        = $level_id;
    $order->subtotal             = $level_price;
    $order->total                = $level_price;
    $order->status               = 'success';
    $order->gateway              = 'free';
    $order->gateway_environment  = 'sandbox';
    $order->payment_type         = 'Migration Import';
    $order->notes                = "Migrated from GF entry #{$entry_id}. Expired ({$days_elapsed} days ago).";

    // Preserve the original purchase date.
    $order->timestamp            = $entry_timestamp;

    $order->saveOrder();
}
```

**Fallback:** If `MemberOrder` is not available (very old PMPro version), log a warning and skip the order creation — but still mark the entry as `_slms_pmpro_migrated` so it does not stall.

To get the level price for the order record, query the PMPro levels table:

```php
global $wpdb;
$level_price = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT initial_payment FROM {$wpdb->prefix}pmpro_membership_levels WHERE id = %d",
    $level_id
));
```

---

## Part 6: Testing Protocol

After implementation, verify the following scenarios:

### 6.1 — Phase Gating

1. Load the Migration Hub with all phases pending.
2. Verify only Phase 1 button is enabled. Phases 2, 3, 4 show "Locked".
3. Complete Phase 1. Verify Phase 2 (PMPro Sync) button unlocks. Phases 3, 4 remain locked.
4. Complete Phase 2. Verify Phase 3 (Progress) button unlocks. Phase 4 remains locked.
5. Complete Phase 3. Verify Phase 4 (Certificates) button unlocks.

### 6.2 — 90-Day Rule

1. Create a GF Form 2 test entry dated **30 days ago**. Run Phase 2. Verify:
   - User gets an active PMPro membership level with ~60 days remaining.
   - `Relationships::enroll_user()` was called with source `'pmpro_migration'`.
2. Create a GF Form 2 test entry dated **120 days ago**. Run Phase 2. Verify:
   - User does NOT get an active PMPro membership.
   - A `MemberOrder` record exists with `status = 'success'` and original date.
   - `Relationships::enroll_user()` was called with source `'pmpro_migration_expired'`.

### 6.3 — Pagination Safety

1. Reset Phase 2. Set 50+ entries in GF Form 2.
2. Run Phase 2 with `limit=5`.
3. Verify in the log that batches process sequentially (offset 0, 5, 10, 15...) and the migration completes without stalling.
4. Verify no infinite loops: the frontend `while` loop terminates when `res.status === 'complete'`.

### 6.4 — Reset Functionality

1. After Phase 2 completes, click "Reset Phase 2".
2. Verify all `_slms_pmpro_migrated` GF entry meta is deleted.
3. Verify the pending count returns to the original total.
4. Re-run Phase 2 and verify it processes all entries again.

---

## Summary of All Code Changes

| File | Action | Lines Affected |
|------|--------|---------------|
| `includes/class-migration.php` | Rewrite `migrate_pmpro_batch()` — add `$offset` param, 90-day branching, `MemberOrder` for expired, strict offset return | ~993-1212 |
| `includes/class-migration.php` | Update log labels: Phase 4→2, Phase 2→3, Phase 3→4 | ~316, ~447, ~800, ~970, ~996, ~1197, ~1555 |
| `includes/class-rest.php` | Add `offset` arg to PMPro route, update comments | ~263-279 |
| `src/admin/components/MigrationTool.js` | Reorder JSX panels (PMPro→2, Progress→3, History→4) | ~375-817 |
| `src/admin/components/MigrationTool.js` | Update phase number mapping in `runMigration()` | ~142-153 |
| `src/admin/components/MigrationTool.js` | Update gating logic (`isPhase2Complete` = PMPro, etc.) | ~299-331 |
| `src/admin/components/MigrationTool.js` | Update notice messages to match new numbering | ~262-278 |
| `src/admin/components/MigrationTool.js` | Rename `resetPhase4` → `resetPhase2`, update confirm/notice text | ~97-134 |
| `src/admin/components/MigrationTool.js` | Update header comment and phase state comment | ~1-10, ~28 |
