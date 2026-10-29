<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Conservative, read-only preview followed by explicit creation of a new prescription. */
final class Recorded_Workout_Structure {
    public static function preview( $workout_id, $owner_id ) {
        if ( get_current_user_id() !== absint( $owner_id ) || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_conversion_permission', 'Permission denied.' );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
        $workout = Workout::get_for_user( absint( $workout_id ), absint( $owner_id ) );
        if ( ! $workout ) return new \WP_Error( 'swimlog_conversion_owner', 'Recorded workout not found.' );
        $converted = self::from_rows( $workout, Workout::lengths( $workout_id, $owner_id ), Workout::laps( $workout_id, $owner_id ) );
        if ( is_wp_error( $converted ) ) return $converted;
        $converted['hash'] = hash( 'sha256', wp_json_encode( array( 'id' => (int) $workout->id, 'updated_at' => $workout->updated_at, 'document' => $converted['document'], 'warnings' => $converted['warnings'] ) ) );
        $converted['title'] = 'Prescription from swim #' . (int) $workout->id;
        return $converted;
    }

    public static function from_rows( $workout, $lengths, $laps = array() ) {
        if ( 'pool' !== ( $workout->workout_type ?? 'pool' ) ) return new \WP_Error( 'swimlog_conversion_type', 'Automatic conversion currently requires a pool swim with explicit stroke and distance evidence.' );
        $unit = $workout->pool_length_unit ?? $workout->original_distance_unit ?? null;
        if ( ! in_array( $unit, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_conversion_unit', 'Recorded distance units are missing.' );
        $using_laps = ! $lengths;
        $rows = $using_laps ? $laps : $lengths;
        if ( ! $rows || count( $rows ) > 499 ) return new \WP_Error( 'swimlog_conversion_rows', 'Conversion requires 1-499 ordered lengths or laps. No totals-only sets are inferred.' );
        $children = array(); $warnings = array( $using_laps ? 'One step per recorded lap; no internal splits or repeat counts are inferred.' : 'One step per active recorded length; no set or repeat boundaries are inferred.', 'Measured times are observations in descriptions, not prescribed pace targets. Unspecified rest remains unspecified.' );
        $leading = array();
        foreach ( $rows as $row ) {
            if ( ! $using_laps && 'active' !== $row->length_type ) {
                if ( 'rest' !== $row->length_type ) return new \WP_Error( 'swimlog_conversion_length', 'An unknown length type needs manual review.' );
                $rest = null !== $row->elapsed_time_ms ? (string) ( $row->elapsed_time_ms / 1000 ) : null;
                if ( ! $children ) { $leading[] = 'Leading recorded rest: ' . ( null === $rest ? 'unspecified' : $rest . ' seconds' ); continue; }
                $last = count( $children ) - 1;
                if ( null !== $children[$last]['rest'] && null !== $rest ) $rest = (string) ( (float) $children[$last]['rest'] + (float) $rest );
                elseif ( array_key_exists( 'rest_seen', $children[$last] ) ) $rest = null;
                $children[$last]['rest'] = $rest; $children[$last]['rest_seen'] = true;
                continue;
            }
            if ( ! isset( $row->distance_m ) || ! is_numeric( $row->distance_m ) || $row->distance_m <= 0 ) return new \WP_Error( 'swimlog_conversion_distance', 'A recorded step has no reliable distance.' );
            $meta = json_decode( $row->raw_metadata ?? '', true );
            $meta = is_array( $meta ) ? $meta : array();
            $map = array( 'FR' => 'FR', 'BR' => 'BR', 'BK' => 'BK', 'BACK' => 'BK', 'FL' => 'FL', 'BU' => 'FL', 'FLY' => 'FL', 'MX' => 'MX', 'MIXED' => 'MX' );
            $stroke = $map[$row->stroke ?? ''] ?? null;
            $drill = ! empty( $meta['drill'] );
            if ( ! $using_laps && isset( $meta[7] ) ) {
                $fit_strokes = array( 0 => 'FR', 1 => 'BK', 2 => 'BR', 3 => 'FL', 4 => 'MX', 5 => 'MX' );
                $stroke = $fit_strokes[(int) $meta[7]] ?? null;
                $drill = 4 === (int) $meta[7];
            }
            if ( null === $stroke ) return new \WP_Error( 'swimlog_conversion_stroke', 'A recorded stroke is unknown or cannot be represented; create or edit the prescription manually.' );
            $equipment = isset( $meta['equipment'] ) && is_array( $meta['equipment'] ) ? $meta['equipment'] : array();
            $description = null;
            if ( null !== ( $row->elapsed_time_ms ?? null ) ) $description = 'Recorded ' . ( $using_laps ? 'lap' : 'length' ) . ' time: ' . ( $row->elapsed_time_ms / 1000 ) . ' seconds (observation, not a pace target).';
            if ( ! empty( $meta['measured_rep_time_ms'] ) ) $description = 'Recorded repetition time: ' . ( $meta['measured_rep_time_ms'] / 1000 ) . ' seconds; no per-length timing inferred.';
            $children[] = array( 'type' => 'step', 'distance' => round( $row->distance_m / ( 'yd' === $unit ? 0.9144 : 1 ), 3 ), 'stroke' => $stroke, 'pace' => null, 'rest' => null, 'description' => $description, 'drill' => $drill, 'equipment' => $equipment );
        }
        if ( ! $children ) return new \WP_Error( 'swimlog_conversion_empty', 'No active swimming evidence was found.' );
        foreach ( $children as &$child ) unset( $child['rest_seen'] );
        unset( $child );
        $document = array( 'unit' => $unit, 'sections' => array( array( 'type' => 'section', 'label' => $using_laps ? 'Recorded laps' : 'Recorded lengths', 'description' => $leading ? implode( '; ', $leading ) : null, 'children' => $children ) ) );
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        $meters = $summary['totals']['distance'] * ( 'yd' === $unit ? 0.9144 : 1 );
        if ( null !== ( $workout->total_distance_m ?? null ) && abs( $meters - (float) $workout->total_distance_m ) > max( 0.02, count( $children ) * 0.001 ) ) return new \WP_Error( 'swimlog_conversion_total', 'Recorded lengths/laps do not reconcile with the workout distance; no partial prescription was created.' );
        return array( 'document' => $summary['document'], 'warnings' => $warnings );
    }

    public static function confirm( $workout_id, $owner_id, $expected_hash, $title, $date = '' ) {
        $preview = self::preview( $workout_id, $owner_id );
        if ( is_wp_error( $preview ) ) return $preview;
        if ( ! is_string( $expected_hash ) || ! hash_equals( $preview['hash'], $expected_hash ) ) return new \WP_Error( 'swimlog_conversion_stale', 'Recorded workout changed. Preview again before confirming.' );
        $instructions = Structured_Workout_Export::text( $title, $preview['document'] );
        if ( is_wp_error( $instructions ) ) return $instructions;
        $plan_data = null;
        if ( '' !== $date ) {
            if ( ! is_string( $date ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date ) ) return new \WP_Error( 'swimlog_conversion_date', 'Choose a valid planned date.' );
            $summary = Structured_Workout::summarize( $preview['document'] );
            $plan_data = array( 'title' => $title, 'planned_date' => $date, 'workout_type' => 'pool', 'pool_course' => $summary['unit'], 'planned_distance_m' => $summary['totals']['distance'] * ( 'yd' === $summary['unit'] ? 0.9144 : 1 ), 'workout_instructions' => $instructions );
            $valid = Planned_Workout::validate( $plan_data );
            if ( is_wp_error( $valid ) ) return $valid;
            if ( $date < wp_date( 'Y-m-d', null, wp_timezone() ) ) return new \WP_Error( 'swimlog_conversion_date', 'Choose today or a future date.' );
        }
        $id = Structured_Workout_Store::save( $owner_id, $title, $preview['document'] );
        if ( is_wp_error( $id ) ) return $id;
        update_post_meta( $id, '_swimlog_converted_workout_id', absint( $workout_id ) );
        if ( absint( $workout_id ) !== absint( get_post_meta( $id, '_swimlog_converted_workout_id', true ) ) ) { wp_delete_post( $id, true ); return new \WP_Error( 'swimlog_conversion_provenance', 'Could not retain conversion provenance.' ); }
        $plan_id = 0;
        if ( $plan_data ) {
            $plan_id = Planned_Workout::save( $owner_id, $plan_data );
            if ( is_wp_error( $plan_id ) ) { wp_delete_post( $id, true ); return $plan_id; }
            $linked = Planned_Structure_Link::attach( $plan_id, $id, $owner_id );
            if ( is_wp_error( $linked ) ) { Planned_Workout::delete( $plan_id, $owner_id ); wp_delete_post( $id, true ); return $linked; }
        }
        return array( 'structure_id' => $id, 'planned_id' => $plan_id );
    }
}
