<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owner-scoped, all-or-nothing import of explicitly selected verified candidates. */
final class Training_Plan_Bulk_Import {
    const META_FINGERPRINT = '_swimlog_pdf_import_fingerprint';

    public static function run( $owner_id, $name, $parsed, $sequences, $fingerprint ) {
        $owner_id = absint( $owner_id );
        if ( ! $owner_id || get_current_user_id() !== $owner_id || ! current_user_can( 'swimlog_manage_own_workouts' ) ) {
            return new \WP_Error( 'swimlog_bulk_permission', 'Permission denied.' );
        }
        $name = sanitize_text_field( $name );
        if ( '' === trim( $name ) || strlen( $name ) > 120 || ! is_array( $parsed ) || empty( $parsed['workouts'] ) ) {
            return new \WP_Error( 'swimlog_bulk_input', 'Invalid plan or empty candidate list.' );
        }
        if ( ! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/', $fingerprint ) ) {
            return new \WP_Error( 'swimlog_bulk_source', 'Invalid source fingerprint.' );
        }
        $selected = array_values( array_unique( array_map( 'absint', (array) $sequences ) ) );
        if ( ! $selected || count( $selected ) > 500 || in_array( 0, $selected, true ) ) {
            return new \WP_Error( 'swimlog_bulk_selection', 'Select one or more workouts.' );
        }
        $candidates = array();
        foreach ( $parsed['workouts'] as $candidate ) {
            if ( ! in_array( (int) $candidate['sequence'], $selected, true ) ) continue;
            $review = Training_Plan_Review::assess( $candidate );
            if ( ! $review['ready'] ) return new \WP_Error( 'swimlog_bulk_unverified', 'All selected workouts must have verified totals and no review flags.' );
            $summary = Structured_Workout::summarize( $candidate['document'] );
            if ( is_wp_error( $summary ) || abs( (float) $summary['totals']['distance'] - (float) $review['calculated'] ) > 0.001 ) {
                return new \WP_Error( 'swimlog_bulk_invalid', 'A selected workout failed structure validation.' );
            }
            $candidates[] = $candidate;
        }
        if ( count( $candidates ) !== count( $selected ) ) return new \WP_Error( 'swimlog_bulk_missing', 'A selected workout was not found.' );
        $existing = get_posts( array( 'post_type' => Training_Plan_Collection::POST_TYPE, 'author' => $owner_id,
            'post_status' => 'private', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => array( array( 'key' => self::META_FINGERPRINT, 'value' => $fingerprint ) ) ) );
        if ( $existing ) return new \WP_Error( 'swimlog_bulk_duplicate', 'This PDF has already been imported. No duplicates were created.' );
        $plan_id = Training_Plan_Collection::create( $owner_id, $name, count( $candidates ), 'PDF import' );
        if ( is_wp_error( $plan_id ) ) return $plan_id;
        $created = array();
        foreach ( $candidates as $index => $candidate ) {
            $title = Training_Plan_Collection::title( $name, $index + 1, count( $candidates ) );
            $id = Structured_Workout_Store::save( $owner_id, $title, $candidate['document'] );
            if ( ! is_wp_error( $id ) ) {
                $created[] = (int) $id;
                $linked = Training_Plan_Collection::attach( $owner_id, $plan_id, $id, $index + 1, absint( $candidate['week'] ) );
            } else {
                $linked = $id;
            }
            if ( is_wp_error( $linked ) || ! $linked ) {
                foreach ( array_reverse( $created ) as $created_id ) wp_delete_post( $created_id, true );
                wp_delete_post( $plan_id, true );
                return new \WP_Error( 'swimlog_bulk_rollback', 'Import failed; newly created workouts and collection were rolled back.' );
            }
        }
        update_post_meta( $plan_id, self::META_FINGERPRINT, $fingerprint );
        return array( 'plan_id' => (int) $plan_id, 'count' => count( $created ) );
    }
}
