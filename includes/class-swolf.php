<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Stored, reproducible pool-length SWOLF; unavailable measurements remain NULL. */
final class Swolf {
    public static function length_score( $length ) {
        $type = (string) ( is_array( $length ) ? ( $length['length_type'] ?? '' ) : ( $length->length_type ?? '' ) );
        $stroke = strtoupper( (string) ( is_array( $length ) ? ( $length['stroke'] ?? '' ) : ( $length->stroke ?? '' ) ) );
        $ms = is_array( $length ) ? ( $length['elapsed_time_ms'] ?? null ) : ( $length->elapsed_time_ms ?? null );
        $count = is_array( $length ) ? ( $length['stroke_count'] ?? null ) : ( $length->stroke_count ?? null );
        $distance = is_array( $length ) ? ( $length['distance_m'] ?? null ) : ( $length->distance_m ?? null );
        if ( $type !== 'active' || ! in_array( $stroke, array( 'FR', 'BACK', 'BR', 'FLY' ), true ) ) return null;
        if ( ! is_numeric( $ms ) || ! is_numeric( $count ) || ! is_numeric( $distance ) ) return null;
        if ( (float) $ms <= 0 || (int) $count <= 0 || (float) $distance <= 0 ) return null;
        // Round the individual-length score once, before computing the workout mean.
        return (int) round( (float) $ms / 1000 + (int) $count );
    }

    public static function populate_parsed( $parsed ) {
        if ( ( $parsed['workout']['workout_type'] ?? 'pool' ) !== 'pool' ) {
            $parsed['workout']['avg_swolf'] = null;
            return $parsed;
        }
        $scores = array();
        if ( ! isset( $parsed['lengths'] ) || ! is_array( $parsed['lengths'] ) ) $parsed['lengths'] = array();
        foreach ( $parsed['lengths'] as &$length ) {
            $length['swolf_score'] = self::length_score( $length );
            if ( null !== $length['swolf_score'] ) $scores[] = $length['swolf_score'];
        }
        unset( $length );
        $parsed['workout']['avg_swolf'] = $scores ? round( array_sum( $scores ) / count( $scores ), 2 ) : null;
        return $parsed;
    }

    /** Backfill already-stored lengths in batches; safe to run repeatedly. */
    public static function backfill( $batch_size = 50 ) {
        global $wpdb;
        $workouts = Database::table( 'workouts' );
        $lengths = Database::table( 'lengths' );
        $last = (int) get_option( 'swimlog_swolf_backfill_last_id', 0 );
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $workouts WHERE id > %d ORDER BY id ASC LIMIT %d", $last, max( 1, min( 200, (int) $batch_size ) ) ) );
        foreach ( $ids as $id ) {
            $id = (int) $id;
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $lengths WHERE workout_id = %d ORDER BY sequence_no ASC", $id ) );
            $scores = array();
            foreach ( $rows as $row ) {
                $score = self::length_score( $row );
                $wpdb->update( $lengths, array( 'swolf_score' => $score ), array( 'id' => (int) $row->id, 'workout_id' => $id ) );
                if ( null !== $score ) $scores[] = $score;
            }
            $avg = $scores ? round( array_sum( $scores ) / count( $scores ), 2 ) : null;
            $wpdb->update( $workouts, array( 'avg_swolf' => $avg ), array( 'id' => $id ) );
            update_option( 'swimlog_swolf_backfill_last_id', $id, false );
        }
        return count( $ids );
    }
}
