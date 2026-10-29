<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Flexible, per-workout scheduling of private imported prescriptions. */
final class Training_Plan_Scheduler {
    public static function schedule( $owner_id, $plan_id, $assignments ) {
        $owner_id = absint( $owner_id );
        if ( ! $owner_id || get_current_user_id() !== $owner_id || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_schedule_permission', 'Permission denied.' );
        $plan = Training_Plan_Collection::get( $plan_id, $owner_id );
        if ( is_wp_error( $plan ) ) return $plan;
        if ( ! is_array( $assignments ) || ! $assignments || count( $assignments ) > 500 ) return new \WP_Error( 'swimlog_schedule_input', 'Select one or more workouts and dates.' );
        $workouts = Training_Plan_Collection::workouts( $owner_id, $plan_id );
        if ( is_wp_error( $workouts ) ) return $workouts;
        $allowed = array();
        foreach ( $workouts as $post ) $allowed[(int) $post->ID] = $post;
        $validated = array();
        foreach ( $assignments as $structure_id => $date ) {
            $id = absint( $structure_id );
            if ( ! preg_match( '/^[1-9][0-9]*$/', (string) $structure_id ) || ! isset( $allowed[$id] ) || ! is_string( $date ) ) return new \WP_Error( 'swimlog_schedule_ownership', 'Workout does not belong to this training plan.' );
            if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date ) ) return new \WP_Error( 'swimlog_schedule_date', 'Choose valid current or future dates.' );
            $day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
            if ( ! $day || $day->format( 'Y-m-d' ) !== $date || $date < wp_date( 'Y-m-d', null, wp_timezone() ) ) return new \WP_Error( 'swimlog_schedule_date', 'Choose current or future dates.' );
            $existing = absint( get_post_meta( $id, Planned_Structure_Link::META_KEY, true ) );
            if ( $existing ) return new \WP_Error( 'swimlog_schedule_existing', 'One of the selected workouts is already scheduled.' );
            $structure = Structured_Workout_Store::get( $id, $owner_id );
            if ( is_wp_error( $structure ) ) return $structure;
            $summary = Structured_Workout::summarize( $structure['document'] );
            if ( is_wp_error( $summary ) ) return $summary;
            $validated[] = array( 'id' => $id, 'date' => $date, 'structure' => $structure, 'summary' => $summary );
        }
        $created = array();
        foreach ( $validated as $item ) {
            $summary = $item['summary'];
            $unit = $item['structure']['document']['unit'];
            $meters = (float) $summary['totals']['distance'] * ( 'yd' === $unit ? 0.9144 : 1 );
            $planned = Planned_Workout::save( $owner_id, array(
                'planned_date' => $item['date'], 'title' => $item['structure']['title'],
                'workout_type' => 'pool', 'planned_distance_m' => $meters,
                'pool_course' => $unit, 'workout_instructions' => 'Imported structured training-plan workout.',
            ) );
            if ( is_wp_error( $planned ) ) { self::rollback( $created, $owner_id ); return $planned; }
            $created[] = (int) $planned;
            $linked = Planned_Structure_Link::attach( $planned, $item['id'], $owner_id );
            if ( is_wp_error( $linked ) ) { self::rollback( $created, $owner_id ); return $linked; }
        }
        return $created;
    }

    private static function rollback( $ids, $owner_id ) {
        foreach ( array_reverse( $ids ) as $id ) {
            Planned_Structure_Link::detach( $id, $owner_id );
            Planned_Workout::delete( $id, $owner_id );
        }
    }
}
