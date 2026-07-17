# Project State: Simple LMS Bridge (One Dog Solutions)

This is the living state document: architecture, current status, and
conventions. The historical log of accomplishments lives in
[`CHANGELOG.md`](./CHANGELOG.md).

## Overview

Simple LMS Bridge is a lightweight, CPT-based LMS for WordPress with a React
admin UI and native Beaver Builder integration. Courses and lessons are custom
post types linked through many-to-many join tables; enrollments, progress, and
9-year compliance history are tracked in custom tables. Paid Memberships Pro
(PMPro) drives course enrollment, and Gravity Forms + GravityPDF issue
certificates on completion.

## Current Status

- Core feature set is in place: course/lesson CPTs, M2M relationships, PMPro
  enrollment, progress tracking, certificate issuance, and the WP Complete
  migration engine (Phases 1–4).
- **Stage 0 (Stabilize the Foundation)** hardened the plugin: fixed the student
  progress 403, corrected certificate-time membership removal, de-duplicated the
  compliance-table DDL, fixed a fatal typo, rewrote expiration to cover all
  enrollments, removed the Tailwind CDN, untracked build output, added schema
  versioning, and stood up CI. See `CHANGELOG.md` for details.
- **Core/Migrator Split:** the one-time WP Complete/Pods/GF→PMPro migration
  machinery (engine, Migration Tool, Debug Log, and their REST routes) now
  lives in a separate, deletable companion plugin, `simple-lms-migrator/`,
  which depends on core. `class-user-meta.php` (a redundant native WP
  user-profile screen) was removed — legacy user-meta editing lives in the
  Student Manager admin and the dashboard profile tab. Duplicated two-stage
  GravityPDF URL resolution was centralized into `Certificates::pdf_url()`.
  See `CHANGELOG.md` for details.
- **Known follow-up:** the PHPCS (WordPress-Extra) CI job is currently
  non-blocking (`continue-on-error`). The pre-existing codebase uses spaces for
  indentation and double quotes throughout, which conflicts with
  WordPress-Extra's tabs/single-quote conventions across essentially every file
  — a dedicated formatting pass (`composer phpcbf`, reviewed by hand for the
  handful of non-auto-fixable `WordPress.DB.PreparedSQL.NotPrepared` sniffs) is
  needed before this can be a hard gate. PHPStan (level 5) is a hard gate; its
  pre-existing, out-of-scope findings are tracked in `phpstan.neon`'s
  `ignoreErrors` as a baseline rather than silently disabled.

## Architecture

### Custom tables

| Table | Purpose | Owner of schema |
|---|---|---|
| `wp_slms_course_lesson` | M2M course ↔ lesson links (with `sort_order`) | `Relationships::create_table()` |
| `wp_slms_user_course` | Enrollments (`user_id`, `course_id`, `enrolled_at`, `source`) | `Relationships::create_table()` |
| `wp_slms_course_history` | 9-year compliance completion records | `CourseHistory::create_table()` |

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
- `POST /progress` — permission `is_user_logged_in()`. Non-privileged callers
  can only write their own progress; the endpoint validates lesson-in-course
  and enrollment before writing.
- `GET /progress/{user_id}`, `/students`, `/forms`, `/videos`, `/analytics/*`,
  and `GET /student/{id}/history` require `edit_users`/`edit_posts` as
  appropriate. Migration endpoints (`/migration/*`, `/debug-log`,
  `/course-history/repair-form-ids`) are registered by the `simple-lms-migrator`
  plugin under the same `simple-lms/v1` namespace.

### Enrollment & expiration

- Enrollment source of truth is `wp_slms_user_course`. `_lms_enrolled_at` user
  meta is written for back-compat but is no longer read for expiration.
- `Expiration::check_expirations()` (daily cron `slms_daily_access_check`)
  iterates the enrollment table, applies `_lms_access_days` (0 = unlimited),
  and on expiry calls `Relationships::unenroll_user()`, clears
  `_lms_progress[$course_id]`, and fires `slms_course_access_expired`.

### Certificates

- `Certificates::check_course_completion()` fires when the final lesson is
  marked complete: records completion, creates/links a Gravity Forms entry
  (populating field 6 State and field 18 Course URL), writes a
  `CourseHistory` row, and calls `remove_course_access()`.
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
`lms-complete-button`, `slms-student-dashboard`.

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
