<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owner-scoped persistence and lifecycle service for planned swim workouts. */
final class Planned_Workout {
	const STATUSES = array( 'planned', 'completed', 'skipped', 'cancelled' );
	const WORKOUT_TYPES = array( 'pool', 'open_water' );
	const STROKES = array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED', 'IM' );
	const INSTRUCTIONS_MAX_BYTES = 20000;
	const NOTES_MAX_BYTES = 5000;

	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'planned_workouts' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND user_id = %d",
				absint( $id ),
				absint( $user_id )
			)
		);
	}

	/** Return all plans owned by one swimmer, newest planned date first. */
	public static function all_for_user( $user_id ) {
		global $wpdb;
		$table = Database::table( 'planned_workouts' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d ORDER BY planned_date DESC, CASE WHEN planned_start_time IS NULL THEN 1 ELSE 0 END ASC, planned_start_time ASC, id DESC",
				absint( $user_id )
			)
		);
	}

	/** Return plans in a half-open date range, ordered deterministically for calendar use. */
	public static function range_for_user( $user_id, $start, $end ) {
		global $wpdb;
		$table = Database::table( 'planned_workouts' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table
				WHERE user_id = %d AND planned_date >= %s AND planned_date < %s
				ORDER BY planned_date ASC,
					CASE WHEN planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
					planned_start_time ASC, id ASC",
				absint( $user_id ),
				$start,
				$end
			)
		);
	}

	/** Create or update a planned workout while preserving the swimmer's free-form prescription. */
	public static function save( $user_id, $data, $id = 0 ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$id = absint( $id );
		if ( ! $user_id ) {
			return new \WP_Error( 'swimlog_planned_workout_user', __( 'A swimmer is required.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( $id && ! self::get_for_user( $id, $user_id ) ) {
			return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) return $validated;

		$table = Database::table( 'planned_workouts' );
		$now = current_time( 'mysql' );
		$values = array(
			'user_id' => $user_id,
			'planned_date' => $validated['planned_date'],
			'planned_start_time' => $validated['planned_start_time'],
			'title' => $validated['title'],
			'workout_type' => $validated['workout_type'],
			'planned_distance_m' => $validated['planned_distance_m'],
			'planned_duration_ms' => $validated['planned_duration_ms'],
			'pool_length_m' => $validated['pool_length_m'],
			'pool_course' => $validated['pool_course'],
			'primary_stroke' => $validated['primary_stroke'],
			'workout_instructions' => $validated['workout_instructions'],
			'notes' => $validated['notes'],
			'updated_at' => $now,
		);

		if ( $id ) {
			$result = $wpdb->update( $table, $values, array( 'id' => $id, 'user_id' => $user_id ) );
			return false === $result
				? new \WP_Error( 'swimlog_planned_workout_save', __( 'The planned workout could not be saved.', 'chriss-swim-training-progress-evaluation' ) )
				: $id;
		}

		$values['status'] = 'planned';
		$values['completed_workout_id'] = null;
		$values['created_at'] = $now;
		$result = $wpdb->insert( $table, $values );
		return false === $result
			? new \WP_Error( 'swimlog_planned_workout_save', __( 'The planned workout could not be saved.', 'chriss-swim-training-progress-evaluation' ) )
			: (int) $wpdb->insert_id;
	}

	/** Duplicate an owned plan as a new planned workout, optionally on another date. */
	public static function duplicate( $id, $user_id, $planned_date = null ) {
		$plan = self::get_for_user( absint( $id ), absint( $user_id ) );
		if ( ! $plan ) return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		$data = array(
			'planned_date' => null === $planned_date || '' === $planned_date ? $plan->planned_date : $planned_date,
			'planned_start_time' => $plan->planned_start_time,
			'title' => $plan->title,
			'workout_type' => $plan->workout_type,
			'planned_distance_m' => $plan->planned_distance_m,
			'planned_duration_ms' => $plan->planned_duration_ms,
			'pool_length_m' => $plan->pool_length_m,
			'pool_course' => $plan->pool_course,
			'primary_stroke' => $plan->primary_stroke,
			'workout_instructions' => $plan->workout_instructions,
			'notes' => $plan->notes,
		);
		return self::save( $user_id, $data );
	}

	/** Mark a plan planned, skipped, or cancelled. Completed requires an actual-workout association. */
	public static function set_status( $id, $user_id, $status ) {
		global $wpdb;
		$id = absint( $id );
		$user_id = absint( $user_id );
		$status = sanitize_key( $status );
		$plan = self::get_for_user( $id, $user_id );
		if ( ! $plan ) return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		if ( ! in_array( $status, self::STATUSES, true ) ) return new \WP_Error( 'swimlog_planned_workout_status', __( 'Select a valid planned-workout status.', 'chriss-swim-training-progress-evaluation' ) );
		if ( 'completed' === $status ) return new \WP_Error( 'swimlog_planned_workout_completion', __( 'Associate the recorded workout to mark this plan completed.', 'chriss-swim-training-progress-evaluation' ) );

		$table = Database::table( 'planned_workouts' );
		$result = $wpdb->update(
			$table,
			array( 'status' => $status, 'completed_workout_id' => null, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id, 'user_id' => $user_id )
		);
		return false === $result
			? new \WP_Error( 'swimlog_planned_workout_status_save', __( 'The planned-workout status could not be saved.', 'chriss-swim-training-progress-evaluation' ) )
			: true;
	}

	/** Associate exactly one recorded workout owned by the same swimmer and mark the plan completed. */
	public static function associate_completed_workout( $id, $user_id, $workout_id ) {
		global $wpdb;
		$id = absint( $id );
		$user_id = absint( $user_id );
		$workout_id = absint( $workout_id );
		if ( ! self::get_for_user( $id, $user_id ) ) return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		if ( ! $workout_id || ! Workout::get_for_user( $workout_id, $user_id ) ) return new \WP_Error( 'swimlog_planned_workout_recorded_workout', __( 'Select a recorded workout owned by this swimmer.', 'chriss-swim-training-progress-evaluation' ) );

		$table = Database::table( 'planned_workouts' );
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE completed_workout_id = %d AND id <> %d",
				$workout_id,
				$id
			)
		);
		if ( $existing ) return new \WP_Error( 'swimlog_planned_workout_recorded_workout_used', __( 'That recorded workout is already associated with another plan.', 'chriss-swim-training-progress-evaluation' ) );

		$result = $wpdb->update(
			$table,
			array( 'status' => 'completed', 'completed_workout_id' => $workout_id, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id, 'user_id' => $user_id )
		);
		return false === $result
			? new \WP_Error( 'swimlog_planned_workout_completion_save', __( 'The completed-workout association could not be saved.', 'chriss-swim-training-progress-evaluation' ) )
			: true;
	}

	public static function clear_completed_workout( $id, $user_id ) {
		global $wpdb;
		$id = absint( $id );
		$user_id = absint( $user_id );
		if ( ! self::get_for_user( $id, $user_id ) ) return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		$table = Database::table( 'planned_workouts' );
		$result = $wpdb->update(
			$table,
			array( 'status' => 'planned', 'completed_workout_id' => null, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id, 'user_id' => $user_id )
		);
		return false === $result
			? new \WP_Error( 'swimlog_planned_workout_completion_save', __( 'The completed-workout association could not be cleared.', 'chriss-swim-training-progress-evaluation' ) )
			: true;
	}

	public static function delete( $id, $user_id ) {
		global $wpdb;
		$id = absint( $id );
		$user_id = absint( $user_id );
		if ( ! self::get_for_user( $id, $user_id ) ) return new \WP_Error( 'swimlog_planned_workout_not_found', __( 'Planned workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		$table = Database::table( 'planned_workouts' );
		$result = $wpdb->delete( $table, array( 'id' => $id, 'user_id' => $user_id ), array( '%d', '%d' ) );
		return false === $result
			? new \WP_Error( 'swimlog_planned_workout_delete', __( 'The planned workout could not be deleted.', 'chriss-swim-training-progress-evaluation' ) )
			: true;
	}

	public static function validate( $data ) {
		$title = sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' );
		$date = sanitize_text_field( isset( $data['planned_date'] ) ? $data['planned_date'] : '' );
		$time = sanitize_text_field( isset( $data['planned_start_time'] ) ? $data['planned_start_time'] : '' );
		$type = sanitize_key( isset( $data['workout_type'] ) ? $data['workout_type'] : 'pool' );
		$course = sanitize_key( isset( $data['pool_course'] ) ? $data['pool_course'] : '' );
		$stroke = strtoupper( sanitize_key( isset( $data['primary_stroke'] ) ? $data['primary_stroke'] : '' ) );
		$instructions = sanitize_textarea_field( isset( $data['workout_instructions'] ) ? $data['workout_instructions'] : '' );
		$notes = sanitize_textarea_field( isset( $data['notes'] ) ? $data['notes'] : '' );

		if ( '' === $title ) return new \WP_Error( 'swimlog_planned_workout_title', __( 'A planned-workout title is required.', 'chriss-swim-training-progress-evaluation' ) );
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) return new \WP_Error( 'swimlog_planned_workout_date', __( 'Enter a valid planned date.', 'chriss-swim-training-progress-evaluation' ) );
		if ( '' !== $time && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time ) ) return new \WP_Error( 'swimlog_planned_workout_time', __( 'Enter a valid planned start time.', 'chriss-swim-training-progress-evaluation' ) );
		if ( ! in_array( $type, self::WORKOUT_TYPES, true ) ) return new \WP_Error( 'swimlog_planned_workout_type', __( 'Select pool or open water.', 'chriss-swim-training-progress-evaluation' ) );
		if ( '' !== $course && ! in_array( $course, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_planned_workout_course', __( 'Select meters or yards for the pool course.', 'chriss-swim-training-progress-evaluation' ) );
		if ( '' !== $stroke && ! in_array( $stroke, self::STROKES, true ) ) return new \WP_Error( 'swimlog_planned_workout_stroke', __( 'Select a supported stroke.', 'chriss-swim-training-progress-evaluation' ) );
		if ( strlen( $instructions ) > self::INSTRUCTIONS_MAX_BYTES ) return new \WP_Error( 'swimlog_planned_workout_instructions_length', __( 'Workout instructions are too long.', 'chriss-swim-training-progress-evaluation' ) );
		if ( strlen( $notes ) > self::NOTES_MAX_BYTES ) return new \WP_Error( 'swimlog_planned_workout_notes_length', __( 'Workout notes are too long.', 'chriss-swim-training-progress-evaluation' ) );

		$distance = self::optional_positive_number( isset( $data['planned_distance_m'] ) ? $data['planned_distance_m'] : null );
		if ( is_wp_error( $distance ) ) return new \WP_Error( 'swimlog_planned_workout_distance', __( 'Planned distance must be greater than zero.', 'chriss-swim-training-progress-evaluation' ) );
		$duration = self::optional_positive_integer( isset( $data['planned_duration_ms'] ) ? $data['planned_duration_ms'] : null );
		if ( is_wp_error( $duration ) ) return new \WP_Error( 'swimlog_planned_workout_duration', __( 'Planned duration must be greater than zero.', 'chriss-swim-training-progress-evaluation' ) );
		$pool_length = self::optional_positive_number( isset( $data['pool_length_m'] ) ? $data['pool_length_m'] : null );
		if ( is_wp_error( $pool_length ) ) return new \WP_Error( 'swimlog_planned_workout_pool_length', __( 'Pool length must be greater than zero.', 'chriss-swim-training-progress-evaluation' ) );
		if ( 'open_water' === $type && ( null !== $pool_length || '' !== $course ) ) return new \WP_Error( 'swimlog_planned_workout_pool_fields', __( 'Pool length and course apply only to pool workouts.', 'chriss-swim-training-progress-evaluation' ) );

		return array(
			'planned_date' => $date,
			'planned_start_time' => '' === $time ? null : ( 5 === strlen( $time ) ? $time . ':00' : $time ),
			'title' => $title,
			'workout_type' => $type,
			'planned_distance_m' => $distance,
			'planned_duration_ms' => $duration,
			'pool_length_m' => $pool_length,
			'pool_course' => '' === $course ? null : $course,
			'primary_stroke' => '' === $stroke ? null : $stroke,
			'workout_instructions' => $instructions,
			'notes' => $notes,
		);
	}

	private static function optional_positive_number( $value ) {
		if ( null === $value || '' === $value ) return null;
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) return new \WP_Error( 'invalid_number' );
		return (float) $value;
	}

	private static function optional_positive_integer( $value ) {
		if ( null === $value || '' === $value ) return null;
		if ( ! is_numeric( $value ) || (int) $value <= 0 || (float) $value !== (float) (int) $value ) return new \WP_Error( 'invalid_integer' );
		return (int) $value;
	}
}
