<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Authoritative storage and normalized projection for manually entered pool workouts.
 *
 * This service is intentionally separate from Importer: a manual workout has no
 * preserved source file and must never acquire an imports row.
 */
final class Manual_Workout {
	const SCHEMA_VERSION = '1';

	public static function validate_document( $document ) {
		if ( ! is_array( $document ) ) return new \WP_Error( 'swimlog_manual_document', __( 'Manual workout data must be a structured document.', 'chriss-swim-training-progress-evaluation' ) );
		$workout = isset( $document['workout'] ) && is_array( $document['workout'] ) ? $document['workout'] : array();
		if ( empty( $workout['workout_start'] ) || ! self::valid_datetime( $workout['workout_start'] ) ) return new \WP_Error( 'swimlog_manual_start', __( 'Enter a valid workout date and time.', 'chriss-swim-training-progress-evaluation' ) );
		$unit = strtolower( trim( (string) ( $workout['pool_length_unit'] ?? '' ) ) );
		if ( ! in_array( $unit, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_manual_course', __( 'Manual pool workouts require a meter or yard course.', 'chriss-swim-training-progress-evaluation' ) );
		$pool = self::positive_number_or_null( $workout['original_pool_length'] ?? null );
		if ( null === $pool ) return new \WP_Error( 'swimlog_manual_pool', __( 'Manual pool workouts require a positive pool length.', 'chriss-swim-training-progress-evaluation' ) );
		$total = self::nonnegative_number_or_null( $workout['original_distance'] ?? null );
		if ( isset( $workout['original_distance'] ) && '' !== $workout['original_distance'] && null === $total ) return new \WP_Error( 'swimlog_manual_distance', __( 'Workout distance must be zero or greater.', 'chriss-swim-training-progress-evaluation' ) );
		$elapsed = self::nonnegative_int_or_null( $workout['elapsed_time_ms'] ?? null );
		if ( isset( $workout['elapsed_time_ms'] ) && '' !== $workout['elapsed_time_ms'] && null === $elapsed ) return new \WP_Error( 'swimlog_manual_elapsed', __( 'Workout duration must be zero or greater.', 'chriss-swim-training-progress-evaluation' ) );

		$segments = isset( $document['segments'] ) ? $document['segments'] : array();
		if ( ! is_array( $segments ) ) return new \WP_Error( 'swimlog_manual_segments', __( 'Manual workout segments must be a list.', 'chriss-swim-training-progress-evaluation' ) );
		$clean_segments = array();
		foreach ( array_values( $segments ) as $index => $segment ) {
			$clean = self::validate_segment( $segment, $index );
			if ( is_wp_error( $clean ) ) return $clean;
			$clean_segments[] = $clean;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'workout' => array(
				'workout_start' => (string) $workout['workout_start'],
				'location_id' => absint( $workout['location_id'] ?? 0 ),
				'original_distance' => $total,
				'original_distance_unit' => $unit,
				'elapsed_time_ms' => $elapsed,
				'original_pool_length' => $pool,
				'pool_length_unit' => $unit,
				'notes' => isset( $workout['notes'] ) ? sanitize_textarea_field( (string) $workout['notes'] ) : '',
			),
			'segments' => $clean_segments,
		);
	}

	private static function validate_segment( $segment, $index ) {
		if ( ! is_array( $segment ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_segment', sprintf( __( 'Segment %d is invalid.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		$type = strtolower( trim( (string) ( $segment['type'] ?? 'active' ) ) );
		if ( ! in_array( $type, array( 'active', 'rest' ), true ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_segment_type', sprintf( __( 'Segment %d has an unsupported type.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		$stroke = strtoupper( trim( (string) ( $segment['stroke'] ?? '' ) ) );
		if ( '' !== $stroke && ! in_array( $stroke, array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED', 'UNKNOWN' ), true ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_stroke', sprintf( __( 'Segment %d has an unsupported stroke.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		$reps = absint( $segment['repetitions'] ?? 1 );
		$distance = self::positive_number_or_null( $segment['distance_per_rep'] ?? null );
		if ( 'active' === $type && ( $reps < 1 || null === $distance ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_segment_distance', sprintf( __( 'Active segment %d requires repetitions and a positive distance.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}

		$timing = strtolower( trim( (string) ( $segment['timing_type'] ?? 'unknown' ) ) );
		if ( ! in_array( $timing, array( 'unknown', 'measured_rep', 'measured_set', 'derived', 'interval' ), true ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_timing', sprintf( __( 'Segment %d has an unsupported timing type.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		$rep_times = array();
		foreach ( (array) ( $segment['rep_times_ms'] ?? array() ) as $value ) {
			$value = self::positive_int_or_null( $value );
			if ( null === $value ) {
				/* translators: %d: one-based manual workout segment number. */
				return new \WP_Error( 'swimlog_manual_rep_time', sprintf( __( 'Segment %d contains an invalid measured repetition time.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
			}
			$rep_times[] = $value;
		}
		if ( 'measured_rep' === $timing && count( $rep_times ) !== $reps ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_rep_count', sprintf( __( 'Segment %d must contain one measured time for every repetition.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		if ( 'measured_rep' !== $timing && ! empty( $rep_times ) ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_rep_provenance', sprintf( __( 'Segment %d repetition times require measured-repetition timing.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}

		$set_time = self::positive_int_or_null( $segment['set_time_ms'] ?? null );
		$interval = self::positive_int_or_null( $segment['interval_ms'] ?? null );
		if ( 'measured_set' === $timing && null === $set_time ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_set_time', sprintf( __( 'Segment %d requires a measured set time.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}
		if ( 'interval' === $timing && null === $interval ) {
			/* translators: %d: one-based manual workout segment number. */
			return new \WP_Error( 'swimlog_manual_interval', sprintf( __( 'Segment %d requires an interval/send-off.', 'chriss-swim-training-progress-evaluation' ), $index + 1 ) );
		}

		return array(
			'type' => $type,
			'repetitions' => 'rest' === $type ? 1 : $reps,
			'distance_per_rep' => 'rest' === $type ? null : $distance,
			'stroke' => '' === $stroke ? 'UNKNOWN' : $stroke,
			'timing_type' => $timing,
			'rep_times_ms' => $rep_times,
			'set_time_ms' => $set_time,
			'interval_ms' => $interval,
			'rest_time_ms' => self::positive_int_or_null( $segment['rest_time_ms'] ?? null ),
			'notes' => isset( $segment['notes'] ) ? sanitize_textarea_field( (string) $segment['notes'] ) : '',
		);
	}

	public static function normalize_document( $document ) {
		$document = self::validate_document( $document );
		if ( is_wp_error( $document ) ) return $document;
		$w = $document['workout'];
		$unit = $w['pool_length_unit'];
		$factor = 'yd' === $unit ? 0.9144 : 1.0;
		$pool_m = $w['original_pool_length'] * $factor;
		$laps = array();
		$lengths = array();
		$sequence = 1;
		$derived_distance = 0.0;
		foreach ( $document['segments'] as $i => $segment ) {
			$distance_native = 'active' === $segment['type'] ? $segment['repetitions'] * $segment['distance_per_rep'] : 0.0;
			$derived_distance += $distance_native;
			$laps[] = array(
				'sequence_no' => $i + 1,
				'start_time' => null,
				'start_offset_ms' => null,
				'distance_m' => $distance_native > 0 ? $distance_native * $factor : 0,
				'elapsed_time_ms' => 'measured_set' === $segment['timing_type'] ? $segment['set_time_ms'] : null,
				'moving_time_ms' => null,
				'stroke' => $segment['stroke'],
				'source_lap_index' => null,
				'raw_metadata' => wp_json_encode( array( 'manual' => true, 'segment' => $segment ) ),
			);
			if ( 'rest' === $segment['type'] ) {
				$lengths[] = array( 'sequence_no' => $sequence++, 'start_time' => null, 'start_offset_ms' => null, 'distance_m' => 0, 'elapsed_time_ms' => $segment['rest_time_ms'], 'stroke' => 'UNKNOWN', 'length_type' => 'rest', 'source_length_index' => null, 'raw_metadata' => wp_json_encode( array( 'manual' => true, 'timing_provenance' => null === $segment['rest_time_ms'] ? 'unknown' : 'measured' ) ) );
				continue;
			}
			$ratio = $segment['distance_per_rep'] / $w['original_pool_length'];
			$pool_lengths_per_rep = (int) round( $ratio );
			if ( $pool_lengths_per_rep < 1 || abs( $ratio - $pool_lengths_per_rep ) > 0.000001 ) continue;
			for ( $rep = 0; $rep < $segment['repetitions']; $rep++ ) {
				for ( $len = 0; $len < $pool_lengths_per_rep; $len++ ) {
					$lengths[] = array(
						'sequence_no' => $sequence++,
						'start_time' => null,
						'start_offset_ms' => null,
						'distance_m' => $pool_m,
						'elapsed_time_ms' => null,
						'stroke' => $segment['stroke'],
						'length_type' => 'active',
						'source_length_index' => null,
						'raw_metadata' => wp_json_encode( array( 'manual' => true, 'segment' => $i + 1, 'repetition' => $rep + 1, 'timing_provenance' => $segment['timing_type'], 'measured_rep_time_ms' => 'measured_rep' === $segment['timing_type'] ? $segment['rep_times_ms'][$rep] : null ) ),
					);
				}
			}
		}
		$total_native = null !== $w['original_distance'] ? $w['original_distance'] : ( $derived_distance > 0 ? $derived_distance : null );
		return array(
			'document' => $document,
			'workout' => array(
				'source_type' => 'manual', 'authoritative_import_id' => null, 'workout_type' => 'pool',
				'workout_start' => $w['workout_start'], 'workout_end' => null,
				'total_distance_m' => null === $total_native ? null : $total_native * $factor,
				'original_distance' => $total_native, 'original_distance_unit' => $unit,
				'elapsed_time_ms' => $w['elapsed_time_ms'], 'moving_time_ms' => null,
				'pool_length_m' => $pool_m, 'original_pool_length' => $w['original_pool_length'], 'pool_length_unit' => $unit,
				'primary_stroke' => self::primary_stroke( $document['segments'] ),
				'device_manufacturer' => null, 'device_model' => null, 'source_activity_id' => null, 'notes' => $w['notes'],
			),
			'laps' => $laps, 'lengths' => $lengths,
		);
	}

	public static function save( $user_id, $document, $workout_id = 0 ) {
		global $wpdb;
		$user_id = absint( $user_id ); $workout_id = absint( $workout_id );
		if ( ! $user_id ) return new \WP_Error( 'swimlog_manual_user', __( 'A valid swimmer is required.', 'chriss-swim-training-progress-evaluation' ) );
		$normalized = self::normalize_document( $document );
		if ( is_wp_error( $normalized ) ) return $normalized;
		$location_id = absint( $normalized['document']['workout']['location_id'] );
		if ( $location_id && ! self::location_belongs_to_user( $location_id, $user_id ) ) return new \WP_Error( 'swimlog_manual_location', __( 'The selected location does not belong to this swimmer.', 'chriss-swim-training-progress-evaluation' ) );
		$wt = Database::table( 'workouts' ); $mt = Database::table( 'manual_workouts' );
		$lt = Database::table( 'laps' ); $nt = Database::table( 'lengths' ); $tt = Database::table( 'trackpoints' ); $pt = Database::table( 'performances' );
		$now = current_time( 'mysql' );
		if ( $workout_id ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,user_id,source_type FROM $wt WHERE id=%d AND user_id=%d", $workout_id, $user_id ) );
			if ( ! $existing ) return new \WP_Error( 'swimlog_manual_not_found', __( 'Manual workout not found.', 'chriss-swim-training-progress-evaluation' ) );
			if ( 'manual' !== (string) $existing->source_type ) return new \WP_Error( 'swimlog_manual_imported', __( 'Imported workouts cannot be rewritten as manual workouts.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new \WP_Error( 'swimlog_manual_transaction', __( 'The manual workout transaction could not be started.', 'chriss-swim-training-progress-evaluation' ) );
		try {
			$values = $normalized['workout']; $values['user_id'] = $user_id; $values['location_id'] = $location_id ?: null; $values['updated_at'] = $now;
			if ( $workout_id ) {
				unset( $values['user_id'] );
				if ( false === $wpdb->update( $wt, $values, array( 'id' => $workout_id, 'user_id' => $user_id ) ) ) throw new \Exception( 'workout' );
			} else {
				$values['created_at'] = $now;
				if ( ! $wpdb->insert( $wt, $values ) ) throw new \Exception( 'workout' );
				$workout_id = (int) $wpdb->insert_id;
			}
			$source = wp_json_encode( $normalized['document'] );
			$source_row = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $mt WHERE workout_id=%d", $workout_id ) );
			if ( $source_row ) {
				if ( false === $wpdb->update( $mt, array( 'user_id' => $user_id, 'schema_version' => self::SCHEMA_VERSION, 'source_document' => $source, 'updated_at' => $now ), array( 'id' => (int) $source_row, 'workout_id' => $workout_id ) ) ) throw new \Exception( 'source' );
			} else {
				if ( ! $wpdb->insert( $mt, array( 'workout_id' => $workout_id, 'user_id' => $user_id, 'schema_version' => self::SCHEMA_VERSION, 'source_document' => $source, 'created_at' => $now, 'updated_at' => $now ) ) ) throw new \Exception( 'source' );
			}
			foreach ( array( $lt, $nt, $tt ) as $table ) if ( false === $wpdb->delete( $table, array( 'workout_id' => $workout_id ), array( '%d' ) ) ) throw new \Exception( 'structure' );
			if ( false === $wpdb->delete( $pt, array( 'workout_id' => $workout_id, 'user_id' => $user_id ), array( '%d', '%d' ) ) ) throw new \Exception( 'performances' );
			foreach ( $normalized['laps'] as $row ) { $row['workout_id'] = $workout_id; $row['created_at'] = $now; if ( ! $wpdb->insert( $lt, $row ) ) throw new \Exception( 'lap' ); }
			foreach ( $normalized['lengths'] as $row ) { $row['workout_id'] = $workout_id; $row['lap_id'] = null; $row['created_at'] = $now; if ( ! $wpdb->insert( $nt, $row ) ) throw new \Exception( 'length' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new \Exception( 'commit' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'swimlog_manual_save_failed', __( 'The manual workout could not be saved and all changes were rolled back.', 'chriss-swim-training-progress-evaluation' ) );
		}
		return array( 'workout_id' => $workout_id, 'source_type' => 'manual', 'laps' => count( $normalized['laps'] ), 'lengths' => count( $normalized['lengths'] ) );
	}

	public static function rebuild( $workout_id, $user_id ) {
		global $wpdb;
		$mt = Database::table( 'manual_workouts' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT source_document FROM $mt WHERE workout_id=%d AND user_id=%d", absint( $workout_id ), absint( $user_id ) ) );
		if ( ! $row ) return new \WP_Error( 'swimlog_manual_source_missing', __( 'The authoritative manual workout source is missing.', 'chriss-swim-training-progress-evaluation' ) );
		$document = json_decode( (string) $row->source_document, true );
		if ( ! is_array( $document ) ) return new \WP_Error( 'swimlog_manual_source_invalid', __( 'The authoritative manual workout source is invalid.', 'chriss-swim-training-progress-evaluation' ) );
		return self::save( $user_id, $document, $workout_id );
	}

	private static function location_belongs_to_user( $location_id, $user_id ) {
		global $wpdb; $table = Database::table( 'locations' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id=%d AND user_id=%d", $location_id, $user_id ) );
	}
	private static function primary_stroke( $segments ) {
		$strokes = array();
		foreach ( $segments as $segment ) if ( 'active' === $segment['type'] && 'UNKNOWN' !== $segment['stroke'] ) $strokes[$segment['stroke']] = true;
		if ( 1 === count( $strokes ) ) return (string) key( $strokes );
		return count( $strokes ) > 1 ? 'MIXED' : null;
	}
	private static function valid_datetime( $value ) {
		$d = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $value, wp_timezone() );
		return $d && $d->format( 'Y-m-d H:i:s' ) === (string) $value;
	}
	private static function positive_number_or_null( $value ) { return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null; }
	private static function nonnegative_number_or_null( $value ) { return is_numeric( $value ) && (float) $value >= 0 ? (float) $value : null; }
	private static function positive_int_or_null( $value ) { return is_numeric( $value ) && (int) $value > 0 && (float) $value === (float) (int) $value ? (int) $value : null; }
	private static function nonnegative_int_or_null( $value ) { return is_numeric( $value ) && (int) $value >= 0 && (float) $value === (float) (int) $value ? (int) $value : null; }
}
