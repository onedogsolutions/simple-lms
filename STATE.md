# Project State: Simple LMS Bridge (One Dog Solutions)

## Overview

This document maintains continuity for the Simple LMS Bridge project (rebranded to One Dog Solutions). It tracks current progress, architecture decisions, and remaining tasks.

The project has been moved to a private GitHub repository. Core features are in place. A full QA pass (Feb 2026) fixed six static bugs. A subsequent migration QA (Feb 2026) identified and fixed two runtime bugs: progress was being duplicated across all M2M-linked courses, and the Student Manager only displayed users with existing progress data. Phase 1-4 of the migration engine are now fully implemented and tested.

## Accomplishments (Recent)

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
- [x] **Student Dashboard BB Module:** New 3-tab BB module (profile, purchase history, certificates).
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

## Migration Diagnostic Logging

Comprehensive logging in `class-migration.php` and `MigrationTool.js` to debug student progress import issues.

### PHP Backend (`class-migration.php`)

- **Triple-output logging**: Every log entry writes to an in-memory buffer (returned via REST API), `wp-content/debug.log` via `error_log()`, and a persistent plugin log file at `wp-content/uploads/slms-logs/migration.log`.
- **Phase 1 (CPT)**: Logs legacy course discovery, import/dedup results, lesson linking counts.
- **Phase 2 (Student Progress)**: Logs per-user processing with email labels, WPComplete data format detection (serialized vs JSON), multi-step lesson lookup (by `_legacy_id`, direct ID, title), course linkage with fallback search via `_simple_lms_order`, auto-enrollment actions, and a final summary with skip-reason stats.
- **Phase 3 (History)**: Logs entry counts per user, certificate form discovery, dedup stats.
- **Phase 4 (PMPro)**: Logs GF entry processing, product field extraction, level creation/matching, PMPro enrollment results with enddate.
- All REST migration responses include a `log` array and Phase 2 includes a `stats` object.

### React Frontend

- **MigrationTool.js**: Live collapsible log panel with level filters, badge counts, dark terminal UI, auto-scroll.
- **DebugLog.js**: Dedicated admin page (`SimpleLMS > Debug Log`) with persistent log viewer, level filters, stats bar, refresh, and clear.

### Debugging Workflow

1. Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are `true` in `wp-config.php`.
2. Run migration from the admin Migration Hub page.
3. Watch the live log panel in the browser for immediate feedback.
4. Navigate to SimpleLMS > Debug Log for the persistent log viewer.
5. For deeper analysis, check `wp-content/debug.log` for `[SimpleLMS]` prefixed entries.

## Student Dashboard Module Architecture

**Module:** `slms-student-dashboard` (BB module under `includes/bb-modules/`)

### Directory Layout

```text
includes/bb-modules/slms-student-dashboard/
├── slms-student-dashboard.php   # Module class + register_module()
└── includes/
    ├── frontend.php             # Tab shell + per-tab render (Profile, History, Certs)
    └── frontend.css.php         # Dynamic CSS via FLBuilderCSS::rule()
```

### Core Features

- **Tab 1 (Profile)**: Uses internal SLMS profile form via `AccountDashboard::render_profile()`.
- **Tab 2 (History)**: Wraps PMPro `[pmpro_account]` shortcode with native fallbacks.
- **Tab 3 (Certificates)**: Queries certificate API for entries created by current user.
- **Styling**: Fully stylable via BB settings (Colors, Typography, Card-style tabs).

## Continuity Notes

- **GitHub Username:** `onedogsolutions`
- **Next Step:** Continue feature development toward 1.0 release. Run `npm run build` after pulling to compile Tailwind v4 + React. Use diagnostic logging to validate migration data as new features are built out.
- **Zip Output:** Final plugin zip should be written to `/Users/rwaterbury/Documents/`
- **Working Directory:** `/Users/rwaterbury/Documents/simple-lms`
