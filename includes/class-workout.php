<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** User-owned normalized workout history service. */
final class Workout {
	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		return $wpdb->get_row($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE w.id=%d AND w.user_id=%d",$id,$user_id));
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
		$sql_where=implode(' AND ',$where);
		$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $w w WHERE $sql_where");
		$offset=max(0,($page-1)*$per_page);
		$rows=$wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE $sql_where ORDER BY w.workout_start DESC,w.id DESC LIMIT %d OFFSET %d",$per_page,$offset));
		return array('rows'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$per_page));
	}

	public static function recent_for_user( $user_id, $limit=5 ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		$limit=max(1,min(20,(int)$limit));
		return $wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE w.user_id=%d ORDER BY w.workout_start DESC,w.id DESC LIMIT %d",$user_id,$limit));
	}

	public static function for_month( $user_id, $year, $month ) {
		global $wpdb; $w=Database::table('workouts'); $l=Database::table('locations');
		$start=sprintf('%04d-%02d-01 00:00:00',$year,$month); $end=(new \DateTimeImmutable($start,wp_timezone()))->modify('+1 month')->format('Y-m-d H:i:s');
		return $wpdb->get_results($wpdb->prepare("SELECT w.*,l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE w.user_id=%d AND w.workout_start >= %s AND w.workout_start < %s ORDER BY w.workout_start ASC,w.id ASC",$user_id,$start,$end));
	}

	public static function totals_by_month_for_year( $user_id, $year ) {
		global $wpdb; $w=Database::table('workouts');
		$start=sprintf('%04d-01-01 00:00:00',$year); $end=sprintf('%04d-01-01 00:00:00',$year+1);
		return $wpdb->get_results($wpdb->prepare("SELECT MONTH(workout_start) month_num, COUNT(*) workout_count, SUM(CASE WHEN original_distance_unit='m' THEN original_distance ELSE 0 END) meters, SUM(CASE WHEN original_distance_unit='yd' THEN original_distance ELSE 0 END) yards, SUM(elapsed_time_ms) elapsed_ms FROM $w WHERE user_id=%d AND workout_start >= %s AND workout_start < %s GROUP BY MONTH(workout_start) ORDER BY month_num ASC",$user_id,$start,$end));
	}

	public static function lengths( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('lengths');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE workout_id=%d ORDER BY sequence_no ASC",$workout_id));
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

	public static function performances( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('performances');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE workout_id=%d AND user_id=%d ORDER BY course_unit,distance_value,stroke,duration_ms",$workout_id,$user_id));
	}

	public static function imports( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('imports');
		return $wpdb->get_results($wpdb->prepare("SELECT id,source_type,original_filename,import_status,imported_at FROM $t WHERE workout_id=%d AND user_id=%d ORDER BY imported_at ASC,id ASC",$workout_id,$user_id));
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
				$wpdb->prepare( "SELECT id FROM $locations WHERE id = %d AND user_id = %d", $location_id, $user_id )
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
		$source_paths = $wpdb->get_col( $wpdb->prepare( "SELECT stored_path FROM $imports WHERE workout_id=%d AND user_id=%d AND stored_path IS NOT NULL AND stored_path<>''", $workout_id, $user_id ) );

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

		foreach ( array_unique( array_filter( $source_paths ) ) as $source_path ) {
			if ( is_file( $source_path ) ) wp_delete_file( $source_path );
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
