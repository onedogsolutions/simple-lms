# SimpleLMS — Product Evaluation & Roadmap to 1.0

**Date:** July 2026
**Scope:** Evaluation of `STATE.md` and the current codebase against the original product vision, and a staged path to a complete product.

## The Product Vision (restated)

Our own version of WP Complete, modernized:

1. Enhanced student and progress manager
2. Updated student analytics
3. Our own CPTs (courses, lessons)
4. Direct Beaver Builder integration with custom modules
5. Paid Memberships Pro integration for payment gateway **and content guarding**

---

## Part 1 — Where the Product Stands

### What is genuinely solid

| Area | Status | Notes |
|---|---|---|
| CPTs & taxonomy | ✅ Done | `slms_course`, `slms_lesson`, `slms_course_cat`, REST-exposed meta (`class-cpt.php`) |
| M2M data model | ✅ Done | `slms_course_lesson`, `slms_user_course` join tables with clean `Relationships` API |
| Compliance history | ✅ Done | `slms_course_history` table, 9-year retention, dedup on insert |
| Student Manager (admin) | ✅ Done | React UI: search, sort, filter, details view, legacy Pods meta editing, completion history w/ PDF links |
| Course/Lesson editors | ✅ Done | React metaboxes: lesson sorter, lesson type/video/quiz settings, PMPro level mapping |
| Migration engine | ✅ Done (mature) | 4-phase WP Complete import, battle-tested against 1300+ real user records, triple-output logging, debug log viewer |
| PMPro enrollment sync | ✅ Done | `pmpro_after_change_membership_level` → enroll/de-enroll, group accounts support |
| BB modules (lesson-side) | ✅ Done | `lms-content`, `lms-outline`, `lms-complete-button`, `slms-student-dashboard` — all self-contained per the architecture rule |
| Certificate automation | ⚠️ Works, fragile | Completion → GF entry → GravityPDF conditional resolution. Functional after many rounds of fixes, but deeply coupled to GF form 5's 11-template conditional structure |
| Access expiration | ⚠️ Partial | Daily cron exists but only covers PMPro-sourced enrollments (see defects) |

### The two pillars of the vision that don't exist yet

**1. Content guarding — not implemented.**
The `simple_lms_check_access` filter is registered (`class-pmpro.php:44`) but `apply_filters('simple_lms_check_access', ...)` is never called anywhere in the plugin. There is no `template_redirect` guard, no content filter, no login/enrollment check in any BB module. `lms-content/includes/frontend.php` renders `the_content()` for anyone who hits a lesson URL. Both CPTs are registered fully `public` with archives. Today, all course content is publicly readable by URL. `PMPro::has_course_access()` exists and works — it's simply never wired to anything.

**2. Student analytics — not implemented.**
There is per-student progress display in the Student Manager, but no aggregate reporting of any kind: no enrollment trends, completion rates, lesson drop-off, time-to-complete, certificate issuance volume, or at-risk students. Nothing in REST, nothing in React.

### Critical defects found in this review

These are pre-existing bugs, listed most severe first:

1. **Students cannot mark lessons complete.** `POST /progress` requires `current_user_can('edit_users')` (`class-rest.php:70-72`), but the frontend Complete button (`lms-complete-button` + `assets/js/frontend.js`) posts there as the logged-in student. Every real student gets a 403. It only ever worked when testing as an admin. Related: the endpoint takes an arbitrary `user_id` and never checks the acting user, enrollment, or that the lesson belongs to the course.

2. **Certificate submission cancels the user's entire PMPro membership unconditionally.** `Certificates::remove_course_access()` calls `PMPro::de_enroll_user()` (which already removes the mapped level *only if it matches*), then **also** calls `pmpro_changeMembershipLevel(0, $user_id)` with no level check (`class-certificates.php:91-93`). A student completing one course loses membership levels unrelated to that course.

3. **Duplicate, divergent DDL for `slms_course_history`.** `Relationships::create_table()` (`class-relationships.php:86-95`) creates the table with an old schema (no `form_id`, no `cert_data`, `gf_entry_id NOT NULL DEFAULT 0`), then `CourseHistory::create_table()` creates the richer schema. Both run on activation in sequence; correctness currently depends on call order. One owner, one schema.

4. **Fatal on a live code path:** `class-rest.php:1081` calls `\get_page_to_path()` — a function that does not exist (typo of `get_page_by_path`). Any history row whose course name is a URL that `url_to_postid()` can't resolve fatals the `/student/{id}/history` response.

5. **Expiration misses non-PMPro enrollments.** `Expiration` reads only the `_lms_enrolled_at` user meta, which is written by `PMPro::enroll_user()` but **not** by `Relationships::enroll_user()` (manual/migration sources). Manually enrolled and migrated students never expire. `_lms_access_days` is effectively PMPro-source-only.

6. **Tailwind Play CDN in production admin.** `simple-lms-bridge.php:196-202` loads `https://cdn.tailwindcss.com` (a dev-only tool per Tailwind docs) on every SLMS admin page as a "fallback." It papers over a stale local build, adds a remote script dependency to wp-admin, and will fight the compiled v4 CSS. The build pipeline should be fixed and the CDN removed.

7. **Committed-but-ignored build artifacts.** `build/` is in `.gitignore`, yet `build/admin/index.js` and `index.asset.php` are tracked (stale), while `build/admin/tailwind.css` / `index.css` — which the enqueue references — are not. A fresh clone without `npm run build` ships broken admin styling (the reason defect #6 exists).

### Structural debt (not bugs, but taxes on every future feature)

- **Dual sources of truth for progress and enrollment.** Progress lives in `_lms_progress` user meta (serialized array) while enrollment lives in the `slms_user_course` table *and* `_lms_enrolled_at` meta. The Student Manager reads the table for enrollment but meta for progress. Analytics (Stage 3) is nearly impossible to do efficiently against serialized user meta — SQL can't aggregate it.
- **Hardcoded site-specific values.** GF certificate field IDs `6`/`18`, form-title matching on the word "Certificate", the 90-day migration rule, GF Form ID 2 product-field map — all baked into class code. Fine for the first site, blocking for productization. Needs a settings layer.
- **No DB migration/versioning system.** Schema changes only apply via `register_activation_hook`. The `form_id` column addition already required manual reactivation. Need a `slms_db_version` option checked on `admin_init` with incremental upgrade steps.
- **Dead/orphaned code:** `includes/bb-modules/slms-student-dashboard-tab-2/` (never loaded, violates the self-contained module rule), `includes/class-account-dashboard.php` (intentionally unloaded), root-level `cleanup_ghost_enrollments.sql`.
- **STATE.md has drifted from the repo.** It documents an `lms-account-dashboard` module and `deploy.sh` that are not in the repository. STATE.md is also now ~90% changelog; its "current state" signal is buried.
- **Text domain inconsistency:** `simple-lms-bridge` (core) vs `simple-lms` (dashboard BB modules).
- **No tests, no CI, no PHPCS config** despite lint scripts in `package.json`.
- **Quiz features are half-declared.** `_slms_lesson_type=quiz`, `_lms_gravity_form`, `_lms_quiz_timer` meta all exist and the quiz form renders, but nothing enforces the timer and completion is never tied to quiz submission — a student can "Mark as Complete" without passing the quiz (once defect #1 is fixed, this becomes reachable).

### Bottom line

The plugin is a **strong admin/migration tool with a data model ready for an LMS, but it is not yet an LMS product**: a student today cannot be stopped from reading unguarded content, cannot legitimately complete a lesson, and the business owner has no analytics. The good news: the hard, ugly work (migration of 1300+ legacy records, M2M schema, compliance table, admin React foundation) is done. What remains is mostly *new* well-scoped feature work, not archaeology.

---

## Part 2 — Staged Path to a Complete Product

Stages are ordered so each one ships independently and de-risks the next. Sizes are relative (S ≈ days, M ≈ 1–2 weeks, L ≈ 2–4 weeks of focused work).

### Stage 0 — Stabilize the foundation (size: M)

*Goal: the current feature set actually works for a real student, and the repo is trustworthy.*

- Fix the six critical defects above (#1–#6):
  - New student-scoped progress flow (see Stage 1 for the endpoint design; minimum fix here is deriving `user_id` from the session and a `is_user_logged_in()` + enrollment permission model).
  - Remove the unconditional `pmpro_changeMembershipLevel(0, …)` from `Certificates::remove_course_access()`; let `PMPro::de_enroll_user()`'s mapped-level check be the only level mutation.
  - Delete the `slms_course_history` DDL from `Relationships::create_table()`; `CourseHistory` owns its table.
  - Fix the `get_page_to_path` fatal → `get_page_by_path`.
  - Write `_lms_enrolled_at`-equivalent data from `Relationships::enroll_user()` — or better, point `Expiration` at the `enrolled_at` column of `slms_user_course` (single source of truth).
  - Remove the Tailwind CDN; fix the build/packaging story instead (see below).
- Repo hygiene: untrack stale `build/` artifacts (build at package time), delete `slms-student-dashboard-tab-2`, `class-account-dashboard.php`, and stray root scripts; unify text domain; commit `deploy.sh` or replace it with CI.
- Introduce `slms_db_version` option + incremental upgrade runner so schema changes no longer require reactivation.
- Add GitHub Actions: PHPCS (WordPress-Extra), PHPStan level ~5 with WP stubs, `npm run build`, and a zip artifact on tag. This directly replaces the manual zip workflow that caused past merge-conflict pain.
- Rewrite `STATE.md` as a short living document (architecture + current status); move the historical changelog to `CHANGELOG.md`.

**Acceptance:** a subscriber-role test user can log in, open a lesson, click Mark as Complete, and it persists; completing the last lesson issues exactly one certificate and removes exactly one level; CI is green on a fresh clone.

### Stage 1 — Content guarding & the student access model (size: L) ← the missing core

*Goal: deliver the second half of the PMPro promise — content is protected, and access rules live in one place.*

- **`Access` service class** — the single authority every consumer calls:
  `Access::can_view($user_id, $post_id)` → enrollment table check → PMPro level check (`PMPro::has_course_access`) → expiration check → `apply_filters('simple_lms_check_access', …)` (finally wiring the existing filter).
- **Guard enforcement, three layers:**
  1. `template_redirect` guard on `slms_course`/`slms_lesson` singles → redirect to the PMPro checkout/levels page for the mapped level (carrying a return URL), or to login for anonymous users.
  2. `the_content` filter fallback (defense in depth for archive/builder/search contexts) → excerpt + CTA instead of content.
  3. REST guard: course/lesson content in the WP REST API respects the same check (both CPTs are `show_in_rest`).
- **Per-course guard mode** meta + UI in CourseEditor: `public | enrolled | level` (default `enrolled`), plus a configurable "no access" behavior (redirect vs. inline message with checkout button).
- **Student-scoped REST namespace:** `POST /me/progress` (user from session; validates enrollment via `Access` and lesson∈course via the join table), `GET /me/courses`, `GET /me/progress`. Admin endpoints remain as-is for the Student Manager. Frontend button switches to `/me/progress`.
- **Progress storage decision (do it now, before analytics):** add `slms_lesson_progress` table (`user_id, course_id, lesson_id, completed_at`), written alongside the existing meta during a transition release, with a WP-CLI/admin backfill from `_lms_progress`. Meta becomes a read-compatibility layer, then retires. This unblocks Stage 3 analytics and removes the serialized-meta bottleneck.
- Settings page (first iteration): PMPro checkout page mapping, guard defaults, certificate GF field IDs (kills the hardcoded `6`/`18`).

**Acceptance:** logged-out visitor hitting a lesson URL is redirected to checkout; enrolled student passes; expired student is blocked; unenrolled member of the right PMPro level passes (level mode); all three layers verified.

### Stage 2 — Frontend course experience: WP Complete parity and beyond (size: L)

*Goal: a site builder can assemble the full student journey in Beaver Builder with our modules only.*

- **New BB modules** (following the established self-contained pattern):
  - `lms-course-grid` — filterable course catalog card grid (category, enrolled/not, price from `_slms_course_price`, PMPro checkout CTA).
  - `lms-lesson-nav` — previous/next lesson within course order, "back to course."
  - `lms-my-courses` — enrolled courses with progress bars and "continue where you left off" (first incomplete lesson).
  - `lms-course-cta` — state-aware button: Buy (→ PMPro checkout for mapped level) / Start / Continue / Completed (→ certificate).
- **Complete-button intelligence (WP Complete parity+):**
  - Quiz lessons: auto-complete on `gform_after_submission` of the mapped quiz form (optionally gated on a passing score field); hide/disable manual button for quiz lessons.
  - Implement the declared-but-dead `_lms_quiz_timer`.
  - Optional video gating: Presto Player watch-percentage before the button activates.
- **Drip scheduling (WP Complete's headline feature):** per-lesson `_lms_drip_days` (N days after enrollment, using `slms_user_course.enrolled_at`) enforced via the Stage 1 `Access` service, with locked-state rendering in `lms-outline` and `lms-lesson-nav`.
- Course completion redirect/celebration setting (WP Complete parity).
- Add a "My Courses" tab to `slms-student-dashboard` alongside Profile / Purchase History / Certificates.

**Acceptance:** an end-to-end student journey — discover course → buy via PMPro → drip-gated lessons → quiz-gated completion → certificate in dashboard — built purely with SimpleLMS BB modules on a staging site.

### Stage 3 — Student analytics (size: M–L)

*Goal: the "updated student analytics" pillar. Depends on the Stage 1 progress table.*

- **Analytics REST layer** (`GET /analytics/*`, `manage_options`): overview KPIs (active students, enrollments/completions over time, certificates issued), per-course funnel (enrolled → started → % through lessons → completed → certificate), lesson drop-off ranking, time-to-complete distribution, at-risk list (enrolled, inactive N days, expiring access), expiration forecast.
- Nightly rollup via WP-Cron into a small `slms_analytics_daily` summary table (or transient-cached queries at first — the rollup protects a 1300+ user site from slow admin pages).
- **React Analytics page** under the SimpleLMS menu: KPI tiles, trend charts, course drill-down, at-risk table with quick actions (extend access, email), CSV export.
- Cross-link Student Manager ↔ Analytics (click a cohort → filtered student list).

**Acceptance:** owner can answer "how many students completed course X this quarter, where do students stall, and who is about to expire" without SQL.

### Stage 4 — Own the certificate pipeline (size: M)

*Goal: remove the most fragile dependency chain in the plugin (GF entry synthesis → 11 conditional GravityPDF templates), which per STATE.md consumed more debugging time than any other feature.*

- Native PDF generation (dompdf or similar, bundled): per-course certificate template (background image, name/course/date/license placeholders) configured in CourseEditor; rendered from `slms_course_history` data.
- `cert_uuid` on history rows + public verification URL (`/certificate/verify/{uuid}`) — a compliance-friendly feature GF/GravityPDF never gave us.
- Certificates become a direct product of course completion — no synthesized GF entries, no field 6/18 backfills, no conditional-logic reverse engineering — for **new** completions. The existing GravityPDF resolution path stays as the fallback for migrated legacy records (those PDFs remain valid).
- Compliance tooling: audit export (CSV/PDF batch) of history rows for state reporting; retention flag; explicit admin-only UI for `repair_form_ids()` and (heavily guarded) `purge_corrupted_records()`.

**Acceptance:** a new completion produces a branded PDF with zero Gravity-stack involvement; legacy certificate links keep working; a certificate can be verified by URL.

### Stage 5 — Productization & 1.0 release (size: M)

*Goal: from "the plugin for this site" to "a product we can install anywhere."*

- Finish the settings layer: everything site-specific becomes configurable or auto-detected (cert fields, checkout pages, dashboard page, guard defaults, PMPro level auto-creation defaults from `_slms_course_price`).
- Fresh-install path QA: activate on a bare WP + PMPro site with no legacy data — no migration required, sensible defaults, onboarding pointers (create first course, map a level).
- Update delivery: private-repo update mechanism (self-hosted update JSON or a GitHub-release-based updater) so client sites update without manual zips; CI already builds the artifact (Stage 0).
- Versioning discipline: semver, `CHANGELOG.md`, readme.txt; bump off the frozen `1.0.0`.
- Migration tool demoted to an optional "Tools" screen (hidden when no WP Complete data detected).
- Docs: admin guide, BB module reference (the HTML class table in STATE.md is a good seed), hook/filter reference (`slms_course_completed`, `slms_certificate_generated`, `slms_course_access_expired`, `simple_lms_check_access`).
- Uninstall policy: `uninstall.php` that removes options/meta but **never** drops `slms_course_history` (9-year compliance) without an explicit constant opt-in.

**Acceptance:** installable zip on a clean site passes the full Stage 2 journey; an update ships from a tagged release to a client site without SSH.

### Stage 6 — Post-1.0 candidates (unscheduled)

- Gutenberg block equivalents of the BB modules (future-proofing beyond Beaver Builder).
- Email notifications: completion congratulations, expiring-access reminders (pairs with Stage 3's at-risk data), certificate delivery.
- Course modules/sections (grouping lessons) if catalog complexity grows.
- Additional gateways via PMPro add-ons (Stripe/PayPal specifics), group/team seat sales via the already-supported PMPro Group Accounts.
- Frontend course-builder or WP-CLI commands for bulk course operations.

---

## Sequencing rationale

- **Stage 0 before everything:** every later stage builds on progress writes, PMPro level changes, and the DB schema — all of which currently have defects.
- **Stage 1 is the product's missing half** and also makes the *data* right (progress table, single access authority) so Stage 3 analytics doesn't have to be built twice.
- **Stage 2 before Stage 3:** analytics on a funnel students can't actually walk through would measure nothing.
- **Stage 4 is deliberately late:** the GravityPDF path, however fragile, currently works — replace it once the student-facing core is stable, not while it's moving.
- **Stage 5 last:** productize what's proven on the flagship site.
