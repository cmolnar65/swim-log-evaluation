<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Read-only authenticated REST surface for external integrations. */
final class REST_API {
 const NS='swimlog/v1';
 public static function register(){
  register_rest_route(self::NS,'/workouts',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'workouts'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('page'=>array('sanitize_callback'=>'absint'),'per_page'=>array('sanitize_callback'=>'absint'),'workout_type'=>array('sanitize_callback'=>'sanitize_key'),'date_from'=>array('sanitize_callback'=>'sanitize_text_field'),'date_to'=>array('sanitize_callback'=>'sanitize_text_field'))));
  register_rest_route(self::NS,'/workouts/(?P<id>\d+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'workout'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('id'=>array('sanitize_callback'=>'absint'))));
 }
 public static function permission(){return is_user_logged_in()&&current_user_can('swimlog_view_own');}
 public static function workouts($request){
  $uid=get_current_user_id();$page=max(1,(int)$request->get_param('page'));$per=max(1,min(100,(int)($request->get_param('per_page')?:20)));$filters=array();
  foreach(array('workout_type','date_from','date_to')as$key){$v=$request->get_param($key);if(null!==$v&&''!==$v)$filters[$key]=$v;}
  $result=Workout::query_for_user($uid,$filters,$page,$per);$rows=array();foreach($result['rows']as$w)$rows[]=self::summary($w);
  return rest_ensure_response(array('workouts'=>$rows,'page'=>$page,'per_page'=>$per,'total'=>(int)$result['total'],'pages'=>(int)$result['pages']));
 }
 public static function workout($request){
  $uid=get_current_user_id();$id=absint($request->get_param('id'));$w=Workout::get_for_user($id,$uid);if(!$w)return new \WP_Error('swimlog_rest_not_found',__('Workout not found.','chriss-swim-training-progress-evaluation'),array('status'=>404));
  $data=self::summary($w);$data['laps']=array_map(array(__CLASS__,'lap'),Workout::laps($id,$uid));$data['performances']=array_map(array(__CLASS__,'performance'),Workout::performances($id,$uid));
  if('open_water'===($w->workout_type??'pool'))$data['analytics']=self::safe_analytics(Workout::open_water_analytics($id,$uid));
  else $data['lengths']=array_map(array(__CLASS__,'length'),Workout::lengths($id,$uid));
  return rest_ensure_response($data);
 }
 private static function summary($w){return array('id'=>(int)$w->id,'workout_type'=>(string)($w->workout_type??'pool'),'workout_start'=>$w->workout_start,'workout_end'=>$w->workout_end,'location'=>($w->location_name??null),'total_distance_m'=>null!==$w->total_distance_m?(float)$w->total_distance_m:null,'original_distance'=>null!==$w->original_distance?(float)$w->original_distance:null,'original_distance_unit'=>$w->original_distance_unit,'elapsed_time_ms'=>null!==$w->elapsed_time_ms?(int)$w->elapsed_time_ms:null,'moving_time_ms'=>null!==$w->moving_time_ms?(int)$w->moving_time_ms:null,'pool_length_m'=>null!==$w->pool_length_m?(float)$w->pool_length_m:null,'pool_length_unit'=>$w->pool_length_unit,'primary_stroke'=>$w->primary_stroke,'average_heart_rate'=>null!==$w->average_heart_rate?(int)$w->average_heart_rate:null,'max_heart_rate'=>null!==$w->max_heart_rate?(int)$w->max_heart_rate:null,'average_cadence'=>null!==$w->average_cadence?(float)$w->average_cadence:null,'max_cadence'=>null!==$w->max_cadence?(float)$w->max_cadence:null,'device_manufacturer'=>$w->device_manufacturer,'device_model'=>$w->device_model);}
 private static function lap($x){return array('sequence_no'=>(int)$x->sequence_no,'start_time'=>$x->start_time,'distance_m'=>null!==$x->distance_m?(float)$x->distance_m:null,'elapsed_time_ms'=>null!==$x->elapsed_time_ms?(int)$x->elapsed_time_ms:null,'moving_time_ms'=>null!==$x->moving_time_ms?(int)$x->moving_time_ms:null,'stroke'=>$x->stroke);}
 private static function length($x){return array('sequence_no'=>(int)$x->sequence_no,'start_time'=>$x->start_time,'distance_m'=>null!==$x->distance_m?(float)$x->distance_m:null,'elapsed_time_ms'=>null!==$x->elapsed_time_ms?(int)$x->elapsed_time_ms:null,'stroke'=>$x->stroke,'length_type'=>$x->length_type);}
 private static function performance($x){return array('course_unit'=>$x->course_unit,'distance_value'=>(int)$x->distance_value,'stroke'=>$x->stroke,'duration_ms'=>(int)$x->duration_ms,'achieved_at'=>$x->achieved_at);}
 private static function safe_analytics($a){return array('elapsed_pace_100m_ms'=>$a['elapsed_pace_100m_ms']??null,'moving_pace_100m_ms'=>$a['moving_pace_100m_ms']??null,'non_moving_time_ms'=>$a['non_moving_time_ms']??null,'active_percent'=>$a['active_percent']??null,'cadence_point_count'=>(int)($a['cadence_point_count']??0),'splits'=>$a['splits']??array());}
}
