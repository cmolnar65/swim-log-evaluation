<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-land-activity.php';

/** Bounded traversal primitives; job storage, scheduling and UI are added separately. */
final class Garmin_History {
    private static function error( $code = 'cursor' ) {
        return new \WP_Error( 'swimlog_history_' . $code, __( 'Historical traversal could not advance safely. Retry or review this range.', 'chriss-swim-training-progress-evaluation' ) );
    }
    public static function range( $start, $end ) {
        if ( ! is_string( $start ) || ! is_string( $end ) ) return self::error( 'date' );
        $tz = new \DateTimeZone( 'UTC' );
        $a = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $tz );
        $b = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $tz );
        if ( ! $a || ! $b || $a->format( 'Y-m-d' ) !== $start || $b->format( 'Y-m-d' ) !== $end || $b < $a || $end > wp_date( 'Y-m-d', null, wp_timezone() ) ) return self::error( 'date' );
        return array( 'start' => $start, 'end' => $end );
    }
    public static function cursor( $start, $end ) {
        $range = self::range( $start, $end );
        if ( is_wp_error( $range ) ) return $range;
        return array( 'start' => $start, 'end' => $end, 'day' => $start, 'offset' => 0, 'seen' => array(), 'done' => false );
    }
    private static function valid( $cursor ) {
        if ( ! is_array( $cursor ) || ! isset( $cursor['start'], $cursor['end'], $cursor['day'], $cursor['offset'], $cursor['seen'], $cursor['done'] ) || is_wp_error( self::range( $cursor['start'], $cursor['end'] ) ) || is_wp_error( self::range( $cursor['day'], $cursor['end'] ) ) ) return false;
        return $cursor['day'] >= $cursor['start'] && is_int( $cursor['offset'] ) && $cursor['offset'] >= 0 && $cursor['offset'] <= 100000 && is_array( $cursor['seen'] ) && count( $cursor['seen'] ) <= 100000 && is_bool( $cursor['done'] );
    }
    private static function authorized( $owner ) {
        return is_int( $owner ) && $owner > 0 && $owner === (int) get_current_user_id() && is_user_logged_in() && current_user_can( 'swimlog_manage_own_workouts' );
    }
    private static function next_day( $cursor ) {
        if ( $cursor['day'] === $cursor['end'] ) $cursor['done'] = true;
        else $cursor['day'] = ( new \DateTimeImmutable( $cursor['day'], new \DateTimeZone( 'UTC' ) ) )->modify( '+1 day' )->format( 'Y-m-d' );
        $cursor['offset'] = 0; $cursor['seen'] = array();
        return $cursor;
    }
    /** One raw page of one inclusive source-calendar day. Empty pages prove exhaustion. */
    public static function activity_step( $owner, $cursor, $types ) {
        if ( ! self::authorized( $owner ) ) return self::error( 'forbidden' );
        if ( ! self::valid( $cursor ) || ! is_array( $types ) || ! $types || count( $types ) > 4 ) return self::error();
        foreach ( $types as $type ) if ( ! is_string( $type ) || ! in_array( $type, Land_Activity::garmin_types(), true ) ) return self::error();
        if ( $cursor['done'] ) return array( 'cursor' => $cursor, 'activities' => array(), 'raw_count' => 0 );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
        $rows = Garmin_Personal_API::historical_activity_page( $owner, $cursor['day'], $cursor['offset'] );
        if ( is_wp_error( $rows ) ) return $rows; // Caller retains original cursor.
        if ( ! $rows ) return array( 'cursor' => self::next_day( $cursor ), 'activities' => array(), 'raw_count' => 0 );
        $items = array(); $new = 0;
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) return self::error( 'format' );
            $id = $row['activityId'] ?? null;
            $type = $row['activityType']['typeKey'] ?? null;
            $local = $row['startTimeLocal'] ?? null;
            if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || strlen( (string) $id ) > strlen( (string) PHP_INT_MAX ) || ( strlen( (string) $id ) === strlen( (string) PHP_INT_MAX ) && strcmp( (string) $id, (string) PHP_INT_MAX ) > 0 ) || ! is_string( $type ) || ! is_string( $local ) ) return self::error( 'format' );
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
            $local = Health_Measurement_Store::timestamp( str_replace( ' ', 'T', $local ), false );
            if ( is_wp_error( $local ) ) return self::error( 'date' );
            $date = substr( $local, 0, 10 );
            // Do not quietly accept uncertain provider date-filter boundary semantics.
            if ( $date !== $cursor['day'] ) return self::error( 'boundary' );
            $key = 'id_' . $id;
            if ( isset( $cursor['seen'][$key] ) ) continue;
            $cursor['seen'][$key] = true; ++$new;
            if ( in_array( $type, $types, true ) ) $items[] = array( 'activity_id' => (int) $id, 'type' => $type, 'date' => $date );
        }
        if ( ! $new || $cursor['offset'] + count( $rows ) > 100000 ) return self::error( 'stalled' );
        $cursor['offset'] += count( $rows ); // Raw count, including non-swims, never filtered count.
        return array( 'cursor' => $cursor, 'activities' => $items, 'raw_count' => count( $rows ) );
    }
    /** One day, preserving failed/unattempted work for retry. No overall seven-day limit. */
    public static function measurement_step( $owner, $cursor, $categories ) {
        if ( ! self::authorized( $owner ) ) return self::error( 'forbidden' );
        if ( ! self::valid( $cursor ) ) return self::error();
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
        if ( is_wp_error( Garmin_Measurement_Import::range( $cursor['day'], $cursor['day'], $categories ) ) ) return self::error( 'category' );
        if ( $cursor['done'] ) return array( 'cursor' => $cursor, 'results' => array(), 'retry' => false );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
        $rows = Garmin_Measurement_Import::run( $owner, $cursor['day'], $cursor['day'], $categories );
        if ( is_wp_error( $rows ) ) return $rows;
        $retry = false;
        foreach ( $rows as $row ) if ( in_array( $row['status'], array( 'failed', 'not_attempted' ), true ) ) $retry = true;
        return array( 'cursor' => $retry ? $cursor : self::next_day( $cursor ), 'results' => $rows, 'retry' => $retry );
    }
    /** Current-owner historical execution; the generic importer's rules remain authoritative. */
    public static function import_activity( $owner, $id, $type ) {
        if ( ! self::authorized( $owner ) || ! current_user_can( 'swimlog_upload_workouts' ) ) return self::error( 'forbidden' );
        if ( ! is_int( $id ) || $id < 1 || ! in_array( $type, Land_Activity::garmin_types(), true ) ) return self::error();
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
        return Garmin_Personal_API::import_activity( $owner, $id, 0, 'open_water_swimming' === $type );
    }
}

