# Chris's Swim Training Progress and Evaluation

Chris's Swim Training Progress and Evaluation is a WordPress plugin for importing, preserving, and evaluating swimming workout history.

The plugin supports FIT and supported CSV workout sources, normalized workout/lap/length storage, personal-best evaluation, events, locations, and front-end display shortcodes. Original workout source files are preserved so normalized and derived data can be rebuilt without discarding the historical source.

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

Some hosting environments do not permit WordPress to create or write to a directory adjacent to the site's document root. Administrators can explicitly configure another private storage location by defining `SWIMLOG_PRIVATE_STORAGE_DIR` in `wp-config.php`.

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
4. If the automatically selected location is unsuitable, define `SWIMLOG_PRIVATE_STORAGE_DIR` in `wp-config.php` with an appropriate private path.

Do not move the source directory under `public_html`, `htdocs`, the WordPress installation directory, or another publicly served directory merely to resolve a permissions problem.

## Development and Release Checks

Development and release-candidate builds are checked with WordPress Plugin Check. See [PLUGIN-CHECK.md](PLUGIN-CHECK.md) for the reviewed Plugin Check baseline and the project's handling of expected custom-table database warnings.
