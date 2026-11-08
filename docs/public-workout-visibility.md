# Canonical workout visibility (development)

This canonical visibility policy is included in plugin release **1.4**. It was introduced with additive schema **1.5** and remains unchanged in the current schema 1.7. The v1.4 release does not alter any access, privacy, route-consent, owner-management or revocation rules below.

## Policy

Public access is read-only. A supported workout is public only when its actual owner's explicit `swimlog_public_results` user metadata is `'1'` **and** its canonical state is `public` or grandfathered `legacy_public`. Site defaults, a viewer's login, administrator read privileges and a WordPress post cannot bypass this policy. New workouts default private; toggling the master permission cannot publish private history.

Supported types: `pool`, `open_water`, `running`, `walking`. Land records stay owner-private in the existing private calendar/detail/API unless an owner deliberately grants the separate canonical public projection. No land history is backfilled public. Measurements stay private and are never projected. Cycling remains unsupported/deferred; future support must deliberately integrate the same policy.

Public fields are limited to workout ID/date (no start clock), type, distance/native units, elapsed/moving durations, pool length/course, stroke, swimming SWOLF/equivalent steps, active recorded lengths, safe splits and swimming performance durations. Public PBs/progression are recomputed from eligible pool inputs; a private faster PB must not influence or suppress public results. Counts, pagination and separate sport/unit totals use eligible records only.

Notes, photos, location labels, source files/paths/IDs, import provenance, device/raw metadata, heart rate, cadence and health measurements are excluded. This does not move or duplicate workout data.

GPS geometry requires all of: public workout; explicit master Public Results; workout `route_public=true`; and applicable explicit owner route consent. New `swimlog_public_routes='1'` permits all supported sports; existing `swimlog_public_ows_routes='1'` permits only swimming. Old swimming consent never enables a land route. Public geometry contains only latitude/longitude segments; invalid points, recording/sequence gaps and backwards timestamps split routes. Timestamps and sensors are not exposed. Revocation excludes geometry without deleting the private recording.

Only the actual authenticated owner with `swimlog_manage_own_workouts` may change workout data or visibility. Manage-all grants no cross-owner management exemption. HTTP adapters must verify their specific WordPress nonce before all writes. Visibility verifies its own nonce as well. Public reads never authorize editing, photo upload/removal, rebuild, deletion, conversion, plan association/detachment, post creation or sharing changes. Existing authorized administrator private swimming reads remain available; public embeds always use public policy, even in owner/admin sessions.

Creating/publishing a post is a separate explicit action, with existing WordPress post capabilities and CSRF checks. Sharing never creates/publishes a post; a published dynamic block observes revocation. Previously copied static content and already public WordPress attachment URLs cannot be retroactively retracted by this policy. Photos are not automatically protected downloads; no new public photo URLs are emitted.

## Storage and upgrade

`{$wpdb->prefix}swimlog_workout_visibility` is an InnoDB table with:

| Column | Meaning |
|---|---|
| `workout_id` | Primary key; actual workout ID |
| `owner_id` | Actual workout owner; joins always verify against workout owner |
| `visibility` | `private`, `public`, or migration-only `legacy_public` |
| `route_public` | Independent explicit route preference, default 0 |
| `revision` | Monotonic integer for optimistic concurrency |
| `updated_at_gmt` | UTC change time |

Reserved ID 0 is an upgrade marker, never a workout. Upgrade snapshots only existing pool/open-water workouts whose owners have explicit global `'1'`, excluding conflicting global meta rows. Legacy OWS route approval is retained only with explicit old swimming route consent. Site defaults and provisional standalone preferences are not read as publication consent. Existing canonical decisions win via `INSERT IGNORE`; the marker and snapshot commit atomically. Failure rolls back and withholds schema-version advancement; reads fail closed until ready. Repeated upgrades do not resnapshot new history or undo revocation. Normal import/manual creation records explicit private state, including creation overlapping an upgrade. Original records and source files are untouched. Deletion removes canonical and provisional state; WordPress user deletion cleans owner rows.

A missing row reads private with revision 0. New explicitly private rows start at revision 1. Changes require the returned current revision; stale/replayed revisions fail with 409 and never overwrite a newer decision. Missing/unsupported/private public lookups all return 404. Public lists for disabled owners are empty. Public projections and embeds set no-cache/no-store defenses; an upstream cache that ignores WordPress cache directives must separately exclude these pages/endpoints.

`_standalone_workout_public_<id>='1'` remains a **pending request**, not a grant. Explicit owner confirmation calls `adopt_standalone_preference`; it saves public with routes off. Successful explicit canonical saves (including private) consume that key. Absence is neither consent nor a revocation of a grandfathered grant. Do not bulk adopt, infer consent from missing keys or continue writing the provisional key.

## Service contract

Class `\SwimLogEvaluation\Workout_Visibility` loads through the active plugin. WordPress must be bootstrapped; never manufacture a current owner with `wp_set_current_user` from a request parameter.

```php
Workout_Visibility::ready(); // bool; upgrade complete
Workout_Visibility::get_for_owner($workout_id); // array|WP_Error; current actual owner only
Workout_Visibility::set_for_owner($workout_id, array $changes, $nonce, $expected_revision); // array|WP_Error
Workout_Visibility::adopt_standalone_preference($workout_id, $nonce, $expected_revision); // array|WP_Error
Workout_Visibility::is_public($workout_id); // bool
Workout_Visibility::get_public($workout_id); // whitelist array|WP_Error
Workout_Visibility::query_public($owner_id, array $filters = array(), $page = 1, $per_page = 20); // array|WP_Error
Workout_Visibility::personal_bests($owner_id, $course, $age_group = null); // eligible swimming map
Workout_Visibility::progression($owner_id, $distance, $course, $stroke); // eligible swimming improvements
```

`changes` must contain exactly `visibility` (`private` or `public`) and `route_public` (PHP boolean). Private plus true route is rejected. Nonce action is `swimlog_workout_visibility_<id>`. `$expected_revision` must be a nonnegative PHP integer, not an unvalidated string. A nonce protects CSRF, not concurrency; use both nonce and revision. WordPress nonces are not one-use tokens; revision prevents stale replay.

Owner state keys: `workout_id`, `visibility`, `route_public`, `effective_public`, `effective_route_public`, `blocked_reason` (`public_results_disabled`, `workout_private`, or null), `pending_standalone_preference`, `revision`. Always show effective access separately from stored preferences. A successful public preference can remain blocked by the master permission. `effective_route_public` means permission is granted, not that usable GPS exists.

Errors: `swimlog_owner_required` 403; `swimlog_visibility_nonce` 403; `swimlog_visibility_input` 400; `swimlog_visibility_conflict` 409; `swimlog_visibility_no_preference` 409; `swimlog_visibility_upgrade` 503; public/foreign workout `swimlog_public_not_found` 404. Do not treat WP_Error as a truthy successful save.

List filters: `workout_type` (one of supported types or empty), `date_from`, `date_to` (`YYYY-MM-DD`, inclusive). Page >=1, per-page 1–100. Result: `workouts`, `total`, `pages`, `page`, `per_page`. No owner-only raw object may be serialized into public output.

New public GET routes: `/swimlog/v1/public/workouts?user=<owner>` and `/swimlog/v1/public/workouts/<id>`. Existing authenticated portability/calendar/measurement routes retain their independent authorization. There are no public mutation endpoints. Internal lifecycle helpers `created`, `migrate`, `deleted_user` are not application commands or HTTP endpoints.

## Validation

Synthetic PHPUnit renderer fixtures exercise whitelist and separate totals. `tests/workout-visibility-integration.php` exercises the real disposable WordPress schema, failed upgrade/retry, legacy consent, defaults, provisional adoption, CAS/nonces, route gates, public pagination/PBs, anonymous/cross-owner/admin management denial, REST methods, dynamic posts and deletion. Existing integrations continue to cover owner-cookie/REST-nonce measurement privacy, land reads, calendar and workflows. No private fixture, route or source data is used.
