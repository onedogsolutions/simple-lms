# SimpleLMS Project State

Last updated: 2026-11-27

## Current objective

SimpleLMS is a WordPress-native LMS with separate course and lesson post types, ordered many-to-many curricula, native enrollment, context-aware learner progress, Presto Player event tracking, Gravity Forms verification, and Gravity PDF certificate integration. Pods is not a runtime dependency of the core plugin.

The separate `simple-lms-migrator` plugin has completed the approved three-batch staging conversion. Native content, relationships, enrollment, and historical progress are migrated and verified. Learner-facing runtime acceptance remains blocked because course curriculum rendering, Presto player rendering, completion UI, and Beaver Builder modules have not been implemented.

## Target site

- Intended hostname in the brief: `staging.laseresthetics.org`.
- Authorized NovaMira profile: `staging.laseraesthetics.org`.
- Authorized site URL: `https://staging.laseraesthetics.org/`.
- Verified environment: WordPress 7.1.5, PHP 8.3.37, MariaDB 11.8.10.
- Relevant versions: Presto Player 4.5.7, Presto Player Pro 3.3.1, Gravity Forms 3.2.3.1, Gravity PDF 6.18.1, Pods 3.3.9.2, WPComplete 2.9.6, Beaver Builder 2.10.3.1, Beaver Themer 1.6.1.1.
- SimpleLMS core and the migrator were deployed to staging, and Batches 1–3 were completed and verified.
- No runtime-acceptance remediation, form submission, certificate-entry change, legacy-plugin deactivation, decommissioning, or production action occurred.

## Verified legacy schema

The following observations describe the source system that the migrator read during conversion:

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

Staging currently contains `10` published `slms_course` posts (`1863`–`1872`) and `26` published `slms_lesson` posts (`1873`–`1898`). Batch 2 stored `57` ordered curriculum links and synchronized the reverse lesson-to-course lookups.

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
- The REST completion endpoint and event tracker exist, but the plugin does not render a Presto player, curriculum outline, lesson navigation, or completion button.
- No SimpleLMS Beaver Builder module library exists yet. Existing staging layouts still use legacy Pods conditions and hierarchical shortcodes.

## Verification and certificate integration

Local defaults preserve the identifiers requested in the brief:

- Verification page: `465`.
- Gravity Form: `5`.
- Course URL field: `18`.
- Gravity PDF template: `324` / `aala-certificate`.
- Account page: `322`.

Staging settings use Gravity PDF template ID `324` with slug `aalp-certificate`. Live Gravity Forms field `18` and the Gravity PDF conditional rules still contain legacy `/course/...` URLs, while native course permalinks use `/courses/...`. `Gravity_Forms::url_path()` normalizes trailing slashes but does not translate those prefixes. This is a suspected certificate blocker and has not been confirmed through an approved learner submission.

The published account page uses Beaver Builder tabs. Its certificate tab still contains `Coming Soon!`, although the existing certificate block `478` lists Gravity PDF downloads. The native account shortcode is not present in the live layout.

The certificate page `465` places Gravity Form `5` inside a column gated by legacy referrer rules. Direct native verification links therefore render a blank page. Any layout or Gravity Forms/PDF reconciliation requires separate approval.

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

- Core dependency-free integration suite: `69` assertions passed.
- Migrator dependency-free integration suite: `301` assertions passed.
- Coverage includes native CPT registration, activation rewrite flushing, archive rewrite precedence, ordered many-to-many storage, O(1) reverse lookup, context-aware URLs, enrollment, shared completion, active-course next steps, REST curriculum updates, guarded and idempotent migration batches, learner-state remapping, rollback behavior, settings cleanup, and verification identifiers.
- PHP syntax checks passed for both plugin bootstraps and all core/migrator includes.
- The migrator ZIP passed archive-integrity and source-content comparisons.

Completed staging migration:

```text
Batch 1: 10 courses created; 26 canonical lessons created
Batch 2: 10 courses linked; 57 curriculum links; 0 products; 0 levels
Batch 3: 70 users updated; 57 lesson records; 10 course records
         37 enrollment records; 0 verification records
```

Post-Batch-3 verification confirmed `70` valid pre-migration backups, `280` native learner-meta rows, preserved legacy enrollments, unchanged curricula and reverse lookups, and zero pending learner-state writes. Stable hashes:

```text
plan_hash:        c6b4c57b0fd8b55843906ee983ad6bcab4038b037e99c9a318bee6b9cf0bfc1e
relationship_hash: 9668dd366b9b3c94bd876834825a67b7b24b976c02a2f90b5c383bcfe65ad0ae
progress_hash:    9ea219f55ef0ed15e161291f784cf5374fcbcc411f4845919afb7f385ca10840
```

## Known limitations and approval gates

- Native single-course URLs resolve, but no curriculum or lesson links render because native singular rendering and Beaver Builder modules are missing.
- Native lesson URLs resolve and retain `_lms_presto_video_id`, but no Presto player renders. The tracker cannot complete a player that is absent from the DOM.
- The account page loads SimpleLMS assets but has no native account dashboard; its Beaver tabs still use legacy/Pods content.
- Direct native certificate links are blank because the live form column depends on legacy referrer rules.
- Gravity Forms and Gravity PDF still use legacy `/course/...` values; native-to-legacy URL reconciliation remains unapproved and untested.
- Authenticated playback, completion writes, verification submissions, and certificate downloads require an approved learner test account and explicit approval.
- Native `/courses/` rewrites can overlap the legacy Pods course rewrite while both plugins remain active. Recheck routing after any future permalink flush or Pods deactivation.
- Legacy Pods, WPComplete, and other legacy plugins remain active. No decommissioning or production action is authorized.

## Exact next task

Stop at the migration-complete gate. No further staging changes are planned in this session. Future work must first implement the learner-facing curriculum, Presto player/completion UI, and Beaver Builder modules, then integrate the native account and certificate surfaces. Runtime acceptance and legacy-plugin decommissioning remain blocked until those features exist and an approved learner test account passes the full flow.
