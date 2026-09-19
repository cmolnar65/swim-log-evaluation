<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Deterministic native-course performance evaluator. Derived data only. */
final class Evaluator {
	const VERSION='0.1';
	const DISTANCES=array(50,100,200,500,800,1000,1500,1650,2000,2500,3300,5000);

	public static function evaluate_workout($workout_id,$user_id){
		global $wpdb;$wt=Database::table('workouts');$lt=Database::table('lengths');$pt=Database::table('performances');
		$w=$wpdb->get_row($wpdb->prepare("SELECT * FROM $wt WHERE id=%d AND user_id=%d",$workout_id,$user_id));if(!$w)return new \WP_Error('swimlog_eval_workout',__('Workout not found.','swim-log-evaluation'));
		$course=$w->pool_length_unit;if(!in_array($course,array('m','yd'),true))return new \WP_Error('swimlog_eval_course',__('Workout has no valid native course.','swim-log-evaluation'));
		$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $lt WHERE workout_id=%d ORDER BY sequence_no ASC",$workout_id));
		$candidates=!empty($rows)?self::from_lengths($rows,$w,$course):array();
		// Summary-only rule: exact target only, no inferred intermediate performances.
		if(empty($rows)&&in_array((int)$w->original_distance,self::DISTANCES,true)&&$w->elapsed_time_ms){
			$candidates[]=array('distance'=>(int)$w->original_distance,'course'=>$course,'stroke'=>self::known_stroke($w->primary_stroke)?$w->primary_stroke:'UNKNOWN','duration'=>(int)$w->elapsed_time_ms,'start_id'=>null,'end_id'=>null,'start_offset'=>null,'end_offset'=>null);
		}
		// Persist fastest candidate per workout/distance/course/stroke.
		$best=array();foreach($candidates as $c){$k=$c['distance'].'|'.$c['course'].'|'.$c['stroke'];if(!isset($best[$k])||$c['duration']<$best[$k]['duration'])$best[$k]=$c;}
		$wpdb->query('START TRANSACTION');
		try{
			$wpdb->delete($pt,array('workout_id'=>$workout_id,'user_id'=>$user_id));
			foreach($best as $c){$ok=$wpdb->insert($pt,array('user_id'=>$user_id,'workout_id'=>$workout_id,'distance_value'=>$c['distance'],'course_unit'=>$c['course'],'stroke'=>$c['stroke'],'duration_ms'=>$c['duration'],'start_length_id'=>$c['start_id'],'end_length_id'=>$c['end_id'],'start_offset_ms'=>$c['start_offset'],'end_offset_ms'=>$c['end_offset'],'is_personal_best'=>0,'evaluation_version'=>self::VERSION,'achieved_at'=>$w->workout_start,'created_at'=>current_time('mysql')));if(!$ok)throw new \Exception('performance');}
			self::recalculate_personal_bests($user_id);
			$wpdb->query('COMMIT');return count($best);
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');return new \WP_Error('swimlog_eval_save',__('Performance evaluation could not be saved.','swim-log-evaluation'));}
	}

	private static function from_lengths($rows,$w,$course){
		$blocks=array();$block=array();$expected=null;$pool_native=(float)$w->original_pool_length;
		foreach($rows as $r){
			$hard=false;
			if($r->length_type!=='active'||(float)$r->distance_m<=0||null===$r->elapsed_time_ms)$hard=true;
			if($expected!==null&&(int)$r->sequence_no!==$expected)$hard=true;
			if($hard){if($block)$blocks[]=$block;$block=array();$expected=(int)$r->sequence_no+1;continue;}
			$native=$course==='yd'?(float)$r->distance_m/0.9144:(float)$r->distance_m;
			if($pool_native>0&&abs($native-$pool_native)>0.02){if($block)$blocks[]=$block;$block=array();$expected=(int)$r->sequence_no+1;continue;}
			$r->_native=$native;$block[]=$r;$expected=(int)$r->sequence_no+1;
		}
		if($block)$blocks[]=$block;$out=array();
		foreach($blocks as $b){$n=count($b);for($i=0;$i<$n;$i++){$sum=0;$ms=0;$known=array();$unknown=false;for($j=$i;$j<$n;$j++){$sum+=$b[$j]->_native;$ms+=(int)$b[$j]->elapsed_time_ms;$s=strtoupper((string)$b[$j]->stroke;if(self::known_stroke($s))$known[$s]=true;else$unknown=true;
				foreach(self::DISTANCES as $target){if(abs($sum-$target)<0.02){$stroke=$unknown?'UNKNOWN':(count($known)===1?array_key_first($known):(count($known)>1?'MIXED':'UNKNOWN'));$out[]=array('distance'=>$target,'course'=>$course,'stroke'=>$stroke,'duration'=>$ms,'start_id'=>(int)$b[$i]->id,'end_id'=>(int)$b[$j]->id,'start_offset'=>$b[$i]->start_offset_ms,'end_offset'=>null===$b[$j]->start_offset_ms?null:(int)$b[$j]->start_offset_ms+(int)$b[$j]->elapsed_time_ms);break;}if($sum<$target)break;}
				if($sum>max(self::DISTANCES)+0.02)break;
			}}}
		return$out;
	}
	private static function known_stroke($s){return in_array(strtoupper((string)$s),array('FR','BR','BACK','FLY','MIXED'),true);}
	public static function recalculate_personal_bests($user_id){
		global $wpdb;$pt=Database::table('performances');$wpdb->update($pt,array('is_personal_best'=>0),array('user_id'=>$user_id));
		$groups=$wpdb->get_results($wpdb->prepare("SELECT distance_value,course_unit,stroke,MIN(duration_ms) best FROM $pt WHERE user_id=%d GROUP BY distance_value,course_unit,stroke",$user_id));
		foreach($groups as $g){$id=$wpdb->get_var($wpdb->prepare("SELECT id FROM $pt WHERE user_id=%d AND distance_value=%d AND course_unit=%s AND stroke=%s AND duration_ms=%d ORDER BY achieved_at ASC,id ASC LIMIT 1",$user_id,$g->distance_value,$g->course_unit,$g->stroke,$g->best));if($id)$wpdb->update($pt,array('is_personal_best'=>1),array('id'=>$id));}
	}
	public static function rebuild_user($user_id){
		global $wpdb;$wt=Database::table('workouts');$ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $wt WHERE user_id=%d ORDER BY workout_start ASC,id ASC",$user_id));$count=0;foreach($ids as $id){$r=self::evaluate_workout((int)$id,$user_id);if(is_wp_error($r))return$r;$count+=$r;}return array('workouts'=>count($ids),'performances'=>$count);
	}
}
