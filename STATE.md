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
- **DevOps:**
  - Initialized Git repository.
  - Created private GitHub repository: `git@github.com:onedogsolutions/simple-lms.git`.
  - Configured SSH authentication.

## Technical Details

- **WP REST API Base:** `simple-lms/v1`
- **Plugin Meta Key for Access:** `_lms_course_access_days`
- **Remote Repository:** `git@github.com:onedogsolutions/simple-lms.git`
- **Main Branch:** `main`

## Remaining Tasks

- [ ] **Beaver Builder Integration:** Implement modules for course/lesson display.
- [ ] **Certificate Automation:** Ensure certificates are generated and access is revoked automatically.
- [ ] **Migration Utility:** Build tool to migrate data from WP Complete.
- [ ] **QA & Testing:** Conduct thorough end-to-end testing of the enrollment and expiration flow.

## Continuity Notes

- **GitHub Username:** `onedogsolutions`
- **Next Step:** Proceed with Beaver Builder module implementation or Migration tool.

## 🤖 Local AI Context (Ollama Qwen3-30B)

- **Primary Model:** qwen3-coder:30b
- **IDE Bridge:** VS Code + Continue Extension
- **Instruction:** Focus on vanilla WordPress PHP and React (@wordpress/scripts). Avoid suggesting heavy external npm libraries unless essential.
