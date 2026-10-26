<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Longitudinal training trends derived from normalized workout history.
 */
final class Training_Trends {
	const DEFAULT_LOOKBACK_MONTHS = 6;
	const MAX_LOOKBACK_MONTHS = 24;
	const WEEK_SECONDS = 604800;

	public static function for_user( $user_id, $months = self::DEFAULT_LOOKBACK_MONTHS ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$months = max( 1, min( self::MAX_LOOKBACK_MONTHS, (int) $months ) );
		$tz = wp_timezone();
		$end = new \DateTimeImmutable( 'now', $tz );
		$current_start = $end->modify( '-' . $months . ' months' );
		$previous_start = $current_start->modify( '-' . $months . ' months' );
		$w = Database::table( 'workouts' );
		$n = Database::table( 'lengths' );

		$workouts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,workout_start,workout_type,total_distance_m,elapsed_time_ms,moving_time_ms,pool_length_unit
				FROM $w
				WHERE user_id=%d AND workout_start >= %s AND workout_start <= %s
				ORDER BY workout_start ASC,id ASC",
				$user_id,
				$previous_start->format( 'Y-m-d H:i:s' ),
				$end->format( 'Y-m-d H:i:s' )
			)
		);
		$lengths = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.id workout_id,w.workout_start,w.pool_length_unit,
					n.distance_m,n.elapsed_time_ms,n.stroke,n.length_type
				FROM $w w INNER JOIN $n n ON n.workout_id=w.id
				WHERE w.user_id=%d AND w.workout_type='pool'
					AND w.workout_start >= %s AND w.workout_start <= %s
					AND n.length_type='active' AND n.distance_m>0
				ORDER BY w.workout_start ASC,w.id ASC,n.sequence_no ASC",
				$user_id,
				$current_start->format( 'Y-m-d H:i:s' ),
				$end->format( 'Y-m-d H:i:s' )
			)
		);

		return self::aggregate_rows(
			$workouts,
			$lengths,
			array(
				'months' => $months,
				'current_start' => $current_start->format( 'Y-m-d H:i:s' ),
				'previous_start' => $previous_start->format( 'Y-m-d H:i:s' ),
				'end' => $end->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	public static function aggregate_rows( $workouts, $lengths, $window ) {
		$current_start = strtotime( $window['current_start'] );
		$previous_start = strtotime( $window['previous_start'] );
		$end = strtotime( $window['end'] );
		$current = self::new_summary();
		$previous = self::new_summary();
		$weekly = array();
		$monthly = array();

		foreach ( (array) $workouts as $row ) {
			$ts = strtotime( (string) ( $row->workout_start ?? '' ) );
			if ( false === $ts || $ts < $previous_start || $ts > $end ) continue;
			$target = $ts >= $current_start ? 'current' : 'previous';
			if ( 'current' === $target ) self::add_workout( $current, $row ); else self::add_workout( $previous, $row );
			if ( 'current' !== $target ) continue;

			$week = gmdate( 'o-\WW', $ts );
			$month = gmdate( 'Y-m', $ts );
			if ( ! isset( $weekly[ $week ] ) ) $weekly[ $week ] = self::new_summary();
			if ( ! isset( $monthly[ $month ] ) ) $monthly[ $month ] = self::new_summary();
			self::add_workout( $weekly[ $week ], $row );
			self::add_workout( $monthly[ $month ], $row );
		}

		$stroke = array( 'm' => array(), 'yd' => array() );
		$pool_course = array( 'm' => self::new_course(), 'yd' => self::new_course() );
		foreach ( (array) $lengths as $row ) {
			if ( 'active' !== (string) ( $row->length_type ?? 'active' ) ) continue;
			$course = (string) ( $row->pool_length_unit ?? '' );
			if ( ! isset( $stroke[ $course ] ) ) continue;
			$distance_m = (float) ( $row->distance_m ?? 0 );
			if ( $distance_m <= 0 ) continue;
			$native = 'yd' === $course ? $distance_m / 0.9144 : $distance_m;
			$stroke_code = strtoupper( trim( (string) ( $row->stroke ?? '' ) ) );
			if ( '' === $stroke_code ) $stroke_code = 'UNKNOWN';
			if ( ! isset( $stroke[ $course ][ $stroke_code ] ) ) $stroke[ $course ][ $stroke_code ] = array( 'distance_native' => 0.0, 'active_time_ms' => 0, 'length_count' => 0 );
			$stroke[ $course ][ $stroke_code ]['distance_native'] += $native;
			$stroke[ $course ][ $stroke_code ]['active_time_ms'] += max( 0, (int) ( $row->elapsed_time_ms ?? 0 ) );
			$stroke[ $course ][ $stroke_code ]['length_count']++;
			$pool_course[ $course ]['distance_native'] += $native;
			$pool_course[ $course ]['active_time_ms'] += max( 0, (int) ( $row->elapsed_time_ms ?? 0 ) );
			$pool_course[ $course ]['length_count']++;
			$pool_course[ $course ]['workouts'][ absint( $row->workout_id ?? 0 ) ] = true;
		}

		ksort( $weekly );
		ksort( $monthly );
		$weekly_rows = array();
		foreach ( $weekly as $period => $summary ) {
			$summary = self::finalize_summary( $summary );
			$summary['period'] = $period;
			$weekly_rows[] = $summary;
		}
		for ( $i = 0; $i < count( $weekly_rows ); $i++ ) {
			$distance = 0.0;
			$count = 0;
			$workout_count = 0;
			for ( $j = max( 0, $i - 3 ); $j <= $i; $j++ ) {
				$distance += $weekly_rows[ $j ]['distance_m'];
				$workout_count += $weekly_rows[ $j ]['workout_count'];
				$count++;
			}
			$weekly_rows[ $i ]['rolling_4_week_avg_distance_m'] = $count ? $distance / $count : 0.0;
			$weekly_rows[ $i ]['rolling_4_week_avg_workouts'] = $count ? $workout_count / $count : 0.0;
		}
		$monthly_rows = array();
		foreach ( $monthly as $period => $summary ) {
			$summary = self::finalize_summary( $summary );
			$summary['period'] = $period;
			$monthly_rows[] = $summary;
		}

		foreach ( $stroke as &$course_strokes ) {
			uasort( $course_strokes, static function ( $a, $b ) { return $b['distance_native'] <=> $a['distance_native']; } );
			foreach ( $course_strokes as &$item ) $item['distance_native'] = round( $item['distance_native'], 3 );
			unset( $item );
		}
		unset( $course_strokes );
		foreach ( $pool_course as &$course ) {
			$course['workout_count'] = count( array_filter( array_keys( $course['workouts'] ) ) );
			unset( $course['workouts'] );
			$course['distance_native'] = round( $course['distance_native'], 3 );
		}
		unset( $course );

		$current = self::finalize_summary( $current );
		$previous = self::finalize_summary( $previous );
		$current_weeks = max( 1.0, ( $end - $current_start ) / self::WEEK_SECONDS );
		$previous_weeks = max( 1.0, ( $current_start - $previous_start ) / self::WEEK_SECONDS );
		$current['workouts_per_week'] = $current['workout_count'] / $current_weeks;
		$previous['workouts_per_week'] = $previous['workout_count'] / $previous_weeks;
		return array(
			'window' => $window,
			'current' => $current,
			'previous' => $previous,
			'comparison' => array(
				'distance_percent' => self::percent_change( $current['distance_m'], $previous['distance_m'] ),
				'workouts_percent' => self::percent_change( $current['workout_count'], $previous['workout_count'] ),
				'active_time_percent' => self::percent_change( $current['active_time_ms'], $previous['active_time_ms'] ),
				'frequency_percent' => self::percent_change( $current['workouts_per_week'], $previous['workouts_per_week'] ),
			),
			'weekly' => $weekly_rows,
			'monthly' => $monthly_rows,
			'stroke_volume' => $stroke,
			'pool_course' => $pool_course,
			'metric_metadata' => array(
				'distance_m' => array( 'unit' => 'm', 'provenance' => 'recorded/normalized', 'source' => 'workout total_distance_m' ),
				'active_time_ms' => array( 'unit' => 'ms', 'provenance' => 'derived', 'source' => 'pool moving time when present, otherwise elapsed time; open-water moving time when present, otherwise elapsed time' ),
				'stroke_volume' => array( 'unit' => 'native course units', 'provenance' => 'derived', 'source' => 'normalized active pool lengths' ),
				'rolling_4_week_avg_distance_m' => array( 'unit' => 'm/week', 'provenance' => 'derived', 'source' => 'up to four displayed weekly buckets' ),
			),
		);
	}

	private static function new_summary() {
		return array( 'workout_count' => 0, 'distance_m' => 0.0, 'active_time_ms' => 0, 'pool_distance_m' => 0.0, 'open_water_distance_m' => 0.0, 'pool_workouts' => 0, 'open_water_workouts' => 0, 'first_ts' => null, 'last_ts' => null );
	}

	private static function new_course() {
		return array( 'distance_native' => 0.0, 'active_time_ms' => 0, 'length_count' => 0, 'workouts' => array() );
	}

	private static function add_workout( &$summary, $row ) {
		$distance = max( 0.0, (float) ( $row->total_distance_m ?? 0 ) );
		$moving = max( 0, (int) ( $row->moving_time_ms ?? 0 ) );
		$elapsed = max( 0, (int) ( $row->elapsed_time_ms ?? 0 ) );
		$active = $moving > 0 ? $moving : $elapsed;
		$type = 'open_water' === (string) ( $row->workout_type ?? 'pool' ) ? 'open_water' : 'pool';
		$ts = strtotime( (string) ( $row->workout_start ?? '' ) );
		$summary['workout_count']++;
		$summary['distance_m'] += $distance;
		$summary['active_time_ms'] += $active;
		$summary[ $type . '_distance_m' ] += $distance;
		$summary[ $type . '_workouts' ]++;
		if ( false !== $ts ) {
			if ( null === $summary['first_ts'] || $ts < $summary['first_ts'] ) $summary['first_ts'] = $ts;
			if ( null === $summary['last_ts'] || $ts > $summary['last_ts'] ) $summary['last_ts'] = $ts;
		}
	}

	private static function finalize_summary( $summary ) {
		$weeks = 0.0;
		if ( null !== $summary['first_ts'] && null !== $summary['last_ts'] ) $weeks = max( 1.0, ( $summary['last_ts'] - $summary['first_ts'] ) / self::WEEK_SECONDS + 1.0 );
		$summary['distance_m'] = round( $summary['distance_m'], 3 );
		$summary['pool_distance_m'] = round( $summary['pool_distance_m'], 3 );
		$summary['open_water_distance_m'] = round( $summary['open_water_distance_m'], 3 );
		$summary['workouts_per_week'] = $weeks > 0 ? $summary['workout_count'] / $weeks : 0.0;
		unset( $summary['first_ts'], $summary['last_ts'] );
		return $summary;
	}

	private static function percent_change( $current, $previous ) {
		$current = (float) $current;
		$previous = (float) $previous;
		if ( 0.0 === $previous ) return 0.0 === $current ? 0.0 : null;
		return ( ( $current - $previous ) / $previous ) * 100;
	}
}
