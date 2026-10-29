# Chris's Swim Training Progress and Evaluation

Chris's Swim Training Progress and Evaluation is a WordPress plugin for importing or manually recording, preserving, and evaluating swimming workout history.

The plugin supports FIT and supported CSV workout sources plus first-class manual pool-workout entry, normalized workout/lap/length storage, personal-best evaluation, events, locations, WordPress blocks, and front-end display shortcodes. Original workout source files are preserved so normalized and derived data can be rebuilt without discarding the historical source.

## v1.2

Version 1.2 adds private structured swimming workouts with sections, repeats, ladders, stroke, drill, equipment, pace and rest; training-plan PDF preview, review, selection and import; links to planned workouts; and recorded pool-swim conversion without changing recorded history. Download prescriptions as FIT Workout files, printable PDFs or text for copying to FORM. FIT destination support varies; TrainingPeaks planned-workout library import is not guaranteed. Calendar days expand in an accessible dialog on desktop and mobile, and the administration menu groups related tasks. Optional Twenty Twenty-Four layout templates improve wide-table presentation. PHP 7.4 through 8.5 is supported. The database schema remains 1.2; no new schema migration is required.

PDF conversion is conservative: unsupported or ambiguous notation requires review and correction before import. Confidential source PDFs are not shipped. See [functional validation](docs/v1.2-functional-validation.md) and the [structured transfer contract](docs/v1.2-structured-transfers.md) for tested behavior and export limitations.

## v1.2 Phase 03 — Page and post layout templates

For sites using the WordPress **Twenty Twenty-Four** block theme, **SwimLog Wide Content** and **SwimLog Full Width** are the preferred page templates for displaying SwimLog tables. Select either from the Page or Post editor's **Template** selector. Both retain the theme header and footer and render existing page content, including SwimLog blocks and shortcodes.

- **SwimLog Wide Content**: preferred for tables that need more horizontal space while retaining a bounded content area.
- **SwimLog Full Width**: preferred for the widest table layouts and multi-column SwimLog content.

Both templates were validated manually with Twenty Twenty-Four during v1.2 Phase 03. Their table layout, responsive behavior, and page rendering were confirmed working. They are optional; existing pages and other themes do not need to switch. The per-page SwimLog table density and font-size settings remain available.

## v1.2 release readiness

The `develop` branch identifies version **1.2**. All 18 user acceptance areas passed, including separately confirmed desktop/mobile calendar behavior and menu ordering. Automated validation covers PHP 7.4–8.5 and Plugin Check against production staging. See the [release-readiness checklist](docs/v1.2-development-checklist.md) for evidence and scope. Updating this metadata does not publish a release: protected `main` and WordPress.org stable remain at released v1.1 until separately authorized.

## v1.1

v1.1 adds **Planned Workouts** and expands the Swim Training Calendar into an interactive planning and completion-tracking surface. Plans preserve free-form multiline workout instructions and optional private notes while keeping structured workout parsing deferred for a later release.

Swimmers can create, edit, duplicate, repeat, skip, cancel, restore, and delete plans, duplicate a week, and explicitly match or unlink a recorded workout. Matching is never automatic. Planned-versus-actual summaries use straightforward recorded distance and duration differences plus weekly planned/recorded totals; the plugin does not generate adherence scores, TSS, recovery, readiness, or coaching conclusions.

The WordPress administration **Calendar** is another host for the same shared calendar used by the Gutenberg block rather than a second calendar implementation. Month/Week navigation, Previous/Next/Today, Go to Date, swimmer-configured Saturday/Sunday/Monday week boundaries, planning actions, privacy rules, responsive agenda, and accessibility behavior are shared.

Calendar REST remains read-only. Private planned-workout notes, GPS/trackpoints, device/source metadata, and import details remain outside the calendar client surface. v1.1 advances the internal database schema to **1.2** through additive, in-place, data-preserving migrations while preserving existing workout and historical data.

Additional v1.1 refinements include calendar drag-and-drop and Copy/Cut/Paste scheduling, matched-workout and personal-best indicators, primary stroke from active-length distance, measured SWOLF, editable event race results, and equivalent steps calculated from active swimming time (181 steps/minute) with provenance retained.

## v1.0

v1.0 adds the **Swim Training Calendar** as a native dynamic WordPress block. The calendar uses the existing normalized workout and event data rather than creating a duplicate calendar database.

Swimmers can use Month and Week views with Previous, Next, and Today navigation. Each swimmer may configure Saturday, Sunday, or Monday as the first day of the calendar week; the same preference controls natural week boundaries and weekly summaries. Weekly summaries use defensible recorded metrics: workout count, distance, active time, pool distance, and open-water distance. The plugin does not manufacture calories, TSS, physiological load, recovery, fitness, or injury-risk metrics.

Calendar access is intentionally private. The owner requires the existing own-results capability, while cross-user access requires the plugin's manage-all-users capability. Public Results, Public Open-Water Routes, and external REST API opt-in do not make the calendar public. The internal `GET /swimlog/v1/calendar` route follows the same calendar-specific authorization boundary and excludes GPS/trackpoints, notes, device/source metadata, and import details.

Workout cards can expose useful recorded details inside the calendar without navigating away. Narrow screens switch from the seven-column grid to a date-grouped agenda while retaining Month/Week navigation and workout details. Accessibility refinements include keyboard-operable workout controls, Escape-to-close details, focus restoration, busy/status announcements, visible focus treatment, reduced-motion handling, and forced-colors support.

v1.0 requires **no database schema migration**. The plugin release version advances to 1.0 while the internal database schema remains version 0.9.



## v0.9

v0.9 begins a pool performance and efficiency analytics layer built from the normalized active-length data the plugin already preserves. The first implementation adds a dedicated **Pool Analytics** administration screen with course, stroke, and rolling lookback filters.

The analytics keep meter and yard courses, pool lengths, and strokes separate. They derive distance-weighted average pace per 100 native units, fastest single-length pace, per-length pace consistency, average recorded stroke count, normalized strokes per 25 native units, SWOLF, stroke-count coverage, and monthly trends. Missing timing or stroke-count data remains missing rather than being estimated. SWOLF is explicitly treated as pool-length dependent and should only be compared within the same pool length and similar recording conventions.

These metrics are derived from existing normalized workout/length data, so this initial v0.9 analytics work requires no database schema change and applies to both imported and structured manual workouts when qualifying active-length data is available.

The second v0.9 analytics milestone adds a **Training Trends / Training Load** administration dashboard. It provides weekly and monthly workout volume, workout frequency, active swim time, pool/open-water distance splits, four-week rolling averages, equal-period comparisons, pool course volume, and distance by stroke. Combined longitudinal distance is normalized to meters while course-specific pool analytics retain native meter/yard units.

Training load in this dashboard means recorded training volume only. The plugin does not infer physiological stress, recovery status, fitness, or injury risk. Active time uses recorded moving time when available and otherwise recorded elapsed time; stroke-specific active time is derived only from normalized active pool lengths. This milestone also uses the existing schema and requires no database-version change.

The third v0.9 analytics milestone adds **Race Readiness 2.0** for upcoming events. It combines the swimmer's current personal best, exact-distance recent best, exact-distance recent pace evidence, recent performance count and change, matching course/stroke training volume and session count, longest recent evaluated distance, and time remaining until the event. The default evidence window remains 42 days.

Race Readiness 2.0 is deliberately descriptive rather than predictive. It does not calculate a readiness score, forecast a race time, infer fitness or recovery, or state that a swimmer is ready or unready to race. Missing exact-distance performance evidence remains missing rather than being estimated. This milestone also uses the existing normalized workout/performance data and requires no database schema change.

The fourth v0.9 milestone expands the authenticated, opt-in, read-only **REST API and data-portability surface**. Pool workout detail now exposes preserved normalized lap/length offsets and source indexes when available, including per-length stroke counts. New GET endpoints expose current personal bests, exact-distance progression, upcoming events with descriptive Race Readiness 2.0 evidence, Pool Analytics, and Training Trends.

The API remains owner-scoped to the authenticated swimmer and still requires explicit per-user REST API opt-in. It continues to exclude workout notes, preserved source files/paths, import records, open-water coordinates, and GPS trackpoints. No write/create/delete REST methods are added, and this milestone requires no database schema change.

The v0.9 administration architecture was also refactored from the former monolithic Admin class into focused components for workouts, manual workouts, analytics, personal bests, events/locations, shortcode help, Garmin, settings, Race Readiness presentation, and shared UI helpers. The refactor preserves existing menu slugs, permissions, nonces, URLs, and behavior while making future development more maintainable.

A standalone **[REST-API.md](REST-API.md)** developer reference documents the complete v0.9 REST surface, WordPress Application Password authentication, parameters, response fields, privacy boundaries, pagination, examples, and error handling. Regression coverage checks that every registered REST route remains represented in the reference.

v0.9 requires **no new database schema migration**. The internal database schema remains version 0.9 from the v0.8 per-length stroke-count migration.

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

Authenticated WordPress clients may read swimmer-owned normalized and derived data through the read-only `swimlog/v1` REST namespace. REST API Access is an explicit per-swimmer opt-in and is independent of Public Results and Public Open-Water Routes.

See **[REST-API.md](REST-API.md)** for the complete developer reference, including WordPress Application Password authentication, authorization requirements, every endpoint and parameter, response fields, units/provenance, privacy exclusions, pagination, request examples, and error handling.

The API deliberately excludes preserved source files/paths, import records, workout notes, original uploaded filenames, and open-water GPS coordinates/trackpoints.

