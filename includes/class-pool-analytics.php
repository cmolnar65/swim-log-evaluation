<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Derived pool performance and efficiency analytics.
 *
 * Analytics are calculated from normalized active lengths. Missing timing or
 * stroke-count data stays missing; this class never manufactures source data.
 */
final class Pool_Analytics {
	const DEFAULT_LOOKBACK_MONTHS = 6;
	const MAX_LOOKBACK_MONTHS = 24;
	const STROKES = array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED', 'UNKNOWN' );

	public static function for_user( $user_id, $filters = array() ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$course = sanitize_key( $filters['course'] ?? 'm' );
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) $course = 'm';
		$stroke = strtoupper( sanitize_key( $filters['stroke'] ?? '' ) );
		if ( ! in_array( $stroke, self::STROKES, true ) ) $stroke = '';
		$months = max( 1, min( self::MAX_LOOKBACK_MONTHS, (int) ( $filters['months'] ?? self::DEFAULT_LOOKBACK_MONTHS ) ) );

		$timezone = wp_timezone();
		$end = new \DateTimeImmutable( 'now', $timezone );
		$start = $end->modify( '-' . $months . ' months' );
		$workouts = Database::table( 'workouts' );
		$lengths = Database::table( 'lengths' );

		if ( '' !== $stroke ) {
			$rows = Database::read( 'get_results', 
				$wpdb->prepare(
					"SELECT w.id workout_id,w.workout_start,w.pool_length_unit,w.original_pool_length,w.pool_length_m,
						n.sequence_no,n.distance_m,n.elapsed_time_ms,n.stroke,n.stroke_count,n.length_type
					FROM %i w
					INNER JOIN %i n ON n.workout_id=w.id
					WHERE w.user_id=%d
						AND w.workout_type='pool'
						AND w.pool_length_unit=%s
						AND w.workout_start >= %s
						AND w.workout_start <= %s
						AND n.length_type='active'
						AND n.distance_m>0
						AND n.stroke=%s
					ORDER BY w.workout_start ASC,w.id ASC,n.sequence_no ASC",$workouts, $lengths, 
					$user_id,
					$course,
					$start->format( 'Y-m-d H:i:s' ),
					$end->format( 'Y-m-d H:i:s' ),
					$stroke
				)
			);
		} else {
			$rows = Database::read( 'get_results', 
				$wpdb->prepare(
					"SELECT w.id workout_id,w.workout_start,w.pool_length_unit,w.original_pool_length,w.pool_length_m,
						n.sequence_no,n.distance_m,n.elapsed_time_ms,n.stroke,n.stroke_count,n.length_type
					FROM %i w
					INNER JOIN %i n ON n.workout_id=w.id
					WHERE w.user_id=%d
						AND w.workout_type='pool'
						AND w.pool_length_unit=%s
						AND w.workout_start >= %s
						AND w.workout_start <= %s
						AND n.length_type='active'
						AND n.distance_m>0
					ORDER BY w.workout_start ASC,w.id ASC,n.sequence_no ASC",$workouts, $lengths, 
					$user_id,
					$course,
					$start->format( 'Y-m-d H:i:s' ),
					$end->format( 'Y-m-d H:i:s' )
				)
			);
		}

		return array(
			'filters' => array(
				'course' => $course,
				'stroke' => $stroke,
				'months' => $months,
				'start' => $start->format( 'Y-m-d H:i:s' ),
				'end' => $end->format( 'Y-m-d H:i:s' ),
			),
			'groups' => self::aggregate_rows( $rows ),
			'source_row_count' => count( $rows ),
		);
	}

	/**
	 * Aggregate normalized active-length rows.
	 *
	 * Kept public so the metric rules can be regression-tested without a
	 * WordPress database fixture.
	 */
	public static function aggregate_rows( $rows ) {
		$groups = array();
		foreach ( (array) $rows as $row ) {
			if ( 'active' !== (string) ( $row->length_type ?? 'active' ) ) continue;
			$course = (string) ( $row->pool_length_unit ?? '' );
			if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) continue;
			$distance_m = (float) ( $row->distance_m ?? 0 );
			if ( $distance_m <= 0 ) continue;

			$native_distance = 'yd' === $course ? $distance_m / 0.9144 : $distance_m;
			$pool_native = (float) ( $row->original_pool_length ?? 0 );
			if ( $pool_native <= 0 ) {
				$pool_m = (float) ( $row->pool_length_m ?? 0 );
				$pool_native = 'yd' === $course ? $pool_m / 0.9144 : $pool_m;
			}
			if ( $pool_native <= 0 ) $pool_native = $native_distance;
			$pool_native = round( $pool_native, 3 );
			$stroke = strtoupper( trim( (string) ( $row->stroke ?? '' ) ) );
			if ( ! in_array( $stroke, self::STROKES, true ) ) $stroke = 'UNKNOWN';

			$key = $course . '|' . number_format( $pool_native, 3, '.', '' ) . '|' . $stroke;
			if ( ! isset( $groups[ $key ] ) ) $groups[ $key ] = self::new_bucket( $course, $pool_native, $stroke );
			self::add_row( $groups[ $key ], $row, $native_distance );

			$month = substr( (string) ( $row->workout_start ?? '' ), 0, 7 );
			if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
				if ( ! isset( $groups[ $key ]['months'][ $month ] ) ) $groups[ $key ]['months'][ $month ] = self::new_bucket( $course, $pool_native, $stroke );
				self::add_row( $groups[ $key ]['months'][ $month ], $row, $native_distance );
			}
		}

		$out = array();
		foreach ( $groups as $group ) {
			$months = array();
			ksort( $group['months'] );
			foreach ( $group['months'] as $month => $bucket ) {
				$final = self::finalize_bucket( $bucket );
				$final['month'] = $month;
				$months[] = $final;
			}
			$group['months'] = array();
			$final = self::finalize_bucket( $group );
			$final['months'] = $months;
			$out[] = $final;
		}
		usort(
			$out,
			static function ( $a, $b ) {
				$course = strcmp( $a['course_unit'], $b['course_unit'] );
				if ( 0 !== $course ) return $course;
				if ( $a['pool_length'] !== $b['pool_length'] ) return $a['pool_length'] <=> $b['pool_length'];
				return strcmp( $a['stroke'], $b['stroke'] );
			}
		);
		return $out;
	}

	private static function new_bucket( $course, $pool_length, $stroke ) {
		return array(
			'course_unit' => $course,
			'pool_length' => (float) $pool_length,
			'stroke' => $stroke,
			'active_lengths' => 0,
			'distance_native' => 0.0,
			'timed_lengths' => 0,
			'timed_distance_native' => 0.0,
			'total_elapsed_ms' => 0,
			'best_pace_100_ms' => null,
			'pace_sum' => 0.0,
			'pace_sum_squares' => 0.0,
			'pace_samples' => 0,
			'stroke_count_sum' => 0.0,
			'stroke_count_lengths' => 0,
			'normalized_stroke_sum' => 0.0,
			'swolf_sum' => 0.0,
			'swolf_lengths' => 0,
			'workouts' => array(),
			'months' => array(),
		);
	}

	private static function add_row( &$bucket, $row, $native_distance ) {
		$bucket['active_lengths']++;
		$bucket['distance_native'] += $native_distance;
		$workout_id = absint( $row->workout_id ?? 0 );
		if ( $workout_id ) $bucket['workouts'][ $workout_id ] = true;

		$elapsed = null !== ( $row->elapsed_time_ms ?? null ) ? (int) $row->elapsed_time_ms : 0;
		$pace = null;
		if ( $elapsed > 0 && $native_distance > 0 ) {
			$pace = $elapsed / ( $native_distance / 100 );
			$bucket['timed_lengths']++;
			$bucket['timed_distance_native'] += $native_distance;
			$bucket['total_elapsed_ms'] += $elapsed;
			$bucket['pace_sum'] += $pace;
			$bucket['pace_sum_squares'] += $pace * $pace;
			$bucket['pace_samples']++;
			if ( null === $bucket['best_pace_100_ms'] || $pace < $bucket['best_pace_100_ms'] ) $bucket['best_pace_100_ms'] = $pace;
		}

		$stroke_count = $row->stroke_count ?? null;
		if ( null !== $stroke_count && '' !== $stroke_count && is_numeric( $stroke_count ) && (float) $stroke_count >= 0 ) {
			$stroke_count = (float) $stroke_count;
			$bucket['stroke_count_sum'] += $stroke_count;
			$bucket['stroke_count_lengths']++;
			if ( $native_distance > 0 ) $bucket['normalized_stroke_sum'] += $stroke_count / ( $native_distance / 25 );
			if ( null !== $pace && $elapsed > 0 ) {
				$bucket['swolf_sum'] += ( $elapsed / 1000 ) + $stroke_count;
				$bucket['swolf_lengths']++;
			}
		}
	}

	private static function finalize_bucket( $bucket ) {
		$pace_mean = $bucket['pace_samples'] ? $bucket['pace_sum'] / $bucket['pace_samples'] : null;
		$variance = null;
		if ( $bucket['pace_samples'] >= 2 && $pace_mean > 0 ) {
			$variance = max( 0.0, ( $bucket['pace_sum_squares'] / $bucket['pace_samples'] ) - ( $pace_mean * $pace_mean ) );
		}
		return array(
			'course_unit' => $bucket['course_unit'],
			'pool_length' => $bucket['pool_length'],
			'stroke' => $bucket['stroke'],
			'workout_count' => count( $bucket['workouts'] ),
			'active_lengths' => $bucket['active_lengths'],
			'distance_native' => round( $bucket['distance_native'], 3 ),
			'timed_lengths' => $bucket['timed_lengths'],
			'avg_pace_100_ms' => $bucket['timed_distance_native'] > 0 ? (int) round( $bucket['total_elapsed_ms'] / ( $bucket['timed_distance_native'] / 100 ) ) : null,
			'best_pace_100_ms' => null !== $bucket['best_pace_100_ms'] ? (int) round( $bucket['best_pace_100_ms'] ) : null,
			'pace_consistency_cv' => null !== $variance ? ( sqrt( $variance ) / $pace_mean ) * 100 : null,
			'stroke_count_lengths' => $bucket['stroke_count_lengths'],
			'stroke_count_coverage_percent' => $bucket['active_lengths'] ? ( $bucket['stroke_count_lengths'] / $bucket['active_lengths'] ) * 100 : 0.0,
			'avg_stroke_count' => $bucket['stroke_count_lengths'] ? $bucket['stroke_count_sum'] / $bucket['stroke_count_lengths'] : null,
			'normalized_strokes_per_25' => $bucket['stroke_count_lengths'] ? $bucket['normalized_stroke_sum'] / $bucket['stroke_count_lengths'] : null,
			'swolf_lengths' => $bucket['swolf_lengths'],
			'avg_swolf' => $bucket['swolf_lengths'] ? $bucket['swolf_sum'] / $bucket['swolf_lengths'] : null,
			'metric_metadata' => array(
				'avg_pace_100_ms' => array( 'unit' => 'ms/100 native units', 'provenance' => 'derived', 'source' => 'recorded active-length timing and distance' ),
				'best_pace_100_ms' => array( 'unit' => 'ms/100 native units', 'provenance' => 'derived', 'source' => 'fastest recorded active-length pace' ),
				'pace_consistency_cv' => array( 'unit' => 'percent', 'provenance' => 'derived', 'source' => 'population coefficient of variation of recorded active-length pace' ),
				'avg_stroke_count' => array( 'unit' => 'strokes/recorded length', 'provenance' => 'derived', 'source' => 'recorded per-length stroke counts only' ),
				'normalized_strokes_per_25' => array( 'unit' => 'strokes/25 native units', 'provenance' => 'derived', 'source' => 'recorded stroke count normalized by recorded length distance' ),
				'avg_swolf' => array( 'unit' => 'seconds plus strokes', 'provenance' => 'derived', 'source' => 'recorded elapsed seconds plus recorded stroke count on the same active length' ),
			),
		);
	}
}

