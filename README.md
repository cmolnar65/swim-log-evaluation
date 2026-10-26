# Chris's Swim Training Progress and Evaluation

Chris's Swim Training Progress and Evaluation is a WordPress plugin for importing or manually recording, preserving, and evaluating swimming workout history.

The plugin supports FIT and supported CSV workout sources plus first-class manual pool-workout entry, normalized workout/lap/length storage, personal-best evaluation, events, locations, WordPress blocks, and front-end display shortcodes. Original workout source files are preserved so normalized and derived data can be rebuilt without discarding the historical source.

## Current release: v0.8

Version 0.8 is the current published release on GitHub and WordPress.org. The `develop` branch is now used for v0.9 development; `main` remains the protected stable/release branch.

## v0.9 development

v0.9 begins a pool performance and efficiency analytics layer built from the normalized active-length data the plugin already preserves. The first implementation adds a dedicated **Pool Analytics** administration screen with course, stroke, and rolling lookback filters.

The analytics keep meter and yard courses, pool lengths, and strokes separate. They derive distance-weighted average pace per 100 native units, fastest single-length pace, per-length pace consistency, average recorded stroke count, normalized strokes per 25 native units, SWOLF, stroke-count coverage, and monthly trends. Missing timing or stroke-count data remains missing rather than being estimated. SWOLF is explicitly treated as pool-length dependent and should only be compared within the same pool length and similar recording conventions.

These metrics are derived from existing normalized workout/length data, so this initial v0.9 analytics work requires no database schema change and applies to both imported and structured manual workouts when qualifying active-length data is available.

The second v0.9 analytics milestone adds a **Training Trends / Training Load** administration dashboard. It provides weekly and monthly workout volume, workout frequency, active swim time, pool/open-water distance splits, four-week rolling averages, equal-period comparisons, pool course volume, and distance by stroke. Combined longitudinal distance is normalized to meters while course-specific pool analytics retain native meter/yard units.

Training load in this dashboard means recorded training volume only. The plugin does not infer physiological stress, recovery status, fitness, or injury risk. Active time uses recorded moving time when available and otherwise recorded elapsed time; stroke-specific active time is derived only from normalized active pool lengths. This milestone also uses the existing schema and requires no database-version change.

The third v0.9 analytics milestone adds **Race Readiness 2.0** for upcoming events. It combines the swimmer's current personal best, exact-distance recent best, exact-distance recent pace evidence, recent performance count and change, matching course/stroke training volume and session count, longest recent evaluated distance, and time remaining until the event. The default evidence window remains 42 days.

Race Readiness 2.0 is deliberately descriptive rather than predictive. It does not calculate a readiness score, forecast a race time, infer fitness or recovery, or state that a swimmer is ready or unready to race. Missing exact-distance performance evidence remains missing rather than being estimated. This milestone also uses the existing normalized workout/performance data and requires no database schema change.

## v0.8

Version 0.8 adds first-class manual pool-workout creation and editing for swimmers who do not use an automatic recording device. Manual workouts use the same canonical workout history, training totals, public display, blocks, shortcodes, post workflow, personal-best records, progress views, and read-only REST API as imported workouts.

Manual entry supports coarse workouts with only distance and duration, or ordered sets with repetitions, distance, stroke, notes, and optional timing. Timing provenance is preserved: measured repetition times may qualify as performance evidence, while set totals, intervals/send-offs, derived values, overall workout duration, and unknown timing are never divided or treated as invented personal-best times. Untimed structure contributes training volume without manufacturing elapsed times.

Manual workouts are explicitly identified as manual rather than inferred from missing import data. Their authoritative entered document is stored separately from normalized workout/lap/length projections, and they do not create import records or pretend to be device recordings. Editing and rebuilding are owner-scoped, imported workouts cannot be rewritten as manual workouts, and the complete manual save—including derived performance evaluation—is transactional.

v0.8 also improves imported-workout fidelity and administration. Compatible Apple Watch, Garmin, and FORM FIT pool swims retain per-length stroke counts; Workout Details shows those counts and an available total-strokes value. Pool FIT session heart-rate/cadence metrics are preserved, Apple Watch device identification is improved, and FIT Activity local timestamps are used when available for historical local workout times.

Compressed workout sources are supported transparently for FIT gzip and supported FORM ZIP exports, with expanded-size/path validation and preservation of the original archive in protected source storage. Workout Details also supports up to three photos with replacement and deletion.

The v0.8 manual-entry feature remains limited to pool workouts; it does not create manual open-water GPS routes. The REST API remains read-only. The internal database schema is version 0.9 to add normalized per-length stroke counts with existing-installation upgrade handling.

v0.6 expands the plugin with:

- hardened canonical pool/open-water matching, timestamp handling, type-aware queries, annual totals, administration filters, and public presentation;
- expanded open-water analytics including 1 km splits, estimated moving-time splits, heart-rate/cadence charts, sparse/corrected GPS handling, and more efficient trackpoint processing;
- transactional workout rebuilds from preserved FIT, CSV, GPX, and TCX sources;
- configurable rolling pool stroke training-volume analytics with a per-swimmer 1–24 month period (3-month default), separate meter/yard sections ranked by total distance, and current event-readiness summaries;
- explicit opt-in public open-water route privacy controls plus improved self-contained geographic route context;
- scheduled Garmin synchronization, including automatic open-water import when the first valid GPS fix safely matches a saved location;
- saved open-water location reference coordinates with conservative 500 m proximity matching and ambiguity protection;
- defensive GPX and TCX open-water import through the normalized importer;
- hardened FIT recording-device identity and normalized device presentation;
- an authenticated, read-only `swimlog/v1` REST API for external integrations that excludes private source data and open-water GPS coordinates;
- complete admin documentation and drill-down help for all six registered public shortcodes, including `[swimlog_progress]`;
- expanded deterministic regression coverage for open-water import and presentation while keeping private/original activity files out of the public repository; and
- repaired release-package automation with PHP 7.4–8.4 regression testing;
- corrected REST swimmer authorization and hardened v0.5 upgrade behavior;
- deterministic and persisted preserved-source authority for normalized workout rebuilds;
- Garmin scheduled-sync health telemetry including attempts, successes, errors, and the next scheduled check;
- corrected open-water chart time origin plus numeric location-matching boundary coverage; and
- explicit event-readiness policy and open-water analytics unit/provenance metadata.

Open-water workouts remain isolated from pool personal-best evaluation.


## v0.7 Block Editor support

v0.7 adds native WordPress Block Editor integration for all six existing public display features. The legacy shortcodes remain supported public API; the blocks do not replace or deprecate them. Each block is dynamic/server-rendered and uses the same PHP query, authorization, privacy, and rendering path as its corresponding shortcode.

| Block | Legacy shortcode | Editor controls |
| --- | --- | --- |
| Latest Workouts | `[swimlog_latest]` | swimmer override, workout count (1–20), heading |
| Personal Bests | `[swimlog_bests]` | swimmer, course, stroke, distance, lifetime/age-group bests, age-group mode, heading |
| Upcoming Events | `[swimlog_upcoming_events]` | swimmer, site-default or explicit event count (1–50), heading |
| Workout | `[swimlog_workout]` | workout ID and heading; workout ownership determines the swimmer |
| Training Log | `[swimlog_log]` | swimmer, monthly/annual mode, month/year, heading |
| Progress | `[swimlog_progress]` | swimmer, course, stroke, distance, heading |

The Training Log block preserves the shortcode distinction between a monthly log and annual totals: annual mode supplies a year without a month. The Progress block continues to use the existing PHP progression calculation and front-end chart implementation rather than duplicating progression logic in editor JavaScript.

Version 0.7 also adds workout-number visibility in administration, a Create Post/Edit Post workflow from Workout Details that creates a draft containing the native Workout block, and improved responsive public tables. All six native blocks advertise Wide and Full alignment support.\n\nPublic-results privacy behavior is unchanged. When a swimmer override is not supplied, applicable blocks resolve the swimmer from the page/post author; there is no logged-in-viewer fallback. The Workout block does not accept a swimmer override because ownership comes from the selected workout. Open-water GPS routes remain separately opt-in through Public Open-Water Routes, and GPS coordinates/trackpoints are not stored in block attributes or editor scripts.

## Private Workout File Storage

Original uploaded FIT and CSV workout files are preserved in private server storage. These files may contain detailed workout history and must not be directly accessible from the public web.

### Default storage location

By default, the plugin uses the site's `DOCUMENT_ROOT` to determine the public web root and attempts to create a directory named:

```text
swim-log-evaluation-private
```

one directory above the public web root.

For example, if the public web root is:

```text
/var/www/example.com/public
```

the default private storage location would be:

```text
/var/www/example.com/swim-log-evaluation-private
```

The selected directory must be writable by the PHP/WordPress process. The plugin validates that the storage directory is outside the public web root and refuses to preserve workout source files in a web-accessible location.

The plugin also creates an `index.php` file in the private directory as an additional precaution. This file is not the primary security mechanism; the directory itself should remain outside the public web root.

### Configuring a custom private directory

Some hosting environments do not permit WordPress to create or write to a directory adjacent to the site's document root. Administrators can configure another private storage location on the plugin's **Swim Training Settings** screen using **Private Workout Storage Directory**. The setting accepts an absolute server filesystem path and is validated before it is saved.

For advanced or managed hosting deployments, `SWIMLOG_PRIVATE_STORAGE_DIR` may still be defined in `wp-config.php`. When the constant is defined it takes precedence over the Settings value and the field is displayed as locked.

For example:

```php
define( 'SWIMLOG_PRIVATE_STORAGE_DIR', '/path/outside/public/webroot/swim-log-evaluation-private' );
```

The configured path must:

- be outside the site's public web root;
- be writable by the PHP/WordPress process; and
- remain protected from direct web access.

Do not solve permission problems by making broad server directories world-writable (for example, mode `777`). Configure ownership and permissions appropriate for the web server/PHP environment instead.

### Backups and data preservation

The private directory contains the original uploaded workout source files. Because preservation of historical workout sources is part of the plugin's data-retention design, include this directory in an appropriate server backup strategy.

Database backups alone do not contain the original FIT and CSV file contents.

Deactivating or updating the plugin does not intentionally remove preserved workout history or these source files.

### Troubleshooting private storage

If an import reports that the private workout source directory cannot be determined, created, initialized, or written:

1. Confirm that PHP/WordPress can determine the site's document root.
2. Confirm that the intended private directory is outside the public web root.
3. Confirm that the PHP/WordPress process has permission to create and write files in that directory.
4. If the automatically selected location is unsuitable, enter an appropriate absolute path in **Swim Training Settings → Private Workout Storage Directory**. Server administrators may instead define `SWIMLOG_PRIVATE_STORAGE_DIR` as an override.

Do not move the source directory under `public_html`, `htdocs`, the WordPress installation directory, or another publicly served directory merely to resolve a permissions problem.

## Development and Release Checks

Development and release-candidate builds are checked with WordPress Plugin Check. See [PLUGIN-CHECK.md](PLUGIN-CHECK.md) for the reviewed Plugin Check baseline and the project's handling of expected custom-table database warnings.


## External Integration API

Authenticated WordPress clients may read normalized workout data through the `swimlog/v1` REST namespace. The API is read-only and always scopes results to the currently authenticated swimmer.

Available routes are `GET /wp-json/swimlog/v1/workouts` and `GET /wp-json/swimlog/v1/workouts/{id}`. The collection supports pagination plus workout-type and date filters.

The integration API deliberately does not expose preserved source files, source paths, import records, workout notes, or open-water GPS coordinates/trackpoints. Open-water detail includes non-geographic summary analytics and splits only. WordPress authentication, the plugin's own-view capability, and the swimmer's explicit **REST API Access** opt-in are required. REST API Access is configured per swimmer under **Swim Training → Swimmer Configuration**, defaults to OFF, and is independent of Public Results and Public Open-Water Routes. The configuration screen links to an in-plugin API documentation page with authentication guidance, routes, filters, returned fields, privacy exclusions, examples, and errors.
