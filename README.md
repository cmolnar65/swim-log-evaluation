# Chris's Swim Training Progress and Evaluation

Chris's Swim Training Progress and Evaluation is a WordPress plugin for importing, preserving, and evaluating swimming workout history.

The plugin supports FIT and supported CSV workout sources, normalized workout/lap/length storage, personal-best evaluation, events, locations, and front-end display shortcodes. Original workout source files are preserved so normalized and derived data can be rebuilt without discarding the historical source.

## Current development: v0.6

The `develop` branch contains the v0.6 development line. The published/stable plugin remains version 0.5 until the release process is explicitly completed.

v0.6 expands the plugin with:

- hardened canonical pool/open-water matching, timestamp handling, type-aware queries, annual totals, administration filters, and public presentation;
- expanded open-water analytics including 1 km splits, estimated moving-time splits, heart-rate/cadence charts, sparse/corrected GPS handling, and more efficient trackpoint processing;
- transactional workout rebuilds from preserved FIT, CSV, GPX, and TCX sources;
- pool stroke training-volume analytics and current event-readiness summaries;
- explicit opt-in public open-water route privacy controls plus improved self-contained geographic route context;
- scheduled Garmin synchronization, including automatic open-water import when the first valid GPS fix safely matches a saved location;
- saved open-water location reference coordinates with conservative 500 m proximity matching and ambiguity protection;
- defensive GPX and TCX open-water import through the normalized importer;
- hardened FIT recording-device identity and normalized device presentation;
- an authenticated, read-only `swimlog/v1` REST API for external integrations that excludes private source data and open-water GPS coordinates;
- complete admin documentation and drill-down help for all six registered public shortcodes, including `[swimlog_progress]`;
- expanded deterministic regression coverage for open-water import and presentation while keeping private/original activity files out of the public repository; and
- repaired release-package automation with PHP 7.4–8.4 regression testing.

Open-water workouts remain isolated from pool personal-best evaluation.

## Private Workout File Storage

Original uploaded FIT and CSV workout files are preserved in private server storage. These files may contain detailed workout history and must not be directly accessible from the public web.

### Default storage location

By default, the plugin uses the site's `DOCUMENT_ROOT` to determine the public web root and attempts to create a directory named:

```text
swim-log-evaluation-private
```

one directory above the public web root.

For example, if the public web root is:

```text
/var/www/example.com/public
```

the default private storage location would be:

```text
/var/www/example.com/swim-log-evaluation-private
```

The selected directory must be writable by the PHP/WordPress process. The plugin validates that the storage directory is outside the public web root and refuses to preserve workout source files in a web-accessible location.

The plugin also creates an `index.php` file in the private directory as an additional precaution. This file is not the primary security mechanism; the directory itself should remain outside the public web root.

### Configuring a custom private directory

Some hosting environments do not permit WordPress to create or write to a directory adjacent to the site's document root. Administrators can configure another private storage location on the plugin's **Swim Training Settings** screen using **Private Workout Storage Directory**. The setting accepts an absolute server filesystem path and is validated before it is saved.

For advanced or managed hosting deployments, `SWIMLOG_PRIVATE_STORAGE_DIR` may still be defined in `wp-config.php`. When the constant is defined it takes precedence over the Settings value and the field is displayed as locked.

For example:

```php
define( 'SWIMLOG_PRIVATE_STORAGE_DIR', '/path/outside/public/webroot/swim-log-evaluation-private' );
```

The configured path must:

- be outside the site's public web root;
- be writable by the PHP/WordPress process; and
- remain protected from direct web access.

Do not solve permission problems by making broad server directories world-writable (for example, mode `777`). Configure ownership and permissions appropriate for the web server/PHP environment instead.

### Backups and data preservation

The private directory contains the original uploaded workout source files. Because preservation of historical workout sources is part of the plugin's data-retention design, include this directory in an appropriate server backup strategy.

Database backups alone do not contain the original FIT and CSV file contents.

Deactivating or updating the plugin does not intentionally remove preserved workout history or these source files.

### Troubleshooting private storage

If an import reports that the private workout source directory cannot be determined, created, initialized, or written:

1. Confirm that PHP/WordPress can determine the site's document root.
2. Confirm that the intended private directory is outside the public web root.
3. Confirm that the PHP/WordPress process has permission to create and write files in that directory.
4. If the automatically selected location is unsuitable, enter an appropriate absolute path in **Swim Training Settings → Private Workout Storage Directory**. Server administrators may instead define `SWIMLOG_PRIVATE_STORAGE_DIR` as an override.

Do not move the source directory under `public_html`, `htdocs`, the WordPress installation directory, or another publicly served directory merely to resolve a permissions problem.

## Development and Release Checks

Development and release-candidate builds are checked with WordPress Plugin Check. See [PLUGIN-CHECK.md](PLUGIN-CHECK.md) for the reviewed Plugin Check baseline and the project's handling of expected custom-table database warnings.


## External Integration API

Authenticated WordPress clients may read normalized workout data through the `swimlog/v1` REST namespace. The API is read-only and always scopes results to the currently authenticated swimmer.

Available routes are `GET /wp-json/swimlog/v1/workouts` and `GET /wp-json/swimlog/v1/workouts/{id}`. The collection supports pagination plus workout-type and date filters.

The integration API deliberately does not expose preserved source files, source paths, import records, workout notes, or open-water GPS coordinates/trackpoints. Open-water detail includes non-geographic summary analytics and splits only. WordPress authentication and the plugin's own-view capability are required.
