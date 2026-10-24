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

The development branch for version 0.6 further hardens open-water matching, Garmin import state and
location workflows, device presentation, type-aware workout views and totals, public/admin analytics,
1 km split handling, and regression coverage. The stable WordPress.org release remains version 0.5
until the v0.6 release process is completed.

== Changelog ==

= 0.6 - In development =
* Harden canonical pool/open-water matching and timestamp handling.
* Add type-aware workout queries, annual totals, admin filtering, and public workout presentation.
* Improve Garmin Personal API import-state presentation and open-water location assignment, including batch imports.
* Expand open-water admin/public analytics and 1 km split handling.
* Optimize open-water trackpoint analytics processing.
* Harden FIT recording-device identity and normalize device presentation.
* Expand deterministic open-water FIT regression coverage without publishing private activity files.
* Repair release-package automation and maintain PHP 7.4 through 8.4 regression coverage.

= 0.5 =
* Add first-generation open-water FIT import, GPS/lap preservation, route visualization, analytics, and Garmin Personal API matching/import.
* Keep open-water workouts isolated from pool personal-best evaluation.

== Data Preservation ==

Historical data is preserved by default. Deactivation and ordinary updates do
not delete workout history or original source imports. Derived performances are
designed to be rebuildable from preserved authoritative workout data.
