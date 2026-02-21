# Project State: Simple LMS Bridge (One Dog Solutions)

## Overview

This document maintains continuity for the Simple LMS Bridge project (rebranded to One Dog Solutions). It tracks current progress, architecture decisions, and remaining tasks.

## Current Status: [ACTIVE DEVELOPMENT]

The project has recently undergone a major refactor and has been moved to a private GitHub repository. Core features are in place with additional features still planned before a 1.0 release. Recent work added structured diagnostic logging to the admin migration tool to aid ongoing development.

## Accomplishments (Recent)

- **Rebranding:** Renamed plugin to "One Dog Solutions".
- **API Migration:** Moved from jQuery AJAX to WP REST API.
- **Frontend Modernization:** Updated CSS to use Flexbox and modern typography.
- **Backend Refactor:**
  - Implemented proper Namespacing.
  - Fixed PHP linting/syntax errors.
- **Feature Implementation:**
  - Added Course Access time limits (`_slms_course_access_days`).
  - Implemented automatic access removal upon certificate generation.
  - Integrated Paid Memberships Pro (PMPro) for course enrollment.
  - **M2M Overhaul:** Replaced direct meta linking with Many-to-Many join tables (`slms_course_lesson`, `slms_user_course`) for courses, lessons, and student enrollments.
- **Migration Engine:**
  - Built deduplicated importer for WP Complete data.
  - Batch-syncs student progress to the new M2M join table.
  - **Phase 2 Fixes:** Corrected course-to-lesson lookup by leveraging the new `Relationships` class to accurately retrieve linked courses for imported WPComplete progress data. Strengthened timestamp handling and mitigated PHP 8.x notices (e.g. `strpos()` null checks).
  - **Phase 2 Complete Rewrite:** Bypassed arbitrary standard `get_users()` 121 limit. Now iterates over `wp_usermeta` using `$wpdb` to accurately dynamically stream and unpack `wpcomplete_*` structured legacy data for all 1300+ user records.
- **Admin Menu Restructuring:** Unified all LMS-related screens (Courses, Lessons, Categories, Student Manager, Migration Tool) under a single "SimpleLMS" top-level menu.
- **Tailwind CSS Integration:** 
  - Initially enqueued via CDN on custom admin pages (`tw-preflight`).
  - **Refactor:** Migrated off the CDN to a secure, locally compiled Build Pipeline (Tailwind v4 CLI and PostCSS via standard `npm run build` workflows).
- **Student Manager Overhaul:**
    - Built a modern React-based "Details" view.
    - Added UI fields to view and manage **Pods Legacy User Meta** natively mapping exact database schema structures (`billing_address_1`, `license_number`, `pro_exam_status`, etc.).
    - Created native list-view sorting algorithms (Asc/Desc) and Course-based dropdown filtering mechanisms.
    - Designed an isolated **Historical Data** tab parsing complex Gravity Forms entry trees.
    - Integrated real-time timestamps displaying exact date/time for both Lesson and Course completions.
    - Fixed deprecation warnings by updating `@wordpress/components` props (e.g. `__nextHasNoMarginBottom={true}`).
- **REST API Enhancements:** 
  - Updated the `/students` endpoint to return enriched lesson data (titles and completion timestamps) alongside specific legacy Pods meta keys.
  - Formatted a read-only endpoint (`GET /student/{id}/history`) communicating directly to the Gravity Forms REST API fetching derived historical certificates.
  - Created a new secure endpoint (`POST /students/{id}/meta`) for persisting legacy user profile data mappings.
- **DevOps:**
  - Initialized Git repository.
  - Created private GitHub repository: `git@github.com:onedogsolutions/simple-lms.git`.
  - Configured SSH authentication.

## Technical Details

- **WP REST API Base:** `simple-lms/v1`
- **Tailwind CSS:** Fully integrated into local node build pipeline (`build:css`). Compiled `.css` loaded on specific plugin pages (`slms-students`, `slms-migration`).
- **Join Tables:** `wp_slms_course_lesson` (M2M lessons), `wp_slms_user_course` (Enrollments).
- **PMPro Sync:** Membership level changes automatically trigger enrollment via `pmpro_after_change_membership_level`.
- **Plugin Meta Key for Access:** `_slms_course_access_days`
- **Remote Repository:** `git@github.com:onedogsolutions/simple-lms.git`
- **Main Branch:** `main`

## Remaining Tasks

- [x] **Beaver Builder Integration:** Implement modules for course/lesson display.
- [x] **Certificate Automation:** Ensure certificates are generated and access is revoked automatically.
- [x] **Migration Utility:** Build tool to migrate data from WP Complete.
- [x] **Student Dashboard BB Module:** New 3-tab BB module (profile, purchase history, certificates).
- [x] **Admin Menu Hierarchy:** Fix disconnected menus and group under SimpleLMS hub.
- [x] **Student Manager UI:** Modernize with Tailwind CSS and detailed progress views.
- [x] **Migration Logging:** Added structured diagnostic logging across all migration phases.
- [ ] **QA & Testing:** Conduct thorough end-to-end testing of the enrollment and expiration flow.

## Migration Diagnostic Logging

Added comprehensive logging to `class-migration.php` and `MigrationTool.js` to debug student progress import issues.

### PHP Backend (`class-migration.php`)
- **Dual-output logging**: Every log entry writes to both an in-memory buffer (returned via REST API) and `wp-content/debug.log` via `error_log()`.
- **Phase 1 (CPT)**: Logs legacy course discovery, import/dedup results, lesson linking counts.
- **Phase 2 (Student Progress)**: Logs per-user processing with email labels, WPComplete data format detection (serialized vs JSON), legacy-to-new lesson ID mapping results, course linkage failures, enrollment status checks, and a final summary with skip-reason stats (`lessons_mapped`, `lessons_skipped_no_match`, `lessons_skipped_no_course`, `lessons_skipped_not_enrolled`).
- **Phase 3 (History)**: Logs GF entry counts per user, certificate form discovery, dedup stats.
- All REST migration responses now include a `log` array and Phase 2 includes a `stats` object.

### React Frontend (`MigrationTool.js`)
- **Live Log Panel**: Collapsible panel below the migration phases, auto-opens on first log entry.
- **Level Filters**: Filter buttons for All / Error / Warn / Info / Debug.
- **Badge Counts**: Error and warning counts shown in panel header.
- **Dark Terminal UI**: Monospace font on dark background for easy scanning.
- **Auto-scroll**: Log automatically scrolls to newest entries.

### Debugging Workflow
1. Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are `true` in `wp-config.php`.
2. Run migration from the admin Migration Hub page.
3. Watch the live log panel in the browser for immediate feedback.
4. For deeper analysis, check `wp-content/debug.log` for the `[SimpleLMS]` prefixed entries.

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
- **Tab 3 (Certificates)**: Queries GF API for entries created by current user.
- **Styling**: Fully stylable via BB settings (Colors, Typography, Card-style tabs).

### Tab 1 — User Profile

- Embeds a Gravity Form (admin-configurable Form ID) via `gravity_form()`.
- GF dynamic population pre-fills fields from `wp_usermeta`.

### Tab 2 — Purchase History

- Wraps PMPro native shortcode: `[pmpro_account sections="membership,invoices"]`.
- Graceful fallback if PMPro is not active.

### Tab 3 — Certificates Earned

- Queries `GFAPI::get_entries()` filtered by `created_by` = current user.
- Table columns mapped to GF field IDs via BB settings (Name, Course, Date, PDF Link).

### BB Settings

- **General tab:** Form ID selectors, GF field ID mapping for certificate columns, custom tab labels.
- **Style tab:** Color pickers (tabs, table, buttons, content area), typography (heading/body font size), content padding.

## Continuity Notes

- **GitHub Username:** `onedogsolutions`
- **Next Step:** Continue feature development toward 1.0 release. Use diagnostic logging to validate migration data as new features are built out.

## Local AI Context (Ollama Qwen3-30B)

- **Primary Model:** qwen3-coder:30b
- **IDE Bridge:** VS Code + Continue Extension
- **Instruction:** Focus on vanilla WordPress PHP and React (@wordpress/scripts). Avoid suggesting heavy external npm libraries unless essential.
