<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Secure upload, duplicate detection, matching and normalized persistence. */
final class Importer {
	const MAX_BYTES=10485760;
	public static function import_upload($user_id,$file,$location_id=0){
		if(empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))return new \WP_Error('swimlog_upload',__('No valid uploaded file was received.','chriss-swim-training-progress-evaluation'));
		if(!empty($file['error']))return new \WP_Error('swimlog_upload',__('The upload failed before import.','chriss-swim-training-progress-evaluation'));
		if((int)$file['size']>self::MAX_BYTES)return new \WP_Error('swimlog_upload_size',__('Workout files may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$name=sanitize_file_name($file['name']);$ext=self::uploaded_source_type($name,$file['tmp_name']);
		if(is_wp_error($ext))return $ext;
		$allowed=(array)get_option('swimlog_allowed_upload_types',array('fit','csv','gpx','tcx'));
		if(!in_array($ext,array('fit','csv','gpx','tcx'),true)||!in_array($ext,$allowed,true))return new \WP_Error('swimlog_upload_type',__('This workout file type is not currently allowed.','chriss-swim-training-progress-evaluation'));
		return self::import_file($user_id,$file['tmp_name'],$name,(int)$file['size'],$ext,$location_id,array());
	}

	/**
	 * Import a FIT file obtained by a trusted internal provider rather than PHP upload handling.
	 * Browser uploads must continue to use import_upload(), which enforces is_uploaded_file().
	 */
	public static function import_trusted_fit($user_id,$tmp_path,$original_filename,$location_id=0,$source_provider='',$external_activity_id='',$require_ows_location=false){
		$user_id=absint($user_id);$location_id=absint($location_id);$tmp_path=(string)$tmp_path;$original_filename=(string)$original_filename;
		if(!$user_id||$tmp_path===''||!is_file($tmp_path))return new \WP_Error('swimlog_trusted_source',__('The trusted FIT source file could not be found.','chriss-swim-training-progress-evaluation'));
		$size=(int)filesize($tmp_path);if($size<=0||$size>self::MAX_BYTES)return new \WP_Error('swimlog_upload_size',__('Workout files may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$name=basename($original_filename);
		if($name!==$original_filename||sanitize_file_name($name)!==$name||'fit'!==strtolower(pathinfo($name,PATHINFO_EXTENSION)))return new \WP_Error('swimlog_trusted_name',__('The trusted FIT source filename is invalid.','chriss-swim-training-progress-evaluation'));
		$provider=sanitize_key($source_provider);$external=trim((string)$external_activity_id);
		if($provider!==''&&$external==='')return new \WP_Error('swimlog_external_identity',__('The external activity identity is incomplete.','chriss-swim-training-progress-evaluation'));
		return self::import_file($user_id,$tmp_path,$name,$size,'fit',$location_id,array('source_provider'=>$provider,'external_activity_id'=>$external,'preserve_filename'=>$name,'require_ows_location'=>(bool)$require_ows_location));
	}

	private static function import_file($user_id,$tmp_path,$name,$size,$ext,$location_id,$source_meta){
		global $wpdb;
		$user_id=absint($user_id);$location_id=absint($location_id);$source_meta=(array)$source_meta;
		$provider=trim((string)($source_meta['source_provider']??''));$external=trim((string)($source_meta['external_activity_id']??''));$preserve_filename=(string)($source_meta['preserve_filename']??'');
		$hash=hash_file('sha256',$tmp_path);if(false===$hash)return new \WP_Error('swimlog_hash',__('The workout file fingerprint could not be calculated.','chriss-swim-training-progress-evaluation'));
		$it=Database::table('imports');
		if($wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s",$user_id,$hash)))return new \WP_Error('swimlog_duplicate',__('This exact file has already been imported.','chriss-swim-training-progress-evaluation'));
		if($provider!==''&&$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND source_provider=%s AND external_activity_id=%s",$user_id,$provider,$external)))return new \WP_Error('swimlog_external_duplicate',__('This external activity has already been imported.','chriss-swim-training-progress-evaluation'));
		if($location_id&&!Location::get_for_user($location_id,$user_id))return new \WP_Error('swimlog_location',__('Select one of your own locations.','chriss-swim-training-progress-evaluation'));

		$parsed=self::parse_source($ext,$tmp_path);if(is_wp_error($parsed))return $parsed;
		$v=self::validate($parsed);if(is_wp_error($v))return$v;
		if(!$location_id&&$ext==='csv')$location_id=self::location_from_csv($user_id,$parsed);
		if(!$location_id&&'open_water'===($parsed['workout']['workout_type']??'pool'))$location_id=self::location_from_open_water_track($user_id,$parsed);
		if(!empty($source_meta['require_ows_location'])&&'open_water'===($parsed['workout']['workout_type']??'pool')&&!$location_id)return new \WP_Error('swimlog_ows_location_review',__('Open-water GPS did not match a saved location within 500 meters. Review this activity and assign a location manually.','chriss-swim-training-progress-evaluation'));
		$match_info=self::classify_match($user_id,$parsed['workout']);
		$now=current_time('mysql');
		if($match_info['status']==='disagreement')return new \WP_Error('swimlog_source_disagreement',__('A nearby existing workout has incompatible course or pool-length information. Import stopped for explicit review rather than attaching or creating a duplicate.','chriss-swim-training-progress-evaluation'));

		$archive_name=preg_match('/\.(?:gz|zip)$/i',$name)?$name:'';$storage_name=$preserve_filename!==''?$preserve_filename:$archive_name;$upload=self::preserve_source($user_id,$ext,$tmp_path,$storage_name);
		if(is_wp_error($upload))return$upload;
		$identity=array('source_provider'=>$provider!==''?$provider:null,'external_activity_id'=>$external!==''?$external:null);
		if($match_info['status']==='probable'){
			$row=array_merge(array('user_id'=>$user_id,'workout_id'=>(int)$match_info['workout']->id,'source_type'=>$ext,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>$size,'parser_version'=>$parsed['parser_version'],'import_status'=>'pending','error_message'=>null,'imported_at'=>$now,'updated_at'=>$now),$identity);
			$ok=$wpdb->insert($it,$row);
			if(!$ok){self::cleanup_unowned_source($upload['file'],$user_id,$hash);return new \WP_Error('swimlog_import_failed',__('The pending import could not be saved.','chriss-swim-training-progress-evaluation'));}
			self::remember_pending_location($user_id,(int)$wpdb->insert_id,$location_id);
			return array('pending'=>true,'import_id'=>(int)$wpdb->insert_id,'candidate_workout_id'=>(int)$match_info['workout']->id,'source'=>$ext,'workout'=>$parsed['workout']);
		}
		$wpdb->query('START TRANSACTION');$import_id=0;
		try{
			$row=array_merge(array('user_id'=>$user_id,'workout_id'=>null,'source_type'=>$ext,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>$size,'parser_version'=>$parsed['parser_version'],'import_status'=>'processing','imported_at'=>$now,'updated_at'=>$now),$identity);
			$wpdb->insert($it,$row);$import_id=(int)$wpdb->insert_id;if(!$import_id)throw new \Exception('import row');
			$match=$match_info['status']==='exact'?$match_info['workout']:null;$workout_id=$match?(int)$match->id:self::insert_workout($user_id,$location_id,$parsed['workout']);
			if(!$workout_id)throw new \Exception('workout');
			$has_fit=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));$became_authority=false;
			if($ext==='fit'){
				$incoming_form=self::is_form_fit($parsed,$name);
				$existing_form=$match?self::workout_has_form_authority($workout_id):false;
				if(!$match||$incoming_form&&!$existing_form){$became_authority=true;if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if(self::update_workout($workout_id,$user_id,$location_id,$parsed['workout'])===false)throw new \Exception('workout update');}
				elseif(self::supplement_workout($workout_id,$user_id,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');
			}
			elseif(!$match||!$has_fit){$became_authority=true;if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if($match&&self::update_workout($workout_id,$user_id,$location_id,$parsed['workout'],false)===false)throw new \Exception('workout update');}
			else{if(self::supplement_workout($workout_id,$user_id,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');}
			if($wpdb->update($it,array('workout_id'=>$workout_id,'import_status'=>'complete','updated_at'=>current_time('mysql')),array('id'=>$import_id))===false)throw new \Exception('import completion');if($became_authority&&false===$wpdb->update($wt,array('authoritative_import_id'=>$import_id),array('id'=>$workout_id,'user_id'=>$user_id),array('%d'),array('%d','%d')))throw new \Exception('authority metadata');
			if($wpdb->query('COMMIT')===false)throw new \Exception('commit');
			$evaluated=0;
			if(($parsed['workout']['workout_type']??'pool')==='pool'){
				require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
				$evaluated=Evaluator::evaluate_workout($workout_id,$user_id);
				if(is_wp_error($evaluated)){self::record_evaluation_error($import_id,$evaluated);return $evaluated;}
			}
			return array('workout_id'=>$workout_id,'import_id'=>$import_id,'attached'=>(bool)$match,'source'=>$ext,'performances'=>$evaluated);
		}catch(\Throwable $e){
			$wpdb->query('ROLLBACK');
			$failure_saved=self::persist_failed_import($user_id,$ext,$name,$upload,$hash,$size,$parsed['parser_version'],$e->getMessage(),$provider,$external);
			if(!$failure_saved)self::cleanup_unowned_source($upload['file'],$user_id,$hash);
			return new \WP_Error('swimlog_import_failed',$failure_saved?__('The workout could not be committed. The preserved source file and failure status were retained for diagnosis; no normalized workout data was partially saved.','chriss-swim-training-progress-evaluation'):__('The workout could not be committed. No normalized workout data was partially saved, and the untracked source copy was removed.','chriss-swim-training-progress-evaluation'));
		}
	}
	public static function validate($p){$w=$p['workout'];if(empty($w['workout_start']))return new \WP_Error('swimlog_start',__('Workout start time is missing.','chriss-swim-training-progress-evaluation'));$type=$w['workout_type']??'pool';if($type==='open_water'){if(empty($w['total_distance_m'])||(float)$w['total_distance_m']<=0)return new \WP_Error('swimlog_open_water_distance',__('Open-water workout distance is missing or invalid.','chriss-swim-training-progress-evaluation'));return true;}if($type!=='pool')return new \WP_Error('swimlog_workout_type',__('Workout type is not supported.','chriss-swim-training-progress-evaluation'));if(empty($w['pool_length_unit'])||!in_array($w['pool_length_unit'],array('m','yd'),true))return new \WP_Error('swimlog_course',__('Pool course could not be determined.','chriss-swim-training-progress-evaluation'));if(empty($p['lengths'])){if(($p['source']??'')==='csv')return new \WP_Error('swimlog_csv_incomplete',__('This FORM CSV contains workout information but no swim-length data or workout distance. It cannot be imported as a complete workout.','chriss-swim-training-progress-evaluation'));return new \WP_Error('swimlog_lengths',__('No usable swim lengths were found.','chriss-swim-training-progress-evaluation'));}$sum=0;foreach($p['lengths'] as $l){if($l['length_type']==='active')$sum+=(float)$l['distance_m'];}if(isset($w['total_distance_m'])&&$w['total_distance_m']!==null&&abs($sum-(float)$w['total_distance_m'])>max(1.0,(float)$w['pool_length_m']))return new \WP_Error('swimlog_totals',__('Workout distance and normalized active lengths disagree. Import stopped for review.','chriss-swim-training-progress-evaluation'));return true;}
	private static function find_match($uid,$w){$m=self::classify_match($uid,$w);return $m['status']==='exact'?$m['workout']:null;}

	/**
	 * Normalize an incoming workout start for matching against the local
	 * WordPress datetime stored in the workouts table.
	 *
	 * Parser-produced local datetimes are preserved. ISO-8601 values carrying
	 * an explicit offset (or Z) are converted to the WordPress timezone first.
	 */
	public static function normalize_match_start($start){
		$start=trim((string)$start);
		if($start==='')return '';
		if(preg_match('/(?:Z|[+-]\\d{2}:?\\d{2})$/i',$start)){
			try{
				$dt=new \DateTimeImmutable($start);
				$tz=function_exists('wp_timezone')?wp_timezone():new \DateTimeZone('UTC');
				return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
			}catch(\Exception $e){return $start;}
		}
		try{return (new \DateTimeImmutable($start))->format('Y-m-d H:i:s');}catch(\Exception $e){return $start;}
	}

	public static function classify_match($uid,$w){
		global $wpdb;
		$t=Database::table('workouts');
		$start=self::normalize_match_start($w['workout_start']??'');
		if($start==='')return array('status'=>'none','workout'=>null);
		$dist=(float)($w['total_distance_m']??0);
		$elapsed=(int)($w['elapsed_time_ms']??0);
		$type=$w['workout_type']??'pool';

		if($type==='open_water'){
			$moving=(int)($w['moving_time_ms']??0);
			$date=substr($start,0,10);
			$distance_tolerance=max(25.0,$dist*0.02);
			$exact_candidates=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND workout_type='open_water' AND DATE(workout_start)=%s AND ABS(COALESCE(total_distance_m,0)-%f)<=%f AND ((%d>0 AND (ABS(COALESCE(elapsed_time_ms,0)-%d)<=15000 OR ABS(COALESCE(moving_time_ms,0)-%d)<=15000)) OR (%d>0 AND (ABS(COALESCE(elapsed_time_ms,0)-%d)<=15000 OR ABS(COALESCE(moving_time_ms,0)-%d)<=15000))) ORDER BY ABS(COALESCE(total_distance_m,0)-%f) ASC,id ASC",$uid,$date,$dist,$distance_tolerance,$elapsed,$elapsed,$elapsed,$moving,$moving,$moving,$dist));
			if(count((array)$exact_candidates)===1)return array('status'=>'exact','workout'=>$exact_candidates[0]);
			if(count((array)$exact_candidates)>1)return array('status'=>'probable','workout'=>$exact_candidates[0]);

			$candidates=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND workout_type='open_water' AND DATE(workout_start)=%s AND ABS(COALESCE(total_distance_m,0)-%f)<=%f ORDER BY ABS(COALESCE(total_distance_m,0)-%f) ASC,id ASC",$uid,$date,$dist,max(100.0,$dist*0.05),$dist));
			foreach((array)$candidates as $candidate){
				$candidate_elapsed=(int)($candidate->elapsed_time_ms??0);
				$candidate_moving=(int)($candidate->moving_time_ms??0);
				$time_close=($elapsed>0&&(($candidate_elapsed>0&&abs($candidate_elapsed-$elapsed)<=120000)||($candidate_moving>0&&abs($candidate_moving-$elapsed)<=120000)))||($moving>0&&(($candidate_elapsed>0&&abs($candidate_elapsed-$moving)<=120000)||($candidate_moving>0&&abs($candidate_moving-$moving)<=120000)));
				if($time_close)return array('status'=>'probable','workout'=>$candidate);
			}
			return array('status'=>'none','workout'=>null);
		}

		$exact=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND workout_type='pool' AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=2 AND ABS(COALESCE(total_distance_m,0)-%f)<=0.5 AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=2000 ORDER BY id ASC LIMIT 1",$uid,$start,$dist,$elapsed));
		if($exact)return array('status'=>'exact','workout'=>$exact);

		$course=$w['pool_length_unit']??'';
		$pool=(float)($w['pool_length_m']??0);
		if(!in_array($course,array('m','yd'),true)||$pool<=0)return array('status'=>'none','workout'=>null);

		$near=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND workout_type='pool' AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=300 AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=120000 ORDER BY ABS(TIMESTAMPDIFF(SECOND,workout_start,%s)) ASC,id ASC",$uid,$start,$elapsed,$start));
		foreach((array)$near as $candidate){
			$candidate_course=$candidate->pool_length_unit??'';
			$candidate_pool=(float)($candidate->pool_length_m??0);
			if($candidate_course!==$course||($candidate_pool>0&&abs($candidate_pool-$pool)>0.02))return array('status'=>'disagreement','workout'=>$candidate);
		}

		$candidates=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND workout_type='pool' AND pool_length_unit=%s AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=300 AND ABS(COALESCE(total_distance_m,0)-%f)<=%f AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=120000 ORDER BY ABS(TIMESTAMPDIFF(SECOND,workout_start,%s)) ASC,id ASC",$uid,$course,$start,$dist,$pool,$elapsed,$start));
		foreach((array)$candidates as $candidate){if(abs((float)$candidate->pool_length_m-$pool)<=0.02)return array('status'=>'probable','workout'=>$candidate);}
		return array('status'=>'none','workout'=>null);
	}
	public static function pending_for_user($import_id,$uid){global $wpdb;$it=Database::table('imports');$wt=Database::table('workouts');return $wpdb->get_row($wpdb->prepare("SELECT i.*,w.workout_start candidate_start,w.total_distance_m candidate_distance_m,w.elapsed_time_ms candidate_elapsed_ms,w.original_distance candidate_original_distance,w.original_distance_unit candidate_distance_unit,w.original_pool_length candidate_pool_length,w.pool_length_unit candidate_course,w.location_id candidate_location_id FROM $it i JOIN $wt w ON w.id=i.workout_id AND w.user_id=i.user_id WHERE i.id=%d AND i.user_id=%d AND i.import_status='pending'",$import_id,$uid));}
	public static function pending_all_for_user($uid){global $wpdb;$it=Database::table('imports');$wt=Database::table('workouts');return $wpdb->get_results($wpdb->prepare("SELECT i.*,w.workout_start candidate_start,w.total_distance_m candidate_distance_m,w.elapsed_time_ms candidate_elapsed_ms,w.original_distance candidate_original_distance,w.original_distance_unit candidate_distance_unit,w.original_pool_length candidate_pool_length,w.pool_length_unit candidate_course,w.location_id candidate_location_id FROM $it i JOIN $wt w ON w.id=i.workout_id AND w.user_id=i.user_id WHERE i.user_id=%d AND i.import_status='pending' ORDER BY i.imported_at ASC,i.id ASC",$uid));}
	public static function resolve_pending($import_id,$uid,$choice,$location_id=0){
		global $wpdb;$row=self::pending_for_user($import_id,$uid);if(!$row)return new \WP_Error('swimlog_pending',__('Pending import was not found.','chriss-swim-training-progress-evaluation'));
		$saved_location=self::pending_location($uid,$import_id);if(!$location_id&&$saved_location)$location_id=$saved_location;
		if(!in_array($choice,array('attach','separate'),true))return new \WP_Error('swimlog_pending_choice',__('Choose whether to attach or import separately.','chriss-swim-training-progress-evaluation'));
		if($location_id&&!Location::get_for_user($location_id,$uid))return new \WP_Error('swimlog_location',__('Select one of your own locations.','chriss-swim-training-progress-evaluation'));
		if(!is_file($row->stored_path))return new \WP_Error('swimlog_source_missing',__('The preserved source file could not be found.','chriss-swim-training-progress-evaluation'));
		$parsed=self::parse_source($row->source_type,$row->stored_path);if(is_wp_error($parsed))return $parsed;$v=self::validate($parsed);if(is_wp_error($v))return $v;
		$candidate_id=(int)$row->workout_id;if($choice==='attach'){$classification=self::classify_match($uid,$parsed['workout']);if($classification['status']==='none'||(int)$classification['workout']->id!==$candidate_id)return new \WP_Error('swimlog_pending_changed',__('The candidate workout no longer qualifies for attachment. Review the upload again.','chriss-swim-training-progress-evaluation'));}
		$wpdb->query('START TRANSACTION');try{
			$workout_id=$choice==='attach'?$candidate_id:self::insert_workout($uid,$location_id,$parsed['workout']);if(!$workout_id)throw new \Exception('workout');
			$it=Database::table('imports');$has_fit=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));
			if($row->source_type==='fit'&&$choice==='separate'){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');}
			elseif($row->source_type==='fit'&&$choice==='attach'){
				$incoming_form=self::is_form_fit($parsed,(string)$row->original_filename);
				$existing_form=self::workout_has_form_authority($workout_id);
				if($incoming_form&&!$existing_form){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if(self::update_workout($workout_id,$uid,$location_id,$parsed['workout'])===false)throw new \Exception('workout update');}
				elseif(self::supplement_workout($workout_id,$uid,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');
			}
			elseif($choice==='separate'||!$has_fit){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if($choice==='attach'&&self::update_workout($workout_id,$uid,$location_id,$parsed['workout'],false)===false)throw new \Exception('workout update');}
			else if(self::supplement_workout($workout_id,$uid,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');
			if($wpdb->update($it,array('workout_id'=>$workout_id,'import_status'=>'complete','updated_at'=>current_time('mysql'),'error_message'=>null),array('id'=>$import_id,'user_id'=>$uid))===false)throw new \Exception('import completion');if($wpdb->query('COMMIT')===false)throw new \Exception('commit');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';$evaluated=Evaluator::evaluate_workout($workout_id,$uid);if(is_wp_error($evaluated)){self::record_evaluation_error($import_id,$evaluated);return $evaluated;}
			self::forget_pending_location($uid,$import_id);return array('workout_id'=>$workout_id,'import_id'=>(int)$import_id,'attached'=>$choice==='attach','source'=>$row->source_type,'performances'=>$evaluated);
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');$wpdb->update(Database::table('imports'),array('error_message'=>substr((string)$e->getMessage(),0,2000),'updated_at'=>current_time('mysql')),array('id'=>$import_id,'user_id'=>$uid,'import_status'=>'pending'));return new \WP_Error('swimlog_import_failed',__('The pending workout could not be committed. The source remains pending for another review attempt; no normalized workout data was partially saved.','chriss-swim-training-progress-evaluation'));}
	}
	public static function rebuild_workout($workout_id,$uid){
		global $wpdb;$workout_id=absint($workout_id);$uid=absint($uid);
		$wt=Database::table('workouts');$it=Database::table('imports');$pt=Database::table('performances');
		$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $wt WHERE id=%d AND user_id=%d",$workout_id,$uid));
		if(!$existing)return new \WP_Error('swimlog_rebuild_workout',__('Workout not found.','chriss-swim-training-progress-evaluation'));
		$sources=$wpdb->get_results($wpdb->prepare("SELECT * FROM $it WHERE workout_id=%d AND user_id=%d AND import_status='complete' ORDER BY CASE WHEN source_type='fit' THEN 0 ELSE 1 END,id ASC",$workout_id,$uid));
		if(!$sources)return new \WP_Error('swimlog_rebuild_source',__('No completed preserved source is attached to this workout.','chriss-swim-training-progress-evaluation'));
		$parsed_sources=array();
		foreach($sources as$row){
			if(!in_array($row->source_type,array('fit','csv','gpx','tcx'),true))continue;
			if(!$row->stored_path||!is_file($row->stored_path))return new \WP_Error('swimlog_source_missing',__('A preserved source file required for this rebuild could not be found. No workout data was changed.','chriss-swim-training-progress-evaluation'));
			$parser=self::parser_for_source($row->source_type);if(is_wp_error($parser))return$parser;$parsed=in_array($row->source_type,array('gpx','tcx'),true)?$parser->parse($row->stored_path,$row->source_type):$parser->parse($row->stored_path);
			if(is_wp_error($parsed))return new \WP_Error('swimlog_rebuild_parse',sprintf(/* translators: 1: source format, 2: parsing detail. */ __('A preserved %1$s source could not be reparsed: %2$s No workout data was changed.','chriss-swim-training-progress-evaluation'),strtoupper($row->source_type),$parsed->get_error_message()));
			$valid=self::validate($parsed);if(is_wp_error($valid))return $valid;$parsed_sources[]=array('row'=>$row,'parsed'=>$parsed);
		}
		if(!$parsed_sources)return new \WP_Error('swimlog_rebuild_source',__('No supported preserved FIT, CSV, GPX, or TCX source is available for this workout.','chriss-swim-training-progress-evaluation'));
		$authority=$parsed_sources[0];$first_fit=null;foreach($parsed_sources as$item){if('fit'!==$item['row']->source_type)continue;if(null===$first_fit)$first_fit=$item;if(self::is_form_fit($item['parsed'],(string)$item['row']->original_filename)){$authority=$item;$first_fit=null;break;}}if(null!==$first_fit)$authority=$first_fit;
		$location_id=absint($existing->location_id??0);
		if(false===$wpdb->query('START TRANSACTION'))return new \WP_Error('swimlog_rebuild_transaction',__('The rebuild transaction could not be started. No workout data was changed.','chriss-swim-training-progress-evaluation'));
		try{
			if(self::update_workout($workout_id,$uid,$location_id,$authority['parsed']['workout'])===false)throw new \Exception('workout update');
			if(!self::replace_structure($workout_id,$authority['parsed']))throw new \Exception('structure');if(false===$wpdb->update($wt,array('authoritative_import_id'=>(int)$authority['row']->id),array('id'=>$workout_id,'user_id'=>$uid),array('%d'),array('%d','%d')))throw new \Exception('authority metadata');
			foreach($parsed_sources as$item){if($item===$authority)continue;if(self::supplement_workout($workout_id,$uid,$location_id,$item['parsed']['workout'])===false)throw new \Exception('workout supplement');}
			if(false===$wpdb->delete($pt,array('workout_id'=>$workout_id,'user_id'=>$uid),array('%d','%d')))throw new \Exception('performances');
			if(false===$wpdb->query('COMMIT'))throw new \Exception('commit');
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');return new \WP_Error('swimlog_rebuild_failed',__('The workout rebuild failed and was rolled back. The preserved sources and existing workout remain available.','chriss-swim-training-progress-evaluation'));}
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';$evaluated=Evaluator::evaluate_workout($workout_id,$uid);
		if(is_wp_error($evaluated))return $evaluated;
		return array('workout_id'=>$workout_id,'sources'=>count($parsed_sources),'performances'=>$evaluated);
	}
	public static function pending_location($uid,$import_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);return absint($map[(int)$import_id]??0);}
	private static function remember_pending_location($uid,$import_id,$location_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);if($location_id)$map[(int)$import_id]=absint($location_id);else unset($map[(int)$import_id]);update_user_meta($uid,'swimlog_pending_locations',$map);}
	private static function forget_pending_location($uid,$import_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);unset($map[(int)$import_id]);update_user_meta($uid,'swimlog_pending_locations',$map);}
	public static function parse_source($source,$path){
		$source=strtolower((string)$source);$prepared=self::prepare_source_file($source,$path);if(is_wp_error($prepared))return$prepared;
		$parser=self::parser_for_source($source);if(is_wp_error($parser)){self::cleanup_prepared_source($prepared);return$parser;}
		$result=in_array($source,array('gpx','tcx'),true)?$parser->parse($prepared['path'],$source):$parser->parse($prepared['path']);
		self::cleanup_prepared_source($prepared);return$result;
	}
	private static function uploaded_source_type($name,$path){
		$lower=strtolower((string)$name);$supported=array('fit','csv','gpx','tcx');
		if(substr($lower,-3)==='.gz'){
			$inner=substr($lower,0,-3);$ext=strtolower(pathinfo($inner,PATHINFO_EXTENSION));
			if(!in_array($ext,$supported,true))return new \WP_Error('swimlog_upload_type',__('The gzip archive does not contain a supported workout filename.','chriss-swim-training-progress-evaluation'));
			return$ext;
		}
		if(substr($lower,-4)==='.zip')return self::zip_source_type($path);
		$ext=strtolower(pathinfo($lower,PATHINFO_EXTENSION));return in_array($ext,$supported,true)?$ext:new \WP_Error('swimlog_upload_type',__('This workout file type is not currently allowed.','chriss-swim-training-progress-evaluation'));
	}
	private static function zip_source_type($path){
		if(!class_exists('ZipArchive'))return new \WP_Error('swimlog_zip_unavailable',__('This server does not provide ZIP support required to import this workout archive.','chriss-swim-training-progress-evaluation'));
		$zip=new \ZipArchive();if(true!==$zip->open($path))return new \WP_Error('swimlog_zip_invalid',__('The workout ZIP archive could not be opened.','chriss-swim-training-progress-evaluation'));
		$matches=array();
		for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$entry=(string)($stat['name']??'');if($entry===''||substr($entry,-1)==='/')continue;
			if(strpos($entry,"\\0")!==false||preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#',$entry)||preg_match('#^[\\\\/]#',$entry)){ $zip->close();return new \WP_Error('swimlog_zip_path',__('The workout ZIP archive contains an unsafe file path.','chriss-swim-training-progress-evaluation')); }
			$ext=strtolower(pathinfo($entry,PATHINFO_EXTENSION));if(in_array($ext,array('fit','csv','gpx','tcx'),true))$matches[]=array('index'=>$i,'source'=>$ext,'size'=>(int)($stat['size']??0));
		}
		$zip->close();if(count($matches)!==1)return new \WP_Error('swimlog_zip_contents',__('The workout ZIP archive must contain exactly one supported FIT, CSV, GPX, or TCX workout file.','chriss-swim-training-progress-evaluation'));
		if($matches[0]['size']<=0||$matches[0]['size']>self::MAX_BYTES)return new \WP_Error('swimlog_archive_size',__('The expanded workout file may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		return$matches[0]['source'];
	}
	private static function prepare_source_file($source,$path){
		$fs=self::filesystem();if(is_wp_error($fs))return$fs;
		$data=$fs->get_contents($path);if(false===$data)return new \WP_Error('swimlog_source_read',__('The workout source file could not be read.','chriss-swim-training-progress-evaluation'));
		$magic=substr($data,0,4);
		if(substr($magic,0,2)==="\x1f\x8b")return self::prepare_gzip_source($source,$path,$data);
		if(substr($magic,0,2)==='PK')return self::prepare_zip_source($source,$path);
		return array('path'=>$path,'temporary'=>false);
	}
	private static function prepare_gzip_source($source,$path,$compressed=null){
		if(!function_exists('gzdecode'))return new \WP_Error('swimlog_gzip_unavailable',__('This server does not provide gzip support required to import this workout file.','chriss-swim-training-progress-evaluation'));
		$fs=self::filesystem();if(is_wp_error($fs))return$fs;
		if(null===$compressed)$compressed=$fs->get_contents($path);
		if(false===$compressed||strlen($compressed)<18)return new \WP_Error('swimlog_gzip_invalid',__('The gzip workout file is corrupt or incomplete.','chriss-swim-training-progress-evaluation'));
		$isize=unpack('V',substr($compressed,-4))[1];if($isize<=0||$isize>self::MAX_BYTES)return new \WP_Error('swimlog_archive_size',__('The expanded workout file may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$decoded=@gzdecode($compressed,self::MAX_BYTES+1);
		if(false===$decoded||strlen($decoded)<=0)return new \WP_Error('swimlog_gzip_invalid',__('The gzip workout file is corrupt or incomplete.','chriss-swim-training-progress-evaluation'));
		if(strlen($decoded)>self::MAX_BYTES)return new \WP_Error('swimlog_archive_size',__('The expanded workout file may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$tmp=wp_tempnam('swimlog-'.$source);if(!$tmp)return new \WP_Error('swimlog_temp',__('A temporary workout file could not be created.','chriss-swim-training-progress-evaluation'));
		if(!$fs->put_contents($tmp,$decoded,FS_CHMOD_FILE)){wp_delete_file($tmp);return new \WP_Error('swimlog_temp',__('The temporary workout file could not be written.','chriss-swim-training-progress-evaluation'));}
		return array('path'=>$tmp,'temporary'=>true);
	}
	private static function prepare_zip_source($source,$path){
		if(!class_exists('ZipArchive'))return new \WP_Error('swimlog_zip_unavailable',__('This server does not provide ZIP support required to import this workout archive.','chriss-swim-training-progress-evaluation'));
		$zip=new \ZipArchive();if(true!==$zip->open($path))return new \WP_Error('swimlog_zip_invalid',__('The workout ZIP archive could not be opened.','chriss-swim-training-progress-evaluation'));
		$match=null;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$entry=(string)($stat['name']??'');if($entry===''||substr($entry,-1)==='/')continue;$ext=strtolower(pathinfo($entry,PATHINFO_EXTENSION));if($ext!==$source)continue;
			if(strpos($entry,"\\0")!==false||preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#',$entry)||preg_match('#^[\\\\/]#',$entry)){ $zip->close();return new \WP_Error('swimlog_zip_path',__('The workout ZIP archive contains an unsafe file path.','chriss-swim-training-progress-evaluation')); }
			if(null!==$match){$zip->close();return new \WP_Error('swimlog_zip_contents',__('The workout ZIP archive contains more than one supported workout file.','chriss-swim-training-progress-evaluation'));}$match=array('index'=>$i,'size'=>(int)($stat['size']??0));}
		if(!$match||$match['size']<=0||$match['size']>self::MAX_BYTES){$zip->close();return new \WP_Error('swimlog_archive_size',__('The ZIP archive does not contain one valid workout file of 10 MB or less.','chriss-swim-training-progress-evaluation'));}
		$decoded=$zip->getFromIndex($match['index'],self::MAX_BYTES+1);$zip->close();
		if(false===$decoded||strlen($decoded)<=0)return new \WP_Error('swimlog_zip_invalid',__('The workout file inside the ZIP archive could not be read.','chriss-swim-training-progress-evaluation'));
		if(strlen($decoded)>self::MAX_BYTES)return new \WP_Error('swimlog_archive_size',__('The expanded workout file may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$fs=self::filesystem();if(is_wp_error($fs))return$fs;$tmp=wp_tempnam('swimlog-'.$source);if(!$tmp)return new \WP_Error('swimlog_temp',__('A temporary workout file could not be created.','chriss-swim-training-progress-evaluation'));
		if(!$fs->put_contents($tmp,$decoded,FS_CHMOD_FILE)){wp_delete_file($tmp);return new \WP_Error('swimlog_temp',__('The temporary workout file could not be written.','chriss-swim-training-progress-evaluation'));}
		return array('path'=>$tmp,'temporary'=>true);
	}
	private static function cleanup_prepared_source($prepared){if(!empty($prepared['temporary'])&&!empty($prepared['path']))wp_delete_file($prepared['path']);}
	private static function parser_for_source($source){$source=strtolower((string)$source);if('fit'===$source)return new FIT_Importer();if('csv'===$source)return new CSV_Importer();if(in_array($source,array('gpx','tcx'),true)){require_once SWIMLOG_EVALUATION_DIR.'includes/class-xml-importer.php';return new XML_Importer();}return new \WP_Error('swimlog_parser',__('Unsupported preserved workout source type.','chriss-swim-training-progress-evaluation'));}
	public static function location_from_open_water_track($uid,$parsed,$max_m=500.0){foreach((array)($parsed['trackpoints']??array())as$p){if(!isset($p['latitude'],$p['longitude'])||null===$p['latitude']||null===$p['longitude'])continue;$match=Location::nearest_for_user($uid,(float)$p['latitude'],(float)$p['longitude'],$max_m);return$match?(int)$match['location']->id:0;}return 0;}
	private static function location_from_csv($uid,$parsed){$name=trim((string)($parsed['metadata']['_form_location']??''));if($name==='')return 0;foreach(Location::all_for_user($uid) as $loc){if(strcasecmp(trim((string)$loc->name),$name)===0)return(int)$loc->id;}return 0;}
	private static function is_form_fit($parsed,$filename=''){$w=(array)($parsed['workout']??array());$identity=strtolower(trim((string)($w['device_manufacturer']??'')).' '.trim((string)($w['device_model']??'')));if(strpos($identity,'form swim')!==false||preg_match('/(^|[^a-z])form([^a-z]|$)/',$identity))return true;return(bool)preg_match('/^FORM[_-].*\\.fit$/i',basename((string)$filename));}
	private static function workout_has_form_authority($workout_id){global $wpdb;$wt=Database::table('workouts');$it=Database::table('imports');$w=$wpdb->get_row($wpdb->prepare("SELECT device_manufacturer,device_model FROM $wt WHERE id=%d",$workout_id),ARRAY_A);if($w&&self::is_form_fit(array('workout'=>$w)))return true;$names=$wpdb->get_col($wpdb->prepare("SELECT original_filename FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));foreach((array)$names as $name){if(self::is_form_fit(array('workout'=>array()),(string)$name))return true;}return false;}
	private static function source_directory(){
		$dir=defined('SWIMLOG_PRIVATE_STORAGE_DIR')?trim((string)SWIMLOG_PRIVATE_STORAGE_DIR):trim((string)get_option('swimlog_private_storage_dir',''));
		if($dir===''){
			$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '';
			$docroot = '' !== $document_root ? realpath( $document_root ) : false;
			if(!$docroot)return new \WP_Error('swimlog_store_private',__('A private workout source directory could not be determined. Configure the Private Workout Storage Directory in Swim Training Settings.','chriss-swim-training-progress-evaluation'));
			$dir=trailingslashit(dirname($docroot)).'chriss-swim-training-progress-evaluation-private';
		}
		return self::validate_storage_directory($dir,true);
	}
	public static function validate_storage_directory($dir,$create=false){
		$dir=untrailingslashit(trim((string)$dir));
		if($dir===''||!self::is_absolute_path($dir))return new \WP_Error('swimlog_store_path',__('Enter an absolute filesystem path for the private workout storage directory.','chriss-swim-training-progress-evaluation'));
		if(!self::is_outside_web_root($dir))return new \WP_Error('swimlog_store_public',__('The workout source directory must be outside the public web root.','chriss-swim-training-progress-evaluation'));
		$fs=self::filesystem();if(is_wp_error($fs))return$fs;
		if(!$fs->is_dir($dir)){
			if(!$create)return new \WP_Error('swimlog_store_missing',__('The private workout source directory does not exist.','chriss-swim-training-progress-evaluation'));
			if(!$fs->mkdir($dir,FS_CHMOD_DIR))return new \WP_Error('swimlog_store',__('The private workout source directory could not be created. Choose a writable directory outside the public web root.','chriss-swim-training-progress-evaluation'));
		}
		if(!$fs->is_writable($dir))return new \WP_Error('swimlog_store',__('The private workout source directory is not writable by WordPress.','chriss-swim-training-progress-evaluation'));
		$index=trailingslashit($dir).'index.php';if(!$fs->exists($index)&&!$fs->put_contents($index,"<?php\n// Silence is golden.\n",FS_CHMOD_FILE))return new \WP_Error('swimlog_store',__('The private workout source directory could not be initialized.','chriss-swim-training-progress-evaluation'));
		return$dir;
	}
	private static function is_absolute_path($path){
		$path=(string)$path;if($path==='')return false;
		if($path[0]==='/'||$path[0]==='\\\\')return true;
		return (bool)preg_match('/^[A-Za-z]:[\\\\\\\/]/',$path);
	}
	private static function is_outside_web_root($dir){
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '';
		$docroot = '' !== $document_root ? realpath( $document_root ) : false;if(!$docroot)return false;
		$root=rtrim(wp_normalize_path($docroot),'/').'/';$candidate=wp_normalize_path($dir);
		$existing=realpath($dir);if($existing)$candidate=wp_normalize_path($existing);
		$candidate=rtrim($candidate,'/').'/';return strpos($candidate,$root)!==0;
	}
	private static function preserve_source($uid,$ext,$tmp,$preserve_filename=''){
		$dir=self::source_directory();if(is_wp_error($dir))return$dir;$fs=self::filesystem();if(is_wp_error($fs))return$fs;
		$filename=$preserve_filename!==''?basename($preserve_filename):'swimlog-'.absint($uid).'-'.wp_generate_uuid4().'.'.$ext;$path=trailingslashit($dir).$filename;
		if($preserve_filename!==''&&$fs->exists($path))return new \WP_Error('swimlog_store_collision',__('A protected workout source file with this filename already exists. Import stopped without overwriting it.','chriss-swim-training-progress-evaluation'));
		if(!$fs->copy($tmp,$path,false,0640))return new \WP_Error('swimlog_store',__('The original workout file could not be preserved in protected storage.','chriss-swim-training-progress-evaluation'));$fs->chmod($path,0640);return array('file'=>$path,'url'=>'','error'=>false);
	}
	private static function filesystem(){
		global $wp_filesystem;
		if(!function_exists('WP_Filesystem'))require_once ABSPATH.'wp-admin/includes/file.php';
		if(!$wp_filesystem&&!WP_Filesystem())return new \WP_Error('swimlog_filesystem',__('WordPress could not initialize filesystem access for protected workout storage.','chriss-swim-training-progress-evaluation'));
		return$wp_filesystem;
	}
	private static function cleanup_unowned_source($path,$uid,$hash){global $wpdb;if(!$path||!is_file($path))return;$it=Database::table('imports');$owned=$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s AND stored_path=%s",$uid,$hash,$path));if(!$owned)wp_delete_file($path);}
	private static function persist_failed_import($uid,$source,$name,$upload,$hash,$size,$parser_version,$message,$provider='',$external=''){global $wpdb;$it=Database::table('imports');$now=current_time('mysql');$existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s",$uid,$hash));if($existing){$ok=$wpdb->update($it,array('import_status'=>'failed','error_message'=>substr((string)$message,0,2000),'updated_at'=>$now),array('id'=>(int)$existing,'user_id'=>$uid));return false!==$ok;}$ok=$wpdb->insert($it,array('user_id'=>$uid,'workout_id'=>null,'source_type'=>$source,'source_provider'=>$provider!==''?$provider:null,'external_activity_id'=>$external!==''?$external:null,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>$size,'parser_version'=>$parser_version,'import_status'=>'failed','error_message'=>substr((string)$message,0,2000),'imported_at'=>$now,'updated_at'=>$now));return(bool)$ok;}
	private static function record_evaluation_error($import_id,$error){global $wpdb;$it=Database::table('imports');$wpdb->update($it,array('error_message'=>substr('Evaluation: '.$error->get_error_message(),0,2000),'updated_at'=>current_time('mysql')),array('id'=>(int)$import_id));}
	private static function insert_workout($uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);$d['created_at']=current_time('mysql');$d['updated_at']=current_time('mysql');return $wpdb->insert($t,$d)?(int)$wpdb->insert_id:0;}
	private static function update_workout($id,$uid,$loc,$w,$overwrite=true){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);unset($d['user_id']);if(!$overwrite){foreach($d as $k=>$v){if($v===null||$v==='')unset($d[$k]);}}$d['updated_at']=current_time('mysql');return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function supplement_workout($id,$uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=array('updated_at'=>current_time('mysql'));if($loc)$d['location_id']=$loc;if(!empty($w['notes']))$d['notes']=$w['notes'];return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function workout_values($uid,$loc,$w){return array('user_id'=>$uid,'location_id'=>$loc?:null,'workout_type'=>$w['workout_type']??'pool','workout_start'=>$w['workout_start'],'workout_end'=>$w['workout_end']??null,'total_distance_m'=>$w['total_distance_m']??null,'original_distance'=>$w['original_distance']??null,'original_distance_unit'=>$w['original_distance_unit']??null,'elapsed_time_ms'=>$w['elapsed_time_ms']??null,'moving_time_ms'=>$w['moving_time_ms']??null,'average_heart_rate'=>$w['average_heart_rate']??null,'max_heart_rate'=>$w['max_heart_rate']??null,'average_cadence'=>$w['average_cadence']??null,'max_cadence'=>$w['max_cadence']??null,'pool_length_m'=>$w['pool_length_m']??null,'original_pool_length'=>$w['original_pool_length']??null,'pool_length_unit'=>$w['pool_length_unit']??null,'primary_stroke'=>$w['primary_stroke']??null,'device_manufacturer'=>$w['device_manufacturer']??null,'device_model'=>$w['device_model']??null,'source_activity_id'=>$w['source_activity_id']??null,'notes'=>$w['notes']??null);}
	private static function replace_structure($wid,$p){global $wpdb;$lt=Database::table('laps');$nt=Database::table('lengths');$tt=Database::table('trackpoints');if($wpdb->delete($lt,array('workout_id'=>$wid))===false)return false;if($wpdb->delete($nt,array('workout_id'=>$wid))===false)return false;if($wpdb->delete($tt,array('workout_id'=>$wid))===false)return false;foreach($p['laps'] as $x){$x['workout_id']=$wid;$x['created_at']=current_time('mysql');if(!$wpdb->insert($lt,$x))return false;}foreach($p['lengths'] as $x){$x['workout_id']=$wid;$x['lap_id']=null;$x['created_at']=current_time('mysql');if(!$wpdb->insert($nt,$x))return false;}foreach(($p['trackpoints']??array()) as $x){$x['workout_id']=$wid;$x['created_at']=current_time('mysql');if(!$wpdb->insert($tt,$x))return false;}return true;}
}
