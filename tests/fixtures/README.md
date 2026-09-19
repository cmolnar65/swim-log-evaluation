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
