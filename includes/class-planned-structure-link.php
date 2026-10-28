<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Owner-scoped plan-to-structure association stored on the private structure post.
 * No planned-workout schema migration or changes to written instructions.
 */
final class Planned_Structure_Link {
    const META_KEY = '_swimlog_planned_workout_id';

    public static function for_plan( $plan_id, $owner_id ) {
        $plan = Planned_Workout::get_for_user( absint( $plan_id ), absint( $owner_id ) );
        if ( ! $plan ) return new \WP_Error( 'swimlog_plan_not_found', 'Planned workout not found.' );
        $posts = get_posts( array(
            'post_type' => Structured_Workout_Store::POST_TYPE,
            'post_status' => 'private',
            'author' => absint( $owner_id ),
            'meta_key' => self::META_KEY,
            'meta_value' => (string) absint( $plan_id ),
            'posts_per_page' => 2,
            'fields' => 'ids',
        ) );
        if ( count( $posts ) > 1 ) return new \WP_Error( 'swimlog_plan_multiple_structures', 'Multiple structures are linked to this plan.' );
        if ( ! $posts ) return null;
        return Structured_Workout_Store::get( (int) $posts[0], $owner_id );
    }

    public static function attach( $plan_id, $structure_id, $owner_id ) {
        $plan_id = absint( $plan_id );
        $structure_id = absint( $structure_id );
        $owner_id = absint( $owner_id );
        if ( ! $owner_id || ! user_can( $owner_id, 'swimlog_manage_own_workouts' ) ) {
            return new \WP_Error( 'swimlog_link_permission', 'Permission denied.' );
        }
        if ( ! Planned_Workout::get_for_user( $plan_id, $owner_id ) ) {
            return new \WP_Error( 'swimlog_link_plan', 'Planned workout not found.' );
        }
        $structure = Structured_Workout_Store::get( $structure_id, $owner_id );
        if ( is_wp_error( $structure ) ) return $structure;
        $existing = self::for_plan( $plan_id, $owner_id );
        if ( is_wp_error( $existing ) ) return $existing;
        if ( $existing && (int) $existing['id'] !== $structure_id ) {
            return new \WP_Error( 'swimlog_link_existing', 'Unlink the current structure before linking another.' );
        }
        $linked_plan = absint( get_post_meta( $structure_id, self::META_KEY, true ) );
        if ( $linked_plan && $linked_plan !== $plan_id ) {
            return new \WP_Error( 'swimlog_link_used', 'This structure belongs to another planned workout.' );
        }
        if ( ! $linked_plan && ! update_post_meta( $structure_id, self::META_KEY, $plan_id ) ) {
            return new \WP_Error( 'swimlog_link_failed', 'Could not link structured workout.' );
        }
        return true;
    }

    public static function detach( $plan_id, $owner_id ) {
        $existing = self::for_plan( $plan_id, $owner_id );
        if ( is_wp_error( $existing ) ) return $existing;
        if ( ! $existing ) return true;
        delete_post_meta( $existing['id'], self::META_KEY, absint( $plan_id ) );
        return true;
    }
}
