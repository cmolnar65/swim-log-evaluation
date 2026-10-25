=== Chris's Swim Training Progress and Evaluation ===
Contributors: cmolnar65
Tags: swimming, swim log, personal bests, fit, csv
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import, preserve, evaluate, and display swimming workout history and personal bests.

== Description ==

Chris's Swim Training Progress and Evaluation is a multi-user WordPress plugin for importing FIT and
CSV swimming workouts, preserving normalized workout history, evaluating native
meter and yard performances, tracking personal bests, managing swim events and
locations, and displaying selected results through display-only shortcodes.

Version 0.7 includes the open-water, Garmin, rebuild, training-analysis, privacy, and integration work introduced after v0.5, plus native Block Editor support for all six public display features, improved workout administration and post creation, responsive public tables, and per-swimmer REST API permissions and documentation.

= v0.7 Block Editor support =

Version 0.7 adds native Block Editor support for the six existing public display features while preserving their shortcodes for backward compatibility. The blocks are dynamic/server-rendered and share the same PHP rendering, authorization, and privacy logic as the shortcodes.

* Latest Workouts — `[swimlog_latest]`: swimmer override, count, and heading controls.
* Personal Bests — `[swimlog_bests]`: swimmer, course, stroke, distance, lifetime/age-group selection, age-group mode, and heading controls.
* Upcoming Events — `[swimlog_upcoming_events]`: swimmer, event count/site default, and heading controls.
* Workout — `[swimlog_workout]`: workout ID and heading controls; workout ownership determines the swimmer.
* Training Log — `[swimlog_log]`: swimmer, monthly/annual mode, month/year, and heading controls.
* Progress — `[swimlog_progress]`: swimmer, course, stroke, distance, and heading controls.

Public-results authorization is unchanged. Applicable blocks use the page/post author when no swimmer override is supplied and never fall back to the logged-in viewer. Open-water GPS routes remain separately opt-in, and GPS coordinates/trackpoints are not stored in block attributes or editor scripts.

== Changelog ==

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
