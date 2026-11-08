<?php
// Actual disposable WordPress owner records and synthetic scheduling metadata only.
use SwimLogEvaluation\{Admin_UI as UI,Admin_Events_Locations as Events_UI,Admin_Planned_Workouts as Plans_UI,Admin_Structured_Workouts as Structures_UI,Admin_Workouts as Workouts_UI,Admin_Garmin_Measurements as Measurements_UI,Event,Location,Planned_Workout as Plans,Structured_Workout_Store as Store,Workout,Database,Garmin_Personal_API as API,Garmin_Measurement_Sync as Sync};
foreach(array('ui','events-locations','planned-workouts','structured-workouts','workouts','garmin-measurements','race-readiness','settings')as$file)require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-'.$file.'.php';
foreach(array('database','workout','event','location','planned-workout','structured-workout','structured-workout-store','planned-structure-link','garmin-measurement-sync')as$file)require_once SWIMLOG_EVALUATION_DIR.'includes/class-'.$file.'.php';
$old_user=get_current_user_id();$get=$_GET;$post=$_POST;$request=$_REQUEST;$screen=$GLOBALS['current_screen']??null;
$options=array();foreach(array('timezone_string','date_format','time_format')as$key)$options[$key]=get_option($key);
$users=array();$ids=array();$fixtures=array();$checks=0;$http=null;$redirect=null;
$verify=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$set=static function($page,$query=array(),$fields=array()){$_GET=array_merge(array('page'=>$page),$query);$_POST=$fields;$_REQUEST=array_merge($_GET,$_POST);};
$render=static function($call){ob_start();try{call_user_func($call);return ob_get_contents();}finally{ob_end_clean();}};
$xp=static function($html){$d=new DOMDocument();$old=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);return new DOMXPath($d);};
try{
 require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';require_once ABSPATH.'wp-admin/includes/screen.php';set_current_screen('dashboard');
 foreach(array('owner','other')as$name){$id=wp_create_user('swimlog_hierarchy_'.$name.'_'.wp_generate_password(8,false),wp_generate_password(32));if(is_wp_error($id))throw new RuntimeException('Synthetic user');$users[$name]=(int)$id;foreach(array('swimlog_view_own_results','swimlog_manage_own_workouts','swimlog_manage_own_events','swimlog_manage_own_locations','swimlog_upload_workouts','swimlog_manage_settings')as$cap)(new WP_User($id))->add_cap($cap);}
 $owner=$users['owner'];wp_set_current_user($owner);
 update_option('timezone_string','America/Denver');update_option('date_format','Y-m-d');update_option('time_format','H:i');
 $verify('2026-11-01'===UI::date_text('2026-11-01'),'Calendar date remains the captured date');
 $verify('2026-11-01 01:30 (America/Denver)'===UI::datetime_text('2026-11-01 01:30:00'),'Naive recorded local clock is not shifted');
 $first=UI::datetime_text('2026-11-01 07:30:00',true);$second=UI::datetime_text('2026-11-01 08:30:00',true);
 $verify(false!==strpos($first,'01:30')&&false!==strpos($first,'UTC-06:00')&&false!==strpos($second,'01:30')&&false!==strpos($second,'UTC-07:00'),'Known UTC repeated DST hours display their actual offsets');
 $verify('—'===UI::date_text('2026-02-30')&&'—'===UI::datetime_text('invalid')&&'—'===UI::timestamp_text(0),'Invalid/missing dates do not invent a timestamp');
 $today=wp_date('Y-m-d');$future=wp_date('Y-m-d',time()+86400);$past=wp_date('Y-m-d',time()-86400);
 $event=Event::save($owner,array('event_name'=>'Synthetic upcoming event','event_date'=>$future,'distance_value'=>100,'course_unit'=>'m','stroke'=>'FR'));$verify(!is_wp_error($event),'Create event');$ids['event']=$event;
 $loc=Location::save($owner,array('name'=>'Synthetic pool location','pool_length'=>25,'pool_unit'=>'m'));$verify(!is_wp_error($loc),'Create location');$ids['location']=$loc;
 $plan=Plans::save($owner,array('title'=>'Synthetic future plan','planned_date'=>$future,'workout_type'=>'pool'));$verify(!is_wp_error($plan),'Create plan');$ids['plan']=$plan;
 $doc=array('version'=>1,'unit'=>'m','sections'=>array(array('type'=>'section','label'=>'Main','children'=>array(array('type'=>'step','distance'=>100,'stroke'=>'FR')))));
 $structure=Store::save($owner,'Synthetic private prescription',$doc);$verify(!is_wp_error($structure),'Create structure');$ids['structure']=$structure;
 $pages=array('EVENTS'=>array('swimlog-events',array(Events_UI::class,'events'),$event),'LOCATIONS'=>array('swimlog-locations',array(Events_UI::class,'locations'),$loc),'PLANS'=>array('swimlog-planned-workouts',array(Plans_UI::class,'page'),$plan),'STRUCTURES'=>array('swimlog-structured-workouts',array(Structures_UI::class,'page'),$structure));
 foreach($pages as$name=>$page){
  $set($page[0]);$html=$render($page[1]);$fixtures[$name]=$html;$d=$xp($html);
  $verify(1===$d->query('//*[@id="swimlog-page-library"]')->length&&0===$d->query('//*[@id="swimlog-page-editor"]')->length,'Default shows library and no creation editor: '.$name);
  $verify(1===$d->query('//header[@class="swimlog-page-header"]/a[contains(@class,"button-primary")]')->length,'One primary creation action: '.$name);
  $verify($d->query('//details[@class="swimlog-more-actions"]')->length>0,'Secondary actions use disclosure: '.$name);
  $set($page[0],array('new'=>1));$new=$render($page[1]);$fixtures[$name.'_NEW']=$new;$verify(strpos($new,'id="swimlog-page-editor"')<strpos($new,'id="swimlog-page-library"'),'Explicit creation opens before library: '.$name);
  $set($page[0],array('edit'=>$page[2]));$edit=$render($page[1]);$fixtures[$name.'_EDIT']=$edit;$verify(false!==strpos($edit,'id="swimlog-page-editor"'),'Legacy edit URL still opens editor: '.$name);
 }
 // Actual past-event POST must return to the past list, with the saved row anchored.
 $redirect=static function($url){throw new RuntimeException('CAPTURE_REDIRECT:'.$url);};add_filter('wp_redirect',$redirect);
 $set('swimlog-events',array(),array('swimlog_event_action'=>'save','event_id'=>$event,'event_name'=>'Synthetic saved past event','event_date'=>$past,'distance_value'=>100,'course_unit'=>'m','stroke'=>'FR','_wpnonce'=>wp_create_nonce('swimlog_save_event')));$target='';
 try{Events_UI::handle_event_actions();}catch(RuntimeException$e){if(0!==strpos($e->getMessage(),'CAPTURE_REDIRECT:'))throw$e;$target=substr($e->getMessage(),17);}finally{remove_filter('wp_redirect',$redirect);$redirect=null;}
 // Simulate the fresh GET request after a successful POST redirect.
 foreach(array('event_form'=>null,'event_action_error'=>null)as$name=>$value){$prop=new ReflectionProperty(Events_UI::class,$name);$prop->setAccessible(true);$prop->setValue(null,$value);}
 parse_str((string)wp_parse_url($target,PHP_URL_QUERY),$q);$verify('past'===($q['view']??'')&&'swimlog-event-'.$event===wp_parse_url($target,PHP_URL_FRAGMENT),'Past save redirects to matching view and row');
 $set('swimlog-events',$q);$fixtures['PAST']=$render(array(Events_UI::class,'events'));$verify(false!==strpos($fixtures['PAST'],'Synthetic saved past event'),'Saved past event is visible after redirect');
 $set('swimlog-events');$filtered=$render(array(Events_UI::class,'events'));$verify(false!==strpos($filtered,'No events match this view.')&&false!==strpos($filtered,'Clear filters'),'Empty event view offers full-history recovery');
 $set('swimlog-workouts');$fixtures['EMPTY']=$render(array(Workouts_UI::class,'workouts'));$verify(false!==strpos($fixtures['EMPTY'],'No recorded activities yet.'),'Genuinely empty account has first-use guidance');
 global$wpdb;Database::write('insert',Database::table('workouts'),array('user_id'=>$owner,'workout_type'=>'pool','source_type'=>'manual','workout_start'=>$today.' 10:00:00','total_distance_m'=>100,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));$ids['workout']=(int)$wpdb->insert_id;
 // Maintenance and permanent deletion remain separate, with original action nonces/confirmations.
 $set('swimlog-workouts',array('workout_id'=>$ids['workout']));$fixtures['MAINTENANCE']=$render(array(Workouts_UI::class,'workouts'));$d=$xp($fixtures['MAINTENANCE']);
 $verify(1===$d->query('//section[@class="swimlog-workout-maintenance"]//input[@name="swimlog_workout_action" and @value="rebuild"]')->length&&0===$d->query('//section[@class="swimlog-workout-maintenance"]//input[@value="delete"]')->length,'Repair is in its own maintenance section');
 $verify(1===$d->query('//section[@class="swimlog-workout-deletion"]//input[@name="swimlog_workout_action" and @value="delete"]')->length&&0===$d->query('//section[@class="swimlog-workout-deletion"]//input[@value="rebuild"]')->length,'Permanent deletion is separate from repair');
 foreach(array('swimlog-workout-maintenance'=>'swimlog_rebuild_workout_','swimlog-workout-deletion'=>'swimlog_delete_workout_')as$class=>$action){$nonce=$d->query('//section[@class="'.$class.'"]//input[@name="_wpnonce"]')->item(0)->getAttribute('value');$verify((bool)wp_verify_nonce($nonce,$action.$ids['workout']),'Existing action nonce remains valid: '.$class);$verify(false!==strpos($d->query('//section[@class="'.$class.'"]//button')->item(0)->getAttribute('onclick'),'confirm('),'Confirmation remains specific to action: '.$class);}
 $set('swimlog-settings');$fixtures['SETTINGS']=$render(array(SwimLogEvaluation\Admin_Settings::class,'settings'));$verify(false===strpos($fixtures['SETTINGS'],'v0.2')&&false===strpos($fixtures['SETTINGS'],'their Privacy page')&&false!==strpos($fixtures['SETTINGS'],'Swimmer Configuration'),'Settings use current preservation and screen names');
 $verify(false!==strpos($fixtures['SETTINGS'],'for every swimmer')&&false!==strpos($fixtures['SETTINGS'],'original source files remain unchanged'),'Global maintenance scope and retention are explicit');
 $set('swimlog-workouts',array('date_to'=>'2000-01-01'));$fixtures['FILTERED']=$render(array(Workouts_UI::class,'workouts'));$verify(false!==strpos($fixtures['FILTERED'],'No recorded activities match these filters or this page.'),'Filtered history does not claim the account is empty');
 $d=$xp($fixtures['FILTERED']);foreach($d->query('//a[text()="Clear filters"]')as$a){parse_str((string)wp_parse_url($a->getAttribute('href'),PHP_URL_QUERY),$q);$verify(!isset($q['date_to'])&&!isset($q['paged']),'Clear filters discards filters and pagination');}
 // Synthetic ready connection: never contact a real Garmin account.
 $http=static function($pre,$args,$url){if(false===strpos($url,API::API_BASE))throw new RuntimeException('Unexpected request');return array('response'=>array('code'=>200),'headers'=>array(),'body'=>false!==strpos($url,'personal-api-key/token')?'synthetic.hierarchy.token':'[]');};add_filter('pre_http_request',$http,10,3);API::save_key($owner,'synthetic-hierarchy-key');$verify(!is_wp_error(API::test_connection($owner)),'Synthetic connection ready');
 Sync::configure(array('weight'));update_user_meta($owner,Sync::STATE,array('attempt_utc'=>'2026-11-01 07:30:00','status'=>'complete_with_errors','results'=>array(array('date'=>$today,'category'=>'body_composition','status'=>'unsupported_data','added'=>0,'updated'=>0,'skipped'=>2,'reports_added'=>0,'reports_updated'=>1,'reports_skipped'=>0))));
 $set('swimlog-garmin');$fixtures['MEASUREMENTS']=$render(array(Measurements_UI::class,'schedule_controls'));$d=$xp($fixtures['MEASUREMENTS']);$visible=$d->query('//table')->item(0)->textContent;
 $verify(false!==strpos($visible,'BMI')&&false!==strpos($visible,'No supported values')&&false===strpos($visible,'body_composition'),'Measurement category and status use user labels');
 $verify(false!==strpos($visible,'Added 0; updated 0; unchanged/skipped 2'),'Counts name each outcome');
 $verify(false!==strpos($fixtures['MEASUREMENTS'],'UTC-06:00')&&false!==strpos($fixtures['MEASUREMENTS'],'Latest attempt (UTC): 2026-11-01 07:30:00'),'Task display has local time and retained raw UTC details');
 wp_set_current_user($users['other']);foreach($pages as$name=>$page){$set($page[0],array('edit'=>$page[2]));$other=$render($page[1]);foreach(array('Synthetic saved past event','Synthetic pool location','Synthetic future plan','Synthetic private prescription')as$private)$verify(false===strpos($other,$private),'Foreign record stays private on '.$name);}
 foreach($fixtures as$name=>$html)echo 'SWIMLOG_PRESENTATION_'.$name.'_HTML_BASE64='.base64_encode($html)."\n";
 WP_CLI::success('Hierarchy, dates, status and empty-state integration passed '.$checks.' assertions.');
}finally{
 if($http)remove_filter('pre_http_request',$http,10);if($redirect)remove_filter('wp_redirect',$redirect);wp_set_current_user($users['owner']??0);if(isset($users['owner']))Sync::configure(array());
 if(isset($ids['workout']))Workout::delete($ids['workout'],$users['owner']);if(isset($ids['event']))Event::delete($ids['event'],$users['owner']);if(isset($ids['location']))Location::delete($ids['location'],$users['owner']);if(isset($ids['plan']))Plans::delete($ids['plan'],$users['owner']);if(isset($ids['structure']))wp_delete_post($ids['structure'],true);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)wp_delete_user($id);foreach($options as$key=>$value)update_option($key,$value);$_GET=$get;$_POST=$post;$_REQUEST=$request;$GLOBALS['current_screen']=$screen;wp_set_current_user($old_user);
}
