# SimpleLMS

SimpleLMS is a WordPress-native LMS with separate course and lesson post types, ordered many-to-many curricula, native enrollment, Presto Player completion tracking, Gravity Forms verification, Gravity PDF certificates, and a student account area. It does not require Pods.

## Requirements

- WordPress 6.5+
- PHP 8.2+
- Presto Player for video completion tracking
- Gravity Forms and Gravity PDF for verification and certificates
- WooCommerce or Paid Memberships Pro only when selected for access control

## Native data model

- Courses use the `slms_course` post type and `/courses/` rewrite slug.
- Lessons use the `slms_lesson` post type and `/lessons/` rewrite slug.
- `_slms_lesson_ids` stores each course's ordered lesson IDs.
- `_slms_course_ids` stores each lesson's synchronized reverse course lookup.
- `_slms_enrolled_courses` stores native user enrollment as an array of course IDs.
- A canonical lesson can belong to multiple courses without duplicating its post or video.
- Lesson URLs carry `?course_id={id}` so completion and next-step navigation stay in the active course.

Plugin activation flushes rewrite rules after registering the native post types.

## Development

```sh
npm install
npm run build
php tests/run.php
```

The build writes the React admin bundle and vanilla frontend scripts to `build/`. Admin Tailwind CSS is injected into a shadow root, so Tailwind preflight and utilities do not affect WordPress core admin. Frontend scripts use native ES2022+ APIs and have no jQuery dependency.

## Configuration

After activation, open **SimpleLMS → Settings**. Defaults use:

- Native `slms_course` and `slms_lesson` post types.
- Native enrollment through `_slms_enrolled_courses`.
- Presto Player's `endedMedia` completion event.
- Verification page `465`, Gravity Form `5`, and course URL field `18`.
- Gravity PDF template `324` / `aala-certificate` as requested in the project brief.
- Account page `322`.

Settings are stored in the `simple_lms_settings` option. WooCommerce product meta `_slms_course_id` and PMPro level meta `_slms_course_ids` remain supported as optional access adapters.

Staging reconnaissance observed Gravity PDF template `334` / `aalp-certificate`; reconcile that difference before rollout. See [STATE.md](STATE.md) for verified identifiers and approval gates.

## Architecture

- `includes/class-post-types.php` registers native CPTs, taxonomy, meta, admin columns, and activation rewrite flushing.
- `includes/class-relationships.php` stores ordered curricula and synchronized reverse lookups.
- `includes/class-access.php` resolves native, WooCommerce, PMPro, or logged-in access.
- `includes/class-progress.php` stores canonical completion and resolves course-context navigation.
- `includes/class-gravity-forms.php` pre-populates and validates verification, maps course URLs, and resolves conditional PDF configurations.
- `includes/class-rest-api.php` exposes completion, curriculum, and settings endpoints.
- `includes/class-shortcodes.php` renders course, account, and certificate views.
- `src/admin/CurriculumBuilder.jsx` provides search, attachment, removal, and ordering controls.

## REST API

Namespace: `/wp-json/simple-lms/v1/`

- `POST /complete` — records completion using `lesson_id` and optional `course_id`, then returns active-course progress and navigation.
- `GET /courses` — lists courses for the Curriculum Builder.
- `GET /lessons` — searches canonical lessons for the Curriculum Builder.
- `POST /courses/{id}/lessons` — saves an ordered curriculum.
- `GET /settings` — returns settings and system status for administrators.
- `POST /settings` — validates and saves administrator settings.

Cookie-authenticated requests use the standard `X-WP-Nonce` header.

## Hooks

Actions:

- `simple_lms_lesson_completed`
- `simple_lms_course_completed`
- `simple_lms_course_verified`
- `simple_lms_course_lessons_updated`
- `simple_lms_user_enrolled`
- `simple_lms_user_unenrolled`

Filters:

- `simple_lms_user_course_access`
- `simple_lms_user_can_access_course` (compatibility alias)
- `simple_lms_course_lesson_ids`
- `simple_lms_accessible_course_ids`

The global helper `simple_lms_user_has_course_access( $user_id, $course_id )` wraps the configured access resolver.

## Shortcodes

- `[simple_lms_courses]` — course catalog and progress-aware calls to action.
- `[simple_lms_account]` — User Profile, Purchase History, and Certificates Earned tabs.
- `[simple_lms_certificates]` — the current student's verified certificates.

Existing Beaver Builder account tabs can be retained by placing `[simple_lms_certificates]` in the certificate panel. Replace the full tab set with `[simple_lms_account]` only when a layout migration is approved.

## Migration and safety

The WP Complete/Pods content migrator is intentionally separate from this repository. It provides read-only analysis followed by three confirmed batches for content, relationships/product links, and learner progress. SimpleLMS does not activate, migrate, or deploy anything automatically, and shared-staging rollout remains a separate approval gate.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
