<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** User-owned normalized workout history service. */
final class Workout {
	const EVENT_READINESS_LOOKBACK_DAYS = 42;
	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		return $wpdb->get_row($wpdb->prepare("SELECT w.*, l.name AS location_name FROM %i w LEFT JOIN %i l ON l.id=w.location_id AND l.user_id=w.user_id WHERE w.id=%d AND w.user_id=%d",$w,$l,$id,$user_id));
	}

	public static function query_for_user( $user_id, $filters=array(), $page=1, $per_page=20 ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		// Prepare each optional predicate independently. This keeps user-supplied
		// values out of the dynamically assembled WHERE clause while leaving the
		// final pagination query with a fixed, statically visible placeholder count.
		$where=array($wpdb->prepare('w.user_id=%d',$user_id));
		if(!empty($filters['date_from'])){$where[]=$wpdb->prepare('DATE(w.workout_start) >= %s',$filters['date_from']);}
		if(!empty($filters['date_to'])){$where[]=$wpdb->prepare('DATE(w.workout_start) <= %s',$filters['date_to']);}
		if(!empty($filters['location_id'])){$where[]=$wpdb->prepare('w.location_id=%d',absint($filters['location_id']));}
		if(!empty($filters['course_unit'])&&in_array($filters['course_unit'],array('m','yd'),true)){$where[]=$wpdb->prepare('w.pool_length_unit=%s',$filters['course_unit']);}
		if(!empty($filters['stroke'])){$where[]=$wpdb->prepare('w.primary_stroke=%s',$filters['stroke']);}
		if(!empty($filters['workout_type'])&&in_array($filters['workout_type'],array('pool','open_water'),true)){$where[]=$wpdb->prepare('w.workout_type=%s',$filters['workout_type']);}
		$sql_where=implode(' AND ',$where);
		$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $w w WHERE $sql_where");
		$offset=max(0,($page-1)*$per_page);
		$rows=$wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE $sql_where ORDER BY w.workout_start DESC,w.id DESC LIMIT %d OFFSET %d",$per_page,$offset));
		return array('rows'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$per_page));
	}

	/**
	 * Return normalized workouts owned by one swimmer in a half-open date/time range.
	 *
	 * The end boundary is exclusive so callers can compose adjacent calendar
	 * ranges without double-counting workouts that begin at midnight.
	 */
	public static function range_for_user( $user_id, $start, $end ) {
		global $wpdb;
		$w = Database::table( 'workouts' );
		$l = Database::table( 'locations' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.*, l.name AS location_name
				FROM $w w
				LEFT JOIN $l l ON l.id = w.location_id AND l.user_id = w.user_id
				WHERE w.user_id = %d AND w.workout_start >= %s AND w.workout_start < %s
				ORDER BY w.workout_start ASC, w.id ASC",
				absint( $user_id ),
				$start,
				$end
			)
		);
	}

	public static function recent_for_user( $user_id, $limit=5, $workout_type=null ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		$limit=max(1,min(20,(int)$limit));
		$where=$wpdb->prepare('w.user_id=%d',$user_id);
		if(in_array($workout_type,array('pool','open_water'),true))$where.=' AND '.$wpdb->prepare('w.workout_type=%s',$workout_type);
		return $wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE $where ORDER BY w.workout_start DESC,w.id DESC LIMIT %d",$limit));
	}

	public static function for_month( $user_id, $year, $month, $workout_type=null ) {
		global $wpdb; $w=Database::table('workouts'); $l=Database::table('locations');
		$start=sprintf('%04d-%02d-01 00:00:00',$year,$month); $end=(new \DateTimeImmutable($start,wp_timezone()))->modify('+1 month')->format('Y-m-d H:i:s');
		$where=$wpdb->prepare('w.user_id=%d AND w.workout_start >= %s AND w.workout_start < %s',$user_id,$start,$end);
		if(in_array($workout_type,array('pool','open_water'),true))$where.=' AND '.$wpdb->prepare('w.workout_type=%s',$workout_type);
		return $wpdb->get_results("SELECT w.*,l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE $where ORDER BY w.workout_start ASC,w.id ASC");
	}

	public static function totals_by_month_for_year( $user_id, $year, $workout_type=null ) {
		global $wpdb; $w=Database::table('workouts');
		$start=sprintf('%04d-01-01 00:00:00',$year); $end=sprintf('%04d-01-01 00:00:00',$year+1);
		$where=$wpdb->prepare('user_id=%d AND workout_start >= %s AND workout_start < %s',$user_id,$start,$end);
		if(in_array($workout_type,array('pool','open_water'),true))$where.=' AND '.$wpdb->prepare('workout_type=%s',$workout_type);
		return $wpdb->get_results("SELECT MONTH(workout_start) month_num, COUNT(*) workout_count, SUM(CASE WHEN original_distance_unit='m' THEN original_distance ELSE 0 END) meters, SUM(CASE WHEN original_distance_unit='yd' THEN original_distance ELSE 0 END) yards, SUM(elapsed_time_ms) elapsed_ms FROM $w WHERE $where GROUP BY MONTH(workout_start) ORDER BY month_num ASC");
	}



	public static function stroke_volume_for_user( $user_id, $lookback_months=3 ) {
		global $wpdb;
		$lookback_months=max(1,min(24,(int)$lookback_months));
		$today=new \DateTimeImmutable('today',wp_timezone());
		$start=$today->modify('-'.$lookback_months.' months')->format('Y-m-d 00:00:00');
		$end=$today->format('Y-m-d 23:59:59');
		$w=Database::table('workouts'); $n=Database::table('lengths');
		$where=array(
			$wpdb->prepare('w.user_id=%d',$user_id),
			"w.workout_type='pool'",
			"n.length_type='active'",
			"n.distance_m>0",
			$wpdb->prepare('w.workout_start >= %s',$start),
			$wpdb->prepare('w.workout_start <= %s',$end)
		);
		$sql_where=implode(' AND ',$where);
		return $wpdb->get_results("SELECT w.pool_length_unit course_unit,COALESCE(NULLIF(n.stroke,''),'UNKNOWN') stroke,COUNT(DISTINCT w.id) workout_count,COUNT(*) length_count,SUM(CASE WHEN w.pool_length_unit='yd' THEN n.distance_m/0.9144 ELSE n.distance_m END) distance_native,SUM(COALESCE(n.elapsed_time_ms,0)) active_time_ms FROM $w w INNER JOIN $n n ON n.workout_id=w.id WHERE $sql_where GROUP BY w.pool_length_unit,COALESCE(NULLIF(n.stroke,''),'UNKNOWN') ORDER BY CASE WHEN w.pool_length_unit='m' THEN 0 ELSE 1 END ASC,distance_native DESC,stroke ASC");
	}



	public static function event_readiness( $user_id, $event, $lookback_days=42 ) {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-race-readiness.php';
		return Race_Readiness::for_event( $user_id, $event, $lookback_days );
	}

	public static function laps( $workout_id, $user_id ) {
		global $wpdb;
		if ( ! self::get_for_user( $workout_id, $user_id ) ) return array();
		$t = Database::table( 'laps' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sequence_no,start_time,start_offset_ms,distance_m,elapsed_time_ms,moving_time_ms,stroke,source_lap_index FROM $t WHERE workout_id=%d ORDER BY sequence_no ASC",
				$workout_id
			)
		);
	}

	public static function lengths( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('lengths');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d ORDER BY sequence_no ASC",$t,$workout_id));
	}


	public static function trackpoints( $workout_id, $user_id ) {
		global $wpdb;
		if ( ! self::get_for_user( $workout_id, $user_id ) ) return array();
		$t = Database::table( 'trackpoints' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sequence_no,recorded_at,latitude,longitude,distance_m,heart_rate,cadence FROM $t WHERE workout_id=%d ORDER BY sequence_no ASC",
				$workout_id
			)
		);
	}


	public static function open_water_analytics( $workout_id, $user_id ) {
		global $wpdb;
		$workout = self::get_for_user( $workout_id, $user_id );
		if ( ! $workout || 'open_water' !== ( $workout->workout_type ?? 'pool' ) ) return array();

		$distance = (float) ( $workout->total_distance_m ?? 0 );
		$elapsed = (int) ( $workout->elapsed_time_ms ?? 0 );
		$moving = (int) ( $workout->moving_time_ms ?? 0 );
		$t = Database::table( 'trackpoints' );
		$points = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT recorded_at,latitude,longitude,distance_m,cadence FROM $t WHERE workout_id=%d ORDER BY sequence_no ASC",
				$workout_id
			)
		);
		$trackpoint_count = count( $points );
		$gps_points = 0;
		$cadence_points = 0;
		$splits = array();
		$progress_points = array();
		$max_distance = null;
		$last_time = null;
		$timezone = wp_timezone();

		foreach ( $points as $point ) {
			if ( null !== $point->latitude && null !== $point->longitude ) $gps_points++;
			if ( null !== $point->cadence ) $cadence_points++;
			if ( null === $point->distance_m || null === $point->recorded_at ) continue;
			$time = ( new \DateTimeImmutable( $point->recorded_at, $timezone ) )->getTimestamp();
			if ( null !== $last_time && $time < $last_time ) continue;
			$last_time = $time;
			$point_distance = (float) $point->distance_m;
			if ( null === $max_distance || $point_distance > $max_distance ) {
				$progress_points[] = array( 'distance_m' => $point_distance, 'time' => $time );
				$max_distance = $point_distance;
			}
		}

		if ( count( $progress_points ) >= 2 ) {
			$first = $progress_points[0];
			$previous_target_time = (float) $first['time'];
			$segment = 1;
			$reachable_distance = (float) $progress_points[count($progress_points)-1]['distance_m'];
			for ( $target = 1000.0; $target <= $distance && $target <= $reachable_distance; $target += 1000.0 ) {
				while ( $segment < count( $progress_points ) && $progress_points[$segment]['distance_m'] < $target ) $segment++;
				if ( $segment >= count( $progress_points ) ) break;
				$a = $progress_points[$segment - 1];
				$b = $progress_points[$segment];
				if ( $b['distance_m'] <= $a['distance_m'] || $b['time'] < $a['time'] ) continue;
				$fraction = ( $target - $a['distance_m'] ) / ( $b['distance_m'] - $a['distance_m'] );
				$target_time = $a['time'] + ( $b['time'] - $a['time'] ) * $fraction;
				if ( $target_time < $previous_target_time ) continue;
				$split_time_ms = (int) round( ( $target_time - $previous_target_time ) * 1000 );
				$cumulative_time_ms = (int) round( ( $target_time - $first['time'] ) * 1000 );
				$moving_ratio = $elapsed > 0 && $moving > 0 && $moving <= $elapsed ? $moving / $elapsed : null;
				$splits[] = array(
					'distance_m' => (int) $target,
					'split_time_ms' => $split_time_ms,
					'cumulative_time_ms' => $cumulative_time_ms,
					'moving_split_time_ms' => null !== $moving_ratio ? (int) round( $split_time_ms * $moving_ratio ) : null,
					'moving_cumulative_time_ms' => null !== $moving_ratio ? (int) round( $cumulative_time_ms * $moving_ratio ) : null,
					'moving_estimated' => null !== $moving_ratio,
				);
				$previous_target_time = $target_time;
			}
		}

		return array(
			'elapsed_pace_100m_ms' => $distance > 0 && $elapsed > 0 ? (int) round( $elapsed / ( $distance / 100 ) ) : null,
			'moving_pace_100m_ms' => $distance > 0 && $moving > 0 ? (int) round( $moving / ( $distance / 100 ) ) : null,
			'non_moving_time_ms' => $elapsed > 0 && $moving > 0 ? max( 0, $elapsed - $moving ) : null,
			'active_percent' => $elapsed > 0 && $moving > 0 ? min( 100.0, ( $moving / $elapsed ) * 100 ) : null,
			'trackpoint_count' => $trackpoint_count,
			'gps_point_count' => $gps_points,
			'cadence_point_count' => $cadence_points,
			'splits' => $splits,
			'metric_metadata' => array(
				'elapsed_pace_100m_ms' => array( 'unit' => 'ms/100m', 'provenance' => 'derived', 'source' => 'workout elapsed time and distance' ),
				'moving_pace_100m_ms' => array( 'unit' => 'ms/100m', 'provenance' => 'derived', 'source' => 'workout moving time and distance' ),
				'non_moving_time_ms' => array( 'unit' => 'ms', 'provenance' => 'derived', 'source' => 'elapsed time minus moving time' ),
				'active_percent' => array( 'unit' => 'percent', 'provenance' => 'derived', 'source' => 'moving time divided by elapsed time' ),
				'gps_point_count' => array( 'unit' => 'points', 'provenance' => 'measured', 'source' => 'imported trackpoints with GPS coordinates' ),
				'cadence_point_count' => array( 'unit' => 'points', 'provenance' => 'measured', 'source' => 'imported FIT cadence samples' ),
				'splits' => array( 'unit' => 'ms', 'provenance' => 'interpolated', 'source' => 'recorded distance and timestamp trackpoints; moving split values are estimated when marked' ),
			),
		);
	}

	public static function performances( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('performances');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d AND user_id=%d ORDER BY course_unit,distance_value,stroke,duration_ms",$t,$workout_id,$user_id));
	}

	public static function imports( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('imports');
		return $wpdb->get_results($wpdb->prepare("SELECT id,source_type,original_filename,import_status,imported_at FROM %i WHERE workout_id=%d AND user_id=%d ORDER BY imported_at ASC,id ASC",$t,$workout_id,$user_id));
	}


	/** Return up to three WordPress Media attachment IDs owned by a workout. */
	public static function photos( $workout_id, $user_id ) {
		if ( ! self::get_for_user( $workout_id, $user_id ) ) return array();
		$ids = get_user_meta( $user_id, '_swimlog_workout_photos_' . absint( $workout_id ), true );
		$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
		return array_slice( $ids, 0, 3 );
	}

	/** Replace the complete ordered photo set for a workout. */
	public static function set_photos( $workout_id, $user_id, $attachment_ids ) {
		if ( ! self::get_for_user( $workout_id, $user_id ) ) return new \WP_Error( 'swimlog_workout_not_found', __( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $attachment_ids ) ) ) );
		if ( count( $ids ) > 3 ) return new \WP_Error( 'swimlog_workout_photo_limit', __( 'A workout may have no more than three photos.', 'chriss-swim-training-progress-evaluation' ) );
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) return new \WP_Error( 'swimlog_workout_photo_type', __( 'Workout photos must be image attachments.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$key = '_swimlog_workout_photos_' . absint( $workout_id );
		if ( $ids ) update_user_meta( $user_id, $key, $ids ); else delete_user_meta( $user_id, $key );
		return true;
	}


	public static function update_metadata( $workout_id, $user_id, $location_id, $notes ) {
		global $wpdb;
		$workout_id = absint( $workout_id );
		$user_id = absint( $user_id );
		$location_id = absint( $location_id );

		if ( ! self::get_for_user( $workout_id, $user_id ) ) {
			return new \WP_Error( 'swimlog_workout_not_found', __( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		}

		if ( $location_id ) {
			$locations = Database::table( 'locations' );
			$owned_location = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM %i WHERE id = %d AND user_id = %d", $locations, $location_id, $user_id )
			);
			if ( $owned_location !== $location_id ) {
				return new \WP_Error( 'swimlog_workout_location', __( 'Select one of this swimmer\'s locations.', 'chriss-swim-training-progress-evaluation' ) );
			}
		}

		$table = Database::table( 'workouts' );
		$result = $wpdb->update(
			$table,
			array(
				'location_id' => $location_id ?: null,
				'notes'       => sanitize_textarea_field( $notes ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array(
				'id'      => $workout_id,
				'user_id' => $user_id,
			),
			array( '%d', '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false === $result
			? new \WP_Error( 'swimlog_workout_update', __( 'The workout metadata could not be saved.', 'chriss-swim-training-progress-evaluation' ) )
			: true;
	}


	public static function delete( $workout_id, $user_id ) {
		global $wpdb;
		$workout_id = absint( $workout_id );
		$user_id = absint( $user_id );
		if ( ! $workout_id || ! $user_id || ! self::get_for_user( $workout_id, $user_id ) ) {
			return new \WP_Error( 'swimlog_workout_not_found', __( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$imports = Database::table( 'imports' );
		$laps = Database::table( 'laps' );
		$lengths = Database::table( 'lengths' );
		$trackpoints = Database::table( 'trackpoints' );
		$performances = Database::table( 'performances' );
		$workouts = Database::table( 'workouts' );
		$source_paths = $wpdb->get_col( $wpdb->prepare( "SELECT stored_path FROM %i WHERE workout_id=%d AND user_id=%d AND stored_path IS NOT NULL AND stored_path<>''", $imports, $workout_id, $user_id ) );
		$photo_ids = self::photos( $workout_id, $user_id );

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'swimlog_workout_delete_start', __( 'The workout deletion could not be started.', 'chriss-swim-training-progress-evaluation' ) );
		}
		try {
			if ( false === $wpdb->delete( $performances, array( 'workout_id' => $workout_id, 'user_id' => $user_id ), array( '%d', '%d' ) ) ) throw new \Exception( 'performances' );
			if ( false === $wpdb->delete( $lengths, array( 'workout_id' => $workout_id ), array( '%d' ) ) ) throw new \Exception( 'lengths' );
			if ( false === $wpdb->delete( $trackpoints, array( 'workout_id' => $workout_id ), array( '%d' ) ) ) throw new \Exception( 'trackpoints' );
			if ( false === $wpdb->delete( $laps, array( 'workout_id' => $workout_id ), array( '%d' ) ) ) throw new \Exception( 'laps' );
			if ( false === $wpdb->delete( $imports, array( 'workout_id' => $workout_id, 'user_id' => $user_id ), array( '%d', '%d' ) ) ) throw new \Exception( 'imports' );
			if ( 1 !== $wpdb->delete( $workouts, array( 'id' => $workout_id, 'user_id' => $user_id ), array( '%d', '%d' ) ) ) throw new \Exception( 'workout' );
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
			Evaluator::recalculate_personal_bests( $user_id );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new \Exception( 'commit' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'swimlog_workout_delete', __( 'The workout could not be deleted. No workout data was changed.', 'chriss-swim-training-progress-evaluation' ) );
		}

		delete_user_meta( $user_id, '_swimlog_workout_photos_' . $workout_id );
		foreach ( $photo_ids as $photo_id ) wp_delete_attachment( $photo_id, true );

		$source_delete_failures = array();
		foreach ( array_unique( array_filter( $source_paths ) ) as $source_path ) {
			if ( ! is_file( $source_path ) ) continue;
			wp_delete_file( $source_path );
			if ( is_file( $source_path ) ) $source_delete_failures[] = $source_path;
		}
		if ( $source_delete_failures ) {
			return new \WP_Error(
				'swimlog_source_delete_failed',
				__( 'The workout data was deleted, but one or more preserved source files could not be removed from private storage. Check file permissions before reimporting.', 'chriss-swim-training-progress-evaluation' )
			);
		}
		return true;
	}

	public static function format_duration( $ms ) {
		if(null===$ms) return '—';
		$hundredths=(int)round($ms/10); $h=intdiv($hundredths,360000); $rem=$hundredths%360000;
		$m=intdiv($rem,6000); $s=intdiv($rem%6000,100); $cs=$rem%100;
		if($h>0)return sprintf('%d:%02d:%02d.%02d',$h,$m,$s,$cs);
		if($m>0)return sprintf('%d:%02d.%02d',$m,$s,$cs);
		return sprintf('%d.%02d',$s,$cs);
	}
}
