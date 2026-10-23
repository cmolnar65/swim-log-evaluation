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

	public static function age_group_personal_bests( $user_id, $course, $age_group_label = '' ) {
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) {
			$course = 'm';
		}
		$target_group = self::resolve_age_group_label( $user_id, $age_group_label );
		if ( null === $target_group ) {
			return array();
		}

		$rows = self::age_group_rows( $user_id, $course, $target_group );
		$map = array();
		foreach ( $rows as $row ) {
			$distance = (int) $row->distance_value;
			$stroke = (string) $row->stroke;
			if ( ! isset( $map[ $distance ][ $stroke ] ) ) {
				$map[ $distance ][ $stroke ] = $row;
			}
			if ( ! isset( $map[ $distance ]['OVERALL'] ) ) {
				$map[ $distance ]['OVERALL'] = $row;
			}
		}
		return $map;
	}

	public static function exact_age_group_personal_best( $user_id, $distance, $course, $stroke, $age_group_label = '' ) {
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) || ! in_array( $stroke, array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED' ), true ) ) {
			return null;
		}
		$target_group = self::resolve_age_group_label( $user_id, $age_group_label );
		if ( null === $target_group ) {
			return null;
		}
		$rows = self::age_group_rows( $user_id, $course, $target_group, absint( $distance ), $stroke );
		return $rows ? $rows[0] : null;
	}

	private static function resolve_age_group_label( $user_id, $age_group_label ) {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-age-group.php';
		if ( '' === $age_group_label ) {
			$current = Age_Group::for_user( $user_id );
			return $current ? $current['label'] : null;
		}
		return (string) $age_group_label;
	}

	private static function age_group_rows( $user_id, $course, $target_group, $distance = 0, $stroke = '' ) {
		global $wpdb;
		$t = Database::table( 'performances' );
		$sql = "SELECT * FROM $t WHERE user_id=%d AND course_unit=%s";
		$args = array( absint( $user_id ), $course );
		if ( $distance ) {
			$sql .= ' AND distance_value=%d';
			$args[] = $distance;
		}
		if ( '' !== $stroke ) {
			$sql .= ' AND stroke=%s';
			$args[] = $stroke;
		}
		$sql .= ' ORDER BY duration_ms ASC,achieved_at ASC,id ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out = array();
		foreach ( $rows as $row ) {
			$achieved_date = substr( (string) $row->achieved_at, 0, 10 );
			$group = Age_Group::for_user( $user_id, $achieved_date );
			if ( $group && $group['label'] === $target_group ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	public static function progression($user_id,$distance,$course,$stroke=''){
		$rows=self::history($user_id,$distance,$course,$stroke);$best=null;$out=array();foreach($rows as$r){if($best===null||(int)$r->duration_ms<$best){$best=(int)$r->duration_ms;$out[]=$r;}}return$out;
	}
	public static function history($user_id,$distance,$course,$stroke=''){
		global $wpdb;$t=Database::table('performances');
		if($stroke!==''){
			return$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s ORDER BY achieved_at ASC,id ASC",$user_id,$distance,$course,$stroke));
		}
		return$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND distance_value=%d AND course_unit=%s ORDER BY achieved_at ASC,id ASC",$user_id,$distance,$course));
	}
}
