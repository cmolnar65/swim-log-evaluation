<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Descriptive event-preparation evidence.
 *
 * This service summarizes recorded training and performance history for an
 * upcoming event. It deliberately does not calculate a readiness score,
 * predict a race result, or infer physiological fitness/recovery.
 */
final class Race_Readiness {
	public static function for_event( $user_id, $event, $lookback_days = 42 ) {
		global $wpdb;

		$course = strtolower( (string) ( $event->course_unit ?? '' ) );
		$stroke = strtoupper( (string) ( $event->stroke ?? '' ) );
		$distance = absint( $event->distance_value ?? 0 );
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) || ! in_array( $stroke, array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED' ), true ) || $distance <= 0 ) {
			return array();
		}

		$lookback_days = max( 1, min( 365, (int) $lookback_days ) );
		$timezone = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $timezone );
		$end = $now->format( 'Y-m-d 23:59:59' );
		$start = $now->modify( '-' . ( $lookback_days - 1 ) . ' days' )->format( 'Y-m-d 00:00:00' );

		$event_seconds = null;
		$days_until_event = null;
		if ( ! empty( $event->event_date ) ) {
			try {
				$event_time = ! empty( $event->event_time ) ? (string) $event->event_time : '12:00:00';
				$event_at = new \DateTimeImmutable( (string) $event->event_date . ' ' . $event_time, $timezone );
				$event_seconds = $event_at->getTimestamp() - $now->getTimestamp();
				$days_until_event = (int) floor( max( 0, $event_seconds ) / DAY_IN_SECONDS );
			} catch ( \Exception $e ) {
				$event_seconds = null;
				$days_until_event = null;
			}
		}

		$workouts = Database::table( 'workouts' );
		$lengths = Database::table( 'lengths' );
		$performances = Database::table( 'performances' );

		$volume = Database::read( 'get_row', 
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT w.id) workout_count,COUNT(*) length_count,SUM(CASE WHEN w.pool_length_unit='yd' THEN n.distance_m/0.9144 ELSE n.distance_m END) distance_native,SUM(COALESCE(n.elapsed_time_ms,0)) active_time_ms,MAX(w.workout_start) last_workout_start FROM %i w INNER JOIN %i n ON n.workout_id=w.id WHERE w.user_id=%d AND w.workout_type='pool' AND w.pool_length_unit=%s AND w.workout_start >= %s AND w.workout_start <= %s AND n.length_type='active' AND n.stroke=%s AND n.distance_m>0",$workouts, $lengths, 
				$user_id,
				$course,
				$start,
				$end,
				$stroke
			)
		);

		$longest = Database::read( 'get_row', 
			$wpdb->prepare(
				"SELECT p.distance_value,p.duration_ms,p.achieved_at,p.workout_id FROM %i p INNER JOIN %i w ON w.id=p.workout_id WHERE p.user_id=%d AND w.workout_type='pool' AND p.course_unit=%s AND p.stroke=%s AND w.workout_start >= %s AND w.workout_start <= %s ORDER BY p.distance_value DESC,p.duration_ms ASC,p.achieved_at DESC,p.id DESC LIMIT 1",$performances, $workouts, 
				$user_id,
				$course,
				$stroke,
				$start,
				$end
			)
		);

		$lifetime_pb = Database::read( 'get_row', 
			$wpdb->prepare(
				"SELECT id,duration_ms,achieved_at,workout_id FROM %i WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s AND is_personal_best=1 ORDER BY achieved_at ASC,id ASC LIMIT 1",$performances, 
				$user_id,
				$distance,
				$course,
				$stroke
			)
		);

		$recent = Database::read( 'get_results', 
			$wpdb->prepare(
				"SELECT id,duration_ms,achieved_at,workout_id FROM %i WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s AND achieved_at >= %s AND achieved_at <= %s ORDER BY achieved_at ASC,id ASC",$performances, 
				$user_id,
				$distance,
				$course,
				$stroke,
				$start,
				$end
			)
		);

		$recent_best = null;
		$total_duration = 0;
		foreach ( $recent as $row ) {
			$total_duration += (int) $row->duration_ms;
			if ( null === $recent_best || (int) $row->duration_ms < (int) $recent_best->duration_ms ) {
				$recent_best = $row;
			}
		}

		$first_recent = $recent ? $recent[0] : null;
		$latest_recent = $recent ? $recent[ count( $recent ) - 1 ] : null;
		$recent_count = count( $recent );
		$average_duration = $recent_count ? (int) round( $total_duration / $recent_count ) : null;
		$pace_divisor = $distance > 0 ? $distance / 100 : 0;
		$recent_change_ms = $first_recent && $latest_recent ? (int) $first_recent->duration_ms - (int) $latest_recent->duration_ms : null;
		$recent_change_percent = $first_recent && (int) $first_recent->duration_ms > 0
			? ( ( (int) $first_recent->duration_ms - (int) $latest_recent->duration_ms ) / (int) $first_recent->duration_ms ) * 100
			: null;
		$pb_delta_ms = $recent_best && $lifetime_pb ? (int) $recent_best->duration_ms - (int) $lifetime_pb->duration_ms : null;
		$pb_delta_percent = $recent_best && $lifetime_pb && (int) $lifetime_pb->duration_ms > 0
			? ( $pb_delta_ms / (int) $lifetime_pb->duration_ms ) * 100
			: null;

		return array(
			'lookback_days' => $lookback_days,
			'course_unit' => $course,
			'stroke' => $stroke,
			'event_distance' => $distance,
			'days_until_event' => $days_until_event,
			'seconds_until_event' => $event_seconds,
			'workout_count' => (int) ( $volume->workout_count ?? 0 ),
			'length_count' => (int) ( $volume->length_count ?? 0 ),
			'distance_native' => (float) ( $volume->distance_native ?? 0 ),
			'active_time_ms' => (int) ( $volume->active_time_ms ?? 0 ),
			'last_workout_start' => $volume->last_workout_start ?? null,
			'longest_recent_distance' => $longest ? (int) $longest->distance_value : 0,
			'longest_recent_duration_ms' => $longest ? (int) $longest->duration_ms : null,
			'longest_recent_workout_id' => $longest ? (int) $longest->workout_id : 0,
			'event_distance_or_longer' => $longest ? (int) $longest->distance_value >= $distance : false,
			'lifetime_pb_duration_ms' => $lifetime_pb ? (int) $lifetime_pb->duration_ms : null,
			'lifetime_pb_achieved_at' => $lifetime_pb->achieved_at ?? null,
			'lifetime_pb_workout_id' => $lifetime_pb ? (int) $lifetime_pb->workout_id : 0,
			'recent_performance_count' => $recent_count,
			'recent_best_duration_ms' => $recent_best ? (int) $recent_best->duration_ms : null,
			'recent_best_achieved_at' => $recent_best->achieved_at ?? null,
			'recent_best_workout_id' => $recent_best ? (int) $recent_best->workout_id : 0,
			'recent_best_pace_100_ms' => $recent_best && $pace_divisor > 0 ? (int) round( (int) $recent_best->duration_ms / $pace_divisor ) : null,
			'recent_average_pace_100_ms' => null !== $average_duration && $pace_divisor > 0 ? (int) round( $average_duration / $pace_divisor ) : null,
			'latest_duration_ms' => $latest_recent ? (int) $latest_recent->duration_ms : null,
			'latest_achieved_at' => $latest_recent->achieved_at ?? null,
			'latest_pace_100_ms' => $latest_recent && $pace_divisor > 0 ? (int) round( (int) $latest_recent->duration_ms / $pace_divisor ) : null,
			'recent_change_ms' => $recent_change_ms,
			'recent_change_percent' => $recent_change_percent,
			'recent_best_vs_pb_ms' => $pb_delta_ms,
			'recent_best_vs_pb_percent' => $pb_delta_percent,
			'descriptive_only' => true,
		);
	}
}

