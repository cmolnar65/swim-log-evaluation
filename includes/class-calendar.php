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
	 * Return the swimmer's configured first day of the calendar week.
	 *
	 * Stored as user metadata so the preference remains swimmer-specific without
	 * adding plugin schema. Invalid or unset legacy values safely use Sunday.
	 */
	public static function first_day_for_user( $user_id ) {
		$value = sanitize_key( (string) get_user_meta( absint( $user_id ), 'swimlog_calendar_first_day', true ) );
		return in_array( $value, array( 'saturday', 'sunday', 'monday' ), true ) ? $value : 'sunday';
	}

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
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-planned-workout.php';

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
		$planned_workouts = Planned_Workout::range_for_user(
			$user_id,
			$range['start'],
			$range['end_exclusive']
		);

		return self::assemble_rows( $range, $workouts, $events, self::first_day_for_user( $user_id ), $planned_workouts );
	}

	/**
	 * Convert already-scoped service rows into the stable calendar data model.
	 *
	 * Kept public so other plugin layers and focused regression tests can reuse
	 * the exact same normalization without performing additional database reads.
	 */
	public static function assemble_rows( $range, $workouts, $events, $first_day = 'sunday', $planned_workouts = array() ) {
		$workout_rows = array();
		foreach ( (array) $workouts as $workout ) {
			$workout_rows[] = self::workout_row( $workout );
		}

		$event_rows = array();
		foreach ( (array) $events as $event ) {
			$event_rows[] = self::event_row( $event );
		}

		$planned_rows = array();
		foreach ( (array) $planned_workouts as $planned_workout ) {
			$planned_rows[] = self::planned_workout_row( $planned_workout );
		}

		$first_day = self::normalize_first_day( $first_day );
		return array(
			'range' => array(
				'start' => $range['start'],
				'end' => $range['end'],
				'timezone' => $range['timezone'],
				'first_day' => $first_day,
			),
			'workouts' => $workout_rows,
			'planned_workouts' => $planned_rows,
			'events' => $event_rows,
			'weeks' => self::weekly_summaries( $range, $workouts, $first_day ),
			'metric_metadata' => self::workout_metric_metadata(),
		);
	}

	/**
	 * Build consecutive week buckets for the requested range.
	 *
	 * Week boundaries honor the swimmer preference. Buckets may begin before or
	 * end after the requested range so month/week presentation can use natural
	 * calendar boundaries; only supplied workouts contribute to totals.
	 */
	public static function weekly_summaries( $range, $workouts, $first_day = 'sunday' ) {
		$timezone = new \DateTimeZone( (string) $range['timezone'] );
		$first_day = self::normalize_first_day( $first_day );
		$range_start = self::parse_date( $range['start'], $timezone );
		$range_end = self::parse_date( $range['end'], $timezone );
		if ( ! $range_start || ! $range_end || $range_end < $range_start ) return array();

		$week_start = self::week_start_for_date( $range_start, $first_day );
		$last_week_start = self::week_start_for_date( $range_end, $first_day );
		$buckets = array();
		for ( $cursor = $week_start; $cursor <= $last_week_start; $cursor = $cursor->modify( '+7 days' ) ) {
			$key = $cursor->format( 'Y-m-d' );
			$buckets[ $key ] = array(
				'start' => $key,
				'end' => $cursor->modify( '+6 days' )->format( 'Y-m-d' ),
				'rows' => array(),
			);
		}

		foreach ( (array) $workouts as $workout ) {
			$start = trim( (string) ( $workout->workout_start ?? '' ) );
			if ( '' === $start ) continue;
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start, $timezone );
			if ( false === $date ) continue;
			$key = self::week_start_for_date( $date, $first_day )->format( 'Y-m-d' );
			if ( isset( $buckets[ $key ] ) ) $buckets[ $key ]['rows'][] = $workout;
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-trends.php';
		$weeks = array();
		foreach ( $buckets as $bucket ) {
			$summary = Training_Trends::summarize_workouts( $bucket['rows'] );
			$weeks[] = array(
				'start' => $bucket['start'],
				'end' => $bucket['end'],
				'workout_count' => $summary['workout_count'],
				'distance_m' => $summary['distance_m'],
				'active_time_ms' => $summary['active_time_ms'],
				'pool_distance_m' => $summary['pool_distance_m'],
				'open_water_distance_m' => $summary['open_water_distance_m'],
				'pool_workouts' => $summary['pool_workouts'],
				'open_water_workouts' => $summary['open_water_workouts'],
			);
		}
		return $weeks;
	}

	private static function workout_metric_metadata() {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-trends.php';
		return Training_Trends::workout_metric_metadata();
	}

	private static function normalize_first_day( $first_day ) {
		$first_day = sanitize_key( (string) $first_day );
		return in_array( $first_day, array( 'saturday', 'sunday', 'monday' ), true ) ? $first_day : 'sunday';
	}

	private static function week_start_for_date( $date, $first_day ) {
		$target = array( 'sunday' => 0, 'monday' => 1, 'saturday' => 6 )[ $first_day ];
		$current = (int) $date->format( 'w' );
		$days_back = ( $current - $target + 7 ) % 7;
		return $date->modify( '-' . $days_back . ' days' )->setTime( 0, 0, 0 );
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

	private static function planned_workout_row( $plan ) {
		return array(
			'id' => absint( $plan->id ?? 0 ),
			'kind' => 'planned_workout',
			'planned_date' => (string) ( $plan->planned_date ?? '' ),
			'planned_start_time' => null !== ( $plan->planned_start_time ?? null ) ? (string) $plan->planned_start_time : null,
			'title' => (string) ( $plan->title ?? '' ),
			'workout_type' => 'open_water' === (string) ( $plan->workout_type ?? 'pool' ) ? 'open_water' : 'pool',
			'planned_distance_m' => null !== ( $plan->planned_distance_m ?? null ) ? (float) $plan->planned_distance_m : null,
			'planned_duration_ms' => null !== ( $plan->planned_duration_ms ?? null ) ? (int) $plan->planned_duration_ms : null,
			'pool_length_m' => null !== ( $plan->pool_length_m ?? null ) ? (float) $plan->pool_length_m : null,
			'pool_course' => null !== ( $plan->pool_course ?? null ) ? (string) $plan->pool_course : null,
			'primary_stroke' => null !== ( $plan->primary_stroke ?? null ) ? (string) $plan->primary_stroke : null,
			'workout_instructions' => (string) ( $plan->workout_instructions ?? '' ),
			'status' => (string) ( $plan->status ?? 'planned' ),
			'completed_workout_id' => null !== ( $plan->completed_workout_id ?? null ) ? absint( $plan->completed_workout_id ) : null,
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
