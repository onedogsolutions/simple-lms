# SimpleLMS

SimpleLMS is a lean WordPress connector for Pods courses, Presto Player completion tracking, Gravity Forms verification, Gravity PDF certificates, and a student account area. It keeps Pods as the content source of truth and stores learner progress in user meta.

## Requirements

- WordPress 6.5+
- PHP 8.2+
- Pods
- Presto Player
- Gravity Forms
- Gravity PDF for certificate delivery
- WooCommerce or Paid Memberships Pro only when selected for access control

## Development

```sh
npm install
npm run build
```

The build writes the React admin bundle and vanilla frontend scripts to `build/`. Admin Tailwind CSS is injected into a shadow root, so Tailwind preflight and utilities do not affect WordPress core admin. Frontend scripts use native ES2022+ APIs and have no jQuery dependency.

## Configuration

After activation, open **SimpleLMS → Settings**. The defaults match the verified staging schema recorded in [STATE.md](STATE.md):

- One hierarchical `course` post type; lessons are child posts through `post_parent`.
- Lesson ordering uses `menu_order`.
- Enrollment uses the Pods user field and meta key `enrolled_courses`.
- Presto completion uses the `endedMedia` custom event.
- Verification uses page `465`, Gravity Form `5`, and course URL field `18`.
- Gravity PDF template post `334` / slug `aalp-certificate` is selected through the form's conditional PDF configurations.

Settings are stored in the `simple_lms_settings` option and can be overridden for another site without changing core completion logic.

## Architecture

- `includes/class-settings.php` centralizes defaults, sanitization, and persistence.
- `includes/class-access.php` resolves Pods, WooCommerce, PMPro, or logged-in access.
- `includes/class-progress.php` resolves course/lesson hierarchy and stores idempotent progress.
- `includes/class-gravity-forms.php` pre-populates and validates verification, maps course URLs, and resolves conditional PDF configurations.
- `includes/class-rest-api.php` exposes the versioned REST namespace.
- `includes/class-shortcodes.php` renders course, account, and certificate views.
- `includes/class-admin.php` loads the scoped React admin shell.

## REST API

Namespace: `/wp-json/simple-lms/v1/`

- `POST /complete` — records completion for the authenticated user and returns course progress.
- `GET /settings` — returns settings and system status for administrators.
- `POST /settings` — validates and saves administrator settings.

Cookie-authenticated requests use the standard `X-WP-Nonce` header.

## Hooks

Actions:

- `simple_lms_lesson_completed`
- `simple_lms_course_completed`
- `simple_lms_course_verified`

Filters:

- `simple_lms_user_course_access`
- `simple_lms_user_can_access_course` (compatibility alias)
- `simple_lms_course_lesson_ids`
- `simple_lms_accessible_course_ids`

The global helper `simple_lms_user_has_course_access( $user_id, $course_id )` wraps the configured access resolver.

## Shortcodes

- `[simple_lms_courses]` — course catalog and progress-aware calls to action.
- `[simple_lms_account]` — User Profile, Purchase History, and Certificates Earned tabs.
- `[simple_lms_certificates]` — current student's verified certificates.

Existing Beaver Builder account tabs can be retained by placing `[simple_lms_certificates]` in the certificate panel. Replace the full tab set with `[simple_lms_account]` only when a layout migration is approved.

## Migration and safety

The WP Complete migrator is intentionally separate from this repository. It must remain disposable and require an explicit dry-run/apply workflow. SimpleLMS does not activate, migrate, or deploy anything automatically, and shared-staging rollout remains a separate approval gate.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
