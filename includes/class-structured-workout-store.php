<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Private owner-scoped structured prescriptions; no new SQL tables. */
final class Structured_Workout_Store {
    const POST_TYPE = 'swimlog_structure';
    public static function register() {
        register_post_type( self::POST_TYPE, array(
            'label' => __( 'Structured Swim Workouts', 'chriss-swim-training-progress-evaluation' ),
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'exclude_from_search' => true, 'supports' => array( 'title', 'author' ),
            'capability_type' => 'post', 'map_meta_cap' => true,
        ) );
    }
    public static function get( $id, $owner_id ) {
        $post = get_post( absint( $id ) );
        if ( ! $post || self::POST_TYPE !== $post->post_type || (int) $post->post_author !== absint( $owner_id ) ) {
            return new \WP_Error( 'swimlog_structure_not_found', __( 'Structured workout not found.', 'chriss-swim-training-progress-evaluation' ) );
        }
        $document = json_decode( $post->post_content, true );
        if ( ! is_array( $document ) ) return new \WP_Error( 'swimlog_structure_corrupt', __( 'Stored workout is invalid.', 'chriss-swim-training-progress-evaluation' ) );
        return array( 'id' => (int) $post->ID, 'title' => $post->post_title, 'document' => $document );
    }
    public static function all( $owner_id ) {
        return get_posts( array( 'post_type' => self::POST_TYPE, 'author' => absint( $owner_id ),
            'post_status' => 'private', 'posts_per_page' => 100, 'orderby' => 'modified', 'order' => 'DESC' ) );
    }
    public static function save( $owner_id, $title, $document, $id = 0 ) {
        $owner_id = absint( $owner_id );
        if ( ! $owner_id || ! user_can( $owner_id, 'swimlog_manage_own_workouts' ) ) {
            return new \WP_Error( 'swimlog_structure_permission', __( 'Permission denied.', 'chriss-swim-training-progress-evaluation' ) );
        }
        if ( ! is_string( $title ) || '' === trim( $title ) || strlen( $title ) > 191 ) {
            return new \WP_Error( 'swimlog_structure_title', __( 'A title is required (maximum 191 bytes).', 'chriss-swim-training-progress-evaluation' ) );
        }
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        if ( $id ) {
            $existing = self::get( $id, $owner_id );
            if ( is_wp_error( $existing ) ) return $existing;
        }
        $data = array( 'post_type' => self::POST_TYPE, 'post_status' => 'private',
            'post_author' => $owner_id, 'post_title' => sanitize_text_field( $title ),
            'post_content' => wp_json_encode( $summary['document'] ) );
        if ( $id ) $data['ID'] = absint( $id );
        $saved = $id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
        return $saved;
    }
}
