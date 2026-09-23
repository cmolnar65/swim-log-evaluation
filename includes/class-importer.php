<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Secure upload, duplicate detection, matching and normalized persistence. */
final class Importer {
	const MAX_BYTES=10485760;
	public static function import_upload($user_id,$file,$location_id=0){
		global $wpdb;
		if(empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))return new \WP_Error('swimlog_upload',__('No valid uploaded file was received.','chriss-swim-training-progress-evaluation'));
		if(!empty($file['error']))return new \WP_Error('swimlog_upload',__('The upload failed before import.','chriss-swim-training-progress-evaluation'));
		if((int)$file['size']>self::MAX_BYTES)return new \WP_Error('swimlog_upload_size',__('Workout files may not exceed 10 MB.','chriss-swim-training-progress-evaluation'));
		$name=sanitize_file_name($file['name']);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
		$allowed=(array)get_option('swimlog_allowed_upload_types',array('fit','csv'));
		if(!in_array($ext,array('fit','csv'),true)||!in_array($ext,$allowed,true))return new \WP_Error('swimlog_upload_type',__('This workout file type is not currently allowed.','chriss-swim-training-progress-evaluation'));
		$hash=hash_file('sha256',$file['tmp_name']);$it=Database::table('imports');
		if($wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s",$user_id,$hash)))return new \WP_Error('swimlog_duplicate',__('This exact file has already been imported.','chriss-swim-training-progress-evaluation'));
		if($location_id&&!Location::get_for_user($location_id,$user_id))return new \WP_Error('swimlog_location',__('Select one of your own locations.','chriss-swim-training-progress-evaluation'));

		$parser=$ext==='fit'?new FIT_Importer():new CSV_Importer();$parsed=$parser->parse($file['tmp_name']);if(is_wp_error($parsed))return $parsed;
		$v=self::validate($parsed);if(is_wp_error($v))return$v;
		if(!$location_id&&$ext==='csv')$location_id=self::location_from_csv($user_id,$parsed);
		$match_info=self::classify_match($user_id,$parsed['workout']);
		$now=current_time('mysql');
		// Reject disagreements before permanent preservation so rejected uploads cannot become orphan source files.
		if($match_info['status']==='disagreement')return new \WP_Error('swimlog_source_disagreement',__('A nearby existing workout has incompatible course or pool-length information. Import stopped for explicit review rather than attaching or creating a duplicate.','chriss-swim-training-progress-evaluation'));

		$upload=self::preserve_source($user_id,$ext,$file['tmp_name']);
		if(is_wp_error($upload))return$upload;
		if($match_info['status']==='probable'){
			$ok=$wpdb->insert($it,array('user_id'=>$user_id,'workout_id'=>(int)$match_info['workout']->id,'source_type'=>$ext,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>(int)$file['size'],'parser_version'=>$parsed['parser_version'],'import_status'=>'pending','error_message'=>null,'imported_at'=>$now,'updated_at'=>$now));
			if(!$ok){self::cleanup_unowned_source($upload['file'],$user_id,$hash);return new \WP_Error('swimlog_import_failed',__('The pending import could not be saved.','chriss-swim-training-progress-evaluation'));}
			self::remember_pending_location($user_id,(int)$wpdb->insert_id,$location_id);
			return array('pending'=>true,'import_id'=>(int)$wpdb->insert_id,'candidate_workout_id'=>(int)$match_info['workout']->id,'source'=>$ext,'workout'=>$parsed['workout']);
		}
		$wpdb->query('START TRANSACTION');$import_id=0;
		try{
			$wpdb->insert($it,array('user_id'=>$user_id,'workout_id'=>null,'source_type'=>$ext,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>(int)$file['size'],'parser_version'=>$parsed['parser_version'],'import_status'=>'processing','imported_at'=>$now,'updated_at'=>$now));$import_id=(int)$wpdb->insert_id;if(!$import_id)throw new \Exception('import row');
			$match=$match_info['status']==='exact'?$match_info['workout']:null;$workout_id=$match? (int)$match->id:self::insert_workout($user_id,$location_id,$parsed['workout']);
			if(!$workout_id)throw new \Exception('workout');
			// FIT is structural authority. CSV supplements an existing FIT-backed workout without replacing its lengths.
			$has_fit=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));
			if($ext==='fit'){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if(self::update_workout($workout_id,$user_id,$location_id,$parsed['workout'])===false)throw new \Exception('workout update');}
			elseif(!$match||!$has_fit){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if($match&&self::update_workout($workout_id,$user_id,$location_id,$parsed['workout'],false)===false)throw new \Exception('workout update');}
			else{if(self::supplement_workout($workout_id,$user_id,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');}
			if($wpdb->update($it,array('workout_id'=>$workout_id,'import_status'=>'complete','updated_at'=>current_time('mysql')),array('id'=>$import_id))===false)throw new \Exception('import completion');
			if($wpdb->query('COMMIT')===false)throw new \Exception('commit');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
			$evaluated=Evaluator::evaluate_workout($workout_id,$user_id);
			if(is_wp_error($evaluated)){self::record_evaluation_error($import_id,$evaluated);return $evaluated;}
			return array('workout_id'=>$workout_id,'import_id'=>$import_id,'attached'=>(bool)$match,'source'=>$ext,'performances'=>$evaluated);
		}catch(\Throwable $e){
			$wpdb->query('ROLLBACK');
			$failure_saved=self::persist_failed_import($user_id,$ext,$name,$upload,$hash,(int)$file['size'],$parsed['parser_version'],$e->getMessage());
			if(!$failure_saved)self::cleanup_unowned_source($upload['file'],$user_id,$hash);
			return new \WP_Error('swimlog_import_failed',$failure_saved?__('The workout could not be committed. The preserved source file and failure status were retained for diagnosis; no normalized workout data was partially saved.','chriss-swim-training-progress-evaluation'):__('The workout could not be committed. No normalized workout data was partially saved, and the untracked source copy was removed.','chriss-swim-training-progress-evaluation'));
		}
	}
	public static function validate($p){$w=$p['workout'];if(empty($w['workout_start']))return new \WP_Error('swimlog_start',__('Workout start time is missing.','chriss-swim-training-progress-evaluation'));if(empty($w['pool_length_unit'])||!in_array($w['pool_length_unit'],array('m','yd'),true))return new \WP_Error('swimlog_course',__('Pool course could not be determined.','chriss-swim-training-progress-evaluation'));if(empty($p['lengths'])){if(($p['source']??'')==='csv')return new \WP_Error('swimlog_csv_incomplete',__('This FORM CSV contains workout information but no swim-length data or workout distance. It cannot be imported as a complete workout.','chriss-swim-training-progress-evaluation'));return new \WP_Error('swimlog_lengths',__('No usable swim lengths were found.','chriss-swim-training-progress-evaluation'));}$sum=0;foreach($p['lengths'] as $l){if($l['length_type']==='active')$sum+=(float)$l['distance_m'];}if(isset($w['total_distance_m'])&&$w['total_distance_m']!==null&&abs($sum-(float)$w['total_distance_m'])>max(1.0,(float)$w['pool_length_m']))return new \WP_Error('swimlog_totals',__('Workout distance and normalized active lengths disagree. Import stopped for review.','chriss-swim-training-progress-evaluation'));return true;}
	private static function find_match($uid,$w){$m=self::classify_match($uid,$w);return $m['status']==='exact'?$m['workout']:null;}
	public static function classify_match($uid,$w){
		global $wpdb;$t=Database::table('workouts');$start=$w['workout_start'];$dist=(float)($w['total_distance_m']??0);$elapsed=(int)($w['elapsed_time_ms']??0);
		$exact=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=2 AND ABS(COALESCE(total_distance_m,0)-%f)<=0.5 AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=2000 ORDER BY id ASC LIMIT 1",$uid,$start,$dist,$elapsed));
		if($exact)return array('status'=>'exact','workout'=>$exact);
		$course=$w['pool_length_unit']??'';$pool=(float)($w['pool_length_m']??0);if(!in_array($course,array('m','yd'),true)||$pool<=0)return array('status'=>'none','workout'=>null);
		$near=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=300 AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=120000 ORDER BY ABS(TIMESTAMPDIFF(SECOND,workout_start,%s)) ASC,id ASC",$uid,$start,$elapsed,$start));foreach((array)$near as $candidate){$candidate_course=$candidate->pool_length_unit??'';$candidate_pool=(float)($candidate->pool_length_m??0);if($candidate_course!==$course||($candidate_pool>0&&abs($candidate_pool-$pool)>0.02))return array('status'=>'disagreement','workout'=>$candidate);}
		$candidates=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND pool_length_unit=%s AND ABS(TIMESTAMPDIFF(SECOND,workout_start,%s))<=300 AND ABS(COALESCE(total_distance_m,0)-%f)<=%f AND ABS(COALESCE(elapsed_time_ms,0)-%d)<=120000 ORDER BY ABS(TIMESTAMPDIFF(SECOND,workout_start,%s)) ASC,id ASC",$uid,$course,$start,$dist,$pool,$elapsed,$start));
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
		$parser=$row->source_type==='fit'?new FIT_Importer():new CSV_Importer();$parsed=$parser->parse($row->stored_path);if(is_wp_error($parsed))return $parsed;$v=self::validate($parsed);if(is_wp_error($v))return $v;
		$candidate_id=(int)$row->workout_id;if($choice==='attach'){$classification=self::classify_match($uid,$parsed['workout']);if($classification['status']==='none'||(int)$classification['workout']->id!==$candidate_id)return new \WP_Error('swimlog_pending_changed',__('The candidate workout no longer qualifies for attachment. Review the upload again.','chriss-swim-training-progress-evaluation'));}
		$wpdb->query('START TRANSACTION');try{
			$workout_id=$choice==='attach'?$candidate_id:self::insert_workout($uid,$location_id,$parsed['workout']);if(!$workout_id)throw new \Exception('workout');
			$it=Database::table('imports');$has_fit=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE workout_id=%d AND source_type='fit' AND import_status='complete'",$workout_id));
			if($row->source_type==='fit'){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if(self::update_workout($workout_id,$uid,$location_id,$parsed['workout'])===false)throw new \Exception('workout update');}
			elseif($choice==='separate'||!$has_fit){if(!self::replace_structure($workout_id,$parsed))throw new \Exception('structure');if($choice==='attach'&&self::update_workout($workout_id,$uid,$location_id,$parsed['workout'],false)===false)throw new \Exception('workout update');}
			else if(self::supplement_workout($workout_id,$uid,$location_id,$parsed['workout'])===false)throw new \Exception('workout supplement');
			if($wpdb->update($it,array('workout_id'=>$workout_id,'import_status'=>'complete','updated_at'=>current_time('mysql'),'error_message'=>null),array('id'=>$import_id,'user_id'=>$uid))===false)throw new \Exception('import completion');if($wpdb->query('COMMIT')===false)throw new \Exception('commit');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';$evaluated=Evaluator::evaluate_workout($workout_id,$uid);if(is_wp_error($evaluated)){self::record_evaluation_error($import_id,$evaluated);return $evaluated;}
			self::forget_pending_location($uid,$import_id);return array('workout_id'=>$workout_id,'import_id'=>(int)$import_id,'attached'=>$choice==='attach','source'=>$row->source_type,'performances'=>$evaluated);
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');$wpdb->update(Database::table('imports'),array('error_message'=>substr((string)$e->getMessage(),0,2000),'updated_at'=>current_time('mysql')),array('id'=>$import_id,'user_id'=>$uid,'import_status'=>'pending'));return new \WP_Error('swimlog_import_failed',__('The pending workout could not be committed. The source remains pending for another review attempt; no normalized workout data was partially saved.','chriss-swim-training-progress-evaluation'));}
	}
	public static function pending_location($uid,$import_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);return absint($map[(int)$import_id]??0);}
	private static function remember_pending_location($uid,$import_id,$location_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);if($location_id)$map[(int)$import_id]=absint($location_id);else unset($map[(int)$import_id]);update_user_meta($uid,'swimlog_pending_locations',$map);}
	private static function forget_pending_location($uid,$import_id){$map=(array)get_user_meta($uid,'swimlog_pending_locations',true);unset($map[(int)$import_id]);update_user_meta($uid,'swimlog_pending_locations',$map);}
	private static function location_from_csv($uid,$parsed){$name=trim((string)($parsed['metadata']['_form_location']??''));if($name==='')return 0;foreach(Location::all_for_user($uid) as $loc){if(strcasecmp(trim((string)$loc->name),$name)===0)return(int)$loc->id;}return 0;}
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
	private static function preserve_source($uid,$ext,$tmp){
		$dir=self::source_directory();if(is_wp_error($dir))return$dir;$fs=self::filesystem();if(is_wp_error($fs))return$fs;$filename='swimlog-'.absint($uid).'-'.wp_generate_uuid4().'.'.$ext;$path=trailingslashit($dir).$filename;
		if(!$fs->copy($tmp,$path,true,0640))return new \WP_Error('swimlog_store',__('The original workout file could not be preserved in protected storage.','chriss-swim-training-progress-evaluation'));$fs->chmod($path,0640);return array('file'=>$path,'url'=>'','error'=>false);
	}
	private static function filesystem(){
		global $wp_filesystem;
		if(!function_exists('WP_Filesystem'))require_once ABSPATH.'wp-admin/includes/file.php';
		if(!$wp_filesystem&&!WP_Filesystem())return new \WP_Error('swimlog_filesystem',__('WordPress could not initialize filesystem access for protected workout storage.','chriss-swim-training-progress-evaluation'));
		return$wp_filesystem;
	}
	private static function cleanup_unowned_source($path,$uid,$hash){global $wpdb;if(!$path||!is_file($path))return;$it=Database::table('imports');$owned=$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s AND stored_path=%s",$uid,$hash,$path));if(!$owned)wp_delete_file($path);}
	private static function persist_failed_import($uid,$source,$name,$upload,$hash,$size,$parser_version,$message){global $wpdb;$it=Database::table('imports');$now=current_time('mysql');$existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE user_id=%d AND file_hash=%s",$uid,$hash));if($existing){$ok=$wpdb->update($it,array('import_status'=>'failed','error_message'=>substr((string)$message,0,2000),'updated_at'=>$now),array('id'=>(int)$existing,'user_id'=>$uid));return false!==$ok;}$ok=$wpdb->insert($it,array('user_id'=>$uid,'workout_id'=>null,'source_type'=>$source,'original_filename'=>$name,'stored_filename'=>basename($upload['file']),'stored_path'=>$upload['file'],'file_hash'=>$hash,'file_size'=>$size,'parser_version'=>$parser_version,'import_status'=>'failed','error_message'=>substr((string)$message,0,2000),'imported_at'=>$now,'updated_at'=>$now));return(bool)$ok;}
	private static function record_evaluation_error($import_id,$error){global $wpdb;$it=Database::table('imports');$wpdb->update($it,array('error_message'=>substr('Evaluation: '.$error->get_error_message(),0,2000),'updated_at'=>current_time('mysql')),array('id'=>(int)$import_id));}
	private static function insert_workout($uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);$d['created_at']=current_time('mysql');$d['updated_at']=current_time('mysql');return $wpdb->insert($t,$d)?(int)$wpdb->insert_id:0;}
	private static function update_workout($id,$uid,$loc,$w,$overwrite=true){global $wpdb;$t=Database::table('workouts');$d=self::workout_values($uid,$loc,$w);unset($d['user_id']);if(!$overwrite){foreach($d as $k=>$v){if($v===null||$v==='')unset($d[$k]);}}$d['updated_at']=current_time('mysql');return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function supplement_workout($id,$uid,$loc,$w){global $wpdb;$t=Database::table('workouts');$d=array('updated_at'=>current_time('mysql'));if($loc)$d['location_id']=$loc;if(!empty($w['notes']))$d['notes']=$w['notes'];return $wpdb->update($t,$d,array('id'=>$id,'user_id'=>$uid));}
	private static function workout_values($uid,$loc,$w){return array('user_id'=>$uid,'location_id'=>$loc?:null,'workout_start'=>$w['workout_start'],'workout_end'=>$w['workout_end']??null,'total_distance_m'=>$w['total_distance_m']??null,'original_distance'=>$w['original_distance']??null,'original_distance_unit'=>$w['original_distance_unit']??null,'elapsed_time_ms'=>$w['elapsed_time_ms']??null,'moving_time_ms'=>$w['moving_time_ms']??null,'pool_length_m'=>$w['pool_length_m']??null,'original_pool_length'=>$w['original_pool_length']??null,'pool_length_unit'=>$w['pool_length_unit']??null,'primary_stroke'=>$w['primary_stroke']??null,'device_manufacturer'=>$w['device_manufacturer']??null,'device_model'=>$w['device_model']??null,'source_activity_id'=>$w['source_activity_id']??null,'notes'=>$w['notes']??null);}
	private static function replace_structure($wid,$p){global $wpdb;$lt=Database::table('laps');$nt=Database::table('lengths');if($wpdb->delete($lt,array('workout_id'=>$wid))===false)return false;if($wpdb->delete($nt,array('workout_id'=>$wid))===false)return false;foreach($p['laps'] as $x){$x['workout_id']=$wid;$x['created_at']=current_time('mysql');if(!$wpdb->insert($lt,$x))return false;}foreach($p['lengths'] as $x){$x['workout_id']=$wid;$x['lap_id']=null;$x['created_at']=current_time('mysql');if(!$wpdb->insert($nt,$x))return false;}return true;}
}
