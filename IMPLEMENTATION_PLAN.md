# SimpleLMS Bridge — Bug Fix Implementation Plan

**Date:** 2026-02-28
**Author:** Opus (Architect)
**Implementer:** Sonnet (Lead Developer)
**Scope:** 3 critical bugs + PHP 8.x deprecation hardening

---

## Executive Summary

After a full audit of the codebase, I found:

1. **Bug 1 (Student Manager 500)** — The screen itself is clean (renders a React mount point). The real risk is in `class-pmpro.php` which uses PHP `use function` statements that import `pmpro_hasMembershipLevel` and `pmpro_getLevel` **at file-load time** (line 22-23). Since `class-pmpro.php` is `require_once`'d unconditionally (line 33 of the bootstrap), PHP 8.x will trigger a fatal if PMPro is deactivated and any codepath touches those imported symbols. The conditional `function_exists()` check on line 61 of the bootstrap only guards `PMPro::init()` — it does NOT prevent the file from loading and the `use function` declarations from being evaluated.

2. **Bug 2 (Infinite loop in Phase 2/3)** — The migration uses a `pending`-count-driven loop, NOT an offset-driven one. The loop (`while (pending > 0)`) relies on each batch **deleting** the WPComplete meta keys it processes (Phase 2, line 408) or **setting** a `_lms_history_migrated` flag (Phase 3, line 921). If any user's meta fails to delete (permissions, DB error, serialization bug), `get_pending_migration_count()` returns the same number forever. The frontend stall detection (3 zero-progress batches) is the only safety net, but it throws an error instead of gracefully completing. The root cause: **Phase 2's SQL has no `LIMIT` offset advancement** — it always queries from row 0, so if a user's meta can't be parsed/deleted, that same user is re-fetched every batch.

3. **Bug 3 (PHP 8.x warnings)** — The codebase already casts most `strpos`/`str_replace` arguments to `(string)`. However, several edge cases remain where `$form['title'] ?? ''` or `$entry['date_created'] ?? ''` could still propagate `null` into string functions. Additionally, the `_load_textdomain_just_in_time` notice is a WordPress core issue triggered by calling `__()` during the `init` hook before textdomains are loaded.

---

## Bug 1: Student Manager 500 / PMPro Fatal Error

### Root Cause

**File:** `includes/class-pmpro.php`, lines 17-25

```php
use function pmpro_getLevel;
use function pmpro_hasMembershipLevel;
```

These `use function` imports are declarations, not calls — PHP resolves them at first use within the class, not at file parse time. However, because the file is loaded unconditionally via `require_once` on line 33 of `simple-lms-bridge.php`, any method that references these imported names will fatal if PMPro is not active.

The `PMPro::init()` call IS guarded by `function_exists('pmpro_getMembershipLevelForUser')` on line 61, but:
- Other codepaths call `PMPro::has_course_access()` or `PMPro::get_courses_for_level()` **outside** of init (e.g., migration Phase 2 at line 506 of `class-migration.php`).
- The admin column renderer `render_admin_columns` (line 235) calls `pmpro_getLevel()` conditionally — this one is safe.
- `has_course_access()` (line 273) already has `function_exists('pmpro_hasMembershipLevel')` — also safe.

**The true danger vector:** If ANY code path calls a PMPro method without a `function_exists` guard, the `use function` import will resolve to an undefined function at runtime, causing a fatal. The `use function` statements themselves are harmless at parse time, but they shadow the global namespace — if a developer later removes a `function_exists` guard, it will silently fail.

### Implementation Steps

#### Step 1.1: Remove `use function` imports for PMPro functions
**File:** `includes/class-pmpro.php`

Remove lines 21-22:
```php
// REMOVE these lines:
use function pmpro_getLevel;
use function pmpro_hasMembershipLevel;
```

These are dangerous because they create an implicit contract that PMPro is loaded. Instead, all call sites should use the fully-qualified `\pmpro_*()` form with explicit `function_exists()` guards.

#### Step 1.2: Update all PMPro function call sites to use backslash-prefixed calls with guards

In `class-pmpro.php`, audit every method and ensure each PMPro function call:
1. Uses `\pmpro_hasMembershipLevel()` (backslash-prefixed) instead of the imported `pmpro_hasMembershipLevel()`.
2. Is wrapped in `function_exists()` or lives inside a method that is only called after a `function_exists()` gate.

**Specific locations to fix:**

| Line | Current | Fix |
|------|---------|-----|
| 282 | `return pmpro_hasMembershipLevel(...)` | Already guarded by `function_exists` on line 273. Change to `\pmpro_hasMembershipLevel(...)` for explicitness. |
| 236 | `$level = pmpro_getLevel($level_id)` | Already guarded by `function_exists('pmpro_getLevel')` on line 235. Change to `\pmpro_getLevel(...)` for explicitness. |
| 375 | `$current_level = \pmpro_getMembershipLevelForUser(...)` | Already backslash-prefixed and guarded on line 372. No change needed. |

#### Step 1.3: Ensure `class-migration.php` PMPro calls are safe

**File:** `includes/class-migration.php`

**Line 506** — `PMPro::has_course_access()`:
```php
if (class_exists(__NAMESPACE__ . '\\PMPro') && PMPro::has_course_access($user_id, $course_id)) {
```
This is safe because `has_course_access()` internally checks `function_exists('pmpro_hasMembershipLevel')`. No change needed.

**Lines 983, 1189** — Phase 4 PMPro calls:
Already properly guarded with `function_exists('pmpro_changeMembershipLevel')`. No change needed.

#### Step 1.4: Fix the textdomain loading notice

**File:** `simple-lms-bridge.php`

The `_load_textdomain_just_in_time` notice fires because `__()` is called during `init` (priority 10) before WordPress has loaded plugin textdomains. Fix by loading the textdomain explicitly:

Add to `slms_init()` (before any `__()` calls):
```php
load_plugin_textdomain('simple-lms-bridge', false, dirname(SLMS_PLUGIN_BASENAME) . '/languages');
```

Alternatively, if the plugin doesn't ship translation files yet, this can be deferred — the notice is cosmetic, not a fatal.

---

## Bug 2: Migration Infinite Loop (Phase 2 & Phase 3)

### Root Cause Analysis

**This is NOT an offset-based system.** The architecture uses a "pending count" model:

- **Phase 2** queries `SELECT DISTINCT user_id FROM usermeta WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%' LIMIT N`. After processing a user, it calls `delete_user_meta($user_id, $key)` (line 408) to remove the WPComplete keys. Next batch, the same SQL returns fewer users.
- **Phase 3** queries `get_users()` with `meta_compare => 'NOT EXISTS'` on `_lms_history_migrated`. After processing, it calls `update_user_meta($user_id, '_lms_history_migrated', time())` (line 921). Next batch, the same query returns fewer users.

**The infinite loop happens when:**
1. Phase 2: A user's WPComplete meta fails to delete (DB permissions, row lock, or the bulk `wpcomplete` key contains sub-entries that get partially processed but the key itself is not deleted because processing continues to the next iteration of the inner loop, then `delete_user_meta` on line 408 runs inside the foreach — but the outer `wpcomplete` key is also handled on line 374-390, yet is only deleted on line 408 which is **inside the else branch**, not the `$key === 'wpcomplete'` branch). **This is the critical bug: the bulk `wpcomplete` key (line 374) is never deleted.** Only individual `wpcomplete_*` keys (line 408) are deleted. The bulk key persists forever.
2. Phase 3: A user meta update fails silently, leaving the user in the "not migrated" pool.

### Implementation Steps

#### Step 2.1: Fix Phase 2 — Delete the bulk `wpcomplete` key after processing

**File:** `includes/class-migration.php`

**Current code (lines 374-408):**
The `foreach ($wpc_metas as $meta)` loop handles two cases:
- `$key === 'wpcomplete'` (lines 374-391): Iterates sub-entries but **never deletes the meta key**.
- `else` (lines 392-408): Handles `wpcomplete_*` keys and deletes them on line 408.

**Fix:** After the `if ($key === 'wpcomplete' && is_array($data))` block processes all sub-entries, add a `delete_user_meta($user_id, 'wpcomplete')` call. Place this **after** line 390 (end of the foreach over `$data`), but before the `else` branch:

```php
// After line 390, add:
delete_user_meta($user_id, $key); // Delete the bulk 'wpcomplete' key
```

**Simplest fix:** Move the existing `delete_user_meta($user_id, $key)` on line 408 to **after** the entire `if/else` block so it runs for BOTH branches. Currently it's inside the `else`. Restructure to:

```php
foreach ($wpc_metas as $meta) {
    $key = $meta->meta_key;
    $value = $meta->meta_value;

    // ... parsing logic ...

    if ($key === 'wpcomplete' && is_array($data)) {
        // ... process bulk entries ...
    } else {
        // ... process individual wpcomplete_* entries ...
    }

    // DELETE MUST HAPPEN FOR BOTH BRANCHES:
    delete_user_meta($user_id, $key);
}
```

#### Step 2.2: Add a hard safety limit to the Phase 2 SQL query

**File:** `includes/class-migration.php`, line 308

The method signature is `migrate_progress_batch($limit = -1)`. A default of `-1` means **unlimited** if called directly. While the REST endpoint passes `10`, the legacy `run_wpc_migration()` on line 173 passes `100`, and a bare call uses `-1`.

**Fix:** Change the default to `10` and enforce a ceiling:

```php
public static function migrate_progress_batch($limit = 10)
{
    $limit = max(1, min(absint($limit), 100)); // Clamp 1-100
```

#### Step 2.3: Add a processed-users tracking set to prevent re-processing

**File:** `includes/class-migration.php`

In Phase 2, if the `delete_user_meta` fix above somehow still fails, add a fallback: track user IDs that were attempted in this batch via a `NOT IN` clause or a transient.

**Lightweight approach:** After processing each user, regardless of success, set a transient or user meta flag `_slms_wpc_processing` to prevent re-selection. This is a belt-and-suspenders approach on top of the `delete_user_meta` fix.

**However**, the primary fix (Step 2.1) should resolve 100% of cases. Only implement this step if testing reveals edge cases.

#### Step 2.4: Fix Phase 3 — Validate `update_user_meta` success

**File:** `includes/class-migration.php`, line 921

Currently:
```php
update_user_meta($user_id, '_lms_history_migrated', time());
```

**Fix:** Add a verification step:
```php
$updated = update_user_meta($user_id, '_lms_history_migrated', time());
if (!$updated) {
    self::log('CRITICAL: Failed to set _lms_history_migrated for user ' . $user_id . '. This user will be re-processed next batch.', 'error');
}
```

This doesn't prevent the re-processing, but it surfaces the issue in the debug log. If `update_user_meta` returns false, the user lacks a `wp_users` row — handle by skipping (already done on line 812-817, but only for `get_userdata()` failures).

#### Step 2.5: Make the status response more explicit

**File:** `includes/class-migration.php`

For all four `migrate_*_batch()` return arrays, ensure the `status` field is set to `'complete'` when:
- `$pending === 0`, OR
- `$count === 0 && $pending > 0` (stuck state — nothing processed but items remain)

Currently, the stuck state returns `'processing'` which causes the frontend to loop. **Fix:**

```php
// At the end of each batch method, replace:
'status' => $pending === 0 ? 'complete' : 'processing',

// With:
'status' => ($pending === 0 || $count === 0) ? 'complete' : 'processing',
```

This ensures that if a batch processes zero items, the loop terminates server-side regardless of the pending count. The frontend stall detector (3 consecutive zero batches) provides a secondary safety net, but the backend should terminate first.

**Important caveat:** This changes the semantics — `complete` now means "done OR stuck". The frontend should inspect `processed === 0 && pending > 0` to show a warning that items were skipped.

---

## Bug 2b: Frontend Polling Loop Hardening

### Current Architecture (No Changes Needed to Core Loop)

The frontend in `MigrationTool.js` already uses a sound pattern:

```javascript
while (pending > 0) {
    const res = await apiFetch({ ... });
    pending = res.pending;          // Trust backend's pending count
    if (res.status === 'complete') break;  // Backend termination signal
    // ... stall detection (3 zero-progress batches) ...
}
```

This is **not** an offset-based loop — there is no offset state to lose. The frontend simply re-fires the same endpoint and trusts the backend to return a decreasing `pending` count. The infinite loop is caused entirely by the backend bug (Step 2.1).

### Implementation Steps

#### Step 2b.1: Enhance the stall detector to auto-complete instead of error

**File:** `src/admin/components/MigrationTool.js`, lines 150-159

Currently, 3 consecutive zero-progress batches throw an error. Change to:

```javascript
if (zeroProgressCount >= 3) {
    // Instead of throwing, log a warning and break gracefully.
    appendLog([{
        time: new Date().toLocaleTimeString(),
        level: 'warn',
        msg: `Migration stalled — ${pending} item(s) could not be processed. Check debug log.`,
    }]);
    break; // Exit the while loop gracefully
}
```

This prevents the jarring error modal while still surfacing the issue.

#### Step 2b.2: Add a hard iteration cap as ultimate safety net

**File:** `src/admin/components/MigrationTool.js`

Before the `while (pending > 0)` loop, add:

```javascript
const MAX_ITERATIONS = 500; // Safety ceiling
let iterations = 0;
```

Inside the loop:
```javascript
iterations++;
if (iterations >= MAX_ITERATIONS) {
    appendLog([{
        time: new Date().toLocaleTimeString(),
        level: 'error',
        msg: `Safety limit reached (${MAX_ITERATIONS} batches). Stopping.`,
    }]);
    break;
}
```

#### Step 2b.3: Show a warning when migration completes with remaining items

**File:** `src/admin/components/MigrationTool.js`, lines 178-184

After the while loop, check if items remain:

```javascript
if (pending > 0) {
    setNotice(sprintf(
        __('Phase completed with %d item(s) that could not be processed. Check the debug log.', 'simple-lms-bridge'),
        pending
    ));
} else {
    // existing success notice
}
```

---

## Bug 3: PHP 8.x Null Deprecation Warnings

### Analysis

The codebase is already well-guarded — nearly all `strpos()` calls use `(string)` casts. However, the following edge cases should be hardened:

#### Step 3.1: Harden `$form['title']` usages

**File:** `includes/class-migration.php`

| Line | Code | Fix |
|------|------|-----|
| 823 | `$form_title = $form['title'] ?? '';` | Already safe. No change. |
| 897 | `str_ireplace('Certificate', '', $form['title'] ?? '')` | Already safe. No change. |

**File:** `includes/class-rest.php`

| Line | Code | Fix |
|------|------|-----|
| 940 | `$form_title = $form['title'] ?? '';` | Already safe. No change. |
| 994 | `str_ireplace('Certificate', '', $form['title'] ?? '')` | Already safe. No change. |

#### Step 3.2: Harden `$entry['date_created']` usages

**File:** `includes/class-migration.php`

| Line | Code | Fix |
|------|------|-----|
| 906 | `$entry['date_created'] ?? current_time('mysql')` | Already safe. No change. |
| 1068 | `$entry_date = $entry['date_created'] ?? '';` | Already safe. No change. |

**File:** `includes/class-rest.php`

| Line | Code | Fix |
|------|------|-----|
| 1001 | `$entry['date_created'] ?? ''` | Already safe. No change. |

#### Step 3.3: Harden `$field->label` usages

**File:** `includes/class-migration.php`, line 884
**File:** `includes/class-rest.php`, line 982

Both use: `$label = $field->label ?? '';`

Already safe. No change.

#### Step 3.4: Harden the admin asset screen ID check

**File:** `simple-lms-bridge.php`, line 172

```php
$screen_id = (string)($screen->id ?? '');
```

Already safe. No change.

#### Step 3.5: Fix the textdomain loading notice

**File:** `simple-lms-bridge.php`

Add early textdomain loading. In the `slms_init()` function, add as the **first line**:

```php
load_plugin_textdomain('simple-lms-bridge', false, dirname(SLMS_PLUGIN_BASENAME) . '/languages');
```

This resolves the `_load_textdomain_just_in_time` notice by loading translations before any `__()` calls happen during `init`.

#### Step 3.6: Audit `json_decode($value ?? '', true)` patterns

**File:** `includes/class-migration.php`, line 359

```php
$data = json_decode($value ?? '', true);
```

Already uses null coalescing. Safe.

**Line 364:**
```php
$data = maybe_unserialize($value ?? '');
```

Already safe.

### Summary of PHP 8.x Findings

**The codebase is already well-hardened against null string arguments.** The original `(string)` cast approach used throughout is correct. The only actionable PHP 8.x fix is the `_load_textdomain_just_in_time` notice (Step 3.5), which is a WordPress-level issue, not a `strpos`/`str_replace` issue.

---

## Implementation Priority Order

| Priority | Step | Risk | Effort |
|----------|------|------|--------|
| **P0** | 2.1 — Delete bulk `wpcomplete` key | **Critical** — this is the infinite loop root cause | 5 min |
| **P0** | 1.1 — Remove `use function` PMPro imports | **High** — fatal on PMPro deactivation | 5 min |
| **P1** | 1.2 — Backslash-prefix all PMPro calls | Medium — defense in depth | 10 min |
| **P1** | 2.5 — Backend `complete` on zero-processed | Medium — server-side loop termination | 5 min |
| **P1** | 2b.1 — Frontend stall → graceful break | Medium — UX improvement | 5 min |
| **P2** | 2b.2 — Hard iteration cap | Low — ultimate safety net | 5 min |
| **P2** | 2.2 — Clamp batch limit default | Low — prevents unbounded queries | 2 min |
| **P2** | 2.4 — Verify update_user_meta success | Low — logging improvement | 5 min |
| **P3** | 3.5 — Textdomain loading | Cosmetic — notice suppression | 2 min |
| **P3** | 2b.3 — Partial-completion warning UX | Cosmetic — user clarity | 5 min |

---

## Testing Checklist

After implementation, verify:

- [ ] **Student Manager loads** with PMPro active
- [ ] **Student Manager loads** with PMPro deactivated (no fatal)
- [ ] **Phase 1** migration completes and returns `status: 'complete'`
- [ ] **Phase 2** migration processes all users and loop terminates
- [ ] **Phase 2** with a user whose `wpcomplete` bulk key has unparseable data — verify key is still deleted and loop does not stall
- [ ] **Phase 3** migration completes for all users
- [ ] **Phase 4** PMPro migration completes (if PMPro + GFAPI are active)
- [ ] **No PHP 8.x deprecation warnings** in `wp-content/debug.log`
- [ ] **No `_load_textdomain_just_in_time` notice** in debug log
- [ ] **Frontend stall detection** triggers graceful break after 3 zero batches (not an error modal)
- [ ] **Hard iteration cap** prevents runaway loops (test by temporarily setting MAX_ITERATIONS=3)

---

## Files to Modify

1. `includes/class-pmpro.php` — Remove `use function` imports, backslash-prefix calls
2. `includes/class-migration.php` — Fix bulk key deletion, clamp limits, improve status logic
3. `src/admin/components/MigrationTool.js` — Stall detection UX, iteration cap, partial-completion warning
4. `simple-lms-bridge.php` — Add `load_plugin_textdomain()` call

**No new files required.**
