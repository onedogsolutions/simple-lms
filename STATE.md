# SimpleLMS Project State

Last updated: 2026-11-26

## Current objective

SimpleLMS is now a WordPress-native LMS with separate course and lesson post types, ordered many-to-many curricula, native enrollment, context-aware learner progress, Presto Player tracking, Gravity Forms verification, and Gravity PDF certificates. Pods is not a runtime dependency of the core plugin.

The separate `simple-lms-migrator` plugin owns the one-time conversion from the legacy hierarchical `course` schema. Staging cutover remains pending explicit operational approval.

## Target site

- Intended hostname in the brief: `staging.laseresthetics.org`.
- Authorized NovaMira profile: `staging.laseraesthetics.org`.
- Authorized site URL: `https://staging.laseraesthetics.org/`.
- Verified environment: WordPress 7.1.5, PHP 8.3.37, MariaDB 11.8.10.
- Relevant versions: Presto Player 4.5.7, Presto Player Pro 3.3.1, Gravity Forms 3.2.3.1, Gravity PDF 6.18.0, Pods 3.3.9.2, WPComplete 2.9.6.
- No staging deployment, migration, plugin activation, form submission, certificate action, or layout mutation has occurred in this work.

## Verified legacy schema

The following observations describe the source system that the migrator must read before cutover:

- Pods defined one hierarchical `course` post type for both courses and lessons.
- Course-to-lesson relationships used core `post_parent`; lesson order used `menu_order`.
- The inventory contained `62` posts in the `course` pod.
- Enrollment used Pods user meta `enrolled_courses`; `38` non-empty rows were observed.
- Lesson video embeds used shortcodes such as `[presto_player id=920]`.
- WP Complete stored JSON objects in user meta `wpcomplete`: `1474` rows, including `1405` empty values, `69` JSON objects, and `57` completed records across `33` distinct posts.
- Completed child lessons mapped to courses through `post_parent`; standalone completed course IDs were `688`, `697`, `708`, and `1189`.

These legacy structures are migration inputs only. SimpleLMS core no longer resolves content or access through Pods.

## Native content model

### Custom post types

- `slms_course`
  - Label: Courses.
  - Public archive and rewrite slug: `/courses/`.
  - Supports title, editor, featured image, excerpt, custom fields, and revisions.
  - Exposed through the WordPress REST API.
- `slms_lesson`
  - Label: Lessons.
  - Public rewrite slug: `/lessons/`.
  - Supports title, editor, featured image, excerpt, custom fields, and revisions.
  - Exposed through the WordPress REST API.
- `slms_course_cat` is a public hierarchical taxonomy available to both post types.

Plugin activation registers the native schema and flushes rewrite rules so the new permalinks are persisted.

### Ordered relationships

- Course curriculum: `_slms_lesson_ids` on each `slms_course`, stored as an ordered `int[]`.
- Reverse lookup: `_slms_course_ids` on each `slms_lesson`, stored as an `int[]`.
- `Relationships::set_course_lesson_ids()` updates the course list and synchronizes reverse lookups.
- `Relationships::get_lesson_courses()` reads the reverse meta directly without a course scan or `LIKE` query.
- A canonical lesson can therefore belong to multiple courses without duplicate posts or duplicated Presto Player content.

### Context-aware lesson URLs

Course lesson links include `?course_id={id}` when the lesson belongs to that course. The frontend validates the requested context against the course curriculum before enqueueing the player tracker.

The completion request sends both `lesson_id` and `course_id`. Completion is stored once under the canonical lesson ID, while next-step navigation remains inside the active course.

## Native access and progress

- Enrollment meta: `_slms_enrolled_courses`, stored as an `int[]` of `slms_course` IDs.
- Default access method: `native`.
- Optional WooCommerce adapter: product or variation meta `_slms_course_id`.
- Optional PMPro adapter: membership-level meta `_slms_course_ids`.
- Logged-in access remains available as an explicit alternate mode.
- Legacy Pods settings and relationship modes were removed from Settings.

Progress meta:

- `_slms_completed_lessons`: keyed by canonical lesson ID; records contain `course_id` and `completed_at`, and migrated records may retain `first_seen` and source metadata.
- `_slms_completed_courses`: keyed by native course ID with a MySQL timestamp.
- `_slms_verified_courses`: keyed by native course ID with Gravity Forms entry, form, PDF configuration, and verification timestamp data.

When a shared lesson is completed, every related course recalculates completion from the canonical lesson ID. The active course still controls the next lesson or verification URL.

## Admin and frontend implementation

- React 19 and Tailwind CSS v4 power the Settings admin and Curriculum Builder.
- The Curriculum Builder searches canonical lessons, attaches them to a course, removes them, and reorders them with up/down controls.
- Course admin columns show lesson count and assigned WooCommerce product, variation, or PMPro level.
- Lesson admin columns show assigned course badges and Presto Player video ID/status.
- Admin styles are mounted in a shadow root to avoid changing WordPress core admin styling.
- `player-tracker.js` uses native `fetch`, listens for Presto Player's `endedMedia` event, and retains a native `<video> ended` fallback.
- Completion requests are duplicate-safe and return the active course progress, next-step URL, and verification URL when complete.

## Verification and certificate integration

Local defaults preserve the identifiers requested in the brief:

- Verification page: `465`.
- Gravity Form: `5`.
- Course URL field: `18`.
- Gravity PDF template: `324` / `aala-certificate`.
- Account page: `322`.

Staging reconnaissance observed Gravity PDF template post `334` / slug `aalp-certificate`, with eleven conditional configurations for form `5`. This discrepancy must be reconciled before production rollout. The integration prefers Gravity PDF's entry-specific conditional configuration rather than forcing one universal configuration.

The published account page uses Beaver Builder tabs. Replacing the full layout requires separate approval; the lower-risk integration path is to place `[simple_lms_certificates]` in the existing certificate module `478`.

## REST API

Namespace: `/wp-json/simple-lms/v1/`

- `POST /complete` — records canonical lesson completion in an explicit course context.
- `GET /courses` — lists courses for the Curriculum Builder.
- `GET /lessons` — searches canonical lessons for the Curriculum Builder.
- `POST /courses/{id}/lessons` — saves an ordered curriculum and synchronizes reverse lookups.
- `GET /settings` — returns settings and system status for administrators.
- `POST /settings` — validates and saves administrator settings.

Cookie-authenticated requests use the standard `X-WP-Nonce` header.

## Migration boundary

`simple-lms-migrator` remains a separate disposable plugin. It reads legacy content, WP Complete history, and Pods enrollment only for migration. Core SimpleLMS does not require Pods, WP Complete, WooCommerce, PMPro, Gravity Forms, or Gravity PDF to load; those integrations are used only when their features are selected or invoked.

Migration writes do not fire SimpleLMS completion or verification hooks and do not issue certificates.

## Validation results

- Core dependency-free integration suite: `64` assertions passed.
- Coverage includes native CPT registration, activation rewrite flushing, ordered many-to-many storage, O(1) reverse lookup, context-aware URLs, enrollment, shared completion, active-course next steps, REST curriculum updates, settings cleanup, and verification identifiers.
- PHP syntax checks passed for the bootstrap and modified native post-type class.
- Vite production build passed and emitted the admin bundle, stylesheet, account assets, and player tracker under `build/`.

## Known limitations and approval gates

- The brief's hostname differs from the authorized NovaMira profile by one letter; confirm the deployment target.
- Native `/courses/` rewrites can overlap the legacy Pods course rewrite while both plugins are active. Flush permalinks after Pods deactivation and verify course and lesson URLs.
- Authenticated Presto playback and `endedMedia` capture still require an approved learner test account.
- Gravity PDF template `324` / `aala-certificate` versus observed `334` / `aalp-certificate` remains unresolved.
- Account page `322` contains published content and unpublished draft changes; do not overwrite it without a layout decision.
- No new native-content dry run has been executed on staging. The earlier staging audit covered only the legacy progress source.
- No plugin deployment, activation, database migration, rollback, or deactivation is authorized by local development.

## Exact next task

After explicit approval, back up staging, run the migrator's read-only native-content analyzer, and review the four-course deduplication plan before any write batch. Confirm the hostname and Gravity PDF identifiers first. Execute the three migration batches in order, verify shared completion and active-course navigation, then deactivate legacy plugins only after runtime acceptance.
