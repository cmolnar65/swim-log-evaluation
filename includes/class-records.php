<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Personal-best and performance-history queries. */
final class Records {
	public static function personal_bests($user_id,$course){
		global $wpdb;$t=Database::table('performances');
		if(!in_array($course,array('m','yd'),true))$course='m';
		$rows=$wpdb->get_results($wpdb->prepare("SELECT p.* FROM $t p WHERE p.user_id=%d AND p.course_unit=%s AND p.is_personal_best=1 ORDER BY p.distance_value ASC,p.stroke ASC",$user_id,$course));
		$map=array();foreach($rows as $r)$map[(int)$r->distance_value][$r->stroke]=$r;
		// Overall is derived across every stroke; earliest achieved wins an exact tie.
		foreach(Evaluator::DISTANCES as $d){$o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND course_unit=%s AND distance_value=%d ORDER BY duration_ms ASC,achieved_at ASC,id ASC LIMIT 1",$user_id,$course,$d));if($o)$map[$d]['OVERALL']=$o;}
		return$map;
	}
	public static function exact_personal_best($user_id,$distance,$course,$stroke){
		global $wpdb;$t=Database::table('performances');
		if(!in_array($course,array('m','yd'),true)||!in_array($stroke,array('FR','BR','BACK','FLY','MIXED'),true))return null;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s AND is_personal_best=1 ORDER BY achieved_at ASC,id ASC LIMIT 1",$user_id,$distance,$course,$stroke));
	}

	public static function history($user_id,$distance,$course,$stroke=''){
		global $wpdb;$t=Database::table('performances');$where="user_id=%d AND distance_value=%d AND course_unit=%s";$args=array($user_id,$distance,$course);
		if($stroke!==''){$where.=" AND stroke=%s";$args[]=$stroke;}
		return$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $where ORDER BY achieved_at ASC,id ASC",$args));
	}
}
