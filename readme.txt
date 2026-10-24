=== Chris's Swim Training Progress and Evaluation ===
Contributors: cmolnar65
Tags: swimming, swim log, personal bests, fit, csv
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import, preserve, evaluate, and display swimming workout history and personal bests.

== Description ==

Chris's Swim Training Progress and Evaluation is a multi-user WordPress plugin for importing FIT and
CSV swimming workouts, preserving normalized workout history, evaluating native
meter and yard performances, tracking personal bests, managing swim events and
locations, and displaying selected results through display-only shortcodes.

Version 0.5 adds first-generation open-water swimming support, including FIT detection and import,
GPS trackpoint and lap preservation, route visualization, open-water analytics, Garmin Personal API
open-water importing and matching, and isolation of open-water workouts from pool personal-best evaluation.

The development branch for version 0.6 expands open-water import and analytics, adds GPX/TCX support,
scheduled Garmin synchronization with GPS-based saved-location matching, rebuild and training-analysis
tools, route privacy controls, a read-only integration API, and complete shortcode documentation.
The stable WordPress.org release remains version 0.5 until the v0.6 release process is completed.

== Changelog ==

= 0.6 - In development =
* Harden canonical pool/open-water matching, timestamp handling, type-aware queries, annual totals, admin filtering, and public presentation.
* Add transactional workout rebuilds from preserved FIT, CSV, GPX, and TCX source files.
* Add defensive GPX and TCX open-water import through the normalized importer.
* Expand open-water analytics with 1 km splits, estimated moving-time splits, heart-rate/cadence charts, sparse/corrected GPS handling, and optimized trackpoint processing.
* Add pool stroke training-volume analytics and current event-readiness summaries.
* Add explicit opt-in public open-water route sharing and improved self-contained geographic route context.
* Add scheduled Garmin synchronization for pool swims and known-location open-water swims.
* Add saved open-water location coordinates and conservative first-GPS-fix matching within 500 meters, with ambiguous matches left for review.
* Improve Garmin Personal API import-state presentation, per-activity/batch location workflows, and duplicate/review handling.
* Harden FIT recording-device identity and normalize recording-device presentation.
* Add an authenticated read-only swimlog/v1 REST API for external integrations while excluding private source data and open-water GPS coordinates.
* Add complete admin drill-down documentation for all registered public shortcodes, including swimlog_progress.
* Expand deterministic open-water/import regression coverage without publishing private activity files.
* Repair release-package automation and maintain PHP 7.4 through 8.4 regression coverage.

= 0.5 =
* Add first-generation open-water FIT import, GPS/lap preservation, route visualization, analytics, and Garmin Personal API matching/import.
* Keep open-water workouts isolated from pool personal-best evaluation.

== Data Preservation ==

Historical data is preserved by default. Deactivation and ordinary updates do
not delete workout history or original source imports. Derived performances are
designed to be rebuildable from preserved authoritative workout data.
