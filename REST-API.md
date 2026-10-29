# REST API Reference

This is the developer reference for the read-only REST API provided by **Chris's Swim Training Progress and Evaluation**. The API is intended for swimmer-owned integrations and data portability.

## Base URL and version

All routes use the WordPress REST namespace `swimlog/v1`:

```text
https://example.com/wp-json/swimlog/v1
```

All plugin REST routes are **GET-only**. There are no REST endpoints for creating, editing, rebuilding, or deleting plugin data.

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
| `course` | No | `m` or `yd`; site default if omitted; unsupported values fall back to `m`. |

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
