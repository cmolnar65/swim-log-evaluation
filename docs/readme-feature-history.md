# Historical feature and development notes

These notes were previously appended as custom sections in `readme.txt`. They are retained here as repository documentation; `readme.txt` now uses the standard WordPress sections and concise changelog.

## Data Preservation

Historical data is preserved by default. Deactivation and ordinary updates do
not delete workout history or original source imports. Derived performances are
designed to be rebuildable from preserved authoritative workout data.



Version 1.4 release note: schema 1.5 added canonical per-workout visibility, schema 1.6 added private swim goals, and schema 1.7 added Health Connect receiver storage. Running/walking log and calendar details remain owner-scoped with separate totals; swimming analytics remain swim-only. Public workout views and GPS routes require the explicit canonical approvals described above. Cycling remains deferred. Live Garmin subtype-key coverage and physical-device acceptance have not been established.

Import or manually record, preserve, evaluate, and display swimming workout history and personal bests.

## Twenty Twenty-Four page templates

For WordPress Twenty Twenty-Four sites displaying SwimLog tables, the preferred Page/Post templates are **SwimLog Wide Content** and **SwimLog Full Width**. Select one from the editor's Template selector. Both preserve the theme header, footer, and page content while providing appropriate table width and responsive display. Wide Content is suitable for wider tables with a bounded content area; Full Width is suited to the widest tables and multi-column layouts. Both were manually verified in Twenty Twenty-Four during v1.2 Phase 03. These templates are optional, and existing pages need not change.

## v1.4 release status

The v1.4 release uses plugin/runtime version 1.4 and database schema 1.7. It includes private Garmin and iHealth measurement workflows, the owner-scoped Health Connect receiver, distinct running/walking totals, canonical read-only workout visibility, swimmer unit preferences, and cumulative and single-workout swimming goals with completion dates and capped achieved progress. Existing workouts, measurements, access controls and privacy boundaries are preserved by additive schema migrations. See the v1.4 release checklist for exact commit, test runs, package checksum, tag and WordPress.org revision.

## Garmin measurements and historical imports (v1.3 development)

On the Garmin Connect page, use your own saved Personal API connection. Private measurements supports up to seven inclusive days per manual submission. Automatic private measurement imports are off by default and separately selectable by category; historical imports do not enable regular sync.

Import historical Garmin data accepts inclusive dates and selected pool/open-water swims, weight, BMI and provider SpO2 summaries. Start with a short known range, then select a longer range such as 2018-01-01 through 2018-12-31. Background jobs persist after browser closure and process small batches through WordPress cron; long ranges can take hours or longer and depend on site traffic. Garmin may not expose every old record.

Pause/Resume retains progress. Cancel is terminal and retains already imported data. Retry failed items is available after completion with errors. A request already in progress may finish. Missing permissions/keys block work without dropping progress; after plugin reactivation, resume jobs explicitly. Use Refresh job status or Resume / repair schedule if needed.

Exact duplicates are skipped; distinct same-day readings stay. Activity identities and FIT fingerprints are checked with existing conservative matching/location review. Pending/failed activity records are not silently labelled imported. Follow existing Garmin activity/location and pending-import review links for explicit decisions.

Result filters show all, failed or needs-review items, twenty per page. Counts distinguish swims, individual readings and daily category records (including weight/BMI bookkeeping and SpO2 reports). Empty responses are not proof that no historical data ever existed. An interrupted committed import can replay as already present/skipped without a second record. Review counts reflect the scan outcome, not subsequent decisions on another screen.

Jobs and health data are private to the signed-in owner and excluded from public sharing/existing external portability responses. Calendar measurements follow workouts/events and retain source dates, units and multiple readings. SpO2 daily/window averages, sleep summaries and seven-day baselines remain distinct; no unverified raw-sample average is invented. The separate iHealth setup screen offers bounded, manual weight/body-composition and blood-pressure/pulse imports. Weight is normalized only for recognized API units, and other supported Personal API composition fields remain private raw values labeled with unknown units and are not interpreted or displayed. Blood-pressure records require the observed `BPUnit=mmHg`; `HP`, `LP`, and optional `HR` map to systolic, diastolic, and pulse. Location, notes, device flags, and other unused fields are discarded. Exact same-time cross-provider weights are omitted from calendar readings; equal weights at different times are retained and reported as possible duplicates, while different same-time weights remain conflicts. Individual/hourly SpO2 is not imported.

The iHealth Personal API import requires the administrator's verified OAuth grant plus SC and the matching API SV code. BP and weight signing codes are stored encrypted separately. Each manual action requests one page and accepts at most 90 days; no automatic sync is enabled. A sanitized account probe confirmed the blood-pressure endpoint's response keys, field names/types and `BPUnit=mmHg`, without sharing measurement values. This does not establish complete history coverage, other accounts' response variants, or physical-device behavior.

The repository contains docs/v1.3-garmin-user-guide.md with installation, recovery, limitations and the pending test-site acceptance checklist. Documentation/development scripts and confidential source PDFs are excluded from installable packages.

## Feature details

Chris's Swim Training Progress and Evaluation is a multi-user WordPress plugin for importing FIT and
CSV swimming workouts, manually recording pool workouts, preserving normalized workout history, evaluating native
meter and yard performances, tracking personal bests, managing swim events and
locations, and displaying selected results through display-only shortcodes.

Version 1.2 redesigns the dashboard with navigation tiles and training-volume/SWOLF charts, and adds private structured swimming workouts with sections, repeats, ladders, stroke, drill, equipment, pace and rest; training-plan PDF preview, review, selection and import; links to planned workouts; and recorded pool-swim conversion without changing recorded history. Download prescriptions as FIT Workout files, printable PDFs or text for copying to FORM. FIT destination support varies; TrainingPeaks planned-workout library import is not guaranteed. Calendar days expand in an accessible dialog on desktop and mobile, and the administration menu groups related tasks. Optional Twenty Twenty-Four layout templates improve wide-table presentation. PHP 7.4 through 8.5 is supported. The database schema remains 1.2; no new schema migration is required.

Version 1.1 adds Planned Workouts and turns the Swim Training Calendar into an interactive planning surface. Swimmers can create, edit, duplicate, repeat, skip, cancel, restore, and delete future plans; duplicate a week; explicitly match a completed recorded workout to a plan; and review simple planned-versus-actual distance and duration differences. Planned workout instructions preserve multiline free-form swim notation for future structured-workout parsing without inventing sets, send-offs, training stress, readiness, or coaching conclusions.

The shared calendar now also has a WordPress administration Calendar page while retaining the native Gutenberg Calendar block. Both hosts use the same calendar service, Month/Week behavior, swimmer-configured Saturday/Sunday/Monday week boundaries, planning/matching actions, privacy rules, responsive agenda, and accessibility behavior. Calendar REST remains read-only and private notes, GPS/trackpoints, and device/source metadata remain excluded.

v1.1 advances the internal database schema to 1.2 through additive, in-place migrations for planned workouts and recorded workout metrics. Existing workout, event, import, performance, location, manual-workout, lap, length, and trackpoint data are preserved.

Version 1.0 adds the private Swim Training Calendar: month and week views, swimmer-configurable Saturday/Sunday/Monday week boundaries, defensible weekly training summaries, in-calendar workout details, responsive mobile agenda presentation, and accessibility-focused keyboard/focus behavior. It builds on the v0.9 analytics, REST/data-portability, and administration architecture without adding a database schema migration.

### Training plans and structured workouts (v1.2)

Upload a training-plan PDF from Structured Workouts, inspect each candidate against its source instructions and stated total, correct supported structure as needed, select the workouts to import and confirm review completion. Preview sessions last 24 hours after upload; expired previews require another upload. Unsupported notation requires manual correction rather than guessed distances. Imported workouts retain their collection sequence and can be scheduled on individually chosen dates.

Planned workouts can link to structured prescriptions. Regeneration from changed text requires preview and approval; copied plans receive independent prescriptions. Structures associated with recorded swims are protected against editing, replacement or unlinking. Hide/unhide organizes the library without removing history.

Download FIT Workout, PDF or text from a structured workout. FORM text can be copied and pasted; FIT compatibility depends on the destination and does not guarantee TrainingPeaks planned-workout import. These exports describe prescriptions and do not fabricate completed activities. Existing recorded history, personal bests and privacy restrictions remain intact.

### v1.1

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

### v1.0

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

### v0.9

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

### v0.8

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

### v0.7 Block Editor support

Version 0.7 adds native Block Editor support for the six existing public display features while preserving their shortcodes for backward compatibility. The blocks are dynamic/server-rendered and share the same PHP rendering, authorization, and privacy logic as the shortcodes.

* Latest Workouts — `[swimlog_latest]`: swimmer override, count, and heading controls.
* Personal Bests — `[swimlog_bests]`: swimmer, course, stroke, distance, lifetime/age-group selection, age-group mode, and heading controls.
* Upcoming Events — `[swimlog_upcoming_events]`: swimmer, event count/site default, and heading controls.
* Workout — `[swimlog_workout]`: workout ID and heading controls; workout ownership determines the swimmer.
* Training Log — `[swimlog_log]`: swimmer, monthly/annual mode, month/year, and heading controls.
* Progress — `[swimlog_progress]`: swimmer, course, stroke, distance, and heading controls.

Public-results authorization is unchanged. Applicable blocks use the page/post author when no swimmer override is supplied and never fall back to the logged-in viewer. Open-water GPS routes remain separately opt-in, and GPS coordinates/trackpoints are not stored in block attributes or editor scripts.

## Screenshots
Canonical per-workout sharing in v1.4 is read-only: public viewing requires explicit swimmer Public Results plus individual workout approval. Route sharing requires a separate workout flag and explicit route consent. Notes, photos, sources, raw metadata and measurements are excluded. Schema 1.5 added visibility state without rewriting workout history.
