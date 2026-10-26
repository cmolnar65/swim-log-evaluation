<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Reusable calendar data service built from existing normalized workout/event data.
 *
 * This class does not persist calendar records and has no presentation-layer
 * dependencies. Ownership remains enforced by the underlying user-scoped
 * Workout and Event services.
 */
final class Calendar {
	/**
	 * Build calendar data for one swimmer and inclusive YYYY-MM-DD boundaries.
	 *
	 * @return array|\WP_Error
	 */
	public static function for_user( $user_id, $start, $end ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return new \WP_Error(
				'swimlog_calendar_user',
				__( 'A swimmer is required for calendar data.', 'chriss-swim-training-progress-evaluation' )
			);
		}

		$range = self::normalize_range( $start, $end );
		if ( is_wp_error( $range ) ) return $range;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$workouts = Workout::range_for_user(
			$user_id,
			$range['start_datetime'],
			$range['end_exclusive_datetime']
		);
		$events = Event::range_for_user(
			$user_id,
			$range['start'],
			$range['end_exclusive']
		);

		return self::assemble_rows( $range, $workouts, $events );
	}

	/**
	 * Convert already-scoped service rows into the stable calendar data model.
	 *
	 * Kept public so other plugin layers and focused regression tests can reuse
	 * the exact same normalization without performing additional database reads.
	 */
	public static function assemble_rows( $range, $workouts, $events ) {
		$workout_rows = array();
		foreach ( (array) $workouts as $workout ) {
			$workout_rows[] = self::workout_row( $workout );
		}

		$event_rows = array();
		foreach ( (array) $events as $event ) {
			$event_rows[] = self::event_row( $event );
		}

		return array(
			'range' => array(
				'start' => $range['start'],
				'end' => $range['end'],
				'timezone' => $range['timezone'],
			),
			'workouts' => $workout_rows,
			'events' => $event_rows,
		);
	}

	private static function normalize_range( $start, $end ) {
		$timezone = wp_timezone();
		$start_date = self::parse_date( $start, $timezone );
		$end_date = self::parse_date( $end, $timezone );
		if ( ! $start_date || ! $end_date ) {
			return new \WP_Error(
				'swimlog_calendar_date',
				__( 'Calendar start and end must be valid dates in YYYY-MM-DD format.', 'chriss-swim-training-progress-evaluation' )
			);
		}
		if ( $end_date < $start_date ) {
			return new \WP_Error(
				'swimlog_calendar_range',
				__( 'Calendar end date cannot be before the start date.', 'chriss-swim-training-progress-evaluation' )
			);
		}
		$end_exclusive = $end_date->modify( '+1 day' );
		return array(
			'start' => $start_date->format( 'Y-m-d' ),
			'end' => $end_date->format( 'Y-m-d' ),
			'end_exclusive' => $end_exclusive->format( 'Y-m-d' ),
			'start_datetime' => $start_date->format( 'Y-m-d 00:00:00' ),
			'end_exclusive_datetime' => $end_exclusive->format( 'Y-m-d 00:00:00' ),
			'timezone' => $timezone->getName(),
		);
	}

	private static function parse_date( $value, $timezone ) {
		$value = trim( (string) $value );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $date ) return false;
		if ( false !== $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) return false;
		return $date->format( 'Y-m-d' ) === $value ? $date : false;
	}

	private static function workout_row( $workout ) {
		return array(
			'id' => absint( $workout->id ?? 0 ),
			'kind' => 'workout',
			'workout_type' => 'open_water' === (string) ( $workout->workout_type ?? 'pool' ) ? 'open_water' : 'pool',
			'workout_start' => (string) ( $workout->workout_start ?? '' ),
			'workout_end' => null !== ( $workout->workout_end ?? null ) ? (string) $workout->workout_end : null,
			'location' => null !== ( $workout->location_name ?? null ) ? (string) $workout->location_name : null,
			'total_distance_m' => null !== ( $workout->total_distance_m ?? null ) ? (float) $workout->total_distance_m : null,
			'original_distance' => null !== ( $workout->original_distance ?? null ) ? (float) $workout->original_distance : null,
			'original_distance_unit' => null !== ( $workout->original_distance_unit ?? null ) ? (string) $workout->original_distance_unit : null,
			'elapsed_time_ms' => null !== ( $workout->elapsed_time_ms ?? null ) ? (int) $workout->elapsed_time_ms : null,
			'moving_time_ms' => null !== ( $workout->moving_time_ms ?? null ) ? (int) $workout->moving_time_ms : null,
			'original_pool_length' => null !== ( $workout->original_pool_length ?? null ) ? (float) $workout->original_pool_length : null,
			'pool_length_unit' => null !== ( $workout->pool_length_unit ?? null ) ? (string) $workout->pool_length_unit : null,
			'primary_stroke' => null !== ( $workout->primary_stroke ?? null ) ? (string) $workout->primary_stroke : null,
		);
	}

	private static function event_row( $event ) {
		return array(
			'id' => absint( $event->id ?? 0 ),
			'kind' => 'event',
			'event_name' => (string) ( $event->event_name ?? '' ),
			'event_date' => (string) ( $event->event_date ?? '' ),
			'event_time' => null !== ( $event->event_time ?? null ) ? (string) $event->event_time : null,
			'location' => null !== ( $event->location_name ?? null ) ? (string) $event->location_name : null,
			'distance_value' => null !== ( $event->distance_value ?? null ) ? (int) $event->distance_value : null,
			'course_unit' => null !== ( $event->course_unit ?? null ) ? (string) $event->course_unit : null,
			'stroke' => null !== ( $event->stroke ?? null ) ? (string) $event->stroke : null,
		);
	}
}
