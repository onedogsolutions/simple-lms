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

FluentPlayer (WPManageNinja) is new; public docs confirm a Gutenberg block and per-player shortcode but not the exact shortcode tag, storage model (Fluent plugins typically use custom DB tables, not CPTs), or a PHP/REST API for listing players. Before coding:

1. Install FluentPlayer on staging and confirm:
   - Exact shortcode tag and attributes (e.g. `[fluent_player id="…"]`).
   - Where players are stored and how to enumerate them from PHP (helper class, custom table, or REST route).
   - Whether it ships a PrestoPlayer importer (Fluent products often ship competitor importers) — this decides Phase 1 effort.
2. Confirm feature parity for how videos are actually hosted today (self-hosted / YouTube / Vimeo are in the free tier; Bunny CDN and Mux require Pro).

## Phase 1 — Migrate the video library

1. If FluentPlayer has a Presto importer, use it; otherwise write a one-shot WP-CLI command that reads each `pp_video_block` post (source URL, title, poster) and creates the equivalent FluentPlayer player.
2. Either way, produce and persist an **ID map** (`presto_id → fluent_id`), e.g. as an option or meta on the new players — Phase 3 depends on it.
3. Presto analytics/watch data does not carry over; accept and note this.

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

- **Phase 0 unknowns**: shortcode/API specifics are unverified; everything downstream is shaped by them.
- **Hosting tier**: if current videos use Presto Pro's Bunny CDN hosting, FluentPlayer Pro is required and video files must be re-pointed.
- **Pods key-mismatch bug**: some imported lessons are typed `video` with no working video today; the backfill will surface them — expect a manual review list.
