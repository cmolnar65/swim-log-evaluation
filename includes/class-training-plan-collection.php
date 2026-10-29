<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Phase 04-10A: private training-plan collections.
 * A collection is metadata only; the original PDF is not published or shared.
 */
final class Training_Plan_Collection {
    const POST_TYPE = 'swimlog_training_plan';
    const META_PLAN = '_swimlog_training_plan_id';
    const META_SEQUENCE = '_swimlog_training_plan_sequence';
    const META_WEEK = '_swimlog_training_plan_week';
    const META_SOURCE = '_swimlog_training_plan_source';
    const META_COUNT = '_swimlog_training_plan_count';

    public static function register() {
        register_post_type( self::POST_TYPE, array(
            'label' => __( 'Private Training Plans', 'chriss-swim-training-progress-evaluation' ),
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'exclude_from_search' => true, 'supports' => array( 'title', 'author' ),
            'capability_type' => 'post', 'map_meta_cap' => true,
        ) );
    }

    public static function title( $plan_name, $number, $total ) {
        return sprintf( '%s — Workout %02d of %02d', trim( $plan_name ), $number, $total );
    }

    public static function create( $owner_id, $name, $total, $source = '' ) {
        $owner_id = absint( $owner_id );
        $total = absint( $total );
        if ( ! $owner_id || ! user_can( $owner_id, 'swimlog_manage_own_workouts' ) ) {
            return new \WP_Error( 'swimlog_plan_permission', 'Permission denied.' );
        }
        if ( ! is_string( $name ) || '' === trim( $name ) || strlen( $name ) > 120 || $total < 1 || $total > 500 ) {
            return new \WP_Error( 'swimlog_plan_invalid', 'Invalid plan name or workout count.' );
        }
        $id = wp_insert_post( wp_slash( array(
            'post_type' => self::POST_TYPE, 'post_status' => 'private',
            'post_author' => $owner_id, 'post_title' => sanitize_text_field( $name ),
        ) ), true );
        if ( is_wp_error( $id ) ) return $id;
        update_post_meta( $id, self::META_COUNT, $total );
        update_post_meta( $id, self::META_SOURCE, sanitize_text_field( $source ) );
        if ( $total !== (int) get_post_meta( $id, self::META_COUNT, true ) || sanitize_text_field( $source ) !== get_post_meta( $id, self::META_SOURCE, true ) ) {
            wp_delete_post( $id, true );
            return new \WP_Error( 'swimlog_plan_metadata', 'Could not persist training-plan metadata.' );
        }
        return $id;
    }

    public static function get( $id, $owner_id ) {
        $post = get_post( absint( $id ) );
        if ( ! $post || self::POST_TYPE !== $post->post_type || (int) $post->post_author !== absint( $owner_id ) ) {
            return new \WP_Error( 'swimlog_plan_not_found', 'Training plan not found.' );
        }
        return $post;
    }

    public static function attach( $owner_id, $plan_id, $structure_id, $sequence, $week = 0 ) {
        $plan = self::get( $plan_id, $owner_id );
        if ( is_wp_error( $plan ) ) return $plan;
        $structure = Structured_Workout_Store::get( $structure_id, $owner_id );
        if ( is_wp_error( $structure ) ) return $structure;
        $total = absint( get_post_meta( $plan_id, self::META_COUNT, true ) );
        $sequence = absint( $sequence );
        if ( ! $sequence || $sequence > $total ) {
            return new \WP_Error( 'swimlog_plan_sequence', 'Sequence is outside the plan.' );
        }
        $existing = get_posts( array(
            'post_type' => Structured_Workout_Store::POST_TYPE, 'author' => absint( $owner_id ),
            'post_status' => 'private', 'posts_per_page' => 2, 'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Owner-scoped private-post uniqueness check on existing metadata; two IDs suffice to detect a conflicting sequence without SQL exclusions.
            'meta_query' => array(
                array( 'key' => self::META_PLAN, 'value' => absint( $plan_id ) ),
                array( 'key' => self::META_SEQUENCE, 'value' => $sequence ),
            ),
        ) );
        if ( array_diff( array_map( 'absint', $existing ), array( absint( $structure_id ) ) ) ) return new \WP_Error( 'swimlog_plan_duplicate', 'Sequence already assigned.' );
        $current = absint( get_post_meta( $structure_id, self::META_PLAN, true ) );
        if ( $current && $current !== absint( $plan_id ) ) {
            return new \WP_Error( 'swimlog_plan_assigned', 'Workout already belongs to another plan.' );
        }
        $values = array( self::META_PLAN => absint( $plan_id ), self::META_SEQUENCE => $sequence, self::META_WEEK => absint( $week ) );
        $previous = array();
        foreach ( $values as $key => $value ) {
            $previous[$key] = array( 'exists' => metadata_exists( 'post', $structure_id, $key ), 'value' => get_post_meta( $structure_id, $key, true ) );
        }
        foreach ( $values as $key => $value ) {
            update_post_meta( $structure_id, $key, $value );
            if ( (string) $value !== (string) get_post_meta( $structure_id, $key, true ) ) {
                foreach ( $previous as $restore_key => $old ) {
                    if ( $old['exists'] ) update_post_meta( $structure_id, $restore_key, $old['value'] );
                    else delete_post_meta( $structure_id, $restore_key );
                }
                return new \WP_Error( 'swimlog_plan_metadata', 'Could not persist workout collection membership.' );
            }
        }
        return true;
    }

    public static function workouts( $owner_id, $plan_id ) {
        $plan = self::get( $plan_id, $owner_id );
        if ( is_wp_error( $plan ) ) return $plan;
        return get_posts( array(
            'post_type' => Structured_Workout_Store::POST_TYPE,
            'author' => absint( $owner_id ), 'post_status' => 'private',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Existing private plan metadata supplies numeric sequence ordering; bounded to the supported 500 workouts and one owner.
            'posts_per_page' => 500, 'meta_key' => self::META_SEQUENCE,
            'orderby' => 'meta_value_num', 'order' => 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Owner-scoped membership lookup uses existing post metadata through core, preserving legacy plans without a schema migration.
            'meta_query' => array( array( 'key' => self::META_PLAN, 'value' => absint( $plan_id ) ) ),
        ) );
    }
}

