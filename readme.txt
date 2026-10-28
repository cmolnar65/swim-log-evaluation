=== Chris's Swim Training Progress and Evaluation ===
Contributors: cmolnar65
Tags: swimming, swim log, personal bests, fit, csv
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import or manually record, preserve, evaluate, and display swimming workout history and personal bests.

== v1.2 development: Twenty Twenty-Four page templates ==

For WordPress Twenty Twenty-Four sites displaying SwimLog tables, the preferred Page/Post templates are **SwimLog Wide Content** and **SwimLog Full Width**. Select one from the editor's Template selector. Both preserve the theme header, footer, and page content while providing appropriate table width and responsive display. Wide Content is suitable for wider tables with a bounded content area; Full Width is suited to the widest tables and multi-column layouts. Both were manually verified in Twenty Twenty-Four during v1.2 Phase 03. These templates are optional, and existing pages need not change.

== Description ==

Chris's Swim Training Progress and Evaluation is a multi-user WordPress plugin for importing FIT and
CSV swimming workouts, manually recording pool workouts, preserving normalized workout history, evaluating native
meter and yard performances, tracking personal bests, managing swim events and
locations, and displaying selected results through display-only shortcodes.

Version 1.1 adds Planned Workouts and turns the Swim Training Calendar into an interactive planning surface. Swimmers can create, edit, duplicate, repeat, skip, cancel, restore, and delete future plans; duplicate a week; explicitly match a completed recorded workout to a plan; and review simple planned-versus-actual distance and duration differences. Planned workout instructions preserve multiline free-form swim notation for future structured-workout parsing without inventing sets, send-offs, training stress, readiness, or coaching conclusions.

The shared calendar now also has a WordPress administration Calendar page while retaining the native Gutenberg Calendar block. Both hosts use the same calendar service, Month/Week behavior, swimmer-configured Saturday/Sunday/Monday week boundaries, planning/matching actions, privacy rules, responsive agenda, and accessibility behavior. Calendar REST remains read-only and private notes, GPS/trackpoints, and device/source metadata remain excluded.

v1.1 advances the internal database schema to 1.2 through additive, in-place migrations for planned workouts and recorded workout metrics. Existing workout, event, import, performance, location, manual-workout, lap, length, and trackpoint data are preserved.

Version 1.0 adds the private Swim Training Calendar: month and week views, swimmer-configurable Saturday/Sunday/Monday week boundaries, defensible weekly training summaries, in-calendar workout details, responsive mobile agenda presentation, and accessibility-focused keyboard/focus behavior. It builds on the v0.9 analytics, REST/data-portability, and administration architecture without adding a database schema migration.

= v1.1 =

* Add dedicated planned workouts with date/time, type, distance, duration, pool/stroke context, multiline workout instructions, private notes, lifecycle status, and optional explicit completed-workout association.
* Add create/edit/duplicate/repeat/skip/cancel/restore/delete planning actions plus Duplicate Week through authenticated owner/admin calendar actions while keeping REST read-only.
* Add explicit same-day/type completion candidates and user-confirmed matching; never auto-match a plan to a recorded workout.
* Add simple planned-versus-actual distance/duration differences and weekly planned/recorded totals without adherence scores, TSS, readiness, recovery, or coaching inference.
* Add the wp-admin Calendar as another host for the shared calendar implementation, including Month/Week, Previous/Next/Today, Go to Date, swimmer-configured week starts, and Duplicate Week.
* Improve responsive/mobile planning so empty dates remain plannable, initial agenda content loads correctly, and recorded/planned/event types are textually distinct rather than color-only.
* Improve shared calendar accessibility, loading state, focus restoration, planned-workout aria state, and week-view recorded-workout details.
* Preserve calendar privacy: private planned-workout notes, GPS/trackpoints, device/source, and import metadata are not exposed; calendar REST remains read-only.
* Add calendar drag-and-drop rescheduling, Copy/Cut/Paste and destination-date selection, with date validation and cross-month navigation.
* Highlight explicitly matched recorded workouts, show personal-best indicators, and link directly to recorded Workout Details.
* Determine imported workouts' primary stroke from recorded active-length distance rather than an arbitrary stroke occurrence.
* Persist and display average and per-length SWOLF when source measurements support it, without inventing missing data.
* Add event race-result editing for finishing time, placement, and private notes with authenticated access controls.
* Add equivalent swimming steps using 181 steps per active swimming minute, preserving calculation provenance.
* Advance the internal database schema to 1.2 with additive, in-place, data-preserving migrations.

= v1.0 =

* Add the native dynamic Swim Training Calendar block with private owner/admin authorization independent of Public Results, Public Open-Water Routes, and external REST API opt-in.
* Add Month and Week views with Previous, Next, and Today navigation.
* Add per-swimmer Saturday, Sunday, or Monday first-day-of-week preference used consistently for week boundaries and weekly summaries.
* Add weekly workout count, distance, active time, pool distance, and open-water distance summaries using recorded/defensible metrics only.
* Add an internal authenticated `GET /swimlog/v1/calendar` route with strict owner/admin authorization, bounded date ranges, and no GPS, trackpoints, notes, device/source, or import metadata.
* Add selectable workout cards with in-calendar details for recorded date/time, location, distance, active/elapsed time, pool length, and primary stroke when available.
* Add responsive narrow-screen date-grouped agenda presentation while retaining the seven-column desktop/tablet calendar.
* Add keyboard/focus refinements including Escape-to-close workout details, focus restoration, busy/status announcements, visible focus, reduced-motion handling, and forced-colors support.
* Keep manual and imported workouts visually equivalent in the calendar; recording source does not dominate presentation.
* Do not manufacture calories, TSS, physiological load, recovery, fitness, injury risk, or other unsupported metrics.
* No v1.0 database schema change is required; the internal database schema remains 0.9.

= v0.9 =

Version 0.9 turns the normalized workout history added and refined in earlier releases into a broader swim-analytics and data-portability layer without manufacturing missing measurements.

* Add Pool Analytics with course/stroke/lookback filters, distance-weighted pace, fastest recorded active-length pace, pace consistency, recorded stroke-count coverage, average strokes per length, normalized strokes per 25 native units, SWOLF, and monthly trends.
* Keep meter/yard courses, pool lengths, and strokes separate where required; do not estimate missing timing or stroke-count data.
* Add Training Trends with weekly/monthly volume, workout frequency, active time, pool/open-water splits, four-week rolling averages, equal-period comparisons, course volume, and stroke volume.
* Keep Training Trends descriptive: recorded training volume is not presented as physiological stress, recovery, fitness, or injury risk.
* Add Race Readiness 2.0 for upcoming events using current PBs, recent exact-distance performances/pace/change, matching course/stroke training, longest recent evaluated distance, and time to event.
* Keep Race Readiness descriptive: no readiness score, predicted race time, fitness/recovery inference, or ready/unready conclusion is generated.
* Expand the authenticated, owner-scoped, explicitly opt-in, read-only REST API with personal bests, progression, events/readiness, Pool Analytics, Training Trends, preserved lap/length offsets/source indexes, and per-length stroke counts.
* Add a standalone REST-API.md developer reference covering Application Password authentication, authorization, routes, parameters, response fields, privacy boundaries, pagination, examples, and errors, plus regression coverage that requires registered routes to remain documented.
* Continue excluding workout notes, preserved source files/paths, import records, original uploaded filenames, open-water coordinates, and GPS trackpoints from REST responses.
* Refactor the large administration class into focused workout, manual-workout, analytics, personal-bests, events/locations, shortcode-help, Garmin, settings, Race Readiness, and shared UI components while preserving existing admin URLs, capabilities, nonces, and behavior.
* Maintain PHP 7.4 through 8.4 compatibility and release-package Plugin Check coverage.
* No v0.9 database schema change is required; the internal database schema remains at 0.9 from the v0.8 per-length stroke-count migration.

= v0.8 =

Version 0.8 adds first-class manual pool-workout entry for swimmers who do not use an automatic recording device. Manual workouts may contain coarse totals or ordered sets with repetitions, distance, stroke, notes, and optional timing. Timing provenance is preserved: explicitly measured repetition times may qualify as personal-best evidence, while set totals, intervals/send-offs, derived timing, overall workout duration, and unknown timing are not divided into invented performance times. Untimed sets still contribute training structure and volume.

Manual workouts are explicit manual sources rather than fake imports or device recordings. They use the same canonical workout history, Workout Details, workout numbers, Training Log, training totals, Personal Bests, Progress, public Workout display, Latest Workouts, blocks/shortcodes, Create/Edit Post workflow, and read-only REST API as appropriate. Editing/rebuilding is owner-scoped and transactional, and imported workouts are protected from being rewritten as manual workouts. Manual entry is limited to pool workouts and does not create manual open-water GPS routes.

Version 0.8 also improves source fidelity and workout administration:
* Preserve per-length FIT stroke counts from compatible Apple Watch, Garmin, and FORM pool-swim files in normalized length data.
* Show per-length stroke counts and an available Total Strokes value on Workout Details.
* Preserve FIT session heart-rate and cadence metrics for pool workouts.
* Improve Apple Watch FIT recording-device identification while retaining the device model.
* Use FIT Activity local timestamps when available for historically correct workout-local times, with the configured WordPress timezone retained as a fallback.
* Add transparent compressed-source handling for TrainingPeaks-style FIT gzip files and FORM ZIP exports while preserving the original archive in protected source storage.
* Add up to three workout photos with upload, replacement, deletion, and workout-detail display.
* Add private real-world FIT regression hooks and expanded deterministic regression coverage without publishing private workout files.

= v0.7 Block Editor support =

Version 0.7 adds native Block Editor support for the six existing public display features while preserving their shortcodes for backward compatibility. The blocks are dynamic/server-rendered and share the same PHP rendering, authorization, and privacy logic as the shortcodes.

* Latest Workouts — `[swimlog_latest]`: swimmer override, count, and heading controls.
* Personal Bests — `[swimlog_bests]`: swimmer, course, stroke, distance, lifetime/age-group selection, age-group mode, and heading controls.
* Upcoming Events — `[swimlog_upcoming_events]`: swimmer, event count/site default, and heading controls.
* Workout — `[swimlog_workout]`: workout ID and heading controls; workout ownership determines the swimmer.
* Training Log — `[swimlog_log]`: swimmer, monthly/annual mode, month/year, and heading controls.
* Progress — `[swimlog_progress]`: swimmer, course, stroke, distance, and heading controls.

Public-results authorization is unchanged. Applicable blocks use the page/post author when no swimmer override is supplied and never fall back to the logged-in viewer. Open-water GPS routes remain separately opt-in, and GPS coordinates/trackpoints are not stored in block attributes or editor scripts.

== Screenshots ==

1. Swim Training Dashboard provides an overview of the latest workout, personal bests, upcoming events, and recent training activity.
2. Workout Details displays imported workout information, detected performances, preserved source imports, and normalized swim data.
3. Personal Bests tracks performances by age group, course, distance, and stroke, with lifetime personal bests identified.
4. PB Progression shows the performance history for a selected distance and stroke over time.
5. Native WordPress blocks provide latest workouts, personal bests, upcoming events, individual workouts, training logs, progress displays, and the private Swim Training Calendar.
6. Swim Training Calendar displays recorded workouts, planned swims, and upcoming events in a monthly view, with workout scheduling, personal-best indicators, and planned-workout matching.

== Changelog ==

= 1.1 =
* Add planned-workout creation and lifecycle management, recurrence, duplication, Duplicate Week, explicit recorded-workout matching/unmatching, and simple planned-versus-actual comparisons.
* Add a shared wp-admin Calendar host with Month/Week, Previous/Next/Today, Go to Date, configured week starts, and the same planning behavior as the Gutenberg Calendar.
* Improve mobile empty-date planning, initial responsive agenda loading, dense calendar presentation, textual Recorded/Planned/Event distinctions, focus restoration, aria state, and loading/error feedback.
* Keep calendar REST read-only and preserve privacy exclusions for private notes, GPS/trackpoints, device/source, and import metadata.
* Add calendar drag-and-drop rescheduling, Copy/Cut/Paste and destination-date selection, with date validation and cross-month navigation.
* Highlight explicitly matched recorded workouts, show personal-best indicators, and link directly to recorded Workout Details.
* Determine imported workouts' primary stroke from recorded active-length distance rather than an arbitrary stroke occurrence.
* Persist and display average and per-length SWOLF when source measurements support it, without inventing missing data.
* Add event race-result editing for finishing time, placement, and private notes with authenticated access controls.
* Add equivalent swimming steps using 181 steps per active swimming minute, preserving calculation provenance.
* Advance the internal database schema to 1.2 through additive, in-place, data-preserving migrations.

= 1.0 =
* Add the private Swim Training Calendar block with Month and Week views, Previous/Next/Today navigation, and swimmer-configurable Saturday/Sunday/Monday week starts.
* Add defensible weekly summaries from recorded workout count, distance, active time, pool distance, and open-water distance without manufacturing physiological metrics.
* Add a separately authorized internal calendar REST route that remains private regardless of Public Results, Public Open-Water Routes, or external REST API opt-in.
* Add selectable in-calendar workout details using calendar-safe fields only; GPS, trackpoints, notes, device/source, and import metadata remain excluded.
* Add responsive mobile date-grouped agenda presentation plus keyboard/focus, busy/status, reduced-motion, and forced-colors accessibility refinements.
* Keep the internal database schema at 0.9; v1.0 requires no schema migration.

= 0.9 =
* Add Pool Analytics for pace, consistency, recorded stroke-count coverage, normalized stroke metrics, SWOLF, and monthly trends without estimating missing measurements.
* Add Training Trends for weekly/monthly volume, frequency, active time, pool/open-water splits, four-week rolling averages, equal-period comparisons, course volume, and stroke volume.
* Add descriptive Race Readiness 2.0 evidence for upcoming events without readiness scores, race-time prediction, or fitness/recovery inference.
* Expand the authenticated, owner-scoped, opt-in, read-only REST API with PBs, progression, events/readiness, Pool Analytics, Training Trends, and richer normalized workout detail including per-length stroke counts.
* Add REST-API.md as a standalone developer reference with Application Password examples, endpoint/parameter/response documentation, privacy boundaries, and error handling, protected by route-documentation regression coverage.
* Preserve REST privacy exclusions for notes, source files/paths, import records, original filenames, open-water coordinates, and GPS trackpoints.
* Refactor administration code into focused components and shared UI helpers while preserving public/admin behavior and permissions.
* Maintain PHP 7.4–8.4 regression and packaged Plugin Check coverage.
* Keep the existing internal database schema version; v0.9 introduces no new schema migration.

= 0.8 =
* Add first-class manual pool-workout creation and editing with coarse totals or structured sets, repetitions, strokes, notes, and optional timing.
* Preserve timing provenance so only explicitly measured repetition times can become performance evidence; do not manufacture personal-best times from set totals, intervals/send-offs, derived timing, or workout duration.
* Keep manual workouts as explicit authoritative manual sources with transactional owner-scoped editing/rebuilding and protection against rewriting imported workouts as manual.
* Add up to three workout photos with upload, replacement, deletion, and Workout Details presentation.
* Preserve per-length FIT stroke counts from compatible Apple Watch, Garmin, and FORM pool-swim recordings and display per-length strokes plus available total strokes on Workout Details.
* Preserve pool-session heart-rate and cadence metrics from FIT sources.
* Improve Apple Watch FIT device identification while preserving the recorded model.
* Use FIT Activity local timestamps for historical local workout times when available, with WordPress timezone fallback.
* Add transparent gzip and ZIP workout-source extraction for supported TrainingPeaks/FORM exports while preserving the original archive in protected storage and enforcing expanded-size/path validation.
* Advance the internal database schema to 0.9 for normalized per-length stroke counts, including existing-installation upgrade handling.
* Expand private/deterministic FIT, compressed-import, schema-migration, and Workout Details regression coverage across supported PHP versions.

= 0.7 =
* Add native dynamic WordPress blocks for Latest Workouts, Personal Bests, Upcoming Events, Workout, Training Log, and Progress while retaining all six legacy shortcodes.
* Share the existing PHP rendering/query layer between blocks and shortcodes so public authorization, defaults, validation, and output semantics remain consistent.
* Add InspectorControls for shortcode-equivalent options, including age-group personal bests, monthly/annual training logs, workout selection, and personal-best progression.
* Preserve workout-record ownership, page/post-author swimmer resolution, no logged-in-viewer fallback, and separate open-water GPS route opt-in.
* Keep progression calculations and chart data authoritative in PHP/front-end rendering rather than duplicating business logic in block editor JavaScript.
* Add six-block integration, security/privacy, and release-packaging regression coverage across PHP 7.4 through 8.4.
* Add explicit per-swimmer REST API permission (default OFF) plus an in-plugin REST API help/documentation screen linked from Swimmer Configuration.
* Add workout numbers to the administrative Workouts list and Workout Details screen for easier workout identification.
* Add Create Post/Edit Post workflow from Workout Details, creating a draft post with an automatically generated swim title and the native Workout block linked to that workout.
* Improve public workout-table responsiveness and readability, including horizontal overflow handling, protected column headings/values, and more compact table typography.
* Add Wide and Full alignment support metadata to all six native swim blocks.


= 0.6 =
* Harden canonical pool/open-water matching, timestamp handling, type-aware queries, annual totals, admin filtering, and public presentation.
* Add transactional workout rebuilds from preserved FIT, CSV, GPX, and TCX source files.
* Add defensive GPX and TCX open-water import through the normalized importer.
* Expand open-water analytics with 1 km splits, estimated moving-time splits, heart-rate/cadence charts, sparse/corrected GPS handling, and optimized trackpoint processing.
* Add configurable rolling pool stroke training-volume analytics, with a per-swimmer 1–24 month period (3-month default), separate meter/yard rankings by total distance, and current event-readiness summaries.
* Add explicit opt-in public open-water route sharing and improved self-contained geographic route context.
* Add scheduled Garmin synchronization for pool swims and known-location open-water swims.
* Add saved open-water location coordinates and conservative first-GPS-fix matching within 500 meters, with ambiguous matches left for review.
* Improve Garmin Personal API import-state presentation, per-activity/batch location workflows, and duplicate/review handling.
* Harden FIT recording-device identity and normalize recording-device presentation.
* Add an authenticated read-only swimlog/v1 REST API for external integrations while excluding private source data and open-water GPS coordinates.
* Add complete admin drill-down documentation for all registered public shortcodes, including swimlog_progress.
* Expand deterministic open-water/import regression coverage without publishing private activity files.
* Repair release-package automation and maintain PHP 7.4 through 8.4 regression coverage.
* Fix REST API swimmer authorization to use the installed own-results capability.
* Harden upgrades from v0.5, including GPX/TCX upload defaults and schema migration coverage.
* Make preserved-source rebuild authority deterministic and persist the authoritative import used for normalized structure.
* Add Garmin scheduled-sync operational telemetry for last attempt, last success, last error, result counts, and next scheduled check.
* Correct open-water HR/cadence chart elapsed-time origin and add numeric saved-location boundary coverage.
* Make the 42-day event-readiness policy explicit and add analytics metric unit/provenance metadata.
* Advance the internal database schema to 0.8 for persisted source-authority metadata.

= 0.5 =
* Add first-generation open-water FIT import, GPS/lap preservation, route visualization, analytics, and Garmin Personal API matching/import.
* Keep open-water workouts isolated from pool personal-best evaluation.

== Data Preservation ==

Historical data is preserved by default. Deactivation and ordinary updates do
not delete workout history or original source imports. Derived performances are
designed to be rebuildable from preserved authoritative workout data.
