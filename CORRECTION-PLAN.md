# Correction Plan — Recovering from the Parallel Stage Merges

**Date:** July 2026. **Scope:** corrections only — no new feature work until this list is done.

## What happened

The six stage plans were executed in parallel sessions and merged to `main` out of
order. Stages 0, 2, 3, and 4 are on `main` (Stage 0 merged twice via a criss-cross,
`ba164ca` + `9e5b078`). Stage 1 — the stage the sequencing rationale said must land
first — is still an unmerged branch. Two additional unplanned branches appeared.

### State of `main` (verified)

| Item | Status |
|---|---|
| Stage 0 fixes (expiration→table, cert level fix, upgrade runner, CI) | ✅ Landed. Expiration reads `slms_user_course`; `class-upgrade.php` exists |
| Stage 2 (BB modules, quiz, drip) | ✅ Landed, but built on its own `class-access.php` |
| Stage 3 analytics | ⚠️ Landed, but queries `_lms_progress` **serialized user meta** (LEFT JOIN on usermeta) — the exact data layer the progress table was designed to replace |
| Stage 4 native certificates | ✅ Landed; PHPStan green; **no staging smoke test yet** |
| **Content guarding enforcement** | ❌ **Still absent.** No `template_redirect` guard, no `the_content` filter, no REST content stripping anywhere on `main` |
| Progress table (`slms_lesson_progress`) | ❌ Not on `main` — progress still lives in serialized meta |
| `/me/*` student REST routes, guard modes, Settings | ❌ Not on `main` (only on the Stage 1 branch) |

### The competing implementations

- **`main`'s `includes/class-access.php`** (586 lines, from Stage 2 / `sleepy-dijkstra`):
  a god-class — enrollment checks, drip, **progress writes to user meta**
  (`set_lesson_progress`), progress stats, CTA state machine, price formatting,
  checkout URLs. Signature: `can_view($user_id, $lesson_id, $course_id)`.
- **Stage 1 branch's `includes/class-access.php`** (`content-guarding-access-model-dqa6bx`):
  the per-plan design — guard modes (`_lms_guard_mode`), `denial_reason()`, expiration,
  checkout resolution. Signature: `can_view($user_id, $post_id)`. Ships with
  `class-guard.php` (enforcement), `class-progress.php` (progress table),
  `class-settings.php`, `/me/*` routes, `Settings.js`. Direct file + API collision
  with `main`. Its edits to `lms-complete-button`/`lms-outline` also collide with
  Stage 2's rewrites of the same files.

### Unplanned branches

- **`student-manager-guardrail-x9zzt9`** (+24,783 / −813): extracts the migration
  engine into a second plugin (`simple-lms-migrator/`, with committed build output),
  **deletes `class-user-meta.php`** (a shipped feature — Pods legacy meta editing on
  user profiles), centralizes PDF URLs into `Certificates::pdf_url()`. A major
  architectural fork that was never in the roadmap.
- **`prestoplayer-fluentplayer-migration-hmprhw`** (+83 / −3): FluentPlayer video
  migration — new scope, small, touches hot files (`class-cpt.php`, `class-migration.php`).

---

## Corrections, in order

### C1. Freeze and declare the baseline (immediate, process)

- [ ] `main` as it stands is the integration baseline. Do **not** revert the merged
  stages — the criss-cross history makes clean reverts riskier than forward fixes.
- [ ] **Stop all parallel merging.** From here: one integration PR in flight at a
  time; every PR rebases on current `main` before merge; CI must be green.
- [ ] Enable branch protection on `main` (require PR + passing checks).
- [ ] Delete fully-merged remote branches (`sleepy-dijkstra-fi7s4p`,
  `stage-0-stabilize-foundation-ii687i`, `student-analytics-reporting-d3zrhj`,
  `certificate-pipeline-native-pdf-i93lfy`) so the branch list reflects reality.
- [ ] Declare hot-file ownership: `class-rest.php`, `class-access.php`,
  `simple-lms-bridge.php`, `STATE.md`, `CHANGELOG.md` may only change in the single
  in-flight integration PR.

### C2. Audit the double Stage 0 merge (S)

- [ ] Diff-audit for semantic duplication from the `ba164ca`/`9e5b078` criss-cross:
  upgrade steps registered twice, duplicate CI workflow content, doubled CHANGELOG
  entries, duplicate hooks in `simple-lms-bridge.php`. Fix in one small PR.
- [ ] Confirm the student progress endpoint on `main` derives the user from the
  session (there are both `edit_users` and `is_user_logged_in` routes — verify the
  logged-in route ignores a caller-supplied `user_id`).

### C3. Reconcile Access — do NOT merge the Stage 1 branch wholesale (L, the core correction)

The Stage 1 branch cannot merge as-is: same filename, incompatible API, and `main`'s
Stage 2/3 consumers (BB modules, quiz, analytics) already call `main`'s Access.
Correct by **porting Stage 1's design onto `main`'s incumbent API** in two PRs:

**C3a — Guarding enforcement (what the product is still missing):**
- [ ] Cherry-pick/port from the Stage 1 branch onto a fresh branch off `main`:
  `class-guard.php` (template_redirect + `the_content` + REST-prepare stripping),
  `_lms_guard_mode` meta + CourseEditor UI, `class-settings.php` + `Settings.js`,
  `denial_reason()`/guard-mode/checkout logic **merged into `main`'s existing
  `Access` class** (adapt signatures; one class, one API).
- [ ] Port the Stage 1 `/me/progress`, `/me/courses` routes into `main`'s
  `class-rest.php`; frontend button posts to `/me/progress`.
- [ ] Acceptance: logged-out lesson URL redirects; enrolled student passes; REST
  doesn't leak content. (Stage 1's original acceptance list applies.)

**C3b — Progress table + Access decomposition:**
- [ ] Land `class-progress.php` + `slms_lesson_progress` table (via the Stage 0
  upgrade runner) with dual-write and meta backfill, per the Stage 1 plan.
- [ ] Move `set_lesson_progress`/`get_course_progress`/stats **out of Access into
  Progress**; move CTA/price/checkout presentation helpers out of Access into a
  small `CourseDisplay` helper (or leave in place, documented, if time-boxed).
  Access ends up doing access only.
- [ ] Rewire `main`'s consumers: quiz auto-complete, the four Stage 2 BB modules,
  Certificates completion check.
- [ ] Close the Stage 1 PR/branch with a note pointing at the two corrective PRs.

### C4. Re-point analytics at the progress table (M, after C3b)

- [ ] Replace the `_lms_progress` usermeta joins/parsing in `class-analytics.php`
  with `slms_lesson_progress` queries. The public REST/React contract stays the same.
- [ ] Re-verify the <2s load target on the 1300-user dataset; add the nightly rollup
  if meta parsing was the reason it was skipped.

### C5. Disposition of the unplanned branches (S, decision + small cherry-pick)

- [ ] **`student-manager-guardrail-x9zzt9`: do not merge. Park it.**
  The core/migrator plugin split is an architecture RFC for after 1.0, not a
  correction; it deletes a shipped feature (`class-user-meta.php`) and carries 24k
  lines including build artifacts. Salvage exactly one thing: cherry-pick the
  `Certificates::pdf_url()` centralization commit (`481732a`) once C3 lands, if it
  still applies cleanly. Then close the PR with rationale; keep the branch archived.
- [ ] **`prestoplayer-fluentplayer-migration-hmprhw`: hold.** It's new scope (player
  swap), not a correction. Decide go/no-go separately; if go, it rebases onto `main`
  **after** C3/C4 because it touches hot files.

### C6. CI honesty follow-ups (S–M)

- [ ] Keep PHPStan level 5 as the hard gate (baseline for pre-existing debt is fine).
- [ ] PHPCS: schedule the 371-violation repo-wide `phpcbf` reformat as one dedicated
  no-logic PR **after** C3–C5 are merged and parked branches are closed (a reformat
  now would conflict with every open corrective PR). Then remove
  `continue-on-error: true` so PHPCS becomes a real gate.

### C7. Staging verification (M — the merged code has never run)

None of Stages 2–4 has executed on a live WordPress. Before calling the corrections
complete, run the full journey on staging:

- [ ] Subscriber: enroll via PMPro → drip locks → quiz-gated completion →
  completion redirect → certificate PDF caches → `/certificate/{uuid}/download` and
  `/certificate/verify/{uuid}` both work.
- [ ] Guarding: logged-out redirect, expired-user block, `level`-mode pass, REST leak check.
- [ ] Analytics pages load against real data; numbers match Student Manager.
- [ ] Migration Tool still runs (it shares hot files that were touched by four branches).
- [ ] Document results in STATE.md; anything broken becomes a fix commit on the
  corrective PR, not a new branch.

---

## Order and rationale

**C1 → C2 → C3a → C3b → C4 → C5 → C6 → C7 (gate).**
C1 stops the bleeding. C2 is cheap insurance on a messy merge. C3 resolves the only
true architectural conflict and finally ships the product's missing core (guarding);
porting *design onto incumbent API* is chosen over merging the Stage 1 branch because
`main` already has four stages of consumers calling the incumbent Access. C4 pays off
the analytics shortcut the moment the right data layer exists. C5 keeps unrequested
scope out of the recovery. C6 lands the reformat when it can no longer cause
conflicts. C7 is the exit criterion: nothing here is "done" until it has run once on
a real site.
