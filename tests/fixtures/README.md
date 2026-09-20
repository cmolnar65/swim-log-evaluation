# v0.1 import fixtures

Fixture 001 is the real FORM pool swim from September 19, 2026.

Place the original paired exports here using these repository-local names:

- `form-2026-09-19.fit`
- `form-2026-09-19.csv`

The automated fixture test deliberately skips rather than substituting synthetic source files when either original is absent.

Expected independently established facts:

- 25 m pool
- 3000 m total
- elapsed approximately 3945.671 seconds
- 152 length/rest records
- 120 active lengths
- 32 rest records
- 74 freestyle active lengths = 1850 m
- 46 breaststroke active lengths = 1150 m
- opening uninterrupted 800 m freestyle block
- first active length starts at approximately 11:23:44 local and is approximately 16.591 s
- rest after opening 800 m is approximately 49.079 s
- FIT contains 65 native laps
- FORM CSV contains 7 high-level sets; those are supplemental metadata and are not normalized as native FIT laps

Do not alter fixture bytes to make a parser test pass. Parser behavior must be fixed instead.

## Fixture 002 — incomplete FORM export

Use the original August 26, 2026 export as `form-2026-08-26-empty.csv`.

Expected facts:
- summary start: 2026-08-26 10:20:19
- summary end: 2026-08-26 11:07:03
- 25 m pool
- detail header is present but there are no detail rows
- parser may recognize the FORM export, but the importer must reject it with `swimlog_csv_incomplete` rather than create an empty workout

As with Fixture 001, keep the original file private/out of the public repository and point the test environment at an unchanged local copy.


## Fixture 003 — non-FORM 25-yard FIT

Use the exact unchanged private file `22038489308_ACTIVITY.fit`. Set `SWIMLOG_YARD_FIT_FIXTURE` to its local path when running the regression suite. Do not commit the original FIT file to the public repository.

Expected facts:
- non-FORM FIT source
- 25 yd native pool = 22.86 m normalized
- 1700 yd native workout distance = 1554.48 m normalized
- elapsed 2029.308 seconds
- 2 native FIT laps
- 69 length/rest records
- 68 active lengths
- 1 rest record
- 34 freestyle active lengths = 850 yd
- 34 breaststroke active lengths = 850 yd
- normalized active-length distance totals 1554.48 m

This fixture specifically guards against double-converting FIT distance fields when the FIT session declares yard course units.


## Fixture 004 — Garmin export CSV (unsupported in v0.1)

Use the exact unchanged private Garmin export CSV `activity_22038489308.csv`, paired with Fixture 003's non-FORM yard-pool FIT workout. Set `SWIMLOG_GARMIN_CSV_FIXTURE` to its local path when running the regression suite. Do not commit the original CSV to the public repository.

Known source characteristics:
- Garmin export CSV
- paired with the 25 yd / 1700 yd workout in `22038489308_ACTIVITY.fit`
- CSV columns include Intervals, Swim Stroke, Lengths, Distance, Time, Cumulative Time, Avg Pace, Best Pace, Avg. Swolf, Avg HR, Max HR, Total Strokes, Avg Strokes, and Calories
- 68 active 25 yd lengths = 1700 yd
- 34 freestyle and 34 breaststroke active lengths
- includes a final rest record
- CSV timing differs slightly from FIT session elapsed time

v0.1 intentionally supports FORM swim-export CSV only. The regression must therefore verify that this Garmin CSV returns `swimlog_csv_format` and is never misinterpreted as a FORM CSV. Keep this fixture for future Garmin CSV support; when Garmin support is intentionally added, replace this rejection assertion with Garmin-specific normalization assertions rather than weakening FORM format detection.


### Fixture 005 — clearly different FORM workout (private)

Set `SWIMLOG_DIFFERENT_FIT_FIXTURE` to the unchanged September 18, 2026 FORM FIT file (`FORM_2026-09-18_110730.fit`). It is used against the September 19 reference workout to prove a clearly different real workout does not satisfy the automatic attachment fingerprint. Keep the binary outside the public repository.
