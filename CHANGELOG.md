# Changelog — Simple LMS Bridge

This file is the historical log for the Simple LMS Bridge project. The living
state document (architecture, current status, conventions) is in
[`STATE.md`](./STATE.md).

## 1.1.1 — Progress Backfill Trigger Layer

The 1.1.0 notes below anticipated the progress backfill, but its trigger layer
merged after the `v1.1.0` tag was cut; 1.1.1 completes it.

- **Backfill trigger layer (#13):** `Progress::backfill()` (previously dead
  code) is now reachable three ways — `wp slms progress backfill` (new
  `includes/class-cli.php`, loaded only under WP-CLI), an admin-only
  `POST /simple-lms/v1/tools/progress-backfill` route returning
  `{processed_users, inserted_rows, skipped_entries, next_offset, complete,
  parity}`, and a "Progress Table Backfill" panel on the **SimpleLMS → Tools**
  screen with a batch loop and meta-vs-table parity display. Backfill is
  idempotent (`INSERT IGNORE` against the `user_course_lesson` unique key) and
  counts malformed legacy entries instead of failing.
- **Release packaging:** version bump to 1.1.1; both plugin zips
  (`simple-lms-bridge`, `simple-lms-migrator`) build from the tagged commit.

## 1.1.0 — Guarding, Progress Table, Settings, /me API, and Native Certificates

First major release integrating local student progress, robust content guarding, and a native, branded certificate generator.

- **Content Guarding & Access Authorizations:** Implemented full content guarding with custom behavior (redirect or on-page message/CTA) based on post meta `_lms_guard_mode` (public, level, or enrolled). Adapted template redirect, REST response hooks, and content fallback filters in `class-guard.php` to leverage main's `Access` API, supporting `can_view_course()` and drip-lock checks.
- **Relational Progress Table (`wp_slms_lesson_progress`):** Migrated active lesson completions from `usermeta` serialization to a queryable custom SQL table (`DB version 4`). Implemented a secure, dual-writing mechanism that writes to the table and mirrors it to legacy `usermeta`.
- **REST /me API & Backfill Sorter:** Restored missing endpoints under `/me/progress` (GET/POST) and `/me/courses` (GET) allowing authenticated students to retrieve their enrollment status and safely update completions.
- **Progress Table Backfill Tool:** Created a paginated backfill script (`Progress::backfill`) to process ~1300 users' historical records idempotently, converting UNIX timestamps to SQL datetime format while ignoring malformed rows. Exposed via WP-CLI command `wp slms progress backfill` and a live-updating Admin Tools interface (featuring meta vs SQL row parity checking).
- **Global Settings Panel (`class-settings.php`):** Implemented a configuration storage option and a corresponding REST endpoint `/settings`. Built a new React Settings tab in the Admin screen allowing administrators to configure default course guard modes, checkout URLs, custom login redirect behaviors, and Gravity Forms certificate mapping fields.
- **Direct SQL Analytics Re-pointing:** Removed all `_lms_progress` meta queries from the student reporting backend (`class-analytics.php`). Repointed all KPIs (active learners, course funnels, transition drop-offs, and inactivity at-risk lists) to relational SQL queries using optimal index scans. Installed a `needs_backfill` empty-table warning notice in the React Analytics dashboard.
- **Native Certificate Pipeline (Stage 4):** Embedded dompdf 3.x and chillerlan/php-qrcode dependencies to enable automated, server-side certificate generation. Added a customizable, per-course visual template builder with live preview, and secure download (`/certificate/{uuid}/download`) and verification (`/certificate/verify/{uuid}`) routes.
- **Core / Migrator Separation:** Promoted clean composition by moving one-time legacy migration assets to a standalone, optional plugin (`simple-lms-migrator/`), allowing core SimpleLMS Bridge to stay lightweight.
- **Required PHPCS Gate:** Standardized formatting across the repository with a `phpcbf` formatting pass. Wired `phpcs.xml` and GitHub Actions CI workflow to run PHPCS as a required, strict formatting check.

## Core/Migrator Split

Streamlined the plugin into a clean LMS connector by extracting one-time
data-migration machinery into a separate, deletable companion plugin.

- **`simple-lms-migrator/` (new plugin):** the migration engine
  (`class-migration.php`), the Migration Tool and Debug Log admin
  pages/React components, and their REST routes (`/migration/*`,
  `/debug-log`) moved out of core into a standalone plugin that depends on
  core (checks `class_exists('\SimpleLMS\Relationships')` and declares
  `Requires Plugins: simple-lms-bridge`). It's deletable once a site's
  historical data is fully migrated; the REST routes stay under the same
  `simple-lms/v1` namespace so existing frontends keep working. `deploy.sh`
  excludes it from the core zip; it's packaged/built independently.
  `/course-history/repair-form-ids` and `/course-history/purge-corrupted`
  stay in core — they're permanent compliance-table maintenance tools (the
  Stage 4 Tools screen), not one-time WP Complete migration.
- **Dead code removed:** `includes/class-account-dashboard.php` (the
  `[simple_lms_account]` shortcode, never loaded — superseded by the native
  `lms-account-dashboard` BB module), the orphan
  `slms-student-dashboard-tab-2` module (no `FLBuilderModule` class file, so
  Beaver Builder never registered it), and `cleanup_ghost_enrollments.sql`
  (a one-time fix artifact).
- **`class-user-meta.php` removed:** the native WP user-profile screen was a
  redundant third place to edit the same legacy Pods user meta already
  editable via the Student Manager admin (kept as-is; further improvements
  deferred) and the `slms-student-dashboard` BB module's profile tab.
- **GravityPDF URL resolution centralized:** the duplicated two-stage
  resolution (Stage 1: `GPDFAPI::get_entry_pdfs()` with a field 6/18 backfill
  retry; Stage 2: manual conditional-logic evaluation) that lived separately
  in `slms-student-dashboard/includes/frontend.php` and
  `REST::get_student_history()` is now one helper,
  `Certificates::pdf_url(int $gf_entry_id, int $form_id, string $raw_course,
  int $user_id): string`, called from both places (and from the Stage 4
  native-first fallback path). The `/student/{id}/history` response shape
  (`pdf_url` key, plus Stage 4's `cert_uuid`/`verify_url` keys) is unchanged.

## Stage 2 — Frontend Course Experience

- **Access service (`includes/class-access.php`):** central authority for course
  access + state.
  - `can_view($user_id, $lesson_id, $course_id)` combines enrollment/PMPro access
    with drip scheduling; editors always pass (builder/preview). Filterable via
    `slms_can_view_lesson`.
  - Drip helpers `get_unlock_timestamp()` / `is_dripped()` computed from
    `slms_user_course.enrolled_at` + lesson meta `_lms_drip_days` (falls back to
    legacy `_lms_enrolled_at` user meta).
  - State machine `get_course_state()` → `guest | not_enrolled | not_started |
    in_progress | completed`; plus `get_first_incomplete_lesson()`,
    `get_continue_url()`, `get_progress_stats()`, `is_course_complete()`.
  - `get_cta()` returns the state-aware CTA descriptor (label/url/classes) shared
    by the grid + CTA modules; `get_checkout_url()` builds the PMPro checkout URL
    for the course's mapped level; `format_price()` wraps `pmpro_formatPrice`.
  - `set_lesson_progress()` is the single completion code path used by both REST
    `/progress` and quiz auto-completion (writes `_lms_progress`, fires
    `Certificates::check_course_completion`).
  - `get_enrolled_courses_with_progress()` shared by `lms-my-courses` and the
    dashboard "My Courses" tab.
- **New meta (`class-cpt.php`):** `_lms_drip_days` (lesson),
  `_lms_completion_redirect` (course), `_lms_quiz_pass_field` +
  `_lms_quiz_pass_min` (lesson), `_lms_video_gate_pct` (lesson).
  `Relationships::get_enrolled_at()` / `is_enrolled()` added.
- **Quiz-gated completion (`includes/class-quiz.php`):** `gform_after_submission`
  (priority 5, before Certificates at 10) auto-completes quiz-type lessons whose
  `_lms_gravity_form` matches the submitted form, for every enrolled course.
  Optional passing-score gate via `_lms_quiz_pass_field` + `_lms_quiz_pass_min`.
  Routes through `Access::set_lesson_progress`.
- **Completion redirect:** REST `/progress` returns `redirect` (from
  `_lms_completion_redirect`) + `course_complete` when the course just completed —
  keyed off `_lms_completed_at` because certificate automation wipes
  `_lms_progress` on completion. Frontend JS follows the redirect.
- **Four new self-contained Beaver Builder modules** (category `SimpleLMS`, per
  the STATE.md module architecture rule):
  - `lms-course-grid` — card grid of `slms_course` (category slug filter, columns,
    price, enrolled badge, progress bar, state-aware CTA).
  - `lms-lesson-nav` — prev/next within `_simple_lms_order` + "Back to Course";
    drip/guard-locked targets render disabled with a lock icon + unlock date.
  - `lms-my-courses` — session user's enrollments with progress bars +
    Continue/Start.
  - `lms-course-cta` — single state-machine button (Login/Buy · Buy · Start ·
    Continue · View Certificate).
- **Drip rendering:** `lms-outline` renders drip-locked lessons as non-links with
  a lock icon + unlock date.
- **Complete button (`lms-complete-button`):** hidden on quiz lessons
  (auto-complete notice); video-gated (starts `disabled` with
  `data-video-gate`/`data-video-id` when `_lms_video_gate_pct` is set on a video
  lesson).
- **Frontend JS (`assets/js/frontend.js`, enqueued globally as `slms-frontend`):**
  follows the completion redirect; video gating (polls for the Presto/HTML5 media
  element and enables the Complete button at N% watched, fail-open after ~20s);
  quiz countdown timer injected on quiz lessons by `lms-content` (`_lms_quiz_timer`),
  disabling the GF submit button + showing a retake notice on expiry. The
  per-module enqueue was removed from `lms-complete-button` to avoid double-loading.
- **Student Dashboard:** new "My Courses" tab inserted before Purchase History,
  reusing `Access::get_enrolled_courses_with_progress()`.
- **Admin React:** `LessonSettings.js` gains drip days, quiz passing-score
  field/min, and video-gate percent; `CourseEditor.js` gains the completion
  redirect URL.

## Stage 0 — Stabilize the Foundation

- **Student progress 403 fixed:** `POST /progress` now uses an
  `is_user_logged_in()` permission callback. Non-privileged callers can only
  write their own progress (the request `user_id` is ignored and forced to the
  current user unless the actor has `edit_users`). The endpoint now validates
  that the lesson belongs to the course and that the user is enrolled in
  `wp_slms_user_course` before writing.
- **Certificate membership cancellation fixed:** removed the unconditional
  `pmpro_changeMembershipLevel(0, $user_id)` from
  `Certificates::remove_course_access()`. `PMPro::de_enroll_user()` already
  removes only the course's mapped level when it matches the user's current
  level, so completing a course no longer strips an unrelated membership.
- **Duplicate `slms_course_history` DDL removed:** the redundant table
  definition in `Relationships::create_table()` was deleted.
  `CourseHistory::create_table()` is now the single owner of that schema.
- **Fatal typo fixed:** `class-rest.php` called a non-existent
  `\get_page_to_path()`; replaced with
  `get_page_by_path($slug, OBJECT, array('slms_course', 'slms_lesson', 'page', 'post'))`.
- **Expiration now covers all enrollments:** `Expiration::check_expirations()`
  iterates the `wp_slms_user_course` table (`enrolled_at` column) instead of
  `_lms_enrolled_at` user meta, so non-PMPro enrollments expire correctly. On
  expiry it calls `Relationships::unenroll_user()`, clears
  `_lms_progress[$course_id]`, keeps `_lms_enrolled_at` in sync for back-compat,
  and fires `slms_course_access_expired`.
- **Tailwind Play CDN removed:** the `slms-tailwind-cdn` admin enqueue was
  deleted. Locally compiled CSS is the only source; admin pages make zero
  external CDN requests.
- **Build output untracked:** `build/admin/index.js` and
  `build/admin/index.asset.php` were removed from version control; `.gitignore`
  covers `build/`.
- **`deploy.sh` added:** runs `npm ci && npm run build`, verifies the enqueued
  build artifacts exist (failing the package step if any are missing), and zips
  the plugin with dev/source exclusions.
- **Schema versioning added:** new `SLMS_DB_VERSION` constant and `Upgrade`
  runner on `admin_init` compare `get_option('slms_db_version')` and run
  incremental steps. Table creation moved from activation-only DDL into upgrade
  step 1 so fresh installs and updates converge without reactivation.
- **Repo hygiene:** deleted the orphaned
  `includes/bb-modules/slms-student-dashboard-tab-2/`, the unloaded
  `includes/class-account-dashboard.php`, and `cleanup_ghost_enrollments.sql`.
  Unified the text domain to `simple-lms-bridge` across all BB modules. Split
  this changelog out of `STATE.md`.
- **CI added:** GitHub Actions workflow running PHPCS (WordPress-Extra), PHPStan
  level 5 with WordPress stubs, `npm run build`, and a PHP 8.1/8.2/8.3 lint
  matrix. Tag pushes build, zip, and attach an installable release asset.

## Accomplishments (historical)

- **UI Cleanup:** Removed the legacy global admin migration nag banner in favor of the dedicated React Migration Tool UI.
- **Rebranding:** Renamed plugin to "One Dog Solutions".
- **API Migration:** Moved from jQuery AJAX to WP REST API.
- **Frontend Modernization:** Updated CSS to use Flexbox and modern typography.
- **Backend Refactor:**
  - Implemented proper Namespacing.
  - Fixed PHP linting/syntax errors.
- **Feature Implementation:**
  - Added Course Access time limits (`_lms_access_days`).
  - Implemented automatic access removal upon certificate generation.
  - Integrated Paid Memberships Pro (PMPro) for course enrollment.
  - **M2M Overhaul:** Replaced direct meta linking with Many-to-Many join tables (`slms_course_lesson`, `slms_user_course`) for courses, lessons, and student enrollments.
- **Migration Engine:**
  - Built deduplicated importer for WP Complete data.
  - Batch-syncs student progress to the new M2M join table.
  - **Phase 2 Fixes:** Corrected course-to-lesson lookup by leveraging the new `Relationships` class to accurately retrieve linked courses for imported WPComplete progress data. Strengthened timestamp handling and mitigated PHP 8.x notices (e.g. `strpos()` null checks).
  - **Phase 2 Complete Rewrite:** Bypassed arbitrary standard `get_users()` 121 limit. Now iterates over `wp_usermeta` using `$wpdb` to accurately dynamically stream and unpack `wpcomplete_*` structured legacy data for all 1300+ user records.
  - **Phase 2 Migration Overhaul (Feb 2026):**
    - Removed strict enrollment requirement — users with WPComplete data are now auto-enrolled during migration since their data proves they were active students.
    - Added multi-step lesson lookup: `_legacy_id` meta → direct post ID → title-based matching. Each fallback step is logged with reasons.
    - Added fallback course lookup via `_simple_lms_order` post meta when the `wp_slms_course_lesson` join table has no record.
    - Every skip now logs a detailed reason (post type mismatch, post not found, no course linkage, etc.).
- **Admin Menu Restructuring:** Unified all LMS-related screens (Courses, Lessons, Categories, Student Manager, Migration Tool) under a single "SimpleLMS" top-level menu.
- **Tailwind CSS Integration:**
  - Initially enqueued via CDN on custom admin pages (`tw-preflight`).
  - **Refactor:** Migrated off the CDN to a secure, locally compiled Build Pipeline (Tailwind v4 CLI via standard `npm run build` workflows).
  - **v4 Fix:** Updated `src/admin/tailwind.css` from deprecated v3 `@tailwind` directives to v4 `@import "tailwindcss"` syntax to resolve broken CSS compilation.
  - **Build-Order Fix (Feb 2026):** Reversed `npm run build` script order to run `build:js` first, then `build:css`. This prevents `wp-scripts build` from wiping the `build/admin/` directory after Tailwind already wrote its output there.
- **Student Manager Overhaul:**
  - Built a modern React-based "Details" view.
  - Added UI fields to view and manage **Pods Legacy User Meta** natively mapping exact database schema structures (`billing_address_1`, `license_number`, `pro_exam_status`, etc.).
  - Created native list-view sorting algorithms (Asc/Desc) and Course-based dropdown filtering mechanisms.
  - Designed an isolated **Completion History** tab (renamed from "Historical Data").
  - Integrated real-time timestamps displaying exact date/time for both Lesson and Course completions.
  - Fixed deprecation warnings by updating `@wordpress/components` props (e.g. `__nextHasNoMarginBottom={true}`).
- **REST API Enhancements:**
  - Updated the `/students` endpoint to return enriched lesson data (titles and completion timestamps) alongside specific legacy Pods meta keys.
  - Formatted a read-only endpoint (`GET /student/{id}/history`) returning historical completion records.
  - Added `resolve_course_name()` helper to convert URL-based course names into human-readable post titles using `url_to_postid()` and slug-based fallbacks.
  - Created a new secure endpoint (`POST /students/{id}/meta`) for persisting legacy user profile data mappings.
  - Added debug log REST endpoints (`GET/DELETE /debug-log`) for the admin log viewer.
- **Debug Log Viewer (Feb 2026):**
  - New admin page under SimpleLMS > Debug Log.
  - Persistent log file stored in `wp-content/uploads/slms-logs/migration.log` (protected with `.htaccess`).
  - React-based viewer with dark terminal UI, level filters (All/Error/Warn/Info/Debug), stats bar, refresh, and clear functionality.
  - Triple-output logging: in-memory buffer (REST response), `error_log()`, and persistent file.
- **UI Cleanup (Feb 2026):**
  - Removed all Gravity Forms references from student-facing UI.
  - Historical Data tab renamed to "Completion History" with column "Class" instead of "Course Name".
  - Removed "Form" column from completion history table.
- **DevOps:**
  - Initialized Git repository.
  - Created private GitHub repository: `git@github.com:onedogsolutions/simple-lms.git`.
  - Configured SSH authentication.
- **QA Pass (Feb 2026):**
  - Fixed missing `<table class="form-table">` opening tag in `class-user-meta.php` that produced broken HTML on the WP user profile page.
  - Fixed incorrect `GFAPI::get_entries()` call in `class-certificates.php` — was passing only `field_filters` sub-array instead of the full `$search_criteria` object, causing the dedup check to always return empty and create duplicate GF entries.
  - Fixed undefined `$settings->tab_border_color` in `slms-student-dashboard/includes/frontend.css.php` — registered the missing field in the BB module settings, eliminating a PHP notice and invalid CSS output.
  - Fixed Student Manager "Historical Data" tab label — renamed to "Completion History" to match the documented UI and the `HistoryTab` heading already in place.
  - Fixed double API fetch in `StudentManager.js` — added a `useRef` flag so the page-change effect skips a redundant second fetch when a search also resets the page.
  - Removed dead `MetaBoxes::register_admin_pages()` method — was never called; the submenu is correctly registered in `simple-lms-bridge.php`.
- **Migration & Student Manager Fixes (Feb 2026):**
  - Fixed `class-migration.php` Phase 2 `process_legacy_lesson_progress()` — was assigning progress to ALL M2M-linked courses sharing a lesson. Now resolves the original course via the legacy lesson's `post_parent` → `_legacy_id` lookup, only assigning to the single correct course. Falls back to first linked course if legacy parent is unavailable.
  - Fixed `class-rest.php` `GET /students` endpoint — was filtering by `_lms_progress` meta EXISTS, hiding users without progress data. Removed the meta filter so all WordPress users appear in the Student Manager. Course data now sourced from enrollment table (`Relationships::get_courses_for_user()`) with progress data overlaid from `_lms_progress` meta.
- **Phase 4: PMPro Membership Migration (Feb 2026):**
  - New `migrate_pmpro_batch()` in `class-migration.php` migrates GF Form ID 2 registration entries into PMPro membership levels.
  - Maps product fields (IDs: 21, 22, 44, 23, 30, 24, 25, 26, 27, 34, 43) to PMPro levels — auto-creates missing levels via direct DB insert into `pmpro_membership_levels`.
  - Enrolls users via `pmpro_changeMembershipLevel()` with 90-day `enddate` calculated from the original GF entry date.
  - Tracks migration via GF entry meta `_slms_pmpro_migrated` for batch deduplication.
  - New REST endpoint `POST /migration/pmpro` and updated status endpoint to include `pmpro.pending` count.
  - Full Phase 4 UI panel in `MigrationTool.js` with progress tracking, log integration, and sequential gating (requires Phase 3).
- **Certificate De-access Hook (Feb 2026):**
  - `PMPro::de_enroll_user()` now also removes the user's PMPro membership level via `pmpro_changeMembershipLevel(0, $user_id)` when the course's mapped `_lms_pmpro_levels` includes the user's current level. Triggered automatically when a certificate is generated.
- **Tailwind CDN Fallback (Feb 2026):**
  - Added `wp_enqueue_script('slms-tailwind-cdn', 'https://cdn.tailwindcss.com')` to admin enqueue function as a fallback to ensure Tailwind utility classes render even when the local build is stale or broken. *(Removed in Stage 0.)*
- **Migration Phase 2 & 3 Refinement (Feb 2026):**
  - **JSON Handling:** Improved robustness of Phase 2 data unpacking to handle both serialized and JSON formats from WPComplete.
  - **Loop Termination:** Added explicit `status: complete` flags to migration REST responses to ensure batch loops terminate correctly.
  - **Compliance Table Fix:** Corrected `wp_slms_course_history` column naming from `completed_at` to `completed_date` for consistency across the codebase.
- **Build & Zip Workflow (Feb 2026):**
  - Resolved major merge conflicts in both React components (`StudentManager.js`, `MigrationTool.js`, `CourseEditor.js`) and PHP core (`class-migration.php`) resulting from concurrent development.
  - Fixed syntax errors in `StudentManager.js` (`sprintf` nesting) preventing production builds.
  - Created first production-ready test zip archive (`simple-lms-bridge.zip`) with proper file exclusions.
- **Migration Phase Reorder (Mar 2026):**
  - **Root cause:** PMPro sync was running as Phase 4 (after progress and certificate migration), causing ownership validation failures in Phase 2/3 because users had not yet been enrolled in PMPro levels.
  - **New phase order:** Phase 1 → Content, **Phase 2 → PMPro Registration Sync**, Phase 3 → Student Progress, Phase 4 → Historical Certificates.
  - **`migrate_pmpro_batch()` rewrite (`class-migration.php`):**
    - Signature changed to `migrate_pmpro_batch($limit, $offset)` — offset is now caller-provided, eliminating the internal self-advancing scan loop that caused stalls.
    - Single deterministic `GFAPI::get_entries()` call per batch using the supplied `$offset`; returns `'offset' => $next_offset` so the frontend loop advances correctly every iteration.
    - **90-day rule implemented:** calculates `$days_elapsed` from the GF entry `date_created` to now.
      - `> 90 days`: creates a `MemberOrder` historical receipt for audit purposes only — no active PMPro level granted. Enrolls with source `'pmpro_migration_expired'`.
      - `<= 90 days`: grants an active PMPro membership via `pmpro_changeMembershipLevel()` with `$enddate` set to the remaining days. Enrolls with source `'pmpro_migration'`.
  - **REST route (`class-rest.php`):** `/migration/pmpro` now accepts and forwards an `offset` parameter to the batch method.
  - **Migration Script Hardening (Mar 2026):**
    - **Phase 2 Payment Validation:** Added strict payment status checks (`Paid` or `Approved`) to `migrate_pmpro_batch()` to prevent abandoned Gravity Forms checkouts from granting active PMPro memberships.
    - **Phase 3 Infinite Loop & Parser Fix:** Updated WPComplete data parser to handle legacy boolean/string formats. Added a fallback archiver that renames genuinely unparseable meta keys to `_failed_migration_[key]` to preserve historical data while preventing batch stalls.
    - Fixed markdown formatting and linting errors in project design artifacts.
  - **React frontend (`MigrationTool.js`):**
    - Phase number mapping updated: `pmpro=2, progress=3, history=4`.
    - Panel gating updated: Phase 2 (PMPro) unlocks after Phase 1; Phase 3 (Progress) unlocks after Phase 2; Phase 4 (History) unlocks after Phase 3.
    - JSX panels reordered to match new phase sequence.
    - `resetPhase4` renamed to `resetPhase2` with updated confirm/notice text.
  - **Ghost Enrollment Fix (Mar 2026):**
    - Removed `pmpro_migration_expired` auto-enrollment for memberships older than 90 days in Phase 2.
    - Added retroactive active-enrollment cleanup in Phase 4; finding a historical certificate now triggers immediate removal from the active student enrollment table.
- **Account Dashboard Module — Native BB Rewrite (Apr 2026):**
  - The live My Account page used the older `lms-account-dashboard` BB module, which called `do_shortcode('[simple_lms_account]')` → `AccountDashboard::render_shortcode()` → a URL-param-based tab nav rendering all admin UserMeta fields (AALP Member, Pro Exam Status, etc.).
  - **Rule established:** BB modules must never be rendered via shortcode, and must never delegate rendering via cross-module PHP `include()`. Each module must be fully self-contained with its own native template files. BB's engine injects `$settings`, `$id`, and `$module` directly into `includes/frontend.php` and `includes/frontend.css.php` — no intermediary needed.
  - Both `lms-account-dashboard` and `slms-student-dashboard` were fully rebuilt as self-contained native BB modules. Each now owns:
    - `includes/frontend.php` — complete HTML template; rendered directly by BB; `$settings`/`$id` injected by the engine
    - `includes/frontend.css.php` — complete dynamic CSS using `FLBuilderCSS` helpers and a local `slms_color()` helper for safe hex/rgba output
    - `includes/frontend.js` — vanilla JS for tab switching and password toggle; BB auto-loads from the module's `includes/` dir
    - `css/frontend.css` — structural/layout CSS (tabs, grid, inputs, table, alerts, button base); BB enqueues once per page
  - `AccountDashboard` PHP shortcode class deregistered: removed `require_once class-account-dashboard.php` and `AccountDashboard::init()` from `simple-lms-bridge.php`. *(File deleted in Stage 0.)*
  - `lms-account-dashboard` module settings replaced entirely: old `btn_bg_color` / `ro_bg_color` / `ro_text_color` fields removed; full schema added matching `slms-student-dashboard` field names (tab colors, active/hover states, typography, padding, margin, border groups, input focus state, button styles).
- **Student Dashboard BB Module (Apr 2026):**
  - Developed a single, production-ready native Beaver Builder module `slms-student-dashboard` to replace PowerPack Advanced Tabs, PowerPack Gravity Forms, and Gravity Perks Entry Blocks.
  - **File 1: `slms-student-dashboard.php`**: Full `FLBuilderModule` class + `FLBuilder::register_module()` with two style tabs (Tabs Style, Form Style).
  - **File 2: `includes/frontend.php`** — Nonce-verified POST handler; Tab 1 (User Profile), Tab 2 (Purchase History), Tab 3 (Certificates Earned).
  - **File 3: `includes/frontend.css.php`** — Local `slms_color()` helper; maps all settings via `FLBuilderCSS` rules.
  - **File 4: `includes/frontend.js`** — Vanilla JS tab switching + password toggle scoped per instance.
- **Migration & Deployment Upgrades (Apr 2026):**
  - **Fuzzy Title Matching:** Implemented fallback fuzzy logic in Phase 4 (`migrate_history_batch`) to resolve `slms_course` IDs when Gravity Forms certificate names contain slight typos or variations.
  - **Active De-enrollment:** Enforced explicit removal of students from `wp_slms_user_course` once a certificate is successfully migrated or verified.
  - **Retroactive Graduation Cleanup:** Created `slms_retroactive_graduation_cleanup()` to identify and graduate "stuck" students whose lesson progress is 100% complete and who possess a valid history record.
  - **Automated Deployment Pipeline:** Created `deploy.sh` to automate building assets, packaging the plugin zip (excluding source/dev files), and pushing updates to GitHub.
- **Student Manager Migration Nag Removed (Apr 2026):** Removed the inline pending-migration warning banner, progress bar, and completion notice from `StudentManager.js`, along with the associated state and functions. All migration functionality now lives in the dedicated Migration Tool page.
- **Production Build & Packaging (Apr 2026):** Consolidated both dashboard modules into a self-contained architecture; verified React admin UI builds cleanly with Tailwind v4; generated production-ready `simple-lms-bridge.zip`.
- **BB Module Critical Error Fix & Panel Grouping (Apr 2026):**
  - Fixed a critical PHP fatal error in `lms-account-dashboard`: restored `namespace SimpleLMS;` so BB could resolve `'SimpleLMS\LMSAccountDashboardModule'`.
  - Added `namespace SimpleLMS;` to `slms-student-dashboard.php` and updated its `register_module()` call to `'SimpleLMS\SLMSStudentDashboardModule'`.
  - Both modules extend `\FLBuilderModule`; removed the `'group'` key; changed `'category'` so all five SimpleLMS modules appear together in the BB builder panel.
  - Updated `dir`/`url` in both dashboard modules to use `SLMS_PLUGIN_DIR`/`SLMS_PLUGIN_URL`.
  - Text domain standardised across module registration files. *(Unified to `simple-lms-bridge` in Stage 0.)*
- **Purchase History API Fix (Apr 2026):** Replaced non-existent `MemberOrder::getMemberOrders()` with `MemberOrder::get_orders(['user_id' => $user_id])`; fixed `$order->datetime` → `$order->timestamp`. Applied to both dashboard modules.
- **`$wpdb` Audit & Cleanup (Apr 2026):** Audited all `$wpdb` usage; replaced every call with a proper API equivalent, leaving only custom-table and bulk-migration queries. Replaced raw certificate/course-history/parent-course queries with `CourseHistory::get_for_user()` and `Relationships::get_courses_for_lesson()`; fixed `$row->date` → `$row->completed_date`; replaced PMPro price lookup with `pmpro_getLevel()`.
- **Parse Error Fix — Orphaned `else` in Purchase History tab (Apr 2026):** Removed a dangling `else`/`endif` left behind after the `MemberOrder::get_orders()` refactor in `slms-student-dashboard/includes/frontend.php`.
- **JS Execution & CSS Centering Fixes (Apr 2026):** Moved BB module JS inline into `includes/frontend.php` (BB never loads `includes/frontend.js`, and injects cached JS after `DOMContentLoaded`). Added `margin: 0 auto` to center `.slms-profile-form`.
- **GravityPDF URL Fix (Apr 2026):** Form ID 5 ("Certificate") has 11 PDF templates with conditional logic on field 6 (State) and field 18 (Course URL). Implemented two-stage PDF hash resolution: Stage 1 `GPDFAPI::get_entry_pdfs()`; Stage 2 manual conditional-logic evaluation using `billing_state` (field 6) and resolved course slug (field 18). URL format `home_url('/pdf/{hash}/{entry_id}/download/')`.
- **Student Manager Certificate PDF Column (Apr 2026):** Added `resolve_pdf_url()` to `class-rest.php` (identical two-stage GravityPDF resolution) and a "Certificate PDF" column to `HistoryTab` in `StudentManager.js`.
- **Deployment Note (Apr 2026):** After any JS/CSS change to BB modules, clear the Beaver Builder cache (WP Admin → Settings → Beaver Builder → Tools → Clear Cache) and hard-refresh to re-enqueue updated module assets.
- **Certificate PDF Conditional Logic Fix (Apr 2026):** Diagnosed and resolved three compounding root causes behind "N/A" certificate PDF links:
  1. `class-certificates.php` `check_course_completion()`: passed a proper `$search_criteria` object to `get_entries()`; populated fields `6` and `18` on entry creation; captured the entry ID and form ID for `CourseHistory::insert()`; linked existing entries when found.
  2. `slms-student-dashboard/includes/frontend.php`: resolved `$course_link` from the title in the else branch and changed the backfill to always overwrite field 6 and use the permalink for field 18.
  3. Stage 2 URL matching in both `frontend.php` and `class-rest.php`: switched to `stripos`; added a last-path-segment fallback; added `sanitize_title()` comparison.
- **Certificate PDF Link Fix / Conditional Logic Backfill (Apr 2026):** Added a runtime `form_id` fallback via `GFAPI::get_entry()` when the history row's `form_id` is NULL. When Stage 1 returns empty, backfilled GF entry field 6 (from `billing_state`) and field 18 (from the resolved course permalink) via `GFAPI::update_entry_field()` and retried Stage 1.
- **Concatenated Course ID Bug (Apr 2026):** Added `extract_legacy_course_ids` in `class-migration.php` Phase 2 to split comma-less concatenated integers (e.g. `546630`) into valid mapped components. Added `CourseHistory::repair_form_ids()` bulk backfill wired to `POST /course-history/repair-form-ids` (admin-only). Documented that `purge_corrupted_records()` must never run automatically (it deletes any row where `form_id IS NULL`, including legitimate pre-migration records).

## Completed milestones

- Beaver Builder integration modules for course/lesson display.
- Certificate automation with automatic access revocation.
- Migration utility for WP Complete data (Phases 1–4).
- Student Dashboard BB module (native replacement for PowerPack/Gravity Perks).
- Admin menu hierarchy grouped under the SimpleLMS hub.
- Modern Student Manager UI with detailed progress views.
- Structured diagnostic logging across all migration phases.
- `wp_slms_course_history` compliance table for 9-year record retention.
- Phase 3 rewrite: certificate migration writes to the compliance table.
- PHP 8.x / 8.4 hardening (null-coalescing guards, dynamic-property audit).
- Migration enrollment fix, multi-step lesson lookup, URL-to-class resolution.
- Debug Log admin page with filters, stats, clear, and refresh.
- Build-order fix (build:js before build:css).
- M2M progress scoping to the correct single course.
- Student Manager shows all WordPress users.
- Phase 4 PMPro migration with auto-level creation and 90-day access rule.
- Automated production zip creation with exclusions.
- Phase reorder (Mar 2026): PMPro sync promoted to Phase 2.
- Tailwind v4 modernization (removed `tailwind.config.js`; `@source`/`@theme`).
- Migration & dashboard hardening; `form_id` column added to compliance table.
