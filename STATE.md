# Project State: Simple LMS Bridge (One Dog Solutions)

This is the living state document: architecture, current status, and
conventions. The historical log of accomplishments lives in
[`CHANGELOG.md`](./CHANGELOG.md).

## Overview

Simple LMS Bridge is a lightweight, CPT-based LMS for WordPress with a React
admin UI and native Beaver Builder integration. Courses and lessons are custom
post types linked through many-to-many join tables; enrollments, progress, and
9-year compliance history are tracked in custom tables. Paid Memberships Pro
(PMPro) drives course enrollment, and content is guarded per course
(public / enrolled / level modes) at the template, content-filter, and REST
layers. Certificates are issued natively on completion by a bundled dompdf
renderer (Stage 4); pre-Stage-4 completions still resolve through the legacy
Gravity Forms + GravityPDF path.

## Current Status

- Core feature set is in place: course/lesson CPTs, M2M relationships, PMPro
  enrollment, progress tracking, certificate issuance, and the WP Complete
  migration engine (Phases 1–4).
- **Stage 0 (Stabilize the Foundation)** hardened the plugin: fixed the student
  progress 403, corrected certificate-time membership removal, de-duplicated the
  compliance-table DDL, fixed a fatal typo, rewrote expiration to cover all
  enrollments, removed the Tailwind CDN, untracked build output, added schema
  versioning, and stood up CI. See `CHANGELOG.md` for details.
- **Stage 4 (Native Certificate Pipeline)** replaced Gravity Forms / GravityPDF
  certificate issuance with a native, dompdf-backed renderer bundled via
  Composer behind a `SimpleLMS\Certificates\Renderer` interface. New completions
  allocate a `cert_uuid`, render a branded PDF (with an embedded QR verify code)
  cached under `uploads/slms-certs/`, and drop the GF entry synthesis; legacy
  migrated links still resolve (native path checked first). Adds public
  `/certificate/{uuid}/download` and `/certificate/verify/{uuid}` routes, an
  admin-only compliance export (CSV / ZIP), and a `SimpleLMS > Tools` screen.
  Certificate assets ship via `deploy.sh` (which now runs `composer install
  --no-dev`). See `CHANGELOG.md` for details.
- **Core/Migrator Split:** the one-time WP Complete/Pods/GF→PMPro migration
  machinery (engine, Migration Tool, Debug Log, and their `/migration/*` +
  `/debug-log` REST routes) now lives in a separate, deletable companion
  plugin, `simple-lms-migrator/`, which depends on core.
  `/course-history/repair-form-ids` and `/course-history/purge-corrupted`
  stay in core (Stage 4's permanent Tools screen, not one-time migration).
  `class-user-meta.php` (a redundant native WP user-profile screen) was
  removed — legacy user-meta editing lives in the Student Manager admin and
  the dashboard profile tab. The duplicated two-stage GravityPDF URL
  resolution (both the legacy fallback path here and in Stage 4's
  native-first check) is centralized into `Certificates::pdf_url()`. See
  `CHANGELOG.md` for details.
- **Content Guarding & /me API (v1.1.0):** guarding is enforced at three
  layers by `class-guard.php` (`template_redirect`, a `the_content` fallback,
  and `rest_prepare_*` content stripping), driven by per-course
  `_lms_guard_mode` (public / enrolled / level, default from Settings). The
  incumbent `Access` API gained `can_view_course()`, `denial_reason()`, and a
  return-URL-aware `get_checkout_url()`. Students read/write their own
  progress via session-derived `/me/*` REST routes.
- **Progress Table (v1.1.0) + Backfill (v1.1.1):** lesson completions live in
  `wp_slms_lesson_progress` (Upgrade step 4), dual-written with a legacy
  `_lms_progress` meta mirror. Historical meta is imported by
  `Progress::backfill()` — idempotent, batch-paginated, reachable via
  `wp slms progress backfill`, `POST /tools/progress-backfill`, or the
  **SimpleLMS → Tools** backfill panel (with meta-vs-table parity check).
  Analytics queries read the table exclusively (no serialized-meta parsing)
  and surface a `needs_backfill` notice when the table is empty.
- **CI gates:** PHPCS (WordPress-Extra) is a required check after the
  repo-wide `phpcbf` formatting pass; PHPStan (level 5) is a hard gate with
  pre-existing findings baselined in `phpstan.neon`; PHP lint runs on
  8.1/8.2/8.3; `deploy.sh` builds both plugin zips (rsync-or-tar staging,
  `--prefer-dist` composer, 10 MB size guard).
- **Admin-UI corrections pass (post-1.1.1):** PMPro Enrollment now uses
  toggle switches (custom CSS in `index.css`, Tailwind-toggle styling — the
  full Tailwind build is deliberately NOT enqueued on CPT edit screens
  because its global preflight would reset editor styles); Enrolled
  Students renders as a Name/Email/Source table with a source badge (the
  old markup had no CSS at all, which is why values ran together); the five
  "stray meta boxes" (Student Name, Course Title, …) were the certificate
  placeholder `PanelBody`s from `CertificateTemplate.js` — now grouped and
  restyled under a "Certificate Text Placement" heading; Course/Lesson/Tools
  panel stacks are wrapped in `<Panel>`; dead `tw-`-prefixed Tailwind
  classes (no prefix is configured in the v4 build, so they compiled to
  nothing) converted to plain utilities in Tools/Settings/Analytics; unused
  `MetaBoxes::register_admin_pages()` removed. **Duplicate "Set featured
  image" button: not caused by this plugin** — `thumbnail` support is
  registered exactly once and no plugin code touches featured-image UI;
  diagnose on the live site by toggling other plugins/theme (likely a
  WP Complete/Pods-era leftover).
- **PrestoPlayer → FluentPlayer migration: dropped for now (2026-07).**
  `main` is 100% Presto and the old branch is unmergeable; if the
  migration is still wanted it must be redone against current main as a
  separately scoped project. The parked branch is untouched.
- **Release state:** `v1.1.1` tagged. Next step: the manual staging smoke test
  (activation → guarding → student journey → certificates → analytics →
  migrator dry-run → BB modules), then production rollout: backup, install
  both plugins, run the progress backfill, verify parity, spot-check students.
  Remote branch state (2026-07-18): merged branches deleted; PR #6 closed
  (its docs adopted onto the corrections branch);
  `claude/prestoplayer-fluentplayer-migration-hmprhw` remains parked
  (dropped for now — see above); `claude/wordpress-plugin-roadmap-l58oco`
  is pending manual deletion (branch deletes are blocked from CI sessions).

## Architecture

### Custom tables

| Table | Purpose | Owner of schema |
|---|---|---|
| `wp_slms_course_lesson` | M2M course ↔ lesson links (with `sort_order`) | `Relationships::create_table()` |
| `wp_slms_user_course` | Enrollments (`user_id`, `course_id`, `enrolled_at`, `source`) | `Relationships::create_table()` |
| `wp_slms_course_history` | 9-year compliance completion records | `CourseHistory::create_table()` |
| `wp_slms_lesson_progress` | Per-lesson completions (`user_id`, `course_id`, `lesson_id`, `completed_at`; unique `user_course_lesson`) | `Progress::create_table()` (Upgrade step 4) |

### Schema versioning

- `SLMS_DB_VERSION` (integer, in `simple-lms-bridge.php`) is the target schema
  version. `get_option('slms_db_version')` stores the installed version.
- `Upgrade` (`includes/class-upgrade.php`) runs on `admin_init` and on
  activation. It compares the two and runs pending incremental steps (each a
  `dbDelta` or guarded `ALTER`), updating the option after each. Step 1 creates
  all custom tables, so fresh installs and in-place updates converge on the same
  schema without reactivation.
- To change the schema: append a new step in `Upgrade::steps()` with the next
  integer key and bump `SLMS_DB_VERSION` to match.

### REST API

- Base namespace: `simple-lms/v1` (`includes/class-rest.php`).
- `GET/POST /me/progress`, `GET /me/courses` — permission
  `is_user_logged_in()`; the user is always `get_current_user_id()` (no
  caller-supplied `user_id`). Writes validate lesson-in-course and enrollment,
  go through `Progress::complete()/uncomplete()` (table + meta mirror), and
  trigger `Certificates::check_course_completion()`. The frontend complete
  button posts here.
- `POST /tools/progress-backfill` (`manage_options`) — runs one
  `Progress::backfill()` batch, returns batch stats + `parity`.
- `GET/POST /settings` (`manage_options`) — global settings consumed by the
  React Settings screen (default guard mode, checkout page, login redirect,
  certificate GF field IDs).
- `POST /progress` — permission `is_user_logged_in()`. Non-privileged callers
  can only write their own progress; the endpoint validates lesson-in-course
  and enrollment before writing.
- `GET /progress/{user_id}`, `/students`, `/forms`, `/videos`, `/analytics/*`,
  `/course-history/repair-form-ids`, `/course-history/purge-corrupted`, and
  `GET /student/{id}/history` require `edit_users`/`edit_posts`/
  `manage_options` as appropriate. Migration endpoints (`/migration/*`,
  `/debug-log`) are registered by the `simple-lms-migrator` plugin under the
  same `simple-lms/v1` namespace.

### Access control & guarding

- `Access` (`includes/class-access.php`) is the single authority:
  `can_view($user, $lesson, $course)` for lessons, `can_view_course()` for
  courses, plus drip (`is_dripped`), `denial_reason()`
  (`not_logged_in|not_enrolled|expired|dripped`), and CTA/checkout helpers.
- Guard mode per course: `_lms_guard_mode` post meta — `public` (open),
  `enrolled` (row in `wp_slms_user_course`), `level` (PMPro level via
  `PMPro::has_course_access()`); unset falls back to the Settings default.
- `Guard` (`includes/class-guard.php`) enforces at three layers:
  `template_redirect` on course/lesson singles (logged-out → login; denied →
  PMPro checkout with return URL), a priority-99 `the_content` fallback
  (excerpt + CTA; bypassed in the BB builder), and `rest_prepare_slms_course`
  / `rest_prepare_slms_lesson` filters stripping `content.rendered`.

### Progress data

- Source of truth: `wp_slms_lesson_progress` via `Progress`
  (`complete/uncomplete/get_for_user_course/stats`). Every write also mirrors
  the legacy `_lms_progress` user-meta array for back-compat readers.
- Historical meta → table import: `Progress::backfill()` (idempotent
  `INSERT IGNORE`, malformed entries counted and skipped) +
  `Progress::get_parity()`; triggers listed under REST API above.
- Analytics (`class-analytics.php`) reads the table only.

### PMPro dependency (assessed 2026-07: still required)

- **PMPro provides:** the only checkout/payment layer (`Access::
  get_checkout_url()` → `pmpro_url('checkout')`; `Guard` redirects denied
  users there); automatic enrollment/de-enrollment on level change
  (`pmpro_after_change_membership_level` → `PMPro::handle_level_change`,
  including Group Accounts children); the `level` guard mode
  (`PMPro::has_course_access()` → `pmpro_hasMembershipLevel`); expiring
  90-day access via PMPro level expiration (level change → de-enroll);
  the student-dashboard purchase-history tab (`MemberOrder`); price
  formatting.
- **Native without PMPro:** the enrollment table + manual enrollment
  (Student Manager), `public`/`enrolled` guard modes, the `Expiration`
  daily cron on `_lms_access_days`, progress tracking, certificate
  issuance and completion-time revocation (all PMPro calls are
  `function_exists`-guarded — no fatals).
- **Breaks without PMPro:** no way to sell a course (nothing else takes
  payment); guard checkout redirects dead-end; `level`-guarded courses
  deny everyone; no auto-enrollment on purchase; purchase history empty.
- **Verdict:** keep PMPro. Replacing it means replacing checkout/payments
  wholesale (e.g. WooCommerce) — a new subsystem, out of scope.

### Enrollment & expiration

- Enrollment source of truth is `wp_slms_user_course`. `_lms_enrolled_at` user
  meta is written for back-compat but is no longer read for expiration.
- `Expiration::check_expirations()` (daily cron `slms_daily_access_check`)
  iterates the enrollment table, applies `_lms_access_days` (0 = unlimited),
  and on expiry calls `Relationships::unenroll_user()`, clears
  `_lms_progress[$course_id]`, and fires `slms_course_access_expired`.

### Certificates

- **Verified trigger flow (code-confirmed):** the complete button posts to
  `POST /me/progress` (or legacy `POST /progress`) → `Progress::complete()`
  (table write + `_lms_progress` meta mirror) → `Certificates::
  check_course_completion()`, which compares the mirror against
  `_simple_lms_order`. On the first full completion it stamps
  `_lms_completed_at` (idempotency guard), fires `slms_course_completed`,
  then calls `Certificates\Issuer::issue()` to allocate a `cert_uuid`, write
  a `CourseHistory` row, and render/cache a native branded PDF (dompdf, with
  an embedded QR verify code) to `uploads/slms-certs/{uuid}.pdf` (`.htaccess`
  protected). No Gravity Forms / GravityPDF involvement for new completions.
  Finally it calls `remove_course_access()` → `PMPro::de_enroll_user()`.
- **Renderer abstraction:** `Certificates\Renderer` (interface) →
  `DompdfRenderer` (bundled under `vendor/`, loaded lazily and
  `class_exists`-guarded; swap via the `slms_certificate_renderer` filter).
  `Certificates\Template` holds the per-course `_lms_cert_template` meta and
  builds the HTML; `Certificates\Routes` serves the public download/verify
  URLs. The schema `cert_uuid` column + backfill is Upgrade step 2.
- **Legacy fallback:** rows with a `gf_entry_id` and no native PDF still resolve
  through `Certificates::pdf_url()` (two-stage GravityPDF logic, shared with
  the `slms-student-dashboard` certificates tab); the native cached PDF is
  always checked first. Two other legacy remnants exist: the
  `gform_after_submission` hook (`Certificates::handle_certificate_submission`,
  revokes access for courses with `_lms_certificate_form` set) and the
  "Certificate Gravity Form" dropdown / `certificate_gf_fields` setting.
- **Gravity PDF fallback — keep for now (assessed 2026-07).** Removal is
  gated on: (a) legacy history rows (pre-Stage-4, `gf_entry_id` set, no
  cached native PDF) getting a native re-render backfill — until then their
  only download path is GravityPDF, and 9-year compliance requires those
  PDFs stay downloadable; (b) the GF-entry field 6/18 backfill noted in the
  `pdf_url()` docblock (Stage 2 exists solely for migrated entries missing
  those fields); (c) confirming no live course still relies on
  `gform_after_submission` revocation. Recommended path: build a small
  Tools backfill that renders native PDFs for legacy rows, then delete
  `pdf_url()`, the GF hook, and the legacy dropdown in one release.
- `remove_course_access()` delegates to `PMPro::de_enroll_user()`, which removes
  **only** the course's mapped PMPro level when it matches the user's current
  level (no unconditional level reset).

### Tailwind / admin assets

- Admin styling is locally compiled only — no external CDN. `build:css` compiles
  `src/admin/tailwind.css` (Tailwind v4, `@source`/`@theme` directives) to
  `build/admin/tailwind.css`. The React admin bundle is `build/admin/index.js` +
  `index.css`. Build output is git-ignored.

## Migration Diagnostic Logging

Comprehensive logging in `simple-lms-migrator/includes/class-migration.php`
and `MigrationTool.js` (both in the `simple-lms-migrator` companion plugin) to
debug student progress import issues.

- **Triple-output logging:** every entry writes to an in-memory buffer (returned
  via REST), `wp-content/debug.log` via `error_log()`, and the persistent plugin
  log at `wp-content/uploads/slms-logs/migration.log` (protected by `.htaccess`).
- **Phase 1 (CPT):** legacy course discovery, import/dedup, lesson-linking counts.
- **Phase 2 (PMPro Registration Sync):** GF entry processing per offset,
  days-elapsed, active vs expired path, `MemberOrder` creation, level changes.
- **Phase 3 (Student Progress):** per-user processing, format detection, the
  multi-step lesson lookup, course linkage fallback, auto-enrollment, skip stats.
- **Phase 4 (History):** entry counts per user, certificate form discovery, dedup.
- **Frontend:** `MigrationTool.js` live log panel; `DebugLog.js` persistent viewer
  under **SimpleLMS > Debug Log**.

### Debugging workflow

1. Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are `true` in `wp-config.php`.
2. Run migration from the admin Migration Hub page.
3. Watch the live log panel for immediate feedback.
4. Use **SimpleLMS > Debug Log** for the persistent viewer.
5. Check `wp-content/debug.log` for `[SimpleLMS]` entries for deeper analysis.

## BB Module Architecture

### Rule

Every Beaver Builder module must be fully self-contained. Rendering must never
be delegated through a WordPress shortcode or a PHP `include()` referencing
another module's files. BB's engine injects `$settings`, `$id`, and `$module`
directly into each module's own template files.

### Required file structure (per module)

```text
{module-slug}/
├── {module-slug}.php         # FLBuilderModule class + FLBuilder::register_module()
├── css/
│   └── frontend.css          # Structural/layout CSS — BB enqueues once per page
└── includes/
    ├── frontend.php           # HTML template — BB renders per instance; $settings/$id injected
    ├── frontend.css.php       # Dynamic settings-driven CSS — rendered per instance
    └── frontend.js            # Behaviour — BB enqueues per instance
```

> Note: BB reads `js/frontend.js` into its layout-cache file and injects it at
> `wp_footer` priority `PHP_INT_MAX` (after `DOMContentLoaded`). Module behaviour
> is therefore inlined at the bottom of `includes/frontend.php` inside an IIFE
> scoped via `document.currentScript.previousElementSibling`.

Loaded modules (`slms_load_bb_modules()`): `lms-content`, `lms-outline`,
`lms-complete-button`, `lms-course-grid`, `lms-my-courses`, `lms-course-cta`,
`lms-lesson-nav`, `slms-student-dashboard`.

### Student Dashboard — HTML class reference

| Element | Class / ID |
|---|---|
| Module wrapper | `.slms-student-dashboard` |
| Tab nav list | `.slms-tabs-nav` |
| Tab button | `.slms-tab-link` (active: `.active`) |
| Tab pane | `.slms-tab-pane` (active: `.active`) |
| Profile / History / Certificates panes | `#slms-tab-profile` / `#slms-tab-history` / `#slms-tab-certificates` |
| Profile form | `.slms-profile-form` |
| All inputs + selects | `.slms-input` |
| Field labels | `.slms-field-label` |
| Submit button | `.slms-submit-btn` |
| Password fields wrapper | `#slms-password-fields` (`.slms-hidden` when inactive) |
| Two / three-column rows | `.slms-field-row.slms-two-col` / `.slms-three-col` |
| Data tables | `.slms-table` |
| PDF download link | `.slms-pdf-link` |
| Alerts | `.slms-alert.slms-alert-success` / `.slms-alert-error` |

### Student Dashboard — tabs

- **Tab 1 (Profile):** native `wp_update_user()` + `update_user_meta()` form.
- **Tab 2 (Purchase History):** `MemberOrder::get_orders(['user_id' => $user_id])`.
- **Tab 3 (Certificates Earned):** `CourseHistory::get_for_user($user_id)` with a
  two-stage GravityPDF link resolution (see `CHANGELOG.md`).

## Conventions

- **Text domain:** `simple-lms-bridge` everywhere (including all BB modules).
- **Namespace:** `SimpleLMS`; BB module classes are backslash-qualified from
  within the namespace and registered by fully qualified name.
- **Build command:** `npm run build` (runs `build:js` then `build:css`; order
  matters — `wp-scripts` clears `build/admin/` and must run first).
- **Packaging:** `./deploy.sh` runs `npm ci && npm run build`, verifies the
  enqueued build artifacts exist, and zips the core plugin excluding `src/`,
  `node_modules/`, `.git*`, `*.md`, and `simple-lms-migrator/` (a separate
  plugin, packaged independently — `cd simple-lms-migrator && npm ci && npm
  run build`).
- **`$wpdb`:** use a WP/plugin API where one exists; raw `$wpdb` only for custom
  tables and bulk migration.
- **BB cache:** after any BB module JS/CSS change, clear the Beaver Builder
  cache and hard-refresh to re-enqueue module assets.

## Continuity Notes

- **Remote repository:** `git@github.com:onedogsolutions/simple-lms.git`
- **Default branch:** `main`
- **GitHub org/username:** `onedogsolutions`
- **Debug log path:** `wp-content/uploads/slms-logs/migration.log`
- Run `npm run build` after pulling to compile Tailwind v4 + React.
