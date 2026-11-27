# SimpleLMS Project State

Last updated: 2026-11-26

## Current objective

Replace WP Complete on the Laser Aesthetics staging site with a lean SimpleLMS core plugin and a disposable WP Complete migrator. Core behavior must remain configuration-driven until the staging schema is verified.

## Target site

- Intended host in the brief: `staging.laseresthetics.org`.
- Authorized NovaMira profile: `staging.laseraesthetics.org`.
- Authorized site URL: `https://staging.laseraesthetics.org/`.
- Verified environment: WordPress 7.1.5, PHP 8.3.37, MariaDB 11.8.10, NovaMira plugin 2.0.0, REST API contract 1.
- Relevant plugin versions: Pods 3.3.9.2, Presto Player 4.5.7, Presto Player Pro 3.3.1, Gravity Forms 3.2.3.1, Gravity PDF 6.18.0, Beaver Builder 2.10.3.1, Beaver Themer 1.6.1.1, WPComplete 2.9.6.
- Connection status: healthy. Authentication, metadata, context, REST, and permission checks pass; the only `doctor` warning is that local Node.js 25.8.2 is supported but not a primary tested LTS release.
- Safety decision: reconnaissance was read-only. No staging mutation, deployment, migration, schema upgrade, login, purchase, or form submission was performed.

## Reconnaissance checklist

Phase 1.1–1.7 is complete as of 2026-11-26. Values below were observed on the authorized staging profile.

### 1.1 Pods architecture

- Pods course pod: ID `67`, name/slug `course`, label `Courses`, type `post_type`, storage `meta`, hierarchical.
- There is no separate `lesson` post type. Courses and lessons are both posts of type `course`; lessons are child posts.
- Course-to-lesson relationship: core WordPress `post_parent` on `wp_posts`, with the parent course as the source and child lessons as the targets. No Pods relationship field is involved.
- Relationship storage: not applicable to a Pods field; the hierarchy is stored in the core posts table. The course pod itself uses meta storage.
- Lesson ordering: core `menu_order` (Page Attributes), not a custom Pods numeric field.
- Course archive slug: `courses`.
- Relevant course fields: `course_price` (field ID `515`, currency) and `lesson_video` (field ID `69`, text/shortcode content such as `[presto_player id=920]`).
- Inventory: `62` items in the `course` pod.
- Related taxonomy: `course_group`, taxonomy ID `495`, meta storage.

### 1.2 Purchase and access control

- Access source of truth: Pods user field `enrolled_courses`.
- User pod: ID `305`.
- Enrollment field: ID `388`, type `pick`, `pick_object: post_type`, `pick_val: course`, multi-select.
- Stored user meta key: `enrolled_courses`; values reference `course` post IDs. A scalar value such as `71` is valid observed data.
- Non-empty enrollment rows found: `38`.
- WooCommerce and PMPro were not present in the current plugin inventory. No order-history or membership-level course mapping was verified; the plugin should keep those modes optional rather than treating them as staging dependencies.
- Integration requirement: `simple_lms_user_has_course_access( $user_id, $course_id )` must wrap this Pods enrollment check and retain a filter for alternate access sources.

### 1.3 Presto Player

- Lesson embed pattern: `[presto_player id=...]` stored in the Pods `lesson_video` field.
- Player DOM: `<presto-player>` custom element with an open shadow root; native `<video>` is nested under `<presto-video>`.
- Exact public completion event: `endedMedia`, emitted by `<presto-player>`.
- Event behavior: bubbles, is composed, is cancelable, and carries the Plyr instance in `detail`.
- Installed bundle evidence: the Plyr `ended` callback emits `endedMedia`; `presto.videoEnded` is not the event exposed by this implementation.
- Frontend policy: listen for `endedMedia` without jQuery. Internal/native `ended` may be used only as a fallback where required.
- Runtime limitation: the public lesson inspected while logged out returned an enrollment notice and no player. Same-origin installed bundle source verified the event contract; an authenticated learner playback/end-event capture remains a staging validation gate.

### 1.4 Gravity Forms and Gravity PDF

- Verification page: ID `465`, title `Certificate`, slug `/certificate/`.
- Page implementation: Beaver Builder PowerPack `pp-gravity-form` module, `select_form_field: "5"`, AJAX enabled.
- Verification form: ID `5`, title `Certificate`, active, `1997` entries at audit time.
- Form fields:
  - `19` — Select Student; default `{user:ID}`; admin-conditional.
  - `20` — User Role.
  - `1` — Name; first input `1.3`, last input `1.6`.
  - `17` — Email.
  - `5` — Technician License Number; exam-conditional.
  - `6` — State; drives certificate conditional logic.
  - `18` — Course; default `{referer}`; choice values are course URLs, not IDs or titles.
  - `21` — Completion Date; default `{date_mdy}`.
- Course mapping requirement: resolve the submitted field `18` URL back to a `course` post; there are no separate course ID and course title fields to pre-populate.
- Notifications: `64546eaf189f5` (Course Completion Notification) and `64d2ea22a2924` (Exam Completion Notification).
- Confirmation: `6358877aa0d08`; redirects to page `1174`, title `Certificate Thank You`, slug `/certificate-thank-you/`.
- Gravity PDF template: post ID `334`, post type `pdf_template`, title `Certificate`, template slug `aalp-certificate`.
- Gravity PDF does not use one universal configuration ID for form `5`. It has eleven active conditional configurations sharing template `aalp-certificate`:
  - `63589084394c6` — Certificate PDF - Single Courses.
  - `64d2e4a77792d` — Certificate PDF - 10 Hour Regular.
  - `64d2e723c4ffc` — Certificate PDF - 10 Hour - North Carolina.
  - `64d2e91a101e9` — Certificate PDF - 8 Hour Regular.
  - `64d2eb9e7695e` — Certificate PDF - Laser Safety Officer.
  - `64d2ec308b349` — Certificate PDF - 8 Hour - Texas.
  - `64d2ee7804230` — Certificate PDF - Professional Exam.
  - `6703e04bad763` — Certificate PDF - 10 Hour Texas.
  - `68f7bd0f69c7f` — Certificate PDF - 5 Hour Regular.
  - `68f7bd3a88441` — Certificate PDF - 5 Hour - North Carolina.
  - `68f7bd8299f1a` — Certificate PDF - 5 Hour Texas.
- Certificate URL implementation must preserve Gravity PDF's conditional configuration selection rather than forcing one configuration for every course.

### 1.5 Student account tabs

- Account page: ID `322`, title `My Account`, slug `/my-account/`.
- Layout status: published layout exists and draft changes also exist.
- Tab implementation: Beaver Builder PowerPack `pp-advanced-tabs`, style `style-1`, ID prefix `account-tab`.
- Tab colors: active background `#011638`, inactive background `#ffffff`, inactive text `#ec2991`, active text `#b7c3f3`.
- Existing tabs:
  - `User Profile` — `Update your account information.` — content module `363`, reusable layout `33`.
  - `Purchase History` — `View your course purchases.` — content module `364`, reusable layout `33`.
  - `Certificates Earned` — `Download your certificates.` — content module `478`, reusable layout `33`, fallback content `<p>Coming Soon!</p>`.
- These live labels differ slightly from the brief's Personal Info / Purchased Courses / Earned Certificates wording. Any replacement should preserve or deliberately migrate the existing labels and layout ownership.

### 1.6 WP Complete data

- Exact meta key: `wpcomplete`.
- Meta rows: `1474`; `1405` empty values, `69` JSON objects, `0` PHP-serialized values. Empty rows are not corrupt records.
- Value shape: a JSON object keyed by post ID, plus a `0-site` timestamp. Non-empty post records contain `first_seen` and optional `completed` timestamps.
- Observed example:

```json
{
  "0-site": 1795652760,
  "556": {
    "first_seen": "2026-11-18 20:14:11",
    "completed": "2026-11-18 20:24:20"
  }
}
```

- Completed records: `57`; distinct completed post IDs: `33`. Every resolved completed post has post type `course`.
- Completed IDs grouped by parent:
  - Parent `546`: `556`, `559`, `562`, `565`, `568`, `571`, `574`, `577`, `580`, `583`, `586`, `589`, `592`, `595`, `598`, `601`.
  - Parent `71`: `74`, `77`, `81`, `84`, `87`, `90`, `93`, `96`, `99`, `103`, `108`.
  - Parent `1592`: `1597`, `1602`.
  - Top-level standalone course IDs: `688`, `697`, `708`, `1189`.
- Migration requirement: the standalone migrator must parse JSON, ignore `0-site`, preserve first-seen/completed timestamps where appropriate, map child lesson IDs through `post_parent`, and treat standalone completed courses explicitly.

### 1.7 State recording

- All verified slugs, IDs, field names, meta keys, event names, schema details, and rollout gates are recorded in this file before Phase 2 configuration or implementation.

## Locked architecture decisions

1. Core is a WordPress-native connector, not a replacement content schema. Pods remains the content source of truth; staging uses one hierarchical `course` post type and `post_parent` for lessons.
2. The core remains configurable for alternate schemas, but verified staging defaults must match the live architecture rather than the earlier assumed separate `lesson` post type or Pods relationship field.
3. Lesson completion is stored idempotently in configurable user meta. Course completion is derived from ordered child lessons; standalone courses require explicit handling.
4. Staging access comes from Pods user meta `enrolled_courses`. WooCommerce, PMPro, and logged-in modes remain optional adapters behind the same filterable access helper.
5. Gravity Forms owns verification submission and Gravity PDF owns certificate rendering. SimpleLMS stores verified course/entry references and resolves conditional PDF configurations instead of hard-coding one template configuration.
6. Frontend tracking is vanilla ES2022+ using `fetch`, `CustomEvent`, and native DOM APIs. Presto completion uses `endedMedia`; no frontend or admin enqueue may declare jQuery as a dependency.
7. Admin settings use React 19 and Tailwind CSS v4. Styles are mounted inside a shadow root so Tailwind preflight and utilities cannot leak into WordPress core admin.
8. `simple-lms-migrator` remains standalone and disposable, supports dry-run/apply safety, and never migrates without an explicit apply command or approved admin action.
9. Production or shared-staging rollout remains a separate approval gate.

## Completed milestones

- Phase 1 reconnaissance is complete and recorded.
- Verified the authorized NovaMira connection and read-only access to the staging environment.
- Resolved the actual Pods hierarchy, access source, Presto event, Gravity Forms/PDF identifiers, account structure, and WP Complete schema.
- Confirmed the existing license is the GNU GPL version 2 text; plugin headers use `GPL-2.0-or-later`.
- Phase 2 MVP implementation is complete in the local repository. Defaults now match the verified staging schema.
- Added the plugin bootstrap and modular settings, access, progress, Gravity Forms, REST API, shortcode, and admin classes under `includes/`.
- Added `/wp-json/simple-lms/v1/complete`, `/settings`, and transition-only lesson/course completion hooks with the required filterable access helper.
- Added shared-CPT child lesson resolution, standalone video-course handling, idempotent user-meta progress, verification records, and conditional Gravity PDF resolution.
- Added Gravity Forms course-URL mapping and composite Name pre-population using the verified page, form, and field IDs.
- Added the vanilla Presto `endedMedia` tracker with native `<video> ended` fallback, REST nonce authentication, duplicate-request prevention, and `CustomEvent` status dispatch.
- Added `[simple_lms_courses]`, `[simple_lms_account]`, and `[simple_lms_certificates]` shortcodes.
- Added the React 19 / Tailwind CSS v4 tabbed admin shell. Its generated stylesheet is fetched into a shadow root so admin styles remain isolated.
- Corrected Gravity PDF integration to prefer `GPDFAPI::get_entry_pdfs()` and `GPDFAPI::get_pdf_url()`, with top-level `gfpdf_form_settings` as the form-settings fallback.
- Final local validation passed: PHP syntax checks for the bootstrap and all seven includes, `npm run build`, `git diff --check`, and `npm audit --omit=dev` with zero vulnerabilities.

## Known limitations and approval gates

- The intended hostname in the brief differs from the authorized profile hostname by one letter. Confirm the deployment target before any staging or production rollout.
- Authenticated Presto playback and `endedMedia` capture still require learner access or an approved staging test account.
- The account page has unpublished draft changes; do not overwrite the published Beaver Builder layout without a separate content decision.
- Account page `322` is not automatically connected to the new certificate UI. The approved integration path is to place `[simple_lms_certificates]` in existing certificate module `478`; replacing the full tab set with `[simple_lms_account]` requires layout approval.
- Gravity PDF configuration selection and certificate download URLs still require an authenticated end-to-end staging test with a real verified entry.
- No migration, plugin activation, deployment, or shared-staging change is authorized by this reconnaissance or local build.

## Exact next task

Review the completed local MVP and approve an isolated staging activation/test plan. Runtime validation must cover authenticated course access, Presto playback through `endedMedia`, idempotent REST completion, Gravity Forms verification, conditional Gravity PDF selection/download, and the approved account-page integration. After runtime acceptance, scaffold the separate disposable `simple-lms-migrator` repository against the verified `wpcomplete` JSON schema with dry-run/apply safeguards. Do not deploy, mutate page `322`, activate the plugin, submit forms, or migrate data without explicit approval.
