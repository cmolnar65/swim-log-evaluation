<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Secure upload, duplicate detection, matching and normalized persistence. */
final class Importer {
	const MAX_BYTES=10485760;
	public static function import_upload($user_id,$file,$location_id=0){
		global $wpdb;
		if(empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))return new \WP_Error('swimlog_upload',__('No valid uploaded file was received.','swim-log-evaluation'));
		if(!empty($file['error']))return new \WP_Error('swimlog_upload',__('The upload failed before import.','swim-log-evaluation'));
		if((int)$file['size']>self::MAX_BYTES)return new \WP_Error('swimlog_upload_size',__('Workout files may not exceed 10 MB.','swim-log-evaluation'));
		$name=sanitize_file_name($file['name']);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
		if(!in_array($ext,array('fit','csv'),true))return new \WP_Error('swimlog_upload_type',__('Only FIT and CSV files are supported.','swim-log-evaluation'));
		$hash=hash_file('sha256',$file['tmp_name']);$it=Database::table('imports');
		if($wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s",$user_id,$hash)))return new \WP_Error('swimlog_duplicate',__('This exact file has already been imported.','swim-log-evaluation'));
		if($location_id&&!Location::get_for_user($location_id,$user_id))return new \WP_Error('swimlog_location',__('Select one of your own locations.','swim-log-evaluation'));

		$parser=$ext==='fit'?new FIT_Importer():new CSV_Importer();$parsed=$parser->parse($file['tmp_name']);if(is_wp_error($parsed))return $parsed;
		$v=self::validate($parsed);if(is_wp_error($v))return$v;
		$upload=wp_upload_bits('swimlog-'.$user_id.'-'.wp_generate_uuid4().'.'.$ext,null,file_get_contents($file['tmp_name']));
		if(!empty($upload['error']))return new \WP_Error('swimlog_store',__('The original workout file could not be preserved.','swim-log-evaluation'));

		$now=current_time('mysql');$wpdb->query('START TRANSACTION');$import_id=0;
		try{
			$wpdb->insert($it,array('user_id'=>$user_id,'workout_id'=>null,'source_type'=>$ext,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>(int)$file['size'],'parser_version'=>$parsed['parser_version'],'import_status'=>'processing','imported_at'=>$now,'updated_at'=>$now));$import_id=(int)$wpdb->insert_id;if(!$import_id)throw new \Exception('import row');
			$match=self::find_match($user_id,$parsed['workout']);$workout_id=$match? (int)$match->id:self::insert_workout($user_id,$location_id,$parsed['workout']);
			if(!$workout_id)throw new \Exception('workout');
			// FIT is structural authority. CSV supplements an existing FIT-backed workout without replacing its lengths.
			$has_fit=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));
			if($ext==='fit'){self::replace_structure($workout_id,$parsed);self::update_workout($workout_id,$user_id,$location_id,$parsed['workout']);}
			elseif(!$match||!$has_fit){self::replace_structure($workout_id,$parsed);if($match)self::update_workout($workout_id,$user_id,$location_id,$parsed['workout'],false);}
			else{self::supplement_workout($workout_id,$user_id,$location_id,$parsed['workout']);}
			$wpdb->update($it,array('workout_id'=>$workout_id,'import_status'=>'complete','updated_at'=>current_time('mysql')),array('id'=>$import_id));
			$wpdb->query('COMMIT');
			return array('workout_id'=>$workout_id,'import_id'=>$import_id,'attached'=>(bool)$match,'source'=>$ext);
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');@unlink($upload['file']);return new \WP_Error('swimlog_import_failed',__('The workout could not be committed. No normalized workout data was partially saved.','swim-log-evaluation'));}
	}
	private static function validate($p){$w=$p['workout'];if(empty($w['workout_start']))return new \WP_Error('swimlog_start',__('Workout start time is missing.','swim-log-evaluation'));if(empty($w['pool_length_unit'])||!in_array($w['pool_length_unit'],array('m','yd'),true))return new \WP_Error('swimlog_course',__('Pool course could not be determined.','swim-log-evaluation'));if(empty($p['lengths']))return new \WP_Error('swimlog_lengths',__('No usable swim lengths were found.','swim-log-evaluation'));$sum=0;foreach($p['lengths'] as $l){if($l['length_type']==='active')$sum+=(float)$l['distance_m'];}if(isset($w['total_distance_m'])&&$w['total_distance_m']!==null&&abs($sum-(float)$w['total_distance_m'])>max(1.0,(float)$w['pool_length_m']))return new \WP_Error('swimlog_totals',__('Workout distance and normalized active lengths disagree. Import stopped for review.','swim-log-evaluation'));return true;}
	private static function find_match($uid,$w){global $wpdb;$t=Database::table('workouts');$start=$w['workout_start'];$dist=$w['total_distance_m']??0;$elapsed=$w['elapsed_time_ms']??0;return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=2 AND ABS(COALESCE(total_distance_m,0)-%f)<=0.5 AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=2000 ORDER BY id ASC LIMIT 1",$uid,$start,$dist,$elapsed));}
	private static function insert_workout($uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);$d['created_at']=current_time('mysql');$d['updated_at']=current_time('mysql');return $wpdb->insert($t,$d)?(int)$wpdb->insert_id:0;}
	private static function update_workout($id,$uid,$loc,$w,$overwrite=true){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);unset($d['user_id']);if(!$overwrite){foreach($d as $k=>$v){if($v===null||$v==='')unset($d[$k]);}}$d['updated_at']=current_time('mysql');return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function supplement_workout($id,$uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=array('updated_at'=>current_time('mysql'));if($loc)$d['location_id']=$loc;if(!empty($w['notes']))$d['notes']=$w['notes'];if(!empty($w['device_model']))$d['device_model']=$w['device_model'];return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function workout_values($uid,$loc,$w){return array('user_id'=>$uid,'location_id'=>$loc?:null,'workout_start'=>$w['workout_start'],'workout_end'=>$w['workout_end']??null,'total_distance_m'=>$w['total_distance_m']??null,'original_distance'=>$w['original_distance']??null,'original_distance_unit'=>$w['original_distance_unit']??null,'elapsed_time_ms'=>$w['elapsed_time_ms']??null,'moving_time_ms'=>$w['moving_time_ms']??null,'pool_length_m'=>$w['pool_length_m']??null,'original_pool_length'=>$w['original_pool_length']??null,'pool_length_unit'=>$w['pool_length_unit']??null,'primary_stroke'=>$w['primary_stroke']??null,'device_manufacturer'=>$w['device_manufacturer']??null,'device_model'=>$w['device_model']??null,'source_activity_id'=>$w['source_activity_id']??null,'notes'=>$w['notes']??null);}
	private static function replace_structure($wid,$p){global $wpdb;$lt=Database::table('laps');$nt=Database::table('lengths');$wpdb->delete($lt,array('workout_id'=>$wid));$wpdb->delete($nt,array('workout_id'=>$wid));foreach($p['laps'] as $x){$x['workout_id']=$wid;$x['created_at']=current_time('mysql');$wpdb->insert($lt,$x);}foreach($p['lengths'] as $x){$x['workout_id']=$wid;$x['lap_id']=null;$x['created_at']=current_time('mysql');$wpdb->insert($nt,$x);}}
}
