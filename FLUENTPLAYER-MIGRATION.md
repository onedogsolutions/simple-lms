# PrestoPlayer → FluentPlayer Migration Plan

## Current PrestoPlayer footprint

The integration surface is small — five touchpoints, one render path:

| File | Role |
|------|------|
| `includes/class-cpt.php:198-206` | Registers `_lms_presto_video` (integer) post meta on `slms_lesson`, REST-exposed |
| `includes/class-rest.php:552-575` | `GET /simple-lms/v1/videos` — lists PrestoPlayer `pp_video_block` posts (id + title) for the admin picker |
| `src/admin/components/LessonSettings.js` | React lesson editor: "Presto Player Video" `SelectControl`, reads/writes `_lms_presto_video` |
| `includes/bb-modules/lms-content/includes/frontend.php:39-47` | **Only render path.** If `_slms_lesson_type === 'video'`, outputs `[presto_player id=N]` |
| `includes/class-migration.php:1459-1464` | Legacy Pods importer writes `_slms_presto_video` — **note the key mismatch**: everything else reads `_lms_presto_video`, so Pods-imported videos never render today (pre-existing bug) |

No PrestoPlayer PHP classes, hooks, or analytics are referenced — the coupling is entirely meta key + shortcode + one CPT query.

## Phase 0 — Verify FluentPlayer internals (blocking)

**We are an early adopter.** FluentPlayer (WPManageNinja) just launched, and the target site is its **first install** in the fleet — there is no existing FluentPlayer content anywhere, and no accumulated in-house experience with it. Treat it as a v1.0: expect thin docs, an API that may change between early releases, and possible rough edges. Practical consequences:

- **Pin the plugin version** once the migration is built and tested; do not auto-update FluentPlayer mid-rollout.
- **Do not assume a bundled PrestoPlayer importer or a stable public API.** Verify everything against the actual install rather than docs.

Public docs confirm a Gutenberg block and a per-player shortcode but not the exact shortcode tag, the storage model (Fluent plugins typically use custom DB tables, not CPTs), or a PHP/REST API for listing players. Before coding, install FluentPlayer on staging and confirm:

1. Exact shortcode tag and attributes (e.g. `[fluent_player id="…"]`).
2. Where players are stored and how to enumerate them from PHP (helper class, custom table, or REST route).
3. Whether a PrestoPlayer importer exists at all — if not (likely, given we're first-install), our own importer is the plan, not a fallback.
4. **Bunny Stream attach.** All videos are on Bunny Stream (no self-hosting), identified by library ID + video GUID. Confirm FluentPlayer Pro can attach to the **same** Bunny library and reference the **same** GUIDs — so migration re-points players, never re-uploads. Critically, check whether the current embeds use Bunny **token authentication** (signed URLs); if so, verify FluentPlayer Pro supports the same token auth against that library, or embeds will 403.
5. **Inventory the Pro-only extras** attached to each Presto video: chapters, captions/subtitles, poster frame, and interactive overlays (email opt-in gates, CTAs/action bars). These have FluentPlayer analogs but do **not** transfer via an id→id map — decide per item what to rebuild.

## Phase 1 — Migrate the video library

FluentPlayer starts empty (first install), so every player is created fresh — no collision with existing content.

1. Write a one-shot WP-CLI command that reads each `pp_video_block` post — Bunny library ID + video GUID, title, poster, chapters, captions — and creates the equivalent FluentPlayer Pro player pointing at the **same** Bunny GUID. Uniform host means no per-video branching. (Only use a bundled importer if Phase 0 confirms one exists and it emits an ID map we can capture.)
2. Produce and persist an **ID map** (`presto_id → fluent_id`), e.g. as an option or meta on the new players — Phase 3 depends on it.
3. Rebuild the inventoried Pro overlays (opt-in gates, CTAs) as FluentPlayer layers where they matter — manual, not scripted.
4. Presto analytics/watch data does not carry over; accept and note this.

## Phase 2 — Code changes in this plugin

1. **New meta key** `_lms_fluent_video` on `slms_lesson` (same shape as the old key) in `class-cpt.php`. Keep `_lms_presto_video` registered during the transition.
2. **REST** `get_videos()` in `class-rest.php`: replace the `pp_video_block` WP_Query with FluentPlayer's listing API (per Phase 0). Response shape stays `{id, title}[]` so the React side barely changes.
3. **LessonSettings.js**: rename `prestoVideo` state → `fluentVideo`, read/write `_lms_fluent_video`, relabel the control "FluentPlayer Video".
4. **BB module** `frontend.php`: render the FluentPlayer shortcode; during rollout, fall back to `[presto_player]` when only the old meta is set.
5. **class-migration.php**: write the new key for Pods imports — and fix the `_slms_presto_video` / `_lms_presto_video` key mismatch as part of this.

## Phase 3 — Backfill lesson meta

One-shot routine (WP-CLI command or Migration Tool button): for every `slms_lesson` with `_lms_presto_video`, look up the Phase 1 ID map and set `_lms_fluent_video`. Log lessons whose Presto ID has no mapping. Also sweep the orphaned `_slms_presto_video` keys from the Pods bug and map those too.

## Phase 4 — Verify and decommission

1. Spot-check video lessons on the front end (BB module) and the lesson editor picker; lesson progress/completion is independent of the player, so no LMS data is at risk.
2. After a soak period: remove the Presto fallback from `frontend.php`, deregister `_lms_presto_video`, delete old meta rows, deactivate/remove PrestoPlayer.

## Risks

- **Early-adopter / first-install**: FluentPlayer is v1.0 and unproven here; thin docs, possible bugs, and API drift between early releases. Pin the version after testing and re-verify the shortcode/API on each update.
- **Phase 0 unknowns**: shortcode/API specifics are unverified; everything downstream is shaped by them.
- **Bunny token auth**: all videos are Bunny Stream, so files stay put and players re-point by GUID — the one real risk is signed-URL/token authentication. If Presto's embeds are token-authenticated and FluentPlayer Pro can't match that against the same library, embeds 403. Verify in Phase 0.
- **Pro extras don't auto-migrate**: chapters, captions, posters, and interactive overlays (opt-ins, CTAs) need per-video review and manual rebuild; analytics history is lost.
- **Pods key-mismatch bug**: some imported lessons are typed `video` with no working video today; the backfill will surface them — expect a manual review list.
