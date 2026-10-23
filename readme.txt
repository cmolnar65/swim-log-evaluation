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

== Data Preservation ==

Historical data is preserved by default. Deactivation and ordinary updates do
not delete workout history or original source imports. Derived performances are
designed to be rebuildable from preserved authoritative workout data.
