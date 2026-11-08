<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Private swim goals evaluated against the swimmer's recorded history. */
final class Swim_Goals {
	const SINGLE = 'single_performance';
	const PERIOD = 'period_distance';
	const SINGLE_WORKOUT = 'single_workout_distance';
	const YARDS_TO_METERS = 0.9144;

	public static function create( $user_id, $input ) {
		$user_id = absint( $user_id );
		$goal = self::normalize_input( $input );
		if ( is_wp_error( $goal ) ) return $goal;
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) return new \WP_Error( 'swimlog_goal_user', __( 'Select a valid swimmer.', 'chriss-swim-training-progress-evaluation' ) );
		global $wpdb;
		$now = current_time( 'mysql' );
		$data = array_merge( $goal, array( 'user_id' => $user_id, 'created_at' => $now, 'updated_at' => $now ) );
		if ( false === Database::write( 'insert', Database::table( 'goals' ), $data ) ) return new \WP_Error( 'swimlog_goal_save', __( 'The goal could not be saved.', 'chriss-swim-training-progress-evaluation' ) );
		return self::get_for_user( (int) $wpdb->insert_id, $user_id );
	}

	public static function delete_for_user( $goal_id, $user_id ) {
		$goal_id = absint( $goal_id );
		$user_id = absint( $user_id );
		if ( ! $goal_id || ! $user_id ) return false;
		return 1 === Database::write( 'delete', Database::table( 'goals' ), array( 'id' => $goal_id, 'user_id' => $user_id ) );
	}

	public static function get_for_user( $goal_id, $user_id ) {
		global $wpdb;
		$row = Database::read( 'get_row', $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', Database::table( 'goals' ), absint( $goal_id ), absint( $user_id ) ) );
		return $row ? self::with_progress( $row ) : null;
	}

	public static function list_for_user( $user_id ) {
		global $wpdb;
		$rows = Database::read( 'get_results', $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY start_date DESC, id DESC LIMIT 200', Database::table( 'goals' ), absint( $user_id ) ) );
		return array_map( array( __CLASS__, 'with_progress' ), (array) $rows );
	}

	/** Return only private markers whose requested boundary falls in this calendar range. */
	public static function calendar_markers( $user_id, $start, $end ) {
		global $wpdb;
		$rows = Database::read( 'get_results', $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d AND ((goal_type = %s AND end_date BETWEEN %s AND %s) OR (goal_type IN (%s,%s) AND (start_date BETWEEN %s AND %s OR end_date BETWEEN %s AND %s))) ORDER BY start_date ASC, id ASC",
			Database::table( 'goals' ), absint( $user_id ), self::SINGLE, $start, $end, self::PERIOD, self::SINGLE_WORKOUT, $start, $end, $start, $end
		) );
		$markers = array();
		foreach ( (array) $rows as $row ) {
			$goal = self::with_progress( $row );
			if ( self::SINGLE === $goal['goal_type'] ) {
				$markers[] = self::calendar_row( $goal, $goal['end_date'], 'goal_due' );
				continue;
			}
			if ( $goal['end_date'] === $goal['start_date'] ) {
				$markers[] = self::calendar_row( $goal, $goal['start_date'], 'goal_start_end' );
			} else {
				$markers[] = self::calendar_row( $goal, $goal['start_date'], 'goal_start' );
				$markers[] = self::calendar_row( $goal, $goal['end_date'], 'goal_end' );
			}
		}
		return $markers;
	}

	public static function normalize_input( $input ) {
		$input = is_array( $input ) ? $input : array();
		$type = isset( $input['goal_type'] ) ? sanitize_key( $input['goal_type'] ) : '';
		if ( self::PERIOD === $type && ! empty( $input['single_workout'] ) ) $type = self::SINGLE_WORKOUT;
		$unit = isset( $input['course_unit'] ) ? sanitize_key( $input['course_unit'] ) : '';
		if ( ! in_array( $type, array( self::SINGLE, self::PERIOD, self::SINGLE_WORKOUT ), true ) ) return new \WP_Error( 'swimlog_goal_type', __( 'Select a supported goal type.', 'chriss-swim-training-progress-evaluation' ) );
		if ( ! in_array( $unit, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_goal_course', __( 'Select meters or yards.', 'chriss-swim-training-progress-evaluation' ) );
		$start = self::valid_date( $input['start_date'] ?? '' );
		$end = self::valid_date( $input['end_date'] ?? '' );
		if ( ! $start || ! $end || $end < $start ) return new \WP_Error( 'swimlog_goal_dates', __( 'Enter a valid date range with the end on or after the start.', 'chriss-swim-training-progress-evaluation' ) );
		if ( self::SINGLE === $type ) {
			$start = wp_date( 'Y-m-d', null, wp_timezone() );
			if ( $end < $start ) return new \WP_Error( 'swimlog_goal_dates', __( 'Choose a due date that is today or later.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$title = isset( $input['title'] ) && is_scalar( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$base = array(
			'goal_type' => $type,
			'title' => function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 191 ) : substr( $title, 0, 191 ),
			'course_unit' => $unit,
			'target_distance_m' => null,
			'target_distance_value' => null,
			'target_pace_ms' => null,
			'stroke' => '',
			'start_date' => $start,
			'end_date' => $end,
		);
		if ( self::SINGLE === $type ) {
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
			$distance = absint( $input['target_distance_value'] ?? 0 );
			$pace = absint( $input['target_pace_ms'] ?? 0 );
			if ( ! in_array( $distance, Evaluator::DISTANCES, true ) || ! $pace ) return new \WP_Error( 'swimlog_goal_performance', __( 'Enter a supported swim distance and positive target pace.', 'chriss-swim-training-progress-evaluation' ) );
			$base['target_distance_value'] = $distance;
			$base['target_pace_ms'] = $pace;
			$base['stroke'] = '';
			if ( '' === $base['title'] ) $base['title'] = sprintf( /* translators: 1: distance, 2: course unit. */ __( '%1$d %2$s pace goal', 'chriss-swim-training-progress-evaluation' ), $distance, $unit );
		} else {
			$distance = isset( $input['target_distance'] ) && is_numeric( $input['target_distance'] ) ? (float) $input['target_distance'] : 0.0;
			if ( ! is_finite( $distance ) || $distance <= 0 || $distance > 100000000 ) return new \WP_Error( 'swimlog_goal_volume', __( 'Enter a positive distance for the date-range goal.', 'chriss-swim-training-progress-evaluation' ) );
			$base['target_distance_m'] = round( 'yd' === $unit ? $distance * self::YARDS_TO_METERS : $distance, 3 );
			if ( '' === $base['title'] ) {
				if ( self::SINGLE_WORKOUT === $type ) {
					/* translators: 1: distance, 2: course unit. */
					$title_format = __( '%1$s %2$s single-workout goal', 'chriss-swim-training-progress-evaluation' );
				} else {
					/* translators: 1: distance, 2: course unit. */
					$title_format = __( '%1$s %2$s swim distance goal', 'chriss-swim-training-progress-evaluation' );
				}
				$base['title'] = sprintf( /* translators: 1: distance, 2: course unit. */ $title_format, self::format_native( $distance ), $unit );
			}
		}
		return $base;
	}

	public static function with_progress( $row ) {
		$goal = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
		$goal['id'] = absint( $goal['id'] ?? 0 );
		$goal['user_id'] = absint( $goal['user_id'] ?? 0 );
		$goal['target_distance_m'] = null === ( $goal['target_distance_m'] ?? null ) ? null : (float) $goal['target_distance_m'];
		$goal['target_distance_value'] = null === ( $goal['target_distance_value'] ?? null ) ? null : absint( $goal['target_distance_value'] );
		$goal['target_pace_ms'] = null === ( $goal['target_pace_ms'] ?? null ) ? null : absint( $goal['target_pace_ms'] );
		if ( self::SINGLE === $goal['goal_type'] ) {
			$progress = self::performance_progress( $goal );
		} else {
			$progress = self::distance_progress( $goal );
		}
		return array_merge( $goal, $progress );
	}

	private static function performance_progress( $goal ) {
		global $wpdb;
		$rows = Database::read( 'get_results', $wpdb->prepare(
			'SELECT p.duration_ms,p.achieved_at FROM %i p INNER JOIN %i w ON w.id = p.workout_id AND w.user_id = p.user_id WHERE p.user_id = %d AND p.distance_value = %d AND p.course_unit = %s AND p.achieved_at >= %s AND p.achieved_at < %s ORDER BY p.duration_ms ASC,p.achieved_at ASC',
			Database::table( 'performances' ), Database::table( 'workouts' ), $goal['user_id'], $goal['target_distance_value'], $goal['course_unit'], $goal['start_date'] . ' 00:00:00', self::next_day( $goal['end_date'] ) . ' 00:00:00'
		) );
		$best = null;
		$completion_date = null;
		$target_duration_distance = (int) $goal['target_pace_ms'] * (int) $goal['target_distance_value'];
		foreach ( (array) $rows as $row ) {
			$duration = (int) $row->duration_ms;
			$achieved_at = (string) $row->achieved_at;
			$pace = (int) round( $duration * 100 / max( 1, (int) $goal['target_distance_value'] ) );
			if ( null === $best || $duration < $best['duration_ms'] ) $best = array( 'duration_ms' => $duration, 'pace_ms' => $pace, 'achieved_at' => $achieved_at );
			if ( $duration * 100 <= $target_duration_distance ) {
				$date = substr( $achieved_at, 0, 10 );
				if ( null === $completion_date || $date < $completion_date ) $completion_date = $date;
			}
		}
		return array(
			'best_pace_ms' => null === $best ? null : $best['pace_ms'],
			'achieved_at' => null === $best ? null : $best['achieved_at'],
			'completion_date' => $completion_date,
			'progress_meters' => null,
			'progress_percent' => null,
			'status' => self::status( $goal, null !== $completion_date ),
		);
	}

	private static function distance_progress( $goal ) {
		global $wpdb;
		$rows = Database::read( 'get_results', $wpdb->prepare(
			"SELECT id,workout_start,total_distance_m FROM %i WHERE user_id = %d AND workout_type IN ('pool','open_water') AND total_distance_m IS NOT NULL AND workout_start >= %s AND workout_start < %s ORDER BY workout_start ASC,id ASC",
			Database::table( 'workouts' ), $goal['user_id'], $goal['start_date'] . ' 00:00:00', self::next_day( $goal['end_date'] ) . ' 00:00:00'
		) );
		$target = max( 0.001, (float) $goal['target_distance_m'] );
		$single_workout = self::SINGLE_WORKOUT === $goal['goal_type'];
		$meters = 0.0;
		$best_single_workout = 0.0;
		$completion_date = null;
		foreach ( (array) $rows as $row ) {
			$distance = (float) $row->total_distance_m;
			if ( ! is_finite( $distance ) || $distance <= 0 ) continue;
			if ( $single_workout ) {
				$best_single_workout = max( $best_single_workout, $distance );
				if ( $distance >= $target ) {
					$meters = $target;
					$completion_date = substr( (string) $row->workout_start, 0, 10 );
					break;
				}
				continue;
			}
			$meters += $distance;
			if ( $meters >= $target ) {
				$meters = $target;
				$completion_date = substr( (string) $row->workout_start, 0, 10 );
				break;
			}
		}
		if ( $single_workout && null === $completion_date ) $meters = $best_single_workout;
		return array(
			'progress_meters' => $meters,
			'progress_percent' => round( 100 * $meters / $target, 1 ),
			'best_pace_ms' => null,
			'achieved_at' => null,
			'completion_date' => $completion_date,
			'status' => self::status( $goal, null !== $completion_date ),
		);
	}

	private static function status( $goal, $achieved ) {
		if ( $achieved ) return 'achieved';
		$today = wp_date( 'Y-m-d', null, wp_timezone() );
		if ( $today < $goal['start_date'] ) return 'upcoming';
		if ( $today > $goal['end_date'] ) return 'missed';
		return 'in_progress';
	}

	private static function calendar_row( $goal, $date, $marker ) {
		return array(
			'id' => $goal['id'], 'goal_type' => $goal['goal_type'], 'title' => $goal['title'], 'date' => $date,
			'marker' => $marker, 'course_unit' => $goal['course_unit'], 'target_distance_m' => $goal['target_distance_m'],
			'target_distance_value' => $goal['target_distance_value'], 'target_pace_ms' => $goal['target_pace_ms'],
			'stroke' => $goal['stroke'], 'start_date' => $goal['start_date'], 'end_date' => $goal['end_date'],
			'progress_meters' => $goal['progress_meters'], 'progress_percent' => $goal['progress_percent'],
			'best_pace_ms' => $goal['best_pace_ms'], 'completion_date' => $goal['completion_date'] ?? null,
			'single_workout' => self::SINGLE_WORKOUT === $goal['goal_type'], 'status' => $goal['status'],
		);
	}

	private static function valid_date( $value ) {
		if ( ! is_scalar( $value ) ) return false;
		$value = trim( (string) $value );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $date || ( false !== $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d' ) !== $value ) return false;
		return $value;
	}

	private static function next_day( $date ) {
		return ( new \DateTimeImmutable( $date, wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' );
	}

	private static function format_native( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
	}
}
