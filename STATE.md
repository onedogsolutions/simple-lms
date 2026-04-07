# Project State: Simple LMS Bridge (One Dog Solutions)

## Overview

This document maintains continuity for the Simple LMS Bridge project (rebranded to One Dog Solutions). It tracks current progress, architecture decisions, and remaining tasks.

The project has been moved to a private GitHub repository. Core features are in place. A full QA pass (Feb 2026) fixed six static bugs. A subsequent migration QA (Feb 2026) identified and fixed two runtime bugs: progress was being duplicated across all M2M-linked courses, and the Student Manager only displayed users with existing progress data. Phase 1-4 of the migration engine are fully implemented and tested. A March 2026 architectural refactor moved PMPro Registration Sync to Phase 2 (immediately after course creation), shifting Student Progress to Phase 3 and Historical Certificates to Phase 4, ensuring users own their courses before progress and certificate data is migrated.

## Accomplishments (Recent)

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
  - Added `wp_enqueue_script('slms-tailwind-cdn', 'https://cdn.tailwindcss.com')` to admin enqueue function as a fallback to ensure Tailwind utility classes render even when the local build is stale or broken.
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
  - `AccountDashboard` PHP shortcode class deregistered: removed `require_once class-account-dashboard.php` and `AccountDashboard::init()` from `simple-lms-bridge.php`.
  - `lms-account-dashboard` module settings replaced entirely: old `btn_bg_color` / `ro_bg_color` / `ro_text_color` fields removed; full schema added matching `slms-student-dashboard` field names (tab colors, active/hover states, typography, padding, margin, border groups, input focus state, button styles).
- **Student Dashboard BB Module (Apr 2026):**
  - Developed a single, production-ready native Beaver Builder module `slms-student-dashboard` to replace PowerPack Advanced Tabs, PowerPack Gravity Forms, and Gravity Perks Entry Blocks.
  - **File 1: `slms-student-dashboard.php`**: Full `FLBuilderModule` class + `FLBuilder::register_module()` with two style tabs:
    - **Tabs Style:** Tab Colors (bg, active bg, text, active text, hover bg, hover text), Typography, Padding & Margin (dimension fields), and Border (inactive + active border groups).
    - **Form Style:** Input Fields (bg, text, label color, padding, typography), Input Focus State (focus bg, focus border color, focus text color — collapsed), Input Border & Shadow (border group — collapsed), Button (bg, hover bg, text color, hover text color, padding, border group, typography — collapsed).
  - **File 2: `includes/frontend.php`** — Complete rewrite:
    - Nonce-verified POST handler: `wp_update_user()` for first_name, last_name, user_email; `update_user_meta()` for phone, license_number, billing_address_1/2, billing_city, billing_state, billing_postcode; conditional `user_pass` update when "Update Password" checkbox is checked and passwords match.
    - **Tab 1 (User Profile):** First + Last name side-by-side (`.slms-two-col`); Email; Phone; full "Senior or Professional Laser Hair Removal License Number" label; "Update Password" checkbox (before address section); password fields hidden by default (JS-toggled); Street Address, Address Line 2; City/State/ZIP three-column row with a full 50-state + DC `<select>` dropdown for State.
    - **Tab 2 (Purchase History):** PMPro `MemberOrder::getMemberOrders()` loop; table headers: `ID | Purchase Date | Course Purchases | Total`.
    - **Tab 3 (Certificates Earned):** `$wpdb` query against `wp_slms_course_history` for current user; table headers: `Name | Course | Completion Date | Certificate PDF`; Name column = student full name (first+last, falls back to `display_name`); PDF link format: `/?gf_pdf=1&fid={form_id}&lid={gf_entry_id}`; form_id resolved from stored row column or via `GFAPI::get_entry()` fallback.
  - **File 3: `includes/frontend.css.php`** — Complete rewrite:
    - Local `slms_color()` helper safely prepends `#` to plain hex strings and passes rgba() values through unchanged (replaces missing `FLBuilderColor::hex_or_rgb()` dependency).
    - Maps all settings: tab bg/active bg/text/active text/hover states via inline CSS blocks; `FLBuilderCSS::typography_field_rule()` for tab + input + button typography; `FLBuilderCSS::dimension_field_rule()` for tab padding, tab margin, input padding, button padding; `FLBuilderCSS::border_field_rule()` for tab, active tab, input, and button border groups; input focus state (bg, border-color, text color); label color.
  - **File 4: `includes/frontend.js`** — Vanilla JS, zero dependencies:
    - Tab switching: scoped per `.slms-student-dashboard` instance, manages `active` class and `aria-selected` on both `.slms-tab-link` and `.slms-tab-pane`.
    - Password toggle: `#slms_update_password` checkbox reveals/hides `#slms-password-fields`; clears password inputs on hide; manages `aria-hidden`.
- **Migration & Deployment Upgrades (Apr 2026):**
  - **Fuzzy Title Matching:** Implemented fallback fuzzy logic in Phase 4 (`migrate_history_batch`) to resolve `slms_course` IDs when Gravity Forms certificate names contain slight typos or variations.
  - **Active De-enrollment:** Enforced explicit removal of students from `wp_slms_user_course` once a certificate is successfully migrated or verified.
  - **Retroactive Graduation Cleanup:** Created `slms_retroactive_graduation_cleanup()` to identify and graduate "stuck" students whose lesson progress is 100% complete and who possess a valid history record.
  - **Automated Deployment Pipeline:** Created `deploy.sh` to automate building assets, packaging the plugin zip (excluding source/dev files), and pushing updates to GitHub.
- **UI Cleanup:** Permanent removal of the legacy global admin migration nag banner in favor of the dedicated React Migration Tool UI.
- **Student Manager Migration Nag Removed (Apr 2026):**
  - Removed the inline "There are still N users with WP Complete data pending migration" warning banner, progress bar, and "Migration complete!" notice from `StudentManager.js`.
  - Removed the `migrationStatus` state, `startMigration()` function, and `checkMigrationStatus()` function entirely from the component.
  - Removed the `checkMigrationStatus()` call from the initial `useEffect` load sequence.
  - Removed unused `ProgressBar` from the `@wordpress/components` import.
  - All migration functionality now lives exclusively in the dedicated Migration Tool page (`MigrationTool.js`).
- **Production Build & Packaging (Apr 2026):**
  - Consolidated both `lms-account-dashboard` and `slms-student-dashboard` into a self-contained architecture.
  - Verified React admin UI builds cleanly with Tailwind v4.
  - Generated production-ready `simple-lms-bridge.zip` for deployment.
- **BB Module Critical Error Fix & Panel Grouping (Apr 2026):**
  - Fixed a critical PHP fatal error in `lms-account-dashboard`: the `namespace SimpleLMS;` declaration had been removed but `\FLBuilder::register_module()` still referenced `'SimpleLMS\LMSAccountDashboardModule'` — class was in global scope so BB could not find it. Namespace restored.
  - Added `namespace SimpleLMS;` to `slms-student-dashboard.php` and updated its `register_module()` call to `'SimpleLMS\SLMSStudentDashboardModule'` for consistency.
  - Both modules now extend `\FLBuilderModule` (backslash-qualified from within the namespace).
  - Removed the `'group'` key from both dashboard modules; changed `'category'` to `__('SimpleLMS', 'simple-lms')` to match `lms-complete-button`, `lms-content`, and `lms-outline` — all five SimpleLMS modules now appear together in the BB builder panel.
  - Updated `dir`/`url` in both dashboard modules to use `SLMS_PLUGIN_DIR`/`SLMS_PLUGIN_URL` constants (consistent with other modules).
  - Text domain standardised to `'simple-lms'` across all `__()` calls in both module registration files.
- **Purchase History API Fix (Apr 2026):**
  - Fatal error: `MemberOrder::getMemberOrders()` does not exist in the installed PMPro version.
  - Replaced with `MemberOrder::get_orders(['user_id' => $user_id])` — the correct static PMPro API per official developer docs.
  - Also fixed `$order->datetime` → `$order->timestamp` (actual column name in `pmpro_membership_orders`).
  - Applied to both `slms-student-dashboard` and `lms-account-dashboard` `frontend.php`.
- **`$wpdb` Audit & Cleanup (Apr 2026):**
  - Audited all `$wpdb` usage across the plugin. Replaced every call that had a proper API equivalent; left only calls against custom plugin tables (`slms_course_lesson`, `slms_user_course`, `slms_course_history`) and bulk migration operations where no API exists.
  - **Both dashboard `frontend.php` (certificates tab):** Replaced raw `$wpdb` query + `SHOW TABLES LIKE` existence check with `\SimpleLMS\CourseHistory::get_for_user($user_id)`.
  - **`lms-complete-button/frontend.php`:** Replaced `$wpdb` postmeta `LIKE` search for parent course with `\SimpleLMS\Relationships::get_courses_for_lesson($lesson_id)`.
  - **`lms-outline/frontend.php`:** Same replacement as complete-button.
  - **`class-rest.php` `get_student_history()`:** Replaced `$wpdb` query on `slms_course_history` with `CourseHistory::get_for_user()`; fixed `$row->date` → `$row->completed_date` to match the actual column name; removed unused `global $wpdb`.
  - **`class-migration.php` PMPro price lookup:** Replaced `$wpdb` query on `pmpro_membership_levels` for `initial_payment` with `pmpro_getLevel($level_id)->initial_payment`.
- **Parse Error Fix — Orphaned `else` in Purchase History tab (Apr 2026):**
  - `PHP Parse error: syntax error, unexpected token "else"` on line 272 of `slms-student-dashboard/includes/frontend.php`.
  - Root cause: when the `class_exists('MemberOrder')` wrapper was removed during the `MemberOrder::get_orders()` refactor, its matching `else`/`endif` block ("Paid Memberships Pro is not active.") was left behind, producing a dangling `else` with no opening `if`.
  - Fixed by removing the orphaned `else` and `endif` from the Purchase History tab in `slms-student-dashboard/includes/frontend.php`.
- **JS Execution & CSS Centering Fixes (Apr 2026):**
  - **Tab switching and password toggle were non-functional** — BB reads `js/frontend.js` via `file_get_contents` into the layout cache file; `includes/frontend.js` is never loaded. Additionally, BB injects the cached JS at `wp_footer` priority `PHP_INT_MAX`, after `DOMContentLoaded` has already fired, so event listeners never ran.
  - Fixed in both modules by moving all JS inline into `includes/frontend.php` (bottom of file, inside an IIFE). Uses `document.currentScript.previousElementSibling` to scope to the current module instance — executes immediately with no timing issues.
  - **Profile form not centered** — `.slms-profile-form` had `max-width: 800px` but no `margin: 0 auto`. Added `margin: 0 auto` to `css/frontend.css` in both modules.
- **GravityPDF URL Fix (Apr 2026):**
  - Certificates tab was generating invalid PDF links using `/?gf_pdf=1&fid={form_id}&lid={entry_id}` (wrong query parameter names).
  - Fixed in both `lms-account-dashboard` and `slms-student-dashboard` `includes/frontend.php`: replaced `$form_id` / `GFAPI` lookup entirely with `GPDFAPI::get_entry_pdfs($gf_entry_id)`; constructs URL as `/?gpdf=1&pid={hash_id}&lid={entry_id}&action=download` from the first PDF config returned. Falls back to "N/A" if GravityPDF is not active or no PDF template is configured for the entry.
- **Deployment Note (Apr 2026):** After any JS or CSS change to BB modules, the Beaver Builder cache must be manually cleared (WP Admin → Settings → Beaver Builder → Tools → Clear Cache) to force BB to re-enqueue updated module assets. Hard-refresh (`Cmd+Shift+R`) also required to bypass browser cache.

## Technical Details

- **WP REST API Base:** `simple-lms/v1`
- **Tailwind CSS:** Fully integrated into local node build pipeline (`build:css`). Compiled `.css` loaded on specific plugin pages (`slms-students`, `slms-migration`, `slms-debug-log`).
- **Join Tables:** `wp_slms_course_lesson` (M2M lessons), `wp_slms_user_course` (Enrollments), `wp_slms_course_history` (9-year compliance records).
- **PMPro Sync:** Membership level changes automatically trigger enrollment via `pmpro_after_change_membership_level`.
- **Plugin Meta Key for Access:** `_lms_access_days`
- **Remote Repository:** `git@github.com:onedogsolutions/simple-lms.git`
- **Main Branch:** `main`
- **Debug Log Path:** `wp-content/uploads/slms-logs/migration.log`
- **Build Command:** `npm run build` (runs `build:js` then `build:css`)

## Remaining Tasks

- [x] **Beaver Builder Integration:** Implement modules for course/lesson display.
- [x] **Certificate Automation:** Ensure certificates are generated and access is revoked automatically.
- [x] **Migration Utility:** Build tool to migrate data from WP Complete.
- [x] **Student Dashboard BB Module:** Rebuilt `slms-student-dashboard` from scratch to natively replace PowerPack Advanced Tabs, PowerPack Gravity Forms, and Gravity Perks Entry Blocks. Uses direct `MemberOrder` querying and `wp_slms_course_history` table for fully styleable PMPro history and Certificates tables via BB settings.
- [x] **Admin Menu Hierarchy:** Fix disconnected menus and group under SimpleLMS hub.
- [x] **Student Manager UI:** Modernize with Tailwind CSS and detailed progress views.
- [x] **Migration Logging:** Added structured diagnostic logging across all migration phases.
- [x] **Compliance History Table:** Created `wp_slms_course_history` custom table via `dbDelta` for 9-year state compliance record retention.
- [x] **Phase 3 Rewrite:** Rewrote certificate migration to insert into `wp_slms_course_history` table instead of volatile user meta.
- [x] **Phase 2 Math Fix:** Fixed ProgressBar showing 1100% — WP `ProgressBar` treats `value` as 0–100 percentage, was receiving raw counts.
- [x] **PHP 8.x Hardening:** Replaced `?:` with `??` in REST endpoints, added null-coalescing guards on field accesses.
- [x] **Migration Enrollment Fix:** Removed strict enrollment check — auto-enrolls users during WPComplete migration.
- [x] **Multi-Step Lesson Lookup:** Added fallbacks for `_legacy_id`, direct post ID, and title-based matching.
- [x] **URL-to-Class Resolution:** Historical records with URL-based course names are now resolved to post titles.
- [x] **Debug Log Admin Page:** Full React log viewer with filters, stats, clear, and refresh.
- [x] **Build Order Fix:** Reversed build:js/build:css order to prevent wp-scripts from wiping tailwind.css.
- [x] **UI Cleanup:** Removed Gravity Forms references from student-facing views, renamed columns.
- [x] **QA & Testing:** Conducted full static QA pass (Feb 2026). Six bugs identified and fixed across PHP classes, Beaver Builder module, and React admin components.
- [x] **M2M Progress Scoping:** Migration Phase 2 now resolves legacy parent course to assign progress to the correct single course, preventing duplication across shared lessons.
- [x] **Student Manager All-Users:** Removed `_lms_progress` meta filter from `/students` REST endpoint; all WordPress users now appear. Course data sourced from enrollment table.
- [x] **Phase 4 PMPro Migration:** Full Gravity Forms → PMPro membership migration with auto-level creation, 90-day access, and certificate de-access hook.
- [X] **Re-run Migration:** Phase 2 and 4 tested and validated with batch processing logic. Correctly handles 1300+ records.
- [X] **Zip Archive Creation:** Automated production zip creation with exclusions for `.git`, `node_modules`, and source files.
- [X] **Phase Reorder (Mar 2026):** PMPro Registration Sync promoted to Phase 2. Student Progress shifted to Phase 3. Historical Certificates shifted to Phase 4. Fixes ownership validation failures and migration stalls. Implements 90-day active/expired rule with `MemberOrder` audit receipts and caller-driven offset pagination.
- [X] **Student Dashboard UI Refinement (Apr 2026):** GravityPDF link logic refactored to use native `GPDFAPI::get_pdf_url()` method instead of shortcodes, ensuring properly signed and secure direct URLs.
- [X] **Migration Phase 2 Robustness (Apr 2026):** Refactored `migrate_pmpro_batch()` to accurately map GF Form 2 `payment_amount` and `date_created` to PMPro `MemberOrder->total` and `MemberOrder->timestamp`. Implemented explicit deduplication against `pmpro_membership_ordermeta` using `_gf_entry_id` to prevent duplication.
- [X] **PHP 8.4 Hardening:** Audited codebase to eliminate "Creation of dynamic property" deprecation warnings. Targeted classes (`GW_Set_Entry_Created_By` and `WP_Package_Updater`) were verified to not exist in the current source.
- [X] **Tailwind v4 Modernization:** Eliminated `tailwind.config.js` entirely. Moved all theme and content path configurations into `src/admin/tailwind.css` using modern Tailwind v4 `@source` and `@theme` directives.
## Migration Diagnostic Logging

Comprehensive logging in `class-migration.php` and `MigrationTool.js` to debug student progress import issues.

### PHP Backend (`class-migration.php`)

- **Triple-output logging**: Every log entry writes to an in-memory buffer (returned via REST API), `wp-content/debug.log` via `error_log()`, and a persistent plugin log file at `wp-content/uploads/slms-logs/migration.log`.
- **Phase 1 (CPT)**: Logs legacy course discovery, import/dedup results, lesson linking counts.
- **Phase 2 (PMPro Registration Sync)**: Logs GF entry processing at each offset, days-elapsed calculation per entry, active vs expired path taken, `MemberOrder` creation for expired purchases, `pmpro_changeMembershipLevel()` results with enddate and remaining days, and a batch summary with `enrolled_active`/`enrolled_expired` counts.
- **Phase 3 (Student Progress)**: Logs per-user processing with email labels, WPComplete data format detection (serialized vs JSON), multi-step lesson lookup (by `_legacy_id`, direct ID, title), course linkage with fallback search via `_simple_lms_order`, auto-enrollment actions, and a final summary with skip-reason stats.
- **Phase 4 (History)**: Logs entry counts per user, certificate form discovery, dedup stats.
- All REST migration responses include a `log` array and Phase 3 includes a `stats` object.

### React Frontend

- **MigrationTool.js**: Live collapsible log panel with level filters, badge counts, dark terminal UI, auto-scroll.
- **DebugLog.js**: Dedicated admin page (`SimpleLMS > Debug Log`) with persistent log viewer, level filters, stats bar, refresh, and clear.

### Debugging Workflow

1. Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are `true` in `wp-config.php`.
2. Run migration from the admin Migration Hub page.
3. Watch the live log panel in the browser for immediate feedback.
4. Navigate to SimpleLMS > Debug Log for the persistent log viewer.
5. For deeper analysis, check `wp-content/debug.log` for `[SimpleLMS]` prefixed entries.

## BB Module Architecture

### Rule
Every Beaver Builder module must be fully self-contained. Rendering must never be delegated through a WordPress shortcode or a PHP `include()` referencing another module's files. BB's engine injects `$settings`, `$id`, and `$module` directly into each module's own template files.

### Required File Structure (per module)

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

### Active Dashboard Module

The module below is fully self-contained and is the single native BB module for the Student Dashboard.

```text
includes/bb-modules/
└── slms-student-dashboard/         # Live module — placed on /my-account/ BB page
    ├── slms-student-dashboard.php
    ├── css/frontend.css
    └── includes/
        ├── frontend.php
        ├── frontend.css.php
        └── frontend.js
```

### HTML Class Reference

| Element | Class / ID |
|---|---|
| Module wrapper | `.slms-student-dashboard` |
| Tab nav list | `.slms-tabs-nav` |
| Tab button | `.slms-tab-link` (active: `.active`) |
| Tab pane | `.slms-tab-pane` (active: `.active`) |
| Profile pane | `#slms-tab-profile` |
| History pane | `#slms-tab-history` |
| Certificates pane | `#slms-tab-certificates` |
| Profile form | `.slms-profile-form` |
| All inputs + selects | `.slms-input` |
| Field labels | `.slms-field-label` |
| Submit button | `.slms-submit-btn` |
| Password fields wrapper | `#slms-password-fields` (`.slms-hidden` when inactive) |
| Two-column row | `.slms-field-row.slms-two-col` |
| Three-column row | `.slms-field-row.slms-three-col` |
| Data tables | `.slms-table` |
| PDF download link | `.slms-pdf-link` |
| Success / error banners | `.slms-alert.slms-alert-success` / `.slms-alert-error` |

### Core Features

- **Tab 1 (Profile)**: Native `wp_update_user()` + `update_user_meta()` form. No shortcode or third-party dependency.
- **Tab 2 (Purchase History)**: `MemberOrder::get_orders(['user_id' => $user_id])`. Headers: `ID | Purchase Date | Course Purchases | Total`. Date formatting handles both UNIX integer and MySQL `DATETIME` strings. Course Purchases column resolves PMPro level name directly to `slms_course` permalink via `$wpdb` precise title string matching.
- **Tab 3 (Certificates Earned)**: `CourseHistory::get_for_user($user_id)`. Headers: `Name | Course | Completion Date | Certificate PDF`. Course name resolves legacy Pods URLs recursively (trying `url_to_postid`, lesson-to-course resolution, and URL path-to-slug fallbacks) to output clickable permalinks. GravityPDF link generated securely by extracting `$form_id` from the entry, retrieving `$pdfs = GPDFAPI::get_form_pdfs( $form_id )`, and routing via the native `[gravitypdf]` shortcode to bypass entry-level conditional logic while strictly enforcing native URL signing workflows.
- **Styling**: Structural CSS in `css/frontend.css`; all colors, typography, spacing, and borders driven by BB settings panel via `includes/frontend.css.php`.

## Continuity Notes

- **GitHub Username:** `onedogsolutions`
- **Next Step:** Continue feature development toward 1.0 release. Run `npm run build` after pulling to compile Tailwind v4 + React. Use diagnostic logging to validate migration data as new features are built out.
- **Zip Output:** Final plugin zip should be written to `/Users/rwaterbury/Documents/`
- **Working Directory:** `/Users/rwaterbury/Documents/simple-lms`
