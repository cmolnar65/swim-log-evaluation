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
		$where=array('w.user_id=%d'); $args=array($user_id);
		if(!empty($filters['date_from'])){$where[]='DATE(w.workout_start) >= %s';$args[]=$filters['date_from'];}
		if(!empty($filters['date_to'])){$where[]='DATE(w.workout_start) <= %s';$args[]=$filters['date_to'];}
		if(!empty($filters['location_id'])){$where[]='w.location_id=%d';$args[]=absint($filters['location_id']);}
		if(!empty($filters['course_unit'])&&in_array($filters['course_unit'],array('m','yd'),true)){$where[]='w.pool_length_unit=%s';$args[]=$filters['course_unit'];}
		if(!empty($filters['stroke'])){$where[]='w.primary_stroke=%s';$args[]=$filters['stroke'];}
		$sql_where=implode(' AND ',$where);
		$total=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $w w WHERE $sql_where",$args));
		$offset=max(0,($page-1)*$per_page); $qargs=array_merge($args,array($per_page,$offset));
		$rows=$wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE $sql_where ORDER BY w.workout_start DESC,w.id DESC LIMIT %d OFFSET %d",$qargs));
		return array('rows'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$per_page));
	}

	public static function recent_for_user( $user_id, $limit=5 ) {
		global $wpdb;
		$w=Database::table('workouts'); $l=Database::table('locations');
		$limit=max(1,min(20,(int)$limit));
		return $wpdb->get_results($wpdb->prepare("SELECT w.*, l.name AS location_name FROM $w w LEFT JOIN $l l ON l.id=w.location_id AND l.user_id=w.user_id WHERE w.user_id=%d ORDER BY w.workout_start DESC,w.id DESC LIMIT %d",$user_id,$limit));
	}

	public static function lengths( $workout_id, $user_id ) {
		global $wpdb;
		if(!self::get_for_user($workout_id,$user_id)) return array();
		$t=Database::table('lengths');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE workout_id=%d ORDER BY sequence_no ASC",$workout_id));
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

	public static function format_duration( $ms ) {
		if(null===$ms) return '—';
		$hundredths=(int)round($ms/10); $h=intdiv($hundredths,360000); $rem=$hundredths%360000;
		$m=intdiv($rem,6000); $s=intdiv($rem%6000,100); $cs=$rem%100;
		if($h>0)return sprintf('%d:%02d:%02d.%02d',$h,$m,$s,$cs);
		if($m>0)return sprintf('%d:%02d.%02d',$m,$s,$cs);
		return sprintf('%d.%02d',$s,$cs);
	}
}
