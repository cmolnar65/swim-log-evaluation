=== Chris's Swim Training Progress and Evaluation ===
Contributors: cmolnar65
Tags: swimming, swim log, personal bests, fit, csv
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and review swimming workouts, personal bests, training goals, and private health readings.

== Description ==

Import and review pool and open-water swims from FIT or CSV, record pool workouts manually, and track personal bests, events, and goals. Private Garmin, iHealth, and Health Connect data integrations are supported.

Running and walking logs remain separate from swimming totals. The plugin includes structured swim prescriptions, training-plan review, calendar and analytics views, and selected public swim results.

PHP 7.4 through 8.5 and WordPress 6.2 or later are supported. See the Changelog for release history and supported features.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/chriss-swim-training-progress-evaluation` directory, or install the plugin ZIP through the WordPress Plugins screen.
2. Activate the plugin in WordPress.
3. Use the Swim Log administration pages to configure the plugin and manage workouts.


== Screenshots ==

1. Swim Training Dashboard provides an overview of the latest workout, personal bests, upcoming events, and recent training activity.
2. Workout Details displays imported workout information, detected performances, preserved source imports, and normalized swim data.
3. Personal Bests tracks performances by age group, course, distance, and stroke, with lifetime personal bests identified.
4. PB Progression shows the performance history for a selected distance and stroke over time.
5. Native WordPress blocks provide latest workouts, personal bests, upcoming events, individual workouts, training logs, progress displays, and the private Swim Training Calendar.
6. Swim Training Calendar displays recorded workouts, planned swims, and upcoming events in a monthly view, with workout scheduling, personal-best indicators, and planned-workout matching.

== Changelog ==

= 1.5-dev (unreleased) =
* Add a low-copy Health Connect authorization flow: the Android app opens the configured HTTPS WordPress site for sign-in and explicit consent, then exchanges a short-lived, single-use PKCE authorization code for the signed-in owner's app token. WordPress credentials stay in the browser; the owner remains derived from the WordPress session.
* Add `POST /health-connect/token` with strict client and redirect binding, S256 PKCE verification, atomic one-time code consumption and no-store responses. Preserve the existing owner-scoped import receiver and manual token controls.
* Add private schema 1.8 storage for hashed, short-lived authorization codes. Existing workout, measurement and token records are preserved; a successful new authorization replaces the account's prior Health Connect app token.

= 1.4 =
* Add private swim goals for a single performance by a due date, cumulative distance across an inclusive date range, and a single-workout distance target. Show the first completion date and cap achieved progress so later swims do not add to it.
* Add per-swimmer primary units and optional secondary display units for running, walking, cycling display and body weight; set swim-course defaults without combining meter and yard records.
* Add the owner-scoped Health Connect batch receiver and one-time app tokens with additive private schema 1.7 tables. Support private pool/open-water swims, running, walking, heart-rate samples and supported personal measurements. Retries are idempotent; conflicting changes under a known source identity are rejected, and cross-source possible duplicates remain separate.
* Add private Garmin measurement and historical-import workflows, including weight/BMI, provider SpO2 summaries, sleep-window details, bounded manual imports and resumable historical jobs.
* Add iHealth app setup with encrypted credentials, administrator-authorized OAuth verification, and bounded manual weight/body-composition and blood-pressure/pulse imports with duplicate checks.
* Add private running and walking FIT activity logging, optional recorded GPS maps and separate sport totals. These activities remain outside swimming totals and coaching/evaluation metrics; cycling remains unsupported.
* Add canonical read-only public workout visibility with separate owner-wide and per-workout approvals, distinct route-sharing gates, server-side owner management, and private measurements and notes.
* Add explicit unlinking for planned and structured workouts while preserving recorded workout history.
* Improve swimming stroke classification, units, goal progress, calendar details, responsive administration and accessibility while retaining existing records and privacy boundaries.
= 1.2 =
* Redesign the owner-scoped dashboard with navigation tiles, selectable history ranges, training-volume and measured SWOLF charts, and keyboard/swipe chart controls.
* Add optional SwimLog Wide Content and SwimLog Full Width Page/Post templates for Twenty Twenty-Four, with scoped responsive styles and per-page table display preferences.
* Add private structured swimming workouts with ordered sections, steps, nested repeats and ladders; stroke, drill, equipment, pace and rest fields; and live distance/stroke totals.
* Add a responsive structured-workout editor, equipment checklist and detailed saved-workout list, with active/hidden/all filters, hide/unhide and history-protected deletion.
* Parse supported written swim notation conservatively, retaining meter/yard units, explicit totals, unknown versus zero rest and review flags for ambiguous instructions.
* Link planned workouts to private structures and preview explicitly approved regeneration from changed instructions, with stale-edit protection.
* Copy structured prescriptions independently with duplicated or recurring plans; preserve recorded-swim structures as immutable and prevent replacement or unlinking.
* Add private training-plan collections with source sequence/week metadata and individually assigned, nonconsecutive scheduling dates.
* Extract PDF text with bundled smalot/pdfparser 2.13.1; recognize supported workout headings, compact sets, table-header repeats, odd/even strokes, split lengths and equipment instructions.
* Provide editable PDF candidate previews, selection checkboxes, confirmed distance/unit and explicit review approval; reset approval after edits and reject incomplete submissions.
* Keep PDF previews available for 24 hours after upload. Retain private review provenance, preserve selected-only imports, reject duplicate imports and roll back partial failures.
* Convert supported recorded pool swims into independent private prescriptions through preview and confirmation, without changing source history or inventing timing, sets or personal-best evidence.
* Download standard FIT Workout files, printable Unicode PDFs using Dompdf 3.1.6, and text suitable for copying to FORM. FIT destination support varies; TrainingPeaks planned-library import is not guaranteed.
* Expand calendar dates into accessible day dialogs in month/week views and the mobile agenda, preserving existing workout/planning/event actions, privacy and normal navigation.
* Support empty/busy days, scrolling, keyboard focus containment, Escape/Close/outside-tap dismissal, focus restoration, phone rotation and authorized adjacent-day loading with retry handling.
* Reorder administration navigation around Dashboard, Calendar, workouts/import/planning, analytics, events/locations and configuration, with Settings last.
* Harden nonce/input validation, prepared SQL identifiers and values, owner-scoped query caching and invalidation, FORM import bindings and linked-post lookup freshness; retain individually justified database exceptions.
* Extend full-file PHP lint and regression validation through PHP 8.5. Run strict Plugin Check against production staging and exercise real WordPress/MariaDB persistence, permissions, rollback and desktop/mobile browsers.
* Use Node.js 24-compatible GitHub actions and manual-only ZIP packaging; retain the runtime Composer manifest/dependencies and exclude confidential PDFs, private extracted content and unnecessary development files.
* Keep WordPress minimum version 6.2, PHP minimum 7.4 and database schema version 1.2. Preserve existing data and backward compatibility; no new schema migration is required.

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
