<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Recorded land activities; imports remain gated until calendar/public isolation is complete. */
final class Land_Activity {
    public static function is_land( $type ) { return in_array( $type, array( 'running', 'walking' ), true ); }
    public static function enabled() { return function_exists( 'apply_filters' ) && true === apply_filters( 'swimlog_land_activity_imports_enabled', false ); }
    public static function garmin_types() {
        return self::enabled() ? array( 'lap_swimming', 'open_water_swimming', 'running', 'walking' ) : array( 'lap_swimming', 'open_water_swimming' );
    }
    public static function labels() {
        return array( 'running' => __( 'Running', 'chriss-swim-training-progress-evaluation' ), 'walking' => __( 'Walking', 'chriss-swim-training-progress-evaluation' ) );
    }
    public static function validate( $parsed ) {
        $w = $parsed['workout'];
        if ( ! self::enabled() ) return new \WP_Error( 'swimlog_land_pending', __( 'Running and walking imports are not enabled until calendar and swimming-isolation work is complete.', 'chriss-swim-training-progress-evaluation' ) );
        if ( 'fit' !== ( $parsed['source'] ?? '' ) || ! self::is_land( $w['workout_type'] ?? '' ) || ! in_array( $w['activity_environment'] ?? null, array( null, 'indoor', 'outdoor' ), true ) || ! empty( $parsed['lengths'] ) ) return self::error();
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $w['workout_start'] ?? '', new \DateTimeZone( 'UTC' ) );
        if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $w['workout_start'] ) return self::error();
        foreach ( array( 'total_distance_m', 'elapsed_time_ms', 'moving_time_ms' ) as $field ) {
            if ( isset( $w[$field] ) && ( ! is_numeric( $w[$field] ) || ! is_finite( (float) $w[$field] ) || $w[$field] < 0 ) ) return self::error();
        }
        if ( isset( $w['elapsed_time_ms'], $w['moving_time_ms'] ) && $w['moving_time_ms'] > $w['elapsed_time_ms'] ) return self::error();
        foreach ( array( 'pool_length_m', 'original_pool_length', 'pool_length_unit', 'primary_stroke', 'avg_swolf', 'equivalent_steps' ) as $field ) if ( isset( $w[$field] ) ) return self::error();
        return true;
    }
    private static function error() { return new \WP_Error( 'swimlog_land_format', __( 'The recorded running or walking activity has invalid summary fields.', 'chriss-swim-training-progress-evaluation' ) ); }
}
