# SimpleLMS Migrator

One-time data migration tooling for SimpleLMS Bridge: WP Complete → SimpleLMS
progress migration, Pods user-meta migration, and GF→PMPro registration sync.

**This plugin is disposable.** Once a site's historical data has been fully
migrated (Migration Tool shows zero pending items across all phases), deactivate
and delete this plugin. It has no runtime role in the LMS itself — SimpleLMS
Bridge (core) does not depend on it.

## Requirements

Requires the SimpleLMS Bridge plugin to be active. This plugin registers its
REST routes under the same `simple-lms/v1` namespace as core and depends on
core's `Relationships` and `CourseHistory` classes.

## Contents

- `includes/class-migration.php` — the migration engine (CPTs, progress,
  compliance history, PMPro registrations).
- `includes/class-rest.php` — `/migration/*`, `/debug-log`, and
  `/course-history/repair-form-ids` REST routes, plus the migration-log
  download handler.
- `src/admin/components/MigrationTool.js`, `DebugLog.js` — the React admin UI
  for the Migration Tool and Debug Log submenus under SimpleLMS → in wp-admin.

## Future work

The PrestoPlayer → FluentPlayer lesson-meta backfill (Phase 3 of
`FLUENTPLAYER-MIGRATION.md`, developed on the
`claude/prestoplayer-fluentplayer-migration-hmprhw` branch) is one-time
migration tooling and belongs in this plugin, not in core, once that work
lands.

## Build

```
npm install
npm run build
```

Mirrors core's `build:js` → `build:css` ordering (wp-scripts webpack build,
then the Tailwind v4 CLI build).
