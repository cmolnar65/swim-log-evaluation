<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bounded, owner-scoped receiver for the Android app's versioned Health Connect batches. */
final class Health_Connect_Import {
	const MAX_BYTES = 2097152;
	const MAX_WORKOUTS = 50;
	const MAX_MEASUREMENTS = 500;
	const MAX_LAPS = 1000;
	const MAX_SEGMENTS = 1000;
	const MAX_ROUTE_POINTS = 5000;

	public static function register() {
		add_action( 'deleted_user', array( __CLASS__, 'deleted_user' ) );
	}

	/** Remove only Health Connect-owned records when their owner deletes the WordPress account. */
	public static function deleted_user( $owner ) {
		$owner = absint( $owner );
		if ( ! $owner || ! Database::schema_is_complete() ) return;
		global $wpdb;
		Database::write( 'query', $wpdb->prepare( 'DELETE membership FROM %i membership INNER JOIN %i measurement ON measurement.id = membership.measurement_id AND measurement.user_id = membership.user_id WHERE membership.user_id = %d AND measurement.provider = %s', Database::table( 'health_report_members' ), Database::table( 'health_measurements' ), $owner, 'health_connect' ) );
		Database::write( 'delete', Database::table( 'health_measurements' ), array( 'user_id' => $owner, 'provider' => 'health_connect' ) );
		Database::write( 'delete', Database::table( 'health_reports' ), array( 'user_id' => $owner, 'provider' => 'health_connect' ) );
		Database::write( 'delete', Database::table( 'health_connect_tokens' ), array( 'owner_id' => $owner ) );
		Database::write( 'delete', Database::table( 'health_connect_heart_rate_samples' ), array( 'user_id' => $owner ) );
		Database::write( 'delete', Database::table( 'health_connect_activities' ), array( 'user_id' => $owner ) );
		Database::write( 'query', $wpdb->prepare( 'DELETE visibility FROM %i visibility INNER JOIN %i workout ON workout.id = visibility.workout_id AND workout.user_id = visibility.owner_id WHERE workout.user_id = %d AND workout.source_type = %s', Database::table( 'workout_visibility' ), Database::table( 'workouts' ), $owner, 'health_connect' ) );
		Database::write( 'delete', Database::table( 'workouts' ), array( 'user_id' => $owner, 'source_type' => 'health_connect' ) );
	}

	private static function error( $code, $status = 400 ) {
		return new \WP_Error( 'swimlog_hc_' . $code, __( 'The Health Connect batch could not be imported.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => $status ) );
	}

	private static function item_result( $result ) {
		if ( ! is_wp_error( $result ) ) return $result;
		$code = $result->get_error_code();
		if ( false !== strpos( $code, 'source_conflict' ) || false !== strpos( $code, 'ambiguous_correction' ) ) $status = 'conflict';
		elseif ( false !== strpos( $code, 'storage' ) || false !== strpos( $code, 'busy' ) || false !== strpos( $code, 'engine' ) || false !== strpos( $code, 'transaction' ) || false !== strpos( $code, 'read' ) || false !== strpos( $code, 'write' ) ) $status = 'retry';
		else $status = 'rejected';
		return array( 'status' => $status, 'code' => $code );
	}

	private static function json( $value ) {
		return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function time( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 40 || ! preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,6})?(?:Z|[+-]\d\d:\d\d)$/D', $value ) ) return false;
		$normalized = 'Z' === substr( $value, -1 ) ? substr( $value, 0, -1 ) . '+00:00' : $value;
		if ( ! preg_match( '/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d)(?:\.(\d{1,6}))?([+-]\d\d:\d\d)$/D', $normalized, $parts ) ) return false;
		$normalized = $parts[1] . '.' . str_pad( $parts[2] ?? '', 6, '0' ) . $parts[3];
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\\TH:i:s.uP', $normalized );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( ! $date || ( false !== $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d\\TH:i:s.uP' ) !== $normalized ) return false;
		return array( 'utc' => $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\\TH:i:s.u\\Z' ), 'local' => $date->format( 'Y-m-d\\TH:i:s.u' ), 'offset' => $date->getOffset(), 'date' => $date->format( 'Y-m-d' ), 'mysql' => $date->format( 'Y-m-d H:i:s' ) );
	}

	private static function origin( $value ) {
		return is_string( $value ) && strlen( $value ) <= 191 && preg_match( '/^[A-Za-z0-9_.-]+$/D', $value ) ? $value : false;
	}

	private static function source_hash( $origin, $record_id ) {
		return hash( 'sha256', $origin . "\0" . $record_id );
	}

	/** Validate and canonicalize optional Health Connect workout detail before hashing or storage. */
	private static function workout_details( $details, $start, $end ) {
		if ( null === $details ) return null;
		$allowed = array( 'laps', 'segments', 'route', 'total_calories_kcal', 'active_calories_kcal', 'elevation_gain_m', 'floors_climbed', 'average_speed_mps', 'maximum_speed_mps', 'speed_samples', 'step_cadence_samples', 'cycling_cadence_samples', 'power_samples' );
		if ( ! is_array( $details ) || array_diff( array_keys( $details ), $allowed ) ) return false;
		$start_utc = $start['utc'];
		$end_utc = $end['utc'];
		$valid_interval = static function ( $item ) use ( $start_utc, $end_utc ) {
			if ( ! is_array( $item ) || ! is_string( $item['start_time'] ?? null ) || ! is_string( $item['end_time'] ?? null ) ) return false;
			$a = self::time( $item['start_time'] );
			$b = self::time( $item['end_time'] );
			return $a && $b && $a['utc'] >= $start_utc && $b['utc'] <= $end_utc && $a['utc'] < $b['utc'] ? array( $a, $b ) : false;
		};
		$laps = $details['laps'] ?? array();
		if ( ! self::is_list( $laps ) || count( $laps ) > self::MAX_LAPS ) return false;
		$clean_laps = array();
		foreach ( $laps as $lap ) {
			if ( ! is_array( $lap ) || array_diff( array_keys( $lap ), array( 'start_time', 'end_time', 'distance_m' ) ) || ! ( $interval = $valid_interval( $lap ) ) ) return false;
			$distance = $lap['distance_m'] ?? null;
			if ( null !== $distance && ( ! is_numeric( $distance ) || ! is_finite( (float) $distance ) || (float) $distance <= 0 || (float) $distance > 1000000 ) ) return false;
			$clean_laps[] = array( 'start_time' => $interval[0]['utc'], 'end_time' => $interval[1]['utc'], 'distance_m' => null === $distance ? null : (string) $distance );
		}
		$segments = $details['segments'] ?? array();
		if ( ! self::is_list( $segments ) || count( $segments ) > self::MAX_SEGMENTS ) return false;
		$clean_segments = array();
		foreach ( $segments as $segment ) {
			if ( ! is_array( $segment ) || array_diff( array_keys( $segment ), array( 'start_time', 'end_time', 'segment_type', 'segment_label', 'repetitions' ) ) || ! ( $interval = $valid_interval( $segment ) ) || ! is_int( $segment['segment_type'] ?? null ) || $segment['segment_type'] < 0 || $segment['segment_type'] > 1000 ) return false;
			$repetitions = $segment['repetitions'] ?? null;
			if ( null !== $repetitions && ( ! is_int( $repetitions ) || $repetitions < 0 || $repetitions > 100000 ) ) return false;
			$label = $segment['segment_label'] ?? null;
			$allowed_labels = array( 'unknown', 'biking', 'biking_stationary', 'running', 'running_treadmill', 'swimming_backstroke', 'swimming_breaststroke', 'swimming_butterfly', 'swimming_freestyle', 'swimming_mixed', 'swimming_open_water', 'swimming_other', 'swimming_pool', 'walking' );
			if ( null !== $label && ( ! is_string( $label ) || ! in_array( $label, $allowed_labels, true ) ) ) return false;
			$clean_segments[] = array( 'start_time' => $interval[0]['utc'], 'end_time' => $interval[1]['utc'], 'segment_type' => $segment['segment_type'], 'segment_label' => $label, 'repetitions' => $repetitions );
		}
		$route = $details['route'] ?? array();
		if ( ! self::is_list( $route ) || count( $route ) > self::MAX_ROUTE_POINTS ) return false;
		$clean_route = array();
		$previous_time = '';
		foreach ( $route as $point ) {
			if ( ! is_array( $point ) || array_diff( array_keys( $point ), array( 'time', 'latitude', 'longitude', 'altitude_m', 'horizontal_accuracy_m' ) ) || ! is_string( $point['time'] ?? null ) ) return false;
			$time = self::time( $point['time'] );
			$lat = $point['latitude'] ?? null;
			$lon = $point['longitude'] ?? null;
			$alt = $point['altitude_m'] ?? null;
			$accuracy = $point['horizontal_accuracy_m'] ?? null;
			if ( ! $time || $time['utc'] < $start_utc || $time['utc'] > $end_utc || ( '' !== $previous_time && $time['utc'] < $previous_time ) || ! is_numeric( $lat ) || ! is_finite( (float) $lat ) || (float) $lat < -90 || (float) $lat > 90 || ! is_numeric( $lon ) || ! is_finite( (float) $lon ) || (float) $lon < -180 || (float) $lon > 180 ) return false;
			if ( null !== $alt && ( ! is_numeric( $alt ) || ! is_finite( (float) $alt ) || (float) $alt < -1000 || (float) $alt > 10000 ) ) return false;
			if ( null !== $accuracy && ( ! is_numeric( $accuracy ) || ! is_finite( (float) $accuracy ) || (float) $accuracy < 0 || (float) $accuracy > 10000 ) ) return false;
			$clean_route[] = array( 'time' => $time['utc'], 'latitude' => (float) $lat, 'longitude' => (float) $lon, 'altitude_m' => null === $alt ? null : (float) $alt, 'horizontal_accuracy_m' => null === $accuracy ? null : (float) $accuracy );
			$previous_time = $time['utc'];
		}
		$speeds = $details['speed_samples'] ?? array();
		if ( ! self::is_list( $speeds ) || count( $speeds ) > 2000 ) return false;
		$clean_speeds = array();
		foreach ( $speeds as $sample ) {
			if ( ! is_array( $sample ) || array_diff( array_keys( $sample ), array( 'time', 'meters_per_second' ) ) || ! is_string( $sample['time'] ?? null ) || ! is_numeric( $sample['meters_per_second'] ?? null ) ) return false;
			$time = self::time( $sample['time'] );
			$speed = (float) $sample['meters_per_second'];
			if ( ! $time || $time['utc'] < $start_utc || $time['utc'] > $end_utc || ! is_finite( $speed ) || $speed < 0 || $speed > 100 ) return false;
			$clean_speeds[] = array( 'time' => $time['utc'], 'meters_per_second' => $speed );
		}
		$clean_series = array();
		foreach ( array( 'step_cadence_samples' => 1000, 'cycling_cadence_samples' => 10000, 'power_samples' => 5000 ) as $field => $maximum_value ) {
			$samples = $details[ $field ] ?? array();
			if ( ! self::is_list( $samples ) || count( $samples ) > 2000 ) return false;
			$clean_samples = array();
			foreach ( $samples as $sample ) {
				if ( ! is_array( $sample ) || array_diff( array_keys( $sample ), array( 'time', 'value' ) ) || ! is_string( $sample['time'] ?? null ) || ! is_numeric( $sample['value'] ?? null ) ) return false;
				$time = self::time( $sample['time'] );
				$value = (float) $sample['value'];
				if ( ! $time || $time['utc'] < $start_utc || $time['utc'] > $end_utc || ! is_finite( $value ) || $value < 0 || $value > $maximum_value ) return false;
				$clean_samples[] = array( 'time' => $time['utc'], 'value' => $value );
			}
			$clean_series[ $field ] = $clean_samples;
		}
		$summary = array();
		foreach ( array( 'total_calories_kcal' => 100000, 'active_calories_kcal' => 100000, 'elevation_gain_m' => 1000000, 'floors_climbed' => 1000000, 'average_speed_mps' => 100, 'maximum_speed_mps' => 100 ) as $name => $maximum ) {
			$value = $details[ $name ] ?? null;
			if ( null !== $value && ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value <= 0 || (float) $value > $maximum ) ) return false;
			$summary[ $name ] = null === $value ? null : (string) $value;
		}
		$normalized = array_merge( array( 'laps' => $clean_laps, 'segments' => $clean_segments, 'route' => $clean_route ), $summary, array( 'speed_samples' => $clean_speeds ), $clean_series );
		return ( count( $clean_laps ) || count( $clean_segments ) || count( $clean_route ) || count( $clean_speeds ) || count( $clean_series['step_cadence_samples'] ) || count( $clean_series['cycling_cadence_samples'] ) || count( $clean_series['power_samples'] ) || null !== $summary['total_calories_kcal'] || null !== $summary['active_calories_kcal'] || null !== $summary['elevation_gain_m'] || null !== $summary['floors_climbed'] || null !== $summary['average_speed_mps'] || null !== $summary['maximum_speed_mps'] ) ? $normalized : null;
	}

	public static function receive( $owner, $payload ) {
		$encoded = is_array( $payload ) ? self::json( $payload ) : false;
		if ( ! is_int( $owner ) || $owner <= 0 || ! get_userdata( $owner ) || false === $encoded || strlen( $encoded ) > self::MAX_BYTES ) return self::error( 'payload' );
		if ( array_diff( array_keys( $payload ), array( 'contract_version', 'batch_id', 'workouts', 'measurements' ) ) || 1 !== ( $payload['contract_version'] ?? null ) || ! is_string( $payload['batch_id'] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{8,80}$/D', $payload['batch_id'] ) ) return self::error( 'contract' );
		$workouts = $payload['workouts'] ?? array();
		$measurements = $payload['measurements'] ?? array();
		if ( ! self::is_list( $workouts ) || count( $workouts ) > self::MAX_WORKOUTS || ! self::is_list( $measurements ) || count( $measurements ) > self::MAX_MEASUREMENTS ) return self::error( 'limit', 413 );
		$results = array( 'workouts' => array(), 'measurements' => array() );
		foreach ( $workouts as $item ) {
			$result = self::workout( $owner, $item );
			$results['workouts'][] = self::item_result( $result );
		}
		foreach ( $measurements as $item ) {
			$result = self::measurement( $owner, $item );
			$results['measurements'][] = self::item_result( $result );
		}
		return array( 'contract_version' => 1, 'batch_id' => $payload['batch_id'], 'results' => $results );
	}

	private static function workout( $owner, $item ) {
		global $wpdb;
		if ( ! is_array( $item ) || array_diff( array_keys( $item ), array( 'record_id', 'origin', 'activity', 'environment', 'start_time', 'end_time', 'distance_m', 'pool_length_value', 'pool_length_unit', 'heart_rate_samples', 'details' ) ) ) return self::error( 'workout_fields' );
		$origin = self::origin( $item['origin'] ?? null );
		$id = $item['record_id'] ?? null;
		$type = $item['activity'] ?? '';
		$start = self::time( $item['start_time'] ?? null );
		$end = self::time( $item['end_time'] ?? null );
		$environment = $item['environment'] ?? null;
		if ( false === $origin || ! is_string( $id ) || '' === $id || strlen( $id ) > 191 || preg_match( '/[\x00-\x1f]/', $id ) || ! in_array( $type, array( 'pool_swimming', 'open_water_swimming', 'running', 'running_treadmill', 'walking', 'cycling' ), true ) || ( null !== $environment && ! in_array( $environment, array( 'indoor', 'outdoor' ), true ) ) || ! $start || ! $end || new \DateTimeImmutable( $end['utc'] ) <= new \DateTimeImmutable( $start['utc'] ) ) return self::error( 'workout' );
		if ( 'running_treadmill' === $type && null !== $environment && 'indoor' !== $environment ) return self::error( 'workout' );
		if ( 'running_treadmill' === $type ) $environment = 'indoor';
		if ( ! in_array( $type, array( 'running', 'running_treadmill', 'cycling' ), true ) && null !== $environment ) return self::error( 'workout' );
		$distance = $item['distance_m'] ?? null;
		if ( null !== $distance && ( ! is_numeric( $distance ) || ! is_finite( (float) $distance ) || (float) $distance <= 0 || (float) $distance > 1000000 ) ) return self::error( 'distance' );
		$pool_value = $item['pool_length_value'] ?? null;
		$course = $item['pool_length_unit'] ?? null;
		if ( null !== $pool_value && ( ! is_numeric( $pool_value ) || (float) $pool_value <= 0 || (float) $pool_value > 500 || ! in_array( $course, array( 'm', 'yd' ), true ) ) ) return self::error( 'pool' );
		if ( null === $pool_value && null !== $course ) return self::error( 'pool' );
		$pool_m = null === $pool_value ? null : ( 'yd' === $course ? (float) $pool_value * 0.9144 : (float) $pool_value );
		$details = self::workout_details( $item['details'] ?? null, $start, $end );
		if ( false === $details ) return self::error( 'workout_details' );
		$hr = $item['heart_rate_samples'] ?? array();
		if ( ! self::is_list( $hr ) || count( $hr ) > 2000 ) return self::error( 'heart_rate' );
		$start_instant = new \DateTimeImmutable( $start['utc'] );
		$end_instant = new \DateTimeImmutable( $end['utc'] );
		$duration_us = ( $end_instant->getTimestamp() - $start_instant->getTimestamp() ) * 1000000 + (int) $end_instant->format( 'u' ) - (int) $start_instant->format( 'u' );
		if ( $duration_us <= 0 || $duration_us > 86400000000 ) return self::error( 'duration' );
		$wp_start = $start_instant->setTimezone( wp_timezone() );
		$wp_end = $end_instant->setTimezone( wp_timezone() );
		$wp_start_sql = $wp_start->format( 'Y-m-d H:i:s' );
		$wp_end_sql = $wp_end->format( 'Y-m-d H:i:s' );
		$hr_rows = array();
		foreach ( $hr as $sample ) {
			if ( ! is_array( $sample ) || array_diff( array_keys( $sample ), array( 'record_id', 'origin', 'time', 'bpm' ) ) || ! is_string( $sample['record_id'] ?? null ) || '' === $sample['record_id'] || strlen( $sample['record_id'] ) > 191 || $origin !== self::origin( $sample['origin'] ?? null ) || ! is_int( $sample['bpm'] ?? null ) || $sample['bpm'] < 20 || $sample['bpm'] > 250 ) return self::error( 'heart_rate' );
			$time = self::time( $sample['time'] ?? null );
			if ( ! $time || $time['utc'] < $start['utc'] || $time['utc'] > $end['utc'] ) return self::error( 'heart_rate' );
			$sample_hash = self::source_hash( $origin, $sample['record_id'] );
			foreach ( $hr_rows as $existing_sample ) if ( hash_equals( $existing_sample['hash'], $sample_hash ) ) return self::error( 'heart_rate' );
			$hr_rows[] = array( 'hash' => $sample_hash, 'time' => $time['utc'], 'bpm' => $sample['bpm'] );
		}
		usort( $hr_rows, static function ( $left, $right ) { return strcmp( $left['time'] . $left['hash'], $right['time'] . $right['hash'] ); } );
		$start_utc = Health_Measurement_Store::timestamp( $start['utc'] );
		$end_utc = Health_Measurement_Store::timestamp( $end['utc'] );
		if ( is_wp_error( $start_utc ) || is_wp_error( $end_utc ) ) return self::error( 'time_precision' );
		$key = self::source_hash( $origin, $id );
		$canonical = array( 'activity' => $type, 'environment' => $environment, 'start' => $start_utc, 'end' => $end_utc, 'start_local' => $start['local'], 'end_local' => $end['local'], 'start_offset' => $start['offset'], 'end_offset' => $end['offset'], 'distance_m' => null === $distance ? null : (string) $distance, 'pool_length_value' => null === $pool_value ? null : (string) $pool_value, 'pool_length_unit' => $course, 'heart_rate_samples' => $hr_rows, 'details' => $details );
		$content_hash = hash( 'sha256', self::json( $canonical ) );
		$activities = Database::table( 'health_connect_activities' );
		$old = Database::read( 'get_row', $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND source_key_hash = %s', $activities, $owner, $key ) );
		if ( ! empty( $wpdb->last_error ) ) return self::error( 'read', 503 );
		if ( $old ) return hash_equals( $old->content_hash, $content_hash ) ? array( 'status' => 'duplicate', 'record_key' => $key ) : self::error( 'source_conflict', 409 );
		$duration_ms = (int) round( $duration_us / 1000 );
		$possible = self::possible_duplicate( $owner, $type, $wp_start_sql, $distance );
		if ( is_wp_error( $possible ) ) return $possible;
		$workouts = Database::table( 'workouts' );
		$visibility = Database::table( 'workout_visibility' );
		foreach ( array( $workouts, $visibility, $activities, Database::table( 'health_connect_heart_rate_samples' ), Database::table( 'laps' ), Database::table( 'trackpoints' ) ) as $table ) {
			$status = Database::read( 'get_row', $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( ! $status || 'InnoDB' !== $status->Engine ) return self::error( 'engine', 503 );
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( false === Database::write( 'query', 'START TRANSACTION' ) ) return self::error( 'storage', 503 );
		try {
			$map = array( 'pool_swimming' => 'pool', 'open_water_swimming' => 'open_water', 'running' => 'running', 'running_treadmill' => 'running', 'walking' => 'walking', 'cycling' => 'cycling' );
			$data = array( 'user_id' => $owner, 'source_type' => 'health_connect', 'workout_type' => $map[$type], 'activity_environment' => $environment, 'workout_start' => $wp_start_sql, 'workout_end' => $wp_end_sql, 'total_distance_m' => $distance, 'original_distance' => $distance, 'original_distance_unit' => null === $distance ? null : 'm', 'elapsed_time_ms' => $duration_ms, 'moving_time_ms' => null, 'average_heart_rate' => null, 'max_heart_rate' => null, 'average_cadence' => null, 'max_cadence' => null, 'pool_length_m' => $pool_m, 'original_pool_length' => $pool_value, 'pool_length_unit' => $course, 'created_at' => $now, 'updated_at' => $now, 'source_activity_id' => $key );
			if ( false === Database::write( 'insert', $workouts, $data ) ) throw new \RuntimeException( 'storage' );
			$workout_id = (int) $wpdb->insert_id;
			if ( false === Database::write( 'insert', $visibility, array( 'workout_id' => $workout_id, 'owner_id' => $owner, 'visibility' => 'private', 'route_public' => 0, 'revision' => 1, 'updated_at_gmt' => $now ) ) ) throw new \RuntimeException( 'storage' );
			if ( false === Database::write( 'insert', $activities, array( 'user_id' => $owner, 'workout_id' => $workout_id, 'source_key_hash' => $key, 'content_hash' => $content_hash, 'source_origin' => $origin, 'start_utc' => $start_utc, 'end_utc' => $end_utc, 'start_local' => $start['local'], 'end_local' => $end['local'], 'start_offset_seconds' => $start['offset'], 'end_offset_seconds' => $end['offset'], 'possible_duplicate' => $possible ? 1 : 0, 'details_json' => null === $details ? null : self::json( $details ), 'created_at_utc' => $now, 'updated_at_utc' => $now ) ) ) throw new \RuntimeException( 'storage' );
			if ( $details ) {
				$laps = Database::table( 'laps' );
				foreach ( $details['laps'] as $index => $lap ) {
					$lap_start = new \DateTimeImmutable( $lap['start_time'] );
					$lap_end = new \DateTimeImmutable( $lap['end_time'] );
					$lap_start_local = $lap_start->setTimezone( wp_timezone() );
					$offset_ms = ( $lap_start->getTimestamp() - $start_instant->getTimestamp() ) * 1000 + (int) floor( ( (int) $lap_start->format( 'u' ) - (int) $start_instant->format( 'u' ) ) / 1000 );
					$elapsed_ms = ( $lap_end->getTimestamp() - $lap_start->getTimestamp() ) * 1000 + (int) floor( ( (int) $lap_end->format( 'u' ) - (int) $lap_start->format( 'u' ) ) / 1000 );
					if ( false === Database::write( 'insert', $laps, array( 'workout_id' => $workout_id, 'sequence_no' => $index + 1, 'start_time' => $lap_start_local->format( 'Y-m-d H:i:s' ), 'start_offset_ms' => max( 0, $offset_ms ), 'distance_m' => $lap['distance_m'], 'elapsed_time_ms' => max( 0, $elapsed_ms ), 'moving_time_ms' => null, 'stroke' => null, 'source_lap_index' => $index + 1, 'raw_metadata' => self::json( array( 'source' => 'health_connect' ) ), 'created_at' => $now ) ) ) throw new \RuntimeException( 'storage' );
				}
				$trackpoints = Database::table( 'trackpoints' );
				foreach ( $details['route'] as $index => $point ) {
					$point_time = ( new \DateTimeImmutable( $point['time'] ) )->setTimezone( wp_timezone() );
					if ( false === Database::write( 'insert', $trackpoints, array( 'workout_id' => $workout_id, 'sequence_no' => $index + 1, 'recorded_at' => $point_time->format( 'Y-m-d H:i:s' ), 'latitude' => $point['latitude'], 'longitude' => $point['longitude'], 'distance_m' => null, 'heart_rate' => null, 'cadence' => null, 'created_at' => $now ) ) ) throw new \RuntimeException( 'storage' );
				}
			}
			$samples = Database::table( 'health_connect_heart_rate_samples' );
			foreach ( $hr_rows as $row ) {
				if ( false === Database::write( 'insert', $samples, array( 'user_id' => $owner, 'workout_id' => $workout_id, 'source_key_hash' => $row['hash'], 'sample_utc' => $row['time'], 'beats_per_minute' => $row['bpm'], 'source_origin' => $origin, 'created_at_utc' => $now ) ) ) throw new \RuntimeException( 'storage' );
			}
			if ( false === Database::write( 'query', 'COMMIT' ) ) throw new \RuntimeException( 'storage' );
			return array( 'status' => 'added', 'record_key' => $key, 'possible_duplicate' => (bool) $possible );
		} catch ( \Throwable $error ) {
			Database::write( 'query', 'ROLLBACK' );
			return self::error( 'storage', 503 );
		}
	}

	private static function possible_duplicate( $owner, $activity, $start, $distance ) {
		global $wpdb;
		$type = array( 'pool_swimming' => 'pool', 'open_water_swimming' => 'open_water', 'running' => 'running', 'running_treadmill' => 'running', 'walking' => 'walking', 'cycling' => 'cycling' )[ $activity ];
		$rows = Database::read( 'get_results', $wpdb->prepare( 'SELECT workout_start,total_distance_m FROM %i WHERE user_id=%d AND workout_type=%s AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=300 ORDER BY id DESC LIMIT 50', Database::table( 'workouts' ), $owner, $type, $start ) );
		if ( ! empty( $wpdb->last_error ) ) return self::error( 'read', 503 );
		foreach ( (array) $rows as $row ) {
			if ( null === $distance || null === $row->total_distance_m || abs( (float) $row->total_distance_m - (float) $distance ) <= max( 10, (float) $distance * 0.02 ) ) return true;
		}
		return false;
	}

	private static function measurement( $owner, $item ) {
		if ( ! is_array( $item ) || array_diff( array_keys( $item ), array( 'record_id', 'origin', 'category', 'time', 'values' ) ) ) return self::error( 'measurement_fields' );
		$origin = self::origin( $item['origin'] ?? null );
		$id = $item['record_id'] ?? null;
		$category = $item['category'] ?? '';
		$time = self::time( $item['time'] ?? null );
		if ( false === $origin || ! is_string( $id ) || '' === $id || strlen( $id ) > 191 || ! in_array( $category, array( 'weight', 'body_composition', 'blood_pressure' ), true ) || ! $time || ! is_array( $item['values'] ?? null ) ) return self::error( 'measurement' );
		$values = Health_Measurement_Store::normalize_values( $category, $item['values'] );
		if ( is_wp_error( $values ) ) return self::error( 'measurement_values' );
		$allowed_values = array( 'weight' => array( 'weight' ), 'body_composition' => array( 'weight', 'body_fat', 'lean_body_mass' ), 'blood_pressure' => array( 'systolic', 'diastolic' ) );
		if ( array_diff( array_keys( $values ), $allowed_values[$category] ) ) return self::error( 'measurement_values' );
		$day = $time['date'];
		$local = Health_Measurement_Store::timestamp( $time['local'], false );
		$utc = Health_Measurement_Store::timestamp( $time['utc'] );
		if ( is_wp_error( $local ) || is_wp_error( $utc ) ) return self::error( 'measurement_time' );
		$observation = array( 'role' => 'sample', 'external_id' => self::source_hash( $origin, $id ), 'observed_at_utc' => $utc, 'source_local_time' => $local, 'source_offset_seconds' => $time['offset'], 'calendar_date' => $day, 'date_basis' => 'source_local', 'values' => $values, 'provenance' => array( 'normalization_version' => 'health-connect-v1', 'source_format' => 'health-connect-record', 'method' => 'record', 'coverage' => 'single_record', 'source_fields' => array() ) );
		$result = Health_Measurement_Store::save_report( $owner, array( 'provider' => 'health_connect', 'category' => $category, 'calendar_date' => $day, 'fetched_at_utc' => gmdate( 'Y-m-d\\TH:i:s\\Z' ), 'summary' => array(), 'context' => array( 'normalization_version' => 'health-connect-v1', 'source_format' => 'health-connect' ) ), array( $observation ), true );
		if ( is_wp_error( $result ) ) return self::error( $result->get_error_code(), 'ambiguous_correction' === $result->get_error_code() ? 409 : 503 );
		return array( 'status' => 0 === $result['counts']['added'] && 0 === $result['counts']['updated'] ? 'duplicate' : 'added', 'record_key' => self::source_hash( $origin, $id ) );
	}
}
