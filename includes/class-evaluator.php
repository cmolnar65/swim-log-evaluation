<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Deterministic native-course performance evaluator. Derived data only. */
final class Evaluator {
	const VERSION='0.1.2';
	const MIN_SECONDS_PER_25=13.0;
	const DISTANCES=array(50,100,200,500,800,1000,1500,1650,2000,2500,3300,5000);

	public static function evaluate_workout($workout_id,$user_id){
		global $wpdb;$wt=Database::table('workouts');$lt=Database::table('lengths');$lapt=Database::table('laps');$pt=Database::table('performances');
		$w=Database::read( 'get_row', $wpdb->prepare("SELECT * FROM %i WHERE id=%d AND user_id=%d",$wt, $workout_id,$user_id));if(!$w)return new \WP_Error('swimlog_eval_workout',__('Workout not found.','chriss-swim-training-progress-evaluation'));
		if(($w->workout_type??'pool')!=='pool')return 0;
		$course=$w->pool_length_unit;if(!in_array($course,array('m','yd'),true))return new \WP_Error('swimlog_eval_course',__('Workout has no valid native course.','chriss-swim-training-progress-evaluation'));
		$rows=Database::read( 'get_results', $wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d ORDER BY sequence_no ASC",$lt, $workout_id));
		$candidates=!empty($rows)?self::from_lengths($rows,$w,$course):array();
		// Source hierarchy: active lengths > native laps > exact whole-workout summary.
		$laps=array();if(empty($rows)){$laps=Database::read( 'get_results', $wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d ORDER BY sequence_no ASC",$lapt, $workout_id));if(!empty($laps))$candidates=self::from_laps($laps,$w,$course);}
		// Summary-only rule: the native distance itself must exactly match a target;
		// integer coercion must never turn (for example) 50.9 into a 50 PB.
		$summary_distance=(float)$w->original_distance;$exact_target=null;foreach(self::DISTANCES as $target){if(abs($summary_distance-$target)<0.001){$exact_target=$target;break;}}
		if(empty($rows)&&empty($laps)&&null!==$exact_target&&$w->elapsed_time_ms){
			$candidates[]=array('distance'=>$exact_target,'course'=>$course,'stroke'=>self::known_stroke($w->primary_stroke)?strtoupper($w->primary_stroke):'UNKNOWN','duration'=>(int)$w->elapsed_time_ms,'start_id'=>null,'end_id'=>null,'start_offset'=>null,'end_offset'=>null);
		}
		// Persist fastest plausible candidate per workout/distance/course/stroke.
		$best=array();foreach($candidates as $c){if(!self::is_plausible_performance($c))continue;$k=$c['distance'].'|'.$c['course'].'|'.$c['stroke'];if(!isset($best[$k])||$c['duration']<$best[$k]['duration'])$best[$k]=$c;}
		Database::write( 'query', 'START TRANSACTION');
		try{
			Database::write( 'delete', $pt,array('workout_id'=>$workout_id,'user_id'=>$user_id));
			foreach($best as $c){$ok=Database::write( 'insert', $pt,array('user_id'=>$user_id,'workout_id'=>$workout_id,'distance_value'=>$c['distance'],'course_unit'=>$c['course'],'stroke'=>$c['stroke'],'duration_ms'=>$c['duration'],'start_length_id'=>$c['start_id'],'end_length_id'=>$c['end_id'],'start_offset_ms'=>$c['start_offset'],'end_offset_ms'=>$c['end_offset'],'is_personal_best'=>0,'evaluation_version'=>self::VERSION,'achieved_at'=>$w->workout_start,'created_at'=>current_time('mysql')));if(!$ok)throw new \Exception('performance');}
			self::recalculate_personal_bests($user_id);
			Database::write( 'query', 'COMMIT');return count($best);
		}catch(\Throwable $e){Database::write( 'query', 'ROLLBACK');return new \WP_Error('swimlog_eval_save',__('Performance evaluation could not be saved.','chriss-swim-training-progress-evaluation'));}
	}

	public static function evaluate_manual_measured_repetitions( $workout_id, $user_id, $document, $manage_transaction = true ) {
		global $wpdb;
		$workout_id = absint( $workout_id ); $user_id = absint( $user_id );
		$wt = Database::table( 'workouts' ); $pt = Database::table( 'performances' );
		$w = Database::read( 'get_row',  $wpdb->prepare( "SELECT * FROM %i WHERE id=%d AND user_id=%d",$wt,  $workout_id, $user_id ) );
		if ( ! $w || 'pool' !== ( $w->workout_type ?? 'pool' ) || 'manual' !== (string) ( $w->source_type ?? '' ) ) return new \WP_Error( 'swimlog_eval_manual_workout', __( 'Manual workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		$course = (string) ( $w->pool_length_unit ?? '' ); if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_eval_course', __( 'Workout has no valid native course.', 'chriss-swim-training-progress-evaluation' ) );
		$candidates = array();
		foreach ( (array) ( $document['segments'] ?? array() ) as $segment ) {
			if ( 'active' !== ( $segment['type'] ?? '' ) || 'measured_rep' !== ( $segment['timing_type'] ?? '' ) ) continue;
			$distance = (float) ( $segment['distance_per_rep'] ?? 0 ); $target = null; foreach ( self::DISTANCES as $d ) if ( abs( $distance - $d ) < 0.001 ) { $target = $d; break; }
			$stroke = strtoupper( (string) ( $segment['stroke'] ?? 'UNKNOWN' ) ); if ( null === $target || ! self::known_stroke( $stroke ) ) continue;
			foreach ( (array) ( $segment['rep_times_ms'] ?? array() ) as $duration ) $candidates[] = array( 'distance' => $target, 'course' => $course, 'stroke' => $stroke, 'duration' => (int) $duration, 'start_id' => null, 'end_id' => null, 'start_offset' => null, 'end_offset' => null );
		}
		$best = array(); foreach ( $candidates as $c ) { if ( ! self::is_plausible_performance( $c ) ) continue; $key = $c['distance'] . '|' . $c['course'] . '|' . $c['stroke']; if ( ! isset( $best[$key] ) || $c['duration'] < $best[$key]['duration'] ) $best[$key] = $c; }
		if ( $manage_transaction && false === Database::write( 'query',  'START TRANSACTION' ) ) return new \WP_Error( 'swimlog_eval_save', __( 'Performance evaluation could not be saved.', 'chriss-swim-training-progress-evaluation' ) );
		try {
			if ( false === Database::write( 'delete',  $pt, array( 'workout_id' => $workout_id, 'user_id' => $user_id ), array( '%d', '%d' ) ) ) throw new \Exception( 'clear' );
			foreach ( $best as $candidate ) if ( ! self::insert_performance( $pt, $user_id, $workout_id, $w, $candidate ) ) throw new \Exception( 'performance' );
			self::recalculate_personal_bests( $user_id ); if ( $manage_transaction && false === Database::write( 'query',  'COMMIT' ) ) throw new \Exception( 'commit' ); return count( $best );
		} catch ( \Throwable $exception ) { if ( $manage_transaction ) Database::write( 'query',  'ROLLBACK' ); return new \WP_Error( 'swimlog_eval_save', __( 'Performance evaluation could not be saved.', 'chriss-swim-training-progress-evaluation' ) ); }
	}

	private static function evaluate_candidates($workout_id,$user_id){
		global $wpdb;$wt=Database::table('workouts');$lt=Database::table('lengths');$lapt=Database::table('laps');
		$w=Database::read( 'get_row', $wpdb->prepare("SELECT * FROM %i WHERE id=%d AND user_id=%d",$wt, $workout_id,$user_id));if(!$w)return new \WP_Error('swimlog_eval_workout',__('Workout not found.','chriss-swim-training-progress-evaluation'));
		if(($w->workout_type??'pool')!=='pool')return 0;
		$course=$w->pool_length_unit;if(!in_array($course,array('m','yd'),true))return new \WP_Error('swimlog_eval_course',__('Workout has no valid native course.','chriss-swim-training-progress-evaluation'));
		$rows=Database::read( 'get_results', $wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d ORDER BY sequence_no ASC",$lt, $workout_id));$candidates=!empty($rows)?self::from_lengths($rows,$w,$course):array();$laps=array();
		if(empty($rows)){$laps=Database::read( 'get_results', $wpdb->prepare("SELECT * FROM %i WHERE workout_id=%d ORDER BY sequence_no ASC",$lapt, $workout_id));if(!empty($laps))$candidates=self::from_laps($laps,$w,$course);}
		$summary_distance=(float)$w->original_distance;$exact_target=null;foreach(self::DISTANCES as$target){if(abs($summary_distance-$target)<0.001){$exact_target=$target;break;}}
		if(empty($rows)&&empty($laps)&&null!==$exact_target&&(int)$w->elapsed_time_ms>0)$candidates[]=array('distance'=>$exact_target,'course'=>$course,'stroke'=>self::known_stroke($w->primary_stroke)?strtoupper($w->primary_stroke):'UNKNOWN','duration'=>(int)$w->elapsed_time_ms,'start_id'=>null,'end_id'=>null,'start_offset'=>null,'end_offset'=>null);
		$best=array();foreach($candidates as$c){if(!self::is_plausible_performance($c))continue;$k=$c['distance'].'|'.$c['course'].'|'.$c['stroke'];if(!isset($best[$k])||$c['duration']<$best[$k]['duration'])$best[$k]=$c;}return array('workout'=>$w,'best'=>$best);
	}
	private static function insert_performance($pt,$user_id,$workout_id,$w,$c){global $wpdb;return(bool)Database::write( 'insert', $pt,array('user_id'=>$user_id,'workout_id'=>$workout_id,'distance_value'=>$c['distance'],'course_unit'=>$c['course'],'stroke'=>$c['stroke'],'duration_ms'=>$c['duration'],'start_length_id'=>$c['start_id'],'end_length_id'=>$c['end_id'],'start_offset_ms'=>$c['start_offset'],'end_offset_ms'=>$c['end_offset'],'is_personal_best'=>0,'evaluation_version'=>self::VERSION,'achieved_at'=>$w->workout_start,'created_at'=>current_time('mysql')));}
	private static function from_lengths($rows,$w,$course){
		$blocks=array();$block=array();$expected=null;$pool_native=(float)$w->original_pool_length;
		foreach($rows as $r){
			$hard=false;
			if($r->length_type!=='active'||(float)$r->distance_m<=0||null===$r->elapsed_time_ms||(int)$r->elapsed_time_ms<=0)$hard=true;
			if($expected!==null&&(int)$r->sequence_no!==$expected)$hard=true;
			if($hard){if($block)$blocks[]=$block;$block=array();$expected=(int)$r->sequence_no+1;continue;}
			$native=$course==='yd'?(float)$r->distance_m/0.9144:(float)$r->distance_m;
			if($pool_native>0&&abs($native-$pool_native)>0.02){if($block)$blocks[]=$block;$block=array();$expected=(int)$r->sequence_no+1;continue;}
			$r->_native=$native;$block[]=$r;$expected=(int)$r->sequence_no+1;
		}
		if($block)$blocks[]=$block;$out=array();
		foreach($blocks as $b){$n=count($b);for($i=0;$i<$n;$i++){$sum=0;$ms=0;$known=array();$unknown=false;for($j=$i;$j<$n;$j++){$sum+=$b[$j]->_native;$ms+=(int)$b[$j]->elapsed_time_ms;$s=strtoupper((string)$b[$j]->stroke);if(self::known_stroke($s))$known[$s]=true;else$unknown=true;
				foreach(self::DISTANCES as $target){if(abs($sum-$target)<0.02){$stroke=$unknown?'UNKNOWN':(count($known)===1?array_key_first($known):(count($known)>1?'MIXED':'UNKNOWN'));$out[]=array('distance'=>$target,'course'=>$course,'stroke'=>$stroke,'duration'=>$ms,'start_id'=>(int)$b[$i]->id,'end_id'=>(int)$b[$j]->id,'start_offset'=>$b[$i]->start_offset_ms,'end_offset'=>null===$b[$j]->start_offset_ms?null:(int)$b[$j]->start_offset_ms+(int)$b[$j]->elapsed_time_ms);break;}if($sum<$target)break;}
				if($sum>max(self::DISTANCES)+0.02)break;
			}}}
		return$out;
	}
	private static function from_laps($rows,$w,$course){
		$out=array();$expected=null;$pool_native=(float)$w->original_pool_length;
		foreach($rows as $r){
			if($expected!==null&&(int)$r->sequence_no!==$expected){$expected=(int)$r->sequence_no+1;continue;}$expected=(int)$r->sequence_no+1;
			if(null===$r->distance_m||null===$r->elapsed_time_ms||(float)$r->distance_m<=0||(int)$r->elapsed_time_ms<=0)continue;
			$native=$course==='yd'?(float)$r->distance_m/0.9144:(float)$r->distance_m;$target=null;foreach(self::DISTANCES as $d){if(abs($native-$d)<0.02){$target=$d;break;}}
			if(null===$target)continue;$stroke=self::known_stroke($r->stroke)?strtoupper($r->stroke):'UNKNOWN';
			$out[]=array('distance'=>$target,'course'=>$course,'stroke'=>$stroke,'duration'=>(int)$r->elapsed_time_ms,'start_id'=>null,'end_id'=>null,'start_offset'=>$r->start_offset_ms??null,'end_offset'=>isset($r->start_offset_ms)?(int)$r->start_offset_ms+(int)$r->elapsed_time_ms:null);
		}
		return$out;
	}
	private static function is_plausible_performance( $candidate ) {
		$distance = isset( $candidate['distance'] ) ? (float) $candidate['distance'] : 0.0;
		$duration_ms = isset( $candidate['duration'] ) ? (int) $candidate['duration'] : 0;
		if ( $distance <= 0 || $duration_ms <= 0 ) return false;
		$minimum_ms = (int) round( ( $distance / 25.0 ) * self::MIN_SECONDS_PER_25 * 1000 );
		return $duration_ms >= $minimum_ms;
	}
	private static function known_stroke($s){return in_array(strtoupper((string)$s),array('FR','BR','BACK','FLY','MIXED'),true);}
	public static function recalculate_personal_bests($user_id){
		global $wpdb;$pt=Database::table('performances');Database::write( 'update', $pt,array('is_personal_best'=>0),array('user_id'=>$user_id));
		$groups=Database::read( 'get_results', $wpdb->prepare("SELECT distance_value,course_unit,stroke,MIN(duration_ms) best FROM %i WHERE user_id=%d GROUP BY distance_value,course_unit,stroke",$pt, $user_id));
		foreach($groups as $g){$id=Database::read( 'get_var', $wpdb->prepare("SELECT id FROM %i WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s AND duration_ms=%d ORDER BY achieved_at ASC,id ASC LIMIT 1",$pt, $user_id,$g->distance_value,$g->course_unit,$g->stroke,$g->best));if($id)Database::write( 'update', $pt,array('is_personal_best'=>1),array('id'=>$id));}
	}
	public static function rebuild_user($user_id){
		global $wpdb;$wt=Database::table('workouts');$pt=Database::table('performances');
		$user_id=absint($user_id);if(!$user_id)return new \WP_Error('swimlog_rebuild_user',__('Invalid swimmer.','chriss-swim-training-progress-evaluation'));
		$ids=Database::read( 'get_col', $wpdb->prepare("SELECT id FROM %i WHERE user_id=%d AND workout_type='pool' ORDER BY workout_start ASC,id ASC",$wt, $user_id));
		// Rebuild derived rows atomically for the swimmer. If any workout fails,
		// rollback restores the complete pre-rebuild performance history.
		if(Database::write( 'query', 'START TRANSACTION')===false)return new \WP_Error('swimlog_rebuild_start',__('Performance rebuild could not start. No workout history was changed.','chriss-swim-training-progress-evaluation'));
		try{
			$deleted=Database::write( 'delete', $pt,array('user_id'=>$user_id));if(false===$deleted)throw new \Exception('clear');
			$count=0;foreach($ids as$id){$r=self::evaluate_candidates((int)$id,$user_id);if(is_wp_error($r))throw new \Exception('workout '.$id.': '.$r->get_error_message());foreach($r['best'] as$c){if(!self::insert_performance($pt,$user_id,(int)$id,$r['workout'],$c))throw new \Exception('performance '.$id);$count++;}}
			self::recalculate_personal_bests($user_id);
			if(Database::write( 'query', 'COMMIT')===false)throw new \Exception('commit');
			return array('workouts'=>count($ids),'performances'=>$count,'deleted'=>(int)$deleted);
		}catch(\Throwable $e){Database::write( 'query', 'ROLLBACK');
			/* translators: %s: performance rebuild error message. */
			return new \WP_Error('swimlog_rebuild_eval',sprintf(__('Performance rebuild failed and was rolled back: %s','chriss-swim-training-progress-evaluation'),$e->getMessage()));}
	}
	public static function rebuild_all_users(){
		global $wpdb;$wt=Database::table('workouts');$users=Database::read( 'get_col', $wpdb->prepare( "SELECT DISTINCT user_id FROM %i ORDER BY user_id ASC", $wt ));$summary=array('users'=>0,'workouts'=>0,'performances'=>0,'deleted'=>0);
		foreach($users as$user_id){$r=self::rebuild_user((int)$user_id);if(is_wp_error($r))return$r;$summary['users']++;$summary['workouts']+=$r['workouts'];$summary['performances']+=$r['performances'];$summary['deleted']+=$r['deleted'];}
		return$summary;
	}
}

