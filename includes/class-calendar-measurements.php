<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Private calendar projection; never exposes storage identities or raw provenance. */
final class Calendar_Measurements {
    public static function authorized( $owner ) {
        return is_int( $owner ) && $owner > 0 && is_user_logged_in() && $owner === (int) get_current_user_id()
            && current_user_can( 'swimlog_view_own_results' ) && current_user_can( 'swimlog_manage_own_workouts' );
    }

    public static function range( $start, $end ) {
        if ( ! is_string( $start ) || ! is_string( $end ) ) return self::error( 'date', 400 );
        $tz = new \DateTimeZone( 'UTC' );
        $a = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $tz );
        $b = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $tz );
        if ( ! $a || ! $b || $a->format( 'Y-m-d' ) !== $start || $b->format( 'Y-m-d' ) !== $end || $b < $a || $a->diff( $b )->days > 61 ) return self::error( 'date', 400 );
        return array( 'start' => $start, 'end' => $end, 'end_exclusive' => $b->modify( '+1 day' )->format( 'Y-m-d' ) );
    }

    private static function error( $code, $status = 403 ) {
        return new \WP_Error( 'swimlog_health_calendar_' . $code,
            'date' === $code ? __( 'Choose valid calendar dates spanning no more than 62 inclusive days.', 'chriss-swim-training-progress-evaluation' ) :
            ( 'dense' === $code ? __( 'This measurement range is too large. Choose fewer days.', 'chriss-swim-training-progress-evaluation' ) :
            __( 'Private calendar measurements are unavailable.', 'chriss-swim-training-progress-evaluation' ) ),
            array( 'status' => $status ) );
    }

    private static function envelope( $json, $key ) {
        if ( ! is_string( $json ) || strlen( $json ) > 16384 ) return null;
        $value = json_decode( $json, true, 8 );
        return is_array( $value ) && isset( $value['version'] ) && 1 === $value['version'] && isset( $value[ $key ] ) && is_array( $value[ $key ] ) ? $value[ $key ] : null;
    }

    private static function day( &$days, $date ) {
        if ( ! isset( $days[ $date ] ) ) $days[ $date ] = array( 'date' => $date, 'reports' => array(), 'readings' => array(), 'aggregates' => array() );
    }

    /** Indexed half-open queries; no date inference or averaging at retrieval time. */
    public static function for_user( $owner, $start, $end ) {
        global $wpdb;
        if ( ! self::authorized( $owner ) ) return self::error( 'forbidden' );
        $range = self::range( $start, $end );
        if ( is_wp_error( $range ) ) return $range;
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
        $reports_table = Database::table( 'health_reports' );
        $measurements_table = Database::table( 'health_measurements' );
        $reports = Database::read( 'get_results', $wpdb->prepare(
            'SELECT category,provider,provider_date,summary_json,context_json,fetched_at_utc FROM %i WHERE user_id = %d AND provider_date >= %s AND provider_date < %s ORDER BY provider_date,category,id LIMIT 501',
            $reports_table, $owner, $range['start'], $range['end_exclusive']
        ) );
        if ( ! empty( $wpdb->last_error ) ) return self::error( 'read', 500 );
        $rows = Database::read( 'get_results', $wpdb->prepare(
            'SELECT m.*, EXISTS (SELECT 1 FROM %i p WHERE p.user_id = %d AND p.provider = m.provider AND p.category = m.category AND p.record_role = m.record_role AND p.observed_at_utc = m.observed_at_utc AND p.id != m.id) AS has_conflict FROM %i m WHERE m.user_id = %d AND m.calendar_date >= %s AND m.calendar_date < %s ORDER BY m.calendar_date,m.observed_at_utc,m.id LIMIT 2001',
            $measurements_table, $owner, $measurements_table, $owner, $range['start'], $range['end_exclusive']
        ) );
        if ( ! empty( $wpdb->last_error ) ) return self::error( 'read', 500 );
        if ( count( (array) $reports ) > 500 || count( (array) $rows ) > 2000 ) return self::error( 'dense', 413 );
        $days = array();
        $invalid = 0;
        foreach ( (array) $reports as $report ) {
            $source = self::envelope( $report->summary_json, 'values' );
            $values = null !== $source ? Health_Measurement_Store::normalize_values( $report->category, $source, true ) : null;
            if ( 'garmin' !== $report->provider || null === $values || is_wp_error( $values ) ) { ++$invalid; continue; }
            if ( ! $values ) continue; // Import bookkeeping for weight is not a numeric daily summary.
            $window = array();
            $context = self::envelope( $report->context_json, 'context' );
            foreach ( array( 'window_start_utc', 'window_end_utc', 'window_start_local', 'window_end_local', 'sleep_start_utc', 'sleep_end_utc', 'sleep_start_local', 'sleep_end_local', 'next_sleep_start_utc', 'next_sleep_end_utc', 'next_sleep_start_local', 'next_sleep_end_local', 'latest_time_utc', 'latest_time_local' ) as $key ) {
                if ( ! isset( $context[ $key ] ) ) continue;
                $time = Health_Measurement_Store::timestamp( $context[ $key ], false !== strpos( $key, '_utc' ) );
                if ( ! is_wp_error( $time ) ) $window[ $key ] = $time;
            }
            self::day( $days, $report->provider_date );
            $days[ $report->provider_date ]['reports'][] = array( 'category' => $report->category, 'source' => 'Garmin', 'role' => 'provider_report', 'values' => $values, 'window' => $window );
        }
        foreach ( (array) $rows as $row ) {
            $source = self::envelope( $row->values_json, 'values' );
            $values = null !== $source ? Health_Measurement_Store::normalize_values( $row->category, $source ) : null;
            if ( 'garmin' !== $row->provider || ! in_array( $row->record_role, array( 'sample', 'hourly_average', 'sleep_average', 'calculated_mean' ), true ) || ( 'spo2' !== $row->category && 'sample' !== $row->record_role ) || null === $values || is_wp_error( $values ) || ! $values ) { ++$invalid; continue; }
            $utc = null === $row->observed_at_utc ? null : Health_Measurement_Store::timestamp( $row->observed_at_utc );
            $local = null === $row->source_local_time ? null : Health_Measurement_Store::timestamp( $row->source_local_time, false );
            if ( is_wp_error( $utc ) || is_wp_error( $local ) || ! in_array( $row->date_basis, array( 'provider_calendar', 'source_local', 'site_timezone' ), true ) ) { ++$invalid; continue; }
            if ( null !== $row->source_offset_seconds ) {
                $offset = (int) $row->source_offset_seconds;
                if ( abs( $offset ) > 50400 || null === $utc || null === $local || ( new \DateTimeImmutable( $utc ) )->modify( ( $offset >= 0 ? '+' : '' ) . $offset . ' seconds' )->format( 'Y-m-d H:i:s.u' ) !== ( new \DateTimeImmutable( $local, new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s.u' ) ) { ++$invalid; continue; }
            }
            $item = array( 'id' => (int) $row->id, 'category' => $row->category, 'source' => 'Garmin', 'role' => $row->record_role,
                'values' => $values, 'time_utc' => $utc, 'source_local_time' => $local,
                'source_offset_seconds' => null !== $row->source_offset_seconds ? (int) $row->source_offset_seconds : null,
                'date_basis' => $row->date_basis, 'has_conflict' => (bool) $row->has_conflict );
            if ( 'calculated_mean' === $row->record_role ) {
                $provenance = self::envelope( $row->provenance_json, 'source' );
                if ( ! isset( $provenance['method'] ) || 'arithmetic_mean_v1' !== $provenance['method'] ) { ++$invalid; continue; }
                $item['method'] = 'arithmetic_mean_v1';
            }
            self::day( $days, $row->calendar_date );
            $bucket = 'sample' === $row->record_role ? 'readings' : 'aggregates';
            $days[ $row->calendar_date ][ $bucket ][] = $item;
        }
        ksort( $days );
        return array( 'range' => array( 'start' => $start, 'end' => $end, 'date_policy' => 'stored_source_calendar_date' ), 'days' => array_values( $days ), 'invalid_records' => $invalid );
    }
}
