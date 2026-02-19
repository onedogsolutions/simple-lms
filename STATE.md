# Project State: Simple LMS Bridge (One Dog Solutions)

## Overview

This document maintains continuity for the Simple LMS Bridge project (rebranded to One Dog Solutions). It tracks current progress, architecture decisions, and remaining tasks.

## Current Status: [ACTIVE DEVELOPMENT]

The project has recently undergone a major refactor and has been moved to a private GitHub repository.

## Accomplishments (Recent)

- **Rebranding:** Renamed plugin to "One Dog Solutions".
- **API Migration:** Moved from jQuery AJAX to WP REST API.
- **Frontend Modernization:** Updated CSS to use Flexbox and modern typography.
- **Backend Refactor:**
  - Implemented proper Namespacing.
  - Fixed PHP linting/syntax errors.
- **Feature Implementation:**
  - Added Course Access time limits (`_lms_course_access_days`).
  - Implemented automatic access removal upon certificate generation.
  - Integrated Paid Memberships Pro (PMPro) for course enrollment.
  - **M2M Overhaul:** Replaced direct meta linking with Many-to-Many join tables (`slms_course_lesson`, `slms_user_course`) for courses, lessons, and student enrollments.
- **Migration Engine:**
  - Built deduplicated importer for WP Complete data.
  - Batch-syncs student progress to the new M2M join table.
- **DevOps:**
  - Initialized Git repository.
  - Created private GitHub repository: `git@github.com:onedogsolutions/simple-lms.git`.
  - Configured SSH authentication.

## Technical Details

- **WP REST API Base:** `simple-lms/v1`
- **Join Tables:** `wp_slms_course_lesson` (M2M lessons), `wp_slms_user_course` (Enrollments).
- **PMPro Sync:** Membership level changes automatically trigger enrollment via `pmpro_after_change_membership_level`.
- **Plugin Meta Key for Access:** `_lms_course_access_days`
- **Remote Repository:** `git@github.com:onedogsolutions/simple-lms.git`
- **Main Branch:** `main`

## Remaining Tasks

- [x] **Beaver Builder Integration:** Implement modules for course/lesson display.
- [x] **Certificate Automation:** Ensure certificates are generated and access is revoked automatically.
- [x] **Migration Utility:** Build tool to migrate data from WP Complete.
- [ ] **Student Dashboard BB Module:** New 3-tab BB module (profile, purchase history, certificates) — see architecture below.
- [ ] **QA & Testing:** Conduct thorough end-to-end testing of the enrollment and expiration flow.

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
- **Next Step:** Implement the Student Dashboard BB module per the architecture above.

## 🤖 Local AI Context (Ollama Qwen3-30B)

- **Primary Model:** qwen3-coder:30b
- **IDE Bridge:** VS Code + Continue Extension
- **Instruction:** Focus on vanilla WordPress PHP and React (@wordpress/scripts). Avoid suggesting heavy external npm libraries unless essential.
