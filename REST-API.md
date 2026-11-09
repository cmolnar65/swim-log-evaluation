# REST API Reference

This is the developer reference for the owner-scoped REST API provided by **Chris's Swim Training Progress and Evaluation**. Existing data-portability routes remain read-only; the single Health Connect batch receiver is a restricted write route that creates only private supported records.

## Base URL and version

All routes use the WordPress REST namespace `swimlog/v1`:

```text
https://example.com/wp-json/swimlog/v1
```

The existing **GET-only read routes** are unchanged. The Android Health Connect app uses a browser-based authorization flow and one owner-scoped **POST** import receiver. The receiver only creates private, supported records; it does not provide general workout editing or deletion.

## Authorization

External/data-portability routes require three checks:

1. WordPress authenticates the request as a user.
2. That user has the `swimlog_view_own_results` capability.
3. That swimmer has explicitly enabled **REST API Access** under **Swim Training → Swimmer Configuration**.

REST API Access defaults to OFF and is independent of **Public Results** and **Public Open-Water Routes**. Those external routes derive ownership from `get_current_user_id()`.

`GET /calendar` is an internal authenticated calendar-data route and deliberately uses a separate permission callback. A swimmer with `swimlog_view_own_results` can read their own calendar without enabling REST API Access. A different swimmer ID is accepted only for a user with `swimlog_manage_all_users`. Public Results, Public Open-Water Routes, and REST API Access do not make another swimmer's calendar accessible.

### Application Password example

For an external application, use a WordPress Application Password over HTTPS:

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/workouts"
```

Do not store Application Passwords in source control, client-side JavaScript, public configuration, logs, or documentation. Never transmit them over unencrypted HTTP.

## Common conventions

- Fields ending in `_ms` are milliseconds.
- `distance_m` and `total_distance_m` are normalized meters.
- Course units use `m` or `yd`.
- Stroke codes are `FR`, `BR`, `BACK`, `FLY`, `MIXED`, and where applicable `UNKNOWN`.
- Optional source measurements can be `null`; missing measurements are not manufactured.
- The API deliberately excludes workout notes, preserved source files/paths, original uploaded filenames, import records, open-water coordinates, and GPS trackpoints.

## Health Connect Android authorization

The Android app uses WordPress sign-in in the device browser; it never collects or receives the WordPress password. The browser consent page is:

```text
https://example.com/wp-admin/admin.php?page=swimlog-health-connect-authorize
```

The app adds `response_type=code`, `client_id=swimlog_android`, `redirect_uri=com.cmolnar65.swimlog:/oauth2redirect`, `code_challenge=<base64url-SHA256-of-verifier>`, `code_challenge_method=S256`, and a random `state`. This is an OAuth authorization-code flow protected by PKCE S256. WordPress requires an authenticated user with `swimlog_manage_own_workouts`, displays the data scope, and issues a code only after the user selects **Authorize Swim Log Android**. **Cancel** returns `error=access_denied` and the same state. The app must verify state before continuing. The consent POST is processed by the authenticated `admin-post.php` action before WordPress outputs admin-page markup. The response attempts to open the custom-scheme callback automatically and shows a **Return to Swim Log Android** link if the browser does not hand off automatically. The owner comes from the WordPress login session; clients cannot supply an owner ID. The redirect URI and client ID are fixed; no client secret is used.

The authorization code is high-entropy, expires after five minutes, is stored only as a hash, and is atomically consumed once. The app exchanges it immediately over HTTPS:

```text
POST /wp-json/swimlog/v1/health-connect/token
Content-Type: application/json
```

Request fields are `grant_type` (`authorization_code`), `client_id`, the exact `redirect_uri`, the returned `code`, and the original `code_verifier` (43–128 RFC 7636 unreserved characters). Extra fields, including `owner_id`, are rejected. The server checks S256, code expiry, client and redirect binding, and one-time use, then returns `access_token` and `token_type` in a private no-store response. The token is the same owner-scoped token accepted by the import-batch route; the server keeps only its keyed hash. Only one token is active per owner, so a successful authorization replaces the previous token. Revoke it under **Swim Training → Swimmer Configuration → Health Connect App Sync**. The existing manual generate/revoke controls remain available.

The token endpoint accepts only HTTPS requests. Invalid, expired, replayed, or mismatched codes return 401; non-HTTPS requests return 403. Never log the authorization code, verifier, token, WordPress cookies, or Health Connect values.

## Health Connect app receiver

`POST /health-connect/import-batch` accepts batches from the Swim Log Android app. It uses a dedicated owner-scoped bearer token, separate from WordPress Application Passwords and from read API access. Generate or revoke the token in **Swim Training → Swimmer Configuration → Health Connect App Sync**. The plaintext one-time token is shown once. The server stores only a keyed token hash. Send it only over HTTPS in the `Authorization: Bearer …` header; the route rejects non-HTTPS requests.

The token can write only to the swimmer who generated it. The request has no owner ID, and an owner ID in the payload is rejected. Records are private by default; Health Connect measurements are accessible only through the owner's existing private calendar measurement view. The receiver never returns measurement values or heart-rate sample values in its response.

The contract is version 1, with a 2 MiB maximum body, at most 50 workouts, and at most 500 measurement observations. Each workout supports up to 2,000 heart-rate samples, 1,000 laps, 1,000 segments, 5,000 route points, and up to 2,000 samples per bounded metric series. Unsupported records are rejected individually; successful records in the same batch remain successful. The Android app sends one workout per request and can retry it safely.

```json
{
  "contract_version": 1,
  "batch_id": "batch-20261103-0001",
  "workouts": [{
    "record_id": "stable-source-record-id",
    "origin": "com.example.healthprovider",
    "activity": "pool_swimming",
    "start_time": "2026-11-03T07:00:00-07:00",
    "end_time": "2026-11-03T07:45:00-07:00",
    "distance_m": 1800,
    "pool_length_value": 25,
    "pool_length_unit": "m",
    "heart_rate_samples": [{"record_id":"sample-1","origin":"com.example.healthprovider","time":"2026-11-03T07:01:00-07:00","bpm":120}]
  }],
  "measurements": [{
    "record_id": "stable-source-measurement-id",
    "origin": "com.example.healthprovider",
    "category": "weight",
    "time": "2026-11-03T06:50:00-07:00",
    "values": {"weight":{"value":"72.5","unit":"kg"}}
  }]
}
```

Workout `activity` must be exactly `pool_swimming`, `open_water_swimming`, `running`, `running_treadmill`, `walking`, or `cycling`. These are explicit classifications; missing GPS never changes them. Treadmill running and stationary cycling are stored as indoor; generic cycling does not assume an environment. Swimming, running, walking, and cycling remain separate activity totals. Distance is meters. Pool length carries its original `m` or `yd` value/unit and is normalized to meters for storage; absent course information stays unclassified. Optional `details` contains validated lap intervals and segments (including recognized segment labels), speed samples/summary, total and active energy in kcal, elevation gain in meters, floors climbed, step-cadence samples, cycling-cadence samples in rpm, power samples in watts, and GPS route points with optional altitude/accuracy. All time intervals must be inside the session. Metric samples are attached only when their source and time identify one supported workout. Invalid coordinates, excessive counts, or out-of-range values reject that workout. The receiver includes optional details in the source content hash, so changed details under the same record identity conflict instead of overwriting an existing import. Details are stored only in the owner's private Health Connect activity row and shown on the owner's private activity page; the importer does not create a public post or public route. Android requests route access in the foreground through Health Connect's route consent contract; background imports omit routes that require consent. Heart-rate samples must have the same data origin as their workout and lie within its session interval. The receiver does not invent average or maximum heart-rate summaries from samples.

Measurements accept `weight` (`weight` in kg), `body_composition` (`weight` in kg, `body_fat` in %, `lean_body_mass` in kg), and `blood_pressure` (`systolic` and `diastolic` in mmHg). Timestamps must include `Z` or an explicit UTC offset, with at most microsecond precision. The source local date and offset are retained with UTC time. Unknown measurement types, fields, or units are rejected; no medical interpretation or conflicting-record merge is performed.

The source record ID is never stored in plaintext. Per-owner source-key hashes prevent same-source duplicates; a repeat with identical content is reported as `duplicate`, while a changed record under the same source key is a conflict. Cross-source workout matches are retained and marked as possible duplicates. Measurement records are also retained; Health Connect readings are flagged as possible cross-provider duplicates instead of replacing another source.

Successful HTTP 200 responses contain `contract_version`, the echoed `batch_id`, and per-record status/code entries, never health or sample values. Per-record `duplicate` means an identical prior import, `conflict` means changed content under a known identity and requires review, `retry` means transient storage contention, and `rejected` means invalid/unsupported data. Retry only `retry` items; do not retry a conflict as a new identity. Top-level errors use HTTP 400 for malformed contracts, 401 for a missing/invalid token, 403 for non-HTTPS, and 413 for size/count limits. Do not log request bodies, bearer headers, or health values.

## GET /workouts

Lists the authenticated swimmer's workouts, newest first.

| Parameter | Required | Values/default | Description |
| --- | --- | --- | --- |
| `page` | No | integer ≥1; default 1 | Results page. |
| `per_page` | No | 1–100; default 20 | Workouts per page; clamped to range. |
| `workout_type` | No | `pool`, `open_water` | Workout-type filter. |
| `date_from` | No | normally `YYYY-MM-DD` | Local workout date on/after value. |
| `date_to` | No | normally `YYYY-MM-DD` | Local workout date on/before value. |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/workouts?workout_type=pool&date_from=2026-01-01&per_page=10&page=1"
```

Response fields:

- `workouts[]`: workout rows.
- `page`, `per_page`, `total`, `pages`: pagination.
- Each workout row: `id`, `workout_type`, `source_type`, `workout_start`, `workout_end`, `location`, `total_distance_m`, `original_distance`, `original_distance_unit`, `elapsed_time_ms`, `moving_time_ms`, `pool_length_m`, `original_pool_length`, `pool_length_unit`, `primary_stroke`, `average_heart_rate`, `max_heart_rate`, `average_cadence`, `max_cadence`, `device_manufacturer`, `device_model`.

Example shape:

```json
{
  "workouts": [{"id":341,"workout_type":"pool","source_type":"fit","workout_start":"2026-10-20 07:00:00","total_distance_m":2800,"pool_length_unit":"m","primary_stroke":"BR"}],
  "page": 1,
  "per_page": 10,
  "total": 1,
  "pages": 1
}
```

## GET /workouts/{id}

Returns one workout owned by the authenticated swimmer.

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/workouts/341"
```

The response includes all workout-summary fields plus `laps`, `performances`, and either pool `lengths` or open-water `analytics`.

### laps[]

Fields: `sequence_no`, `start_time`, `start_offset_ms`, `distance_m`, `elapsed_time_ms`, `moving_time_ms`, `stroke`, `source_lap_index`.

### Pool lengths[]

Fields: `sequence_no`, `start_time`, `start_offset_ms`, `distance_m`, `elapsed_time_ms`, `stroke`, `stroke_count`, `length_type`, `source_length_index`.

`stroke_count` is the recorded per-length count when a compatible source supplies it; it is otherwise `null`. `length_type` is normally `active` or `rest`.

### performances[]

Fields: `workout_id`, `course_unit`, `distance_value`, `stroke`, `duration_ms`, `achieved_at`, `is_personal_best`.

### Open-water analytics

Fields: `elapsed_pace_100m_ms`, `moving_pace_100m_ms`, `non_moving_time_ms`, `active_percent`, `cadence_point_count`, and `splits`.

Split fields can include `distance_m`, `split_time_ms`, `cumulative_time_ms`, `moving_split_time_ms`, `moving_cumulative_time_ms`, and `moving_estimated`. GPS coordinates and trackpoint rows are never returned.

If the workout does not exist or is not owned by the authenticated swimmer:

```json
{"code":"swimlog_rest_not_found","message":"Workout not found.","data":{"status":404}}
```

## GET /personal-bests

Returns current PBs for a course.

| Parameter | Required | Values/default |
| --- | --- | --- |
| `course` | No | `m` or `yd`; the swimmer's saved default course if omitted (the site default is the initial fallback); unsupported values fall back to `m`. |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/personal-bests?course=m"
```

Response:

```json
{"course_unit":"m","personal_bests":[{"distance_value":100,"course_unit":"m","stroke":"BR","duration_ms":90000,"achieved_at":"2026-10-07 09:15:00","workout_id":341}]}
```

Each PB row contains `distance_value`, `course_unit`, `stroke`, `duration_ms`, `achieved_at`, and `workout_id`. An `OVERALL` stroke row can represent the fastest stored performance at a distance across strokes.

## GET /progression

Returns chronological PB progression for an exact distance/course and optional stroke.

| Parameter | Required | Values |
| --- | --- | --- |
| `distance` | Yes | positive integer in native course units |
| `course` | Yes | `m` or `yd` |
| `stroke` | No | `FR`, `BR`, `BACK`, `FLY`, `MIXED` |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/progression?distance=100&course=m&stroke=BR"
```

Response fields: `distance_value`, `course_unit`, `stroke`, and `progression[]`. Each progression row uses the performance fields documented above.

Invalid course returns HTTP 400 with code `swimlog_rest_course`. Invalid stroke returns HTTP 400 with code `swimlog_rest_stroke`. WordPress REST validation handles missing required parameters.

## GET /events

Returns upcoming events plus descriptive Race Readiness 2.0 evidence.

| Parameter | Required | Values/default |
| --- | --- | --- |
| `limit` | No | 1–50; default 10 |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/events?limit=5"
```

Each `events[]` row contains `id`, `event_name`, `event_date`, `event_time`, `location`, `distance_value`, `course_unit`, `stroke`, and `readiness`.

The top level also returns `lookback_days` (currently 42).

The readiness object contains: `lookback_days`, `course_unit`, `stroke`, `event_distance`, `days_until_event`, `workout_count`, `length_count`, `distance_native`, `active_time_ms`, `last_workout_start`, `longest_recent_distance`, `longest_recent_duration_ms`, `longest_recent_workout_id`, `event_distance_or_longer`, `lifetime_pb_duration_ms`, `lifetime_pb_achieved_at`, `lifetime_pb_workout_id`, `recent_performance_count`, `recent_best_duration_ms`, `recent_best_achieved_at`, `recent_best_workout_id`, `recent_best_pace_100_ms`, `recent_average_pace_100_ms`, `latest_duration_ms`, `latest_achieved_at`, `latest_pace_100_ms`, `recent_change_ms`, `recent_change_percent`, `recent_best_vs_pb_ms`, `recent_best_vs_pb_percent`, and `descriptive_only`.

Race Readiness is descriptive evidence, not a readiness score, race-time prediction, fitness estimate, or recovery assessment.

## GET /calendar

Returns the private calendar model for an inclusive local date range.

| Parameter | Required | Values/default |
| --- | --- | --- |
| `start` | Yes | strict `YYYY-MM-DD` |
| `end` | Yes | strict `YYYY-MM-DD`; on/after `start` |
| `user` | No | swimmer ID; omitted means the authenticated swimmer; another swimmer requires `swimlog_manage_all_users` |

The requested range is limited to 367 inclusive days. The response contains the normalized calendar `range`, `workouts`, `events`, `weeks`, and `metric_metadata` produced by the shared Calendar service. Week boundaries honor the swimmer's configured Saturday, Sunday, or Monday first day of week.

Calendar workout data excludes notes, source/import details, device fields, coordinates, and GPS trackpoints. Public Results and Public Open-Water Routes do not grant calendar access.

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/calendar?start=2026-10-01&end=2026-10-31"
```

Invalid dates or reversed ranges return HTTP 400. Requests exceeding the range limit return code `swimlog_calendar_range_too_large`.

## GET /analytics/pool

Returns pool performance/efficiency analytics from normalized active lengths.

| Parameter | Required | Values/default |
| --- | --- | --- |
| `course` | No | `m` or `yd`; default `m` |
| `stroke` | No | `FR`, `BR`, `BACK`, `FLY`, `MIXED`, `UNKNOWN` |
| `months` | No | 1–24; default 6 |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/analytics/pool?course=m&stroke=BR&months=6"
```

Top-level fields are `filters`, `groups`, and `source_row_count`.

Each `groups[]` row contains `course_unit`, `pool_length`, `stroke`, `workout_count`, `active_lengths`, `distance_native`, `timed_lengths`, `avg_pace_100_ms`, `best_pace_100_ms`, `pace_consistency_cv`, `stroke_count_lengths`, `stroke_count_coverage_percent`, `avg_stroke_count`, `normalized_strokes_per_25`, `swolf_lengths`, `avg_swolf`, `metric_metadata`, and `months`. Monthly rows use the same metric rules.

Meters/yards, pool lengths, and strokes are kept separate. Missing timing/stroke counts are not estimated. `metric_metadata` documents units and provenance.

## GET /analytics/training-trends

Returns longitudinal training-volume trends.

| Parameter | Required | Values/default |
| --- | --- | --- |
| `months` | No | 1–24; default 6 |

```bash
curl --user "wordpress_username:APPLICATION_PASSWORD" \
  "https://example.com/wp-json/swimlog/v1/analytics/training-trends?months=6"
```

Top-level fields:

- `window`: effective current/previous period boundaries.
- `current` and `previous`: equal-period summaries.
- `comparison`: `distance_percent`, `workouts_percent`, `active_time_percent`, `frequency_percent`.
- `weekly`: weekly summaries plus `rolling_4_week_avg_distance_m` and `rolling_4_week_avg_workouts`.
- `monthly`: monthly summaries.
- `stroke_volume`: active pool-length distance/time/count by course and stroke.
- `pool_course`: active pool-length totals by course.
- `metric_metadata`: units/provenance.

Summary rows can contain `workout_count`, `distance_m`, `active_time_ms`, `pool_distance_m`, `open_water_distance_m`, `pool_workouts`, `open_water_workouts`, and `workouts_per_week`.

A percentage comparison is `null` when the previous period is zero and the current period is nonzero. Training load means recorded volume only, not physiological stress, fitness, recovery, or injury risk.

## Error handling

Exact WordPress authentication error codes/messages can depend on WordPress and the authentication mechanism. Clients should primarily handle HTTP status.

| HTTP status | Meaning |
| --- | --- |
| `400 Bad Request` | Invalid route parameter or missing required REST parameter. |
| `401 Unauthorized` | WordPress did not authenticate the request, such as an absent/invalid Application Password. |
| `403 Forbidden` | Authenticated user failed the route permission callback, such as a missing capability, REST API Access being OFF for portability routes, or attempting to read another swimmer's calendar without `swimlog_manage_all_users`. |
| `404 Not Found` | Owned workout not found/not owned, or route unavailable. |

Do not make client logic depend on a particular WordPress-generated authentication error message.

## Client guidance

1. Use HTTPS and store Application Passwords as secrets.
2. Treat HTTP status as authoritative before parsing a success payload.
3. Expect optional measurements to be `null`.
4. Preserve units; do not mix meter and yard analytics.
5. Honor `moving_estimated`, `descriptive_only`, and metric provenance.
6. Page through `/workouts` using `page`, `pages`, and `per_page`.
7. Do not infer REST GPS access from Public Open-Water Routes.
8. Treat `swimlog/v1` as the compatibility boundary for this API version.

## Route summary

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/workouts` | Paginated workout summaries. |
| GET | `/workouts/{id}` | Owned workout detail. |
| GET | `/personal-bests` | Current personal bests. |
| GET | `/progression` | PB progression. |
| GET | `/events` | Upcoming events plus Race Readiness 2.0. |
| GET | `/calendar` | Private calendar data and weekly summaries. |
| GET | `/analytics/pool` | Pool performance/efficiency analytics. |
| GET | `/analytics/training-trends` | Training-volume trends. |


## GET /calendar/measurements

Internal health-calendar projection only. This route requires the current owner's valid WordPress logged-in cookie AND an X-WP-Nonce header verified for wp_rest, plus swimlog_view_own_results and swimlog_manage_own_workouts. Application Password or other noncookie authentication alone is insufficient. No external REST opt-in or public-results setting grants access. A user parameter may only identify the current owner; swimlog_manage_all_users does not authorize another owner's health records.

Required start/end: exact YYYY-MM-DD, at most 62 inclusive days (enough for a full calendar grid). Unknown or unassigned measurement dates are not inferred from UTC. Queries use stored calendar_date/provider_date and half-open indexed boundaries. More than 500 report rows or 2,000 observations returns HTTP 413, requesting a smaller range; no silent truncation.

Response: range (start/end/date_policy), days (date, reports, readings, aggregates) and invalid_records. Provider reports expose named summary roles and verified reporting/sleep windows. Readings expose approved grouped values/units, source label, UTC/local time and verified offset, date_basis and conflict flag. Non-sample observations remain in aggregates. No averaging or clinical interpretation is performed.

Observation IDs identify only owner-authorized internal records. External measurement/account IDs, owner IDs, hashes, arbitrary provenance/context, devices and raw payloads are excluded. Unknown/malformed numeric envelopes are counted and omitted. Responses use Cache-Control: private, no-store.

This separate route does not change GET /calendar or existing portability responses. No visible measurement indicators are added until the calendar presentation phase.

## Canonical public workout reads (development)

These separate **GET-only** routes allow anonymous reads of the canonical whitelist:

- `/swimlog/v1/public/workouts?user={owner_id}&page=1&per_page=20` (`/public/workouts`): eligible workouts, `total`, `pages`, `page`, `per_page`. Optional `workout_type`, `date_from`, `date_to` filters. Counts and pagination exclude private history.
- `/swimlog/v1/public/workouts/{id}` (`/public/workouts/{id}`): a single projected workout, or 404 for private/missing/unsupported records.

Both require explicit owner Public Results and the per-workout public grant. Responses are `private, no-store`; public embeds also disable caching. Supported types are pool, open_water, running and walking. This does not open the authenticated portability or calendar/measurement routes above. No public route accepts writes.

Public fields: ID, date without start clock, type, distance/native units, elapsed/moving durations, pool course, stroke, SWOLF/equivalent swimming steps, recorded active lengths, safe splits and performance durations. Notes, photos, location labels, device/source details, files, raw metadata, health/sensor fields and stored personal-best flags are excluded. `route` is null unless the additional workout route flag and applicable explicit owner route consent permit geometry. When allowed, it contains `segments` of latitude/longitude pairs, without timestamps or sensor values. A public workout grants no management permission.

See [canonical contract](docs/public-workout-visibility.md) and [standalone implementation prompt](docs/standalone-public-visibility-handoff.md). The legacy authenticated API remains separate and excludes GPS trackpoints even when route sharing is enabled.
