# Plugin Check Baseline

This document records the reviewed WordPress Plugin Check baseline for **Chris's Swim Training Progress and Evaluation**.

It is developer and release documentation. It is not intended to suppress Plugin Check findings or to make warnings disappear. Its purpose is to distinguish reviewed architectural warnings from findings that require investigation.

## Baseline

Baseline build: **RC8**

Plugin Check result:

- Errors: **0**
- Warnings: **253**

RC8 is the first baseline established after targeted remediation of actionable Plugin Check findings. The remaining warnings were reviewed by warning family and by the SQL patterns that produce them.

### RC8 warning families

| Warning | Count | Baseline disposition |
| --- | ---: | --- |
| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | 84 | Reviewed / accepted custom-table architecture |
| `WordPress.DB.DirectDatabaseQuery.NoCaching` | 74 | Reviewed / accepted for current architecture |
| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | 52 | Reviewed only where interpolation is an internally generated custom-table identifier or already-prepared internal SQL fragment |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter` | 43 | Reviewed only where the reported value is an internally generated custom-table identifier |
| **Total** | **253** | |

No RC8 finding in the baseline is automatically considered safe merely because it has one of these codes. The specific source and data flow must continue to satisfy the conditions documented below.

## Why the plugin uses direct database queries

Chris's Swim Training Progress and Evaluation stores structured workout history in dedicated custom WordPress database tables. These tables include imports, locations, workouts, laps, lengths, performances, and events.

The plugin therefore uses `$wpdb` directly for operations that do not map naturally to WordPress post, post-meta, term, or option APIs. This includes transactional imports, normalized swim-length data, performance evaluation, personal-best history, and derived-data rebuilds.

Custom table names are produced internally by `Database::table()`, which applies the active WordPress database prefix. They are not accepted from request parameters, uploaded files, shortcode attributes, or other user-controlled values.

## Accepted warning categories

### DirectDatabaseQuery.DirectQuery

Direct `$wpdb` access is intentional for the plugin's custom tables.

This warning is accepted when the query is required to read or modify those custom tables and all externally derived query values are handled safely.

The warning does **not** exempt a query from normal WordPress SQL security requirements.

### DirectDatabaseQuery.NoCaching

The current implementation intentionally does not add a WordPress object-cache layer solely to satisfy Plugin Check.

Workout imports and evaluation data are mutable, and some operations are transactional. Performance rows can also be deleted and deterministically rebuilt. Introducing caching would require reliable invalidation across imports, FIT/CSV source attachment, workout metadata changes, evaluation, rebuilds, and personal-best recalculation.

Caching may be added later if profiling demonstrates a need. Until then, a `NoCaching` warning on reviewed custom-table access is accepted.

### PreparedSQL.InterpolatedNotPrepared

This warning is accepted only in either of these reviewed situations:

1. The interpolated value is an internally generated custom-table identifier returned by `Database::table()`; or
2. The interpolated SQL fragment is constructed entirely from fixed application-controlled SQL whose externally derived values were separately passed through `$wpdb->prepare()`.

WordPress placeholders are still required for query **values**.

User-controlled table names, column names, operators, ordering expressions, arbitrary SQL fragments, or unprepared values are **not** covered by this exception.

The workout filtering code is an example of the second situation: Plugin Check can still report the assembled `$sql_where` fragment even though optional predicates are individually prepared before the fragment is combined.

### DirectDB.UnescapedDBParameter

Plugin Check can report a custom table variable such as `$table`, `$t`, `$wt`, or `$lt` as an unescaped database parameter because static analysis does not establish that it came from `Database::table()`.

This warning is accepted only when the reported variable can be traced to the plugin's internal custom-table-name helper and cannot be influenced by the user.

It is not an exception for ordinary query values.

## Findings that are not accepted as baseline exceptions

The following must be investigated when they appear, even if Plugin Check otherwise reports only warnings:

- `PreparedSQL.UnfinishedPrepare`
- Placeholder/replacement count mismatches such as `ReplacementsWrongNumber`
- Unsanitized or improperly unslashed request, upload, cookie, or server input
- SQL interpolation involving user-controlled values
- Dynamic table, column, operator, sort, or SQL-fragment selection derived from untrusted input
- Missing capability or nonce validation on state-changing administrative actions
- Filesystem findings involving unsafe source-file handling
- New error-level Plugin Check findings
- A new warning family not documented in this baseline

A warning that resembles an accepted warning must still be reviewed if its source location or data flow changes.

## Release procedure

For each release candidate:

1. Build the distribution ZIP rather than running the release check against development-only files.
2. Run WordPress Plugin Check against the installed release package.
3. Record the error and warning totals.
4. Compare warning families and relevant source locations with this baseline.
5. Investigate all errors, new warning families, and unexpected warning locations.
6. Do not modify secure or transactionally correct database behavior merely to reduce the warning count.
7. Update this document when an intentional architectural change alters the accepted baseline.

A lower warning count is useful only when it results from a legitimate remediation. The release criterion is not “zero warnings”; it is **zero unresolved actionable findings** after review.

## Baseline history

| Release candidate | Errors | Warnings | Notes |
| --- | ---: | ---: | --- |
| RC5 | 0 | 269 | Post-filesystem-remediation release package |
| RC6 | 0 | 258 | Administrative/input cleanup in progress |
| RC7 | 0 | 255 | Prepare and administrative warning cleanup |
| **RC8** | **0** | **253** | **Reviewed baseline; remaining findings classified as architectural/static-analysis warnings** |

## Maintenance rule

Do not add broad PHPCS or Plugin Check suppressions simply to hide this baseline.

Where a targeted inline suppression is ever necessary, it should identify the exact false-positive condition and remain narrow enough that nearby new issues are still detected.

The preferred approach is to keep Plugin Check output visible, compare it with this documented baseline, and investigate changes.
