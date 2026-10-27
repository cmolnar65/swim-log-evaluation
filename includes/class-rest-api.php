<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Read-only authenticated REST surface for external integrations and data portability. */
final class REST_API {
 const NS='swimlog/v1';

 public static function register(){
  register_rest_route(self::NS,'/workouts',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'workouts'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('page'=>array('sanitize_callback'=>'absint'),'per_page'=>array('sanitize_callback'=>'absint'),'workout_type'=>array('sanitize_callback'=>'sanitize_key'),'date_from'=>array('sanitize_callback'=>'sanitize_text_field'),'date_to'=>array('sanitize_callback'=>'sanitize_text_field'))));
  register_rest_route(self::NS,'/workouts/(?P<id>\d+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'workout'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('id'=>array('sanitize_callback'=>'absint'))));
  register_rest_route(self::NS,'/personal-bests',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'personal_bests'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('course'=>array('sanitize_callback'=>'sanitize_key'))));
  register_rest_route(self::NS,'/progression',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'progression'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('distance'=>array('sanitize_callback'=>'absint','required'=>true),'course'=>array('sanitize_callback'=>'sanitize_key','required'=>true),'stroke'=>array('sanitize_callback'=>'sanitize_key'))));
  register_rest_route(self::NS,'/events',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'events'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('limit'=>array('sanitize_callback'=>'absint'))));
  register_rest_route(self::NS,'/analytics/pool',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'pool_analytics'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('course'=>array('sanitize_callback'=>'sanitize_key'),'stroke'=>array('sanitize_callback'=>'sanitize_key'),'months'=>array('sanitize_callback'=>'absint'))));
  register_rest_route(self::NS,'/analytics/training-trends',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'training_trends'),'permission_callback'=>array(__CLASS__,'permission'),'args'=>array('months'=>array('sanitize_callback'=>'absint'))));
  register_rest_route(self::NS,'/calendar',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'calendar'),'permission_callback'=>array(__CLASS__,'calendar_permission'),'args'=>array('user'=>array('sanitize_callback'=>'absint'),'start'=>array('sanitize_callback'=>'sanitize_text_field','required'=>true),'end'=>array('sanitize_callback'=>'sanitize_text_field','required'=>true))));
 }

 public static function permission(){if(!is_user_logged_in()||!current_user_can('swimlog_view_own_results'))return false;return '1'===(string)get_user_meta(get_current_user_id(),'swimlog_rest_api_access',true);}

 /** Internal calendar access is independent of the external REST opt-in. */
 public static function calendar_permission($request){
  if(!is_user_logged_in())return false;
  $current=absint(get_current_user_id());$requested=absint($request->get_param('user'));$target=$requested?:$current;
  if($target===$current)return current_user_can('swimlog_view_own_results');
  return current_user_can('swimlog_manage_all_users');
 }

 public static function workouts($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
  $uid=get_current_user_id();$page=max(1,(int)$request->get_param('page'));$per=max(1,min(100,(int)($request->get_param('per_page')?:20)));$filters=array();
  foreach(array('workout_type','date_from','date_to')as$key){$v=$request->get_param($key);if(null!==$v&&''!==$v)$filters[$key]=$v;}
  $result=Workout::query_for_user($uid,$filters,$page,$per);$rows=array();foreach($result['rows']as$w)$rows[]=self::summary($w);
  return rest_ensure_response(array('workouts'=>$rows,'page'=>$page,'per_page'=>$per,'total'=>(int)$result['total'],'pages'=>(int)$result['pages']));
 }

 public static function workout($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
  $uid=get_current_user_id();$id=absint($request->get_param('id'));$w=Workout::get_for_user($id,$uid);if(!$w)return new \WP_Error('swimlog_rest_not_found',__('Workout not found.','chriss-swim-training-progress-evaluation'),array('status'=>404));
  $data=self::summary($w);$data['laps']=array_map(array(__CLASS__,'lap'),Workout::laps($id,$uid));$data['performances']=array_map(array(__CLASS__,'performance'),Workout::performances($id,$uid));
  if('open_water'===($w->workout_type??'pool'))$data['analytics']=self::safe_analytics(Workout::open_water_analytics($id,$uid));
  else $data['lengths']=array_map(array(__CLASS__,'length'),Workout::lengths($id,$uid));
  return rest_ensure_response($data);
 }

 public static function personal_bests($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
  $uid=get_current_user_id();$course=strtolower((string)($request->get_param('course')?:get_option('swimlog_default_course_unit','m')));if(!in_array($course,array('m','yd'),true))$course='m';
  $map=Records::personal_bests($uid,$course);$rows=array();
  foreach($map as$distance=>$strokes)foreach($strokes as$stroke=>$row)$rows[]=array('distance_value'=>(int)$distance,'course_unit'=>$course,'stroke'=>(string)$stroke,'duration_ms'=>(int)$row->duration_ms,'achieved_at'=>$row->achieved_at,'workout_id'=>(int)($row->workout_id??0));
  return rest_ensure_response(array('course_unit'=>$course,'personal_bests'=>$rows));
 }

 public static function progression($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';
  $uid=get_current_user_id();$distance=max(1,absint($request->get_param('distance')));$course=strtolower((string)$request->get_param('course'));$stroke=strtoupper((string)$request->get_param('stroke'));
  if(!in_array($course,array('m','yd'),true))return new \WP_Error('swimlog_rest_course',__('Course must be m or yd.','chriss-swim-training-progress-evaluation'),array('status'=>400));
  if(''!==$stroke&&!in_array($stroke,array('FR','BR','BACK','FLY','MIXED'),true))return new \WP_Error('swimlog_rest_stroke',__('Unsupported stroke.','chriss-swim-training-progress-evaluation'),array('status'=>400));
  $rows=array_map(array(__CLASS__,'performance'),Records::progression($uid,$distance,$course,$stroke));
  return rest_ensure_response(array('distance_value'=>$distance,'course_unit'=>$course,'stroke'=>$stroke,'progression'=>$rows));
 }

 public static function events($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-event.php';
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
  $uid=get_current_user_id();$limit=max(1,min(50,(int)($request->get_param('limit')?:10)));$rows=array();
  foreach(Event::upcoming_for_user($uid,$limit)as$event){$rows[]=array('id'=>(int)$event->id,'event_name'=>(string)$event->event_name,'event_date'=>$event->event_date,'event_time'=>$event->event_time,'location'=>$event->location_name??null,'distance_value'=>(int)$event->distance_value,'course_unit'=>$event->course_unit,'stroke'=>$event->stroke,'readiness'=>self::safe_readiness(Workout::event_readiness($uid,$event,Workout::EVENT_READINESS_LOOKBACK_DAYS)));}
  return rest_ensure_response(array('events'=>$rows,'lookback_days'=>Workout::EVENT_READINESS_LOOKBACK_DAYS));
 }

 public static function calendar($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-calendar.php';
  $current=absint(get_current_user_id());$requested=absint($request->get_param('user'));$uid=$requested?:$current;
  if($uid!==$current&&!current_user_can('swimlog_manage_all_users'))return new \WP_Error('swimlog_calendar_forbidden',__('You cannot view this swimmer calendar.','chriss-swim-training-progress-evaluation'),array('status'=>403));
  $start=trim((string)$request->get_param('start'));$end=trim((string)$request->get_param('end'));
  $range_error=self::calendar_range_error($start,$end);if($range_error)return $range_error;
  return rest_ensure_response(Calendar::for_user($uid,$start,$end));
 }

 private static function calendar_range_error($start,$end){
  $tz=wp_timezone();$a=\DateTimeImmutable::createFromFormat('!Y-m-d',$start,$tz);$a_errors=\DateTimeImmutable::getLastErrors();$b=\DateTimeImmutable::createFromFormat('!Y-m-d',$end,$tz);$b_errors=\DateTimeImmutable::getLastErrors();
  if(false===$a||false===$b||(false!==$a_errors&&($a_errors['warning_count']||$a_errors['error_count']))||(false!==$b_errors&&($b_errors['warning_count']||$b_errors['error_count']))||$a->format('Y-m-d')!==$start||$b->format('Y-m-d')!==$end)return new \WP_Error('swimlog_calendar_date',__('Calendar start and end must be valid dates in YYYY-MM-DD format.','chriss-swim-training-progress-evaluation'),array('status'=>400));
  if($b<$a)return new \WP_Error('swimlog_calendar_range',__('Calendar end date cannot be before the start date.','chriss-swim-training-progress-evaluation'),array('status'=>400));
  if((int)$a->diff($b)->format('%a')>366)return new \WP_Error('swimlog_calendar_range_too_large',__('Calendar date range cannot exceed 367 inclusive days.','chriss-swim-training-progress-evaluation'),array('status'=>400));
  return null;
 }

 public static function pool_analytics($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-pool-analytics.php';
  $filters=array();foreach(array('course','stroke','months')as$key){$v=$request->get_param($key);if(null!==$v&&''!==$v)$filters[$key]=$v;}
  return rest_ensure_response(Pool_Analytics::for_user(get_current_user_id(),$filters));
 }

 public static function training_trends($request){
  require_once SWIMLOG_EVALUATION_DIR.'includes/class-training-trends.php';
  $months=max(1,min(Training_Trends::MAX_LOOKBACK_MONTHS,(int)($request->get_param('months')?:Training_Trends::DEFAULT_LOOKBACK_MONTHS)));
  return rest_ensure_response(Training_Trends::for_user(get_current_user_id(),$months));
 }

 private static function summary($w){return array('id'=>(int)$w->id,'workout_type'=>(string)($w->workout_type??'pool'),'source_type'=>(string)($w->source_type??''),'workout_start'=>$w->workout_start,'workout_end'=>$w->workout_end,'location'=>($w->location_name??null),'total_distance_m'=>null!==$w->total_distance_m?(float)$w->total_distance_m:null,'original_distance'=>null!==$w->original_distance?(float)$w->original_distance:null,'original_distance_unit'=>$w->original_distance_unit,'elapsed_time_ms'=>null!==$w->elapsed_time_ms?(int)$w->elapsed_time_ms:null,'moving_time_ms'=>null!==$w->moving_time_ms?(int)$w->moving_time_ms:null,'pool_length_m'=>null!==$w->pool_length_m?(float)$w->pool_length_m:null,'original_pool_length'=>null!==($w->original_pool_length??null)?(float)$w->original_pool_length:null,'pool_length_unit'=>$w->pool_length_unit,'primary_stroke'=>$w->primary_stroke,'avg_swolf'=>null!==($w->avg_swolf??null)?(float)$w->avg_swolf:null,'average_heart_rate'=>null!==$w->average_heart_rate?(int)$w->average_heart_rate:null,'max_heart_rate'=>null!==$w->max_heart_rate?(int)$w->max_heart_rate:null,'average_cadence'=>null!==$w->average_cadence?(float)$w->average_cadence:null,'max_cadence'=>null!==$w->max_cadence?(float)$w->max_cadence:null,'device_manufacturer'=>$w->device_manufacturer,'device_model'=>$w->device_model);}
 private static function lap($x){return array('sequence_no'=>(int)$x->sequence_no,'start_time'=>$x->start_time,'start_offset_ms'=>null!==($x->start_offset_ms??null)?(int)$x->start_offset_ms:null,'distance_m'=>null!==$x->distance_m?(float)$x->distance_m:null,'elapsed_time_ms'=>null!==$x->elapsed_time_ms?(int)$x->elapsed_time_ms:null,'moving_time_ms'=>null!==$x->moving_time_ms?(int)$x->moving_time_ms:null,'stroke'=>$x->stroke,'source_lap_index'=>null!==($x->source_lap_index??null)?(int)$x->source_lap_index:null);}
 private static function length($x){return array('sequence_no'=>(int)$x->sequence_no,'start_time'=>$x->start_time,'start_offset_ms'=>null!==($x->start_offset_ms??null)?(int)$x->start_offset_ms:null,'distance_m'=>null!==$x->distance_m?(float)$x->distance_m:null,'elapsed_time_ms'=>null!==$x->elapsed_time_ms?(int)$x->elapsed_time_ms:null,'stroke'=>$x->stroke,'stroke_count'=>null!==($x->stroke_count??null)?(int)$x->stroke_count:null,'swolf_score'=>null!==($x->swolf_score??null)?(int)$x->swolf_score:null,'length_type'=>$x->length_type,'source_length_index'=>null!==($x->source_length_index??null)?(int)$x->source_length_index:null);}
 private static function performance($x){return array('workout_id'=>(int)($x->workout_id??0),'course_unit'=>$x->course_unit,'distance_value'=>(int)$x->distance_value,'stroke'=>$x->stroke,'duration_ms'=>(int)$x->duration_ms,'achieved_at'=>$x->achieved_at,'is_personal_best'=>isset($x->is_personal_best)?(bool)$x->is_personal_best:null);}
 private static function safe_analytics($a){return array('elapsed_pace_100m_ms'=>$a['elapsed_pace_100m_ms']??null,'moving_pace_100m_ms'=>$a['moving_pace_100m_ms']??null,'non_moving_time_ms'=>$a['non_moving_time_ms']??null,'active_percent'=>$a['active_percent']??null,'cadence_point_count'=>(int)($a['cadence_point_count']??0),'splits'=>$a['splits']??array());}
 private static function safe_readiness($r){$keys=array('lookback_days','course_unit','stroke','event_distance','days_until_event','workout_count','length_count','distance_native','active_time_ms','last_workout_start','longest_recent_distance','longest_recent_duration_ms','longest_recent_workout_id','event_distance_or_longer','lifetime_pb_duration_ms','lifetime_pb_achieved_at','lifetime_pb_workout_id','recent_performance_count','recent_best_duration_ms','recent_best_achieved_at','recent_best_workout_id','recent_best_pace_100_ms','recent_average_pace_100_ms','latest_duration_ms','latest_achieved_at','latest_pace_100_ms','recent_change_ms','recent_change_percent','recent_best_vs_pb_ms','recent_best_vs_pb_percent','descriptive_only');$out=array();foreach($keys as$key)$out[$key]=$r[$key]??null;return$out;}
}
