<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Conservative adapters and bounded manual import; no scheduled sync. */
final class Garmin_Measurement_Import {
    private static function error() {
        return new \WP_Error( 'swimlog_health_import', __( 'Choose supported measurement categories and valid dates spanning at most seven days, ending no later than today.', 'chriss-swim-training-progress-evaluation' ) );
    }

    public static function range( $start, $end, $categories ) {
        if ( ! is_string( $start ) || ! is_string( $end ) || ! is_array( $categories ) || ! $categories || count( $categories ) > 3 ) return self::error();
        foreach ( $categories as $category ) if ( ! is_string( $category ) || ! in_array( $category, array( 'weight', 'body_composition', 'spo2' ), true ) ) return self::error();
        $tz = new \DateTimeZone( 'UTC' );
        $a = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $tz );
        $b = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $tz );
        if ( ! $a || ! $b || $a->format( 'Y-m-d' ) !== $start || $b->format( 'Y-m-d' ) !== $end || $b < $a || $a->diff( $b )->days > 6 || $end > wp_date( 'Y-m-d', null, wp_timezone() ) ) return self::error();
        $days = array();
        for ( $d = $a; $d <= $b; $d = $d->modify( '+1 day' ) ) $days[] = $d->format( 'Y-m-d' );
        return $days;
    }

    /** Only timestamps whose offset/GMT semantics are explicitly known are accepted. */
    public static function source_utc( $value, $gmt_without_offset = false ) {
        if ( ! is_string( $value ) ) return self::error();
        if ( $gmt_without_offset && preg_match( '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?$/D', $value ) ) $value .= 'Z';
        if ( ! preg_match( '/^(\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?)(Z|[+-]\\d{2}:?\\d{2})$/D', $value, $match ) ) return self::error();
        $base = Health_Measurement_Store::timestamp( $match[1] . 'Z' );
        if ( is_wp_error( $base ) ) return $base;
        if ( 'Z' !== $match[2] ) {
            $offset = str_replace( ':', '', $match[2] );
            $hours = (int) substr( $offset, 1, 2 );
            $minutes = (int) substr( $offset, 3, 2 );
            if ( $hours > 14 || $minutes > 59 || ( 14 === $hours && 0 !== $minutes ) ) return self::error();
        }
        try {
            $date = new \DateTimeImmutable( $value );
            return Health_Measurement_Store::timestamp( $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\\TH:i:s.u\\Z' ) );
        } catch ( \Exception $exception ) {
            return self::error();
        }
    }

    private static function number( $value, $percent = false ) {
        return ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) && is_numeric( $value ) && is_finite( (float) $value ) && (float) $value > 0 && ( ! $percent || (float) $value <= 100 );
    }

    /** Pure schema adapter. Unsupported fields are counted, never assigned guessed units. */
    public static function normalize( $category, $day, $data, $fetched ) {
        if ( ! in_array( $category, array( 'weight', 'body_composition', 'spo2' ), true ) || ! is_array( $data ) ) return self::error();
        $report = array( 'provider' => 'garmin', 'category' => $category, 'calendar_date' => $day, 'fetched_at_utc' => $fetched, 'summary' => array(), 'context' => array( 'normalization_version' => 'garmin-measurements-v1' ) );
        $rows = array();
        $excluded = 0;
        if ( 'spo2' === $category ) {
            if ( array() === $data ) return array( 'report' => $report, 'observations' => array(), 'excluded' => 0, 'empty' => true );
            if ( ! isset( $data['calendarDate'] ) || $day !== $data['calendarDate'] ) return self::error();
            $fields = array( 'averageSpO2' => 'average', 'lowestSpO2' => 'lowest', 'latestSpO2' => 'latest', 'avgSleepSpO2' => 'sleep_average', 'avgTomorrowSleepSpO2' => 'next_sleep_average', 'lastSevenDaysAvgSpO2' => 'seven_day_average' );
            foreach ( $fields as $source => $target ) {
                if ( ! isset( $data[ $source ] ) ) continue;
                if ( ! self::number( $data[ $source ], true ) ) { ++$excluded; continue; }
                $report['summary'][ $target ] = array( 'value' => $data[ $source ], 'unit' => '%' );
            }
            $times = array( 'startTimestampGMT' => 'window_start_utc', 'endTimestampGMT' => 'window_end_utc', 'sleepStartTimestampGMT' => 'sleep_start_utc', 'sleepEndTimestampGMT' => 'sleep_end_utc', 'tomorrowSleepStartTimestampGMT' => 'next_sleep_start_utc', 'tomorrowSleepEndTimestampGMT' => 'next_sleep_end_utc', 'latestSpO2TimestampGMT' => 'latest_time_utc' );
            foreach ( $times as $source => $target ) {
                if ( ! isset( $data[ $source ] ) ) continue;
                $time = self::source_utc( $data[ $source ], true );
                if ( is_wp_error( $time ) ) { ++$excluded; continue; }
                $report['context'][ $target ] = $time;
            }
            $local_times = array( 'startTimestampLocal' => 'window_start_local', 'endTimestampLocal' => 'window_end_local', 'sleepStartTimestampLocal' => 'sleep_start_local', 'sleepEndTimestampLocal' => 'sleep_end_local', 'tomorrowSleepStartTimestampLocal' => 'next_sleep_start_local', 'tomorrowSleepEndTimestampLocal' => 'next_sleep_end_local', 'latestSpO2TimestampLocal' => 'latest_time_local' );
            foreach ( $local_times as $source => $target ) {
                if ( ! isset( $data[ $source ] ) ) continue;
                $time = Health_Measurement_Store::timestamp( $data[ $source ], false );
                if ( is_wp_error( $time ) ) { ++$excluded; continue; }
                $report['context'][ $target ] = $time;
            }
            // Raw descriptor keys/epoch units still lack a verified contract. No guessed samples.
            foreach ( array( 'spO2SingleValues', 'spO2HourlyAverages' ) as $key ) {
                if ( isset( $data[ $key ] ) ) $excluded += is_array( $data[ $key ] ) ? count( $data[ $key ] ) : 1;
            }
        } else {
            if ( $data && array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) return self::error();
            if ( count( $data ) > 1000 ) return self::error();
            foreach ( $data as $entry ) {
                if ( ! is_array( $entry ) || ! isset( $entry['calendarDate'] ) || $entry['calendarDate'] !== $day ) { ++$excluded; continue; }
                $utc = self::source_utc( $entry['timestampGMT'] ?? '', true );
                if ( is_wp_error( $utc ) ) { ++$excluded; continue; }
                $values = array();
                if ( 'weight' === $category && isset( $entry['weight'] ) && self::number( $entry['weight'] ) ) $values['weight'] = array( 'value' => $entry['weight'], 'unit' => 'kg' );
                if ( 'body_composition' === $category ) {
                    if ( isset( $entry['bmi'] ) && self::number( $entry['bmi'] ) ) $values['bmi'] = array( 'value' => $entry['bmi'], 'unit' => 'kg/m2' );
                    // BMI is dimensionally defined; the other entry units remain unverified.
                    foreach ( array( 'bodyFat', 'bodyWater', 'boneMass', 'muscleMass', 'visceralFat', 'physiqueRating', 'metabolicAge' ) as $field ) if ( isset( $entry[ $field ] ) ) ++$excluded;
                }
                if ( ! $values ) { ++$excluded; continue; }
                $row = array( 'role' => 'sample', 'observed_at_utc' => $utc, 'calendar_date' => $day, 'date_basis' => 'provider_calendar', 'values' => $values, 'provenance' => array( 'normalization_version' => 'garmin-measurements-v1' ) );
                if ( isset( $entry['date'] ) ) {
                    $local = Health_Measurement_Store::timestamp( $entry['date'], false );
                    if ( ! is_wp_error( $local ) ) $row['source_local_time'] = $local;
                    else ++$excluded;
                }
                $normalized = Health_Measurement_Store::normalize_observation( $category, $row );
                if ( is_wp_error( $normalized ) ) { ++$excluded; continue; }
                $rows[] = $row;
            }
        }
        return array( 'report' => $report, 'observations' => $rows, 'excluded' => $excluded, 'empty' => ! $rows && ! $report['summary'] );
    }

    /** Bound each submission; incomplete ranges can safely be retried without duplicate rows. */
    public static function run( $owner, $start, $end, $categories ) {
        if ( ! is_int( $owner ) || $owner !== (int) get_current_user_id() || ! is_user_logged_in() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_health_forbidden', __( 'You cannot import these measurements.', 'chriss-swim-training-progress-evaluation' ) );
        $days = self::range( $start, $end, $categories );
        if ( is_wp_error( $days ) ) return $days;
        $categories = array_values( array_unique( $categories ) );
        $results = array();
        $started = microtime( true );
        $stop = false;
        foreach ( $days as $day ) {
            $responses = array();
            foreach ( $categories as $category ) {
                $result = array( 'date' => $day, 'category' => $category, 'status' => 'not_attempted', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'excluded' => 0, 'reports_added' => 0, 'reports_updated' => 0, 'reports_skipped' => 0 );
                if ( $stop || microtime( true ) - $started >= 20 ) { $results[] = $result; continue; }
                $kind = 'spo2' === $category ? 'spo2' : 'weight';
                if ( ! array_key_exists( $kind, $responses ) ) $responses[ $kind ] = Garmin_Personal_API::measurement_day( $owner, $kind, $day );
                $data = $responses[ $kind ];
                if ( is_wp_error( $data ) ) {
                    $result['status'] = 'failed';
                    $result['failed'] = 1;
                    $result['message'] = $data->get_error_message();
                    if ( 'swimlog_health_rate_limit' === $data->get_error_code() ) {
                        $info = $data->get_error_data();
                        $result['retry_after'] = (int) ( $info['retry_after'] ?? 60 );
                        $stop = true;
                    }
                    $results[] = $result;
                    continue;
                }
                $bundle = self::normalize( $category, $day, $data, gmdate( 'Y-m-d\\TH:i:s\\Z' ) );
                if ( is_wp_error( $bundle ) ) {
                    $result['status'] = 'failed'; $result['failed'] = 1;
                    $result['message'] = __( 'The response did not match the verified measurement format. Existing records were preserved.', 'chriss-swim-training-progress-evaluation' );
                } else {
                    $result['excluded'] = $bundle['excluded'];
                    if ( $bundle['empty'] ) $result['status'] = $bundle['excluded'] ? 'unsupported_data' : 'no_data';
                    else {
                        $saved = Health_Measurement_Store::save_report( $owner, $bundle['report'], $bundle['observations'] );
                        if ( is_wp_error( $saved ) ) { $result['status'] = 'failed'; $result['failed'] = 1; $result['message'] = $saved->get_error_message(); }
                        else {
                            $result['status'] = 'complete';
                            foreach ( array( 'added', 'updated', 'skipped', 'conflicts' ) as $key ) $result[ $key ] = $saved['counts'][ $key ];
                            $result[ 'reports_' . $saved['report_status'] ] = 1;
                        }
                    }
                }
                $results[] = $result;
            }
        }
        return $results;
    }
}
