<?php
// Disposable synthetic FIT/HTTP data only; gate is opened only within this CLI test.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Importer;
use SwimLogEvaluation\Land_Activity;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Garmin_History;
use SwimLogEvaluation\Garmin_History_Jobs as Jobs;
use SwimLogEvaluation\Workout;
use SwimLogEvaluation\Admin_Workouts;
use SwimLogEvaluation\Admin_Land_Activities;
use SwimLogEvaluation\Tests\Land_Fixture;
foreach(array('land-activity','importer','fit-importer','csv-importer','location','workout','garmin-personal-api','garmin-history','garmin-history-jobs') as $file)require_once SWIMLOG_EVALUATION_DIR.'includes/class-'.$file.'.php';
require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-workouts.php';
require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-land-activities.php';
if(!class_exists(Land_Fixture::class))WP_CLI::error('Synthetic FIT fixture class was not loaded.');
global $wpdb;
$storage=get_option('swimlog_private_storage_dir');$docroot=$_SERVER['DOCUMENT_ROOT']??null;$directory=sys_get_temp_dir().'/swimlog-land-'.wp_generate_uuid4();
$original=get_current_user_id();$users=array();$paths=array();$checks=0;$http=null;$die=null;
$verify=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$enable=static function(){return true;};
try{
 $_SERVER['DOCUMENT_ROOT']=ABSPATH;update_option('swimlog_private_storage_dir',$directory);
 foreach(array('owner','other') as $label){$id=wp_create_user('swimlog_land_'.$label.'_'.wp_generate_password(8,false),wp_generate_password(32));$verify(!is_wp_error($id),'Create owner');$users[$label]=(int)$id;$u=new WP_User($id);foreach(array('swimlog_manage_own_workouts','swimlog_upload_workouts','swimlog_view_own_results') as $cap)$u->add_cap($cap);}
 $owner=$users['owner'];wp_set_current_user($owner);
 $path=Land_Fixture::path();$paths[]=$path;
 $closed=Importer::import_trusted_fit($owner,$path,'run.fit');
 $verify(is_wp_error($closed)&&'swimlog_land_pending'===$closed->get_error_code(),'Production gate refuses land imports before any source persistence');
 add_filter('swimlog_land_activity_imports_enabled',$enable);
 $run=Importer::import_trusted_fit($owner,$path,'run.fit');
 $verify(!is_wp_error($run)&&!empty($run['workout_id']),'Import outdoor run without OWS location');
 $w=Workout::get_for_user($run['workout_id'],$owner);
 $verify('running'===$w->workout_type&&'outdoor'===$w->activity_environment,'Persist sport and environment');
 $verify(null===$w->avg_swolf&&null===$w->equivalent_steps&&null===$w->pool_length_m,'No swim-only derivations');
 $verify(3===count(Workout::trackpoints($w->id,$owner))&&array()===Workout::lengths($w->id,$owner),'Keep GPS without swim lengths');
 $verify(array()===Workout::performances($w->id,$owner),'No swim evaluations');
 $duplicate=Importer::import_trusted_fit($owner,$path,'run.fit');
 $verify(is_wp_error($duplicate)&&'swimlog_duplicate'===$duplicate->get_error_code(),'Exact file skipped');
 $walk_path=Land_Fixture::path(11,27,false);$paths[]=$walk_path;
 $walk=Importer::import_trusted_fit($owner,$walk_path,'walk.fit');
 $verify(!is_wp_error($walk)&&$walk['workout_id']!==$run['workout_id'],'Same-time walk does not merge with run');
 $walk_w=Workout::get_for_user($walk['workout_id'],$owner);
 $verify('indoor'===$walk_w->activity_environment&&null===Workout::trackpoints($walk_w->id,$owner)[0]->latitude,'Indoor no-GPS summary persisted');
 $variant=Land_Fixture::path(1,2,true,1);$paths[]=$variant;
 $pending=Importer::import_trusted_fit($owner,$variant,'run-other-source.fit');
 $verify(!is_wp_error($pending)&&!empty($pending['pending']),'Same-sport probable summary match needs review');
 $resolved=Importer::resolve_pending($pending['import_id'],$owner,'attach');
 $verify(!is_wp_error($resolved)&&$resolved['workout_id']===$run['workout_id']&&0===$resolved['performances'],'Reviewed attachment preserves identity without evaluating');
 $rebuilt=Importer::rebuild_workout($run['workout_id'],$owner);
 $verify(!is_wp_error($rebuilt)&&0===$rebuilt['performances'],'Source rebuild remains evaluation-free');
 $verify(!is_wp_error(API::save_key($owner,'synthetic-land-key')),'Store synthetic credential');
 $archives=array();foreach(array(771=>file_get_contents($path),772=>file_get_contents($walk_path)) as $id=>$bytes){$zpath=tempnam(sys_get_temp_dir(),'swimlog-land-zip-');$paths[]=$zpath;$zip=new ZipArchive();$zip->open($zpath,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('activity.fit',$bytes);$zip->close();$archives[$id]=file_get_contents($zpath);}
 $http=static function($pre,$args,$url)use($archives){
  if(false!==strpos($url,'download-service/')){$id=(int)basename(parse_url($url,PHP_URL_PATH));return array('response'=>array('code'=>200),'headers'=>array(),'body'=>$archives[$id]??'');}
  if(false!==strpos($url,'personal-api-key/token'))return array('response'=>array('code'=>200),'headers'=>array(),'body'=>'synthetic.land.token');
  if(false!==strpos($url,'search/activities'))return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array(array('activityId'=>771,'activityType'=>array('typeKey'=>'running'),'startTimeLocal'=>'2018-01-01T06:00:00'),array('activityId'=>772,'activityType'=>array('typeKey'=>'walking'),'startTimeLocal'=>'2018-01-01T07:00:00'),array('activityId'=>773,'activityType'=>array('typeKey'=>'cycling'),'startTimeLocal'=>'2018-01-01T08:00:00'))));
  return new WP_Error('unexpected_synthetic_http','Unexpected HTTP');
 };
 add_filter('pre_http_request',$http,10,3);
 $list=API::recent_activities($owner,100,'2018-01-01','2018-01-01',array('running','walking','cycling'));
 $verify(is_array($list)&&2===count($list),'Manual discovery accepts exact run/walk type keys and excludes cycling');
 $page=Garmin_History::activity_step($owner,Garmin_History::cursor('2018-01-01','2018-01-01'),array('running','walking'));
 $verify(!is_wp_error($page)&&2===count($page['activities'])&&3===$page['cursor']['offset'],'Historical discovery advances raw count before sport filtering');
 update_user_meta($owner,'swimlog_garmin_land_sync_types',array('running','walking'));
 $sync=API::scheduled_sync($owner);
 $verify(!is_wp_error($sync)&&2===$sync['already']&&0===$sync['failed'],'Opted-in scheduled Garmin import associates existing upload hashes without duplicates');
 $history_import=Garmin_History::import_activity($owner,771,'running');
 $verify(!is_wp_error($history_import)&&'already_associated'===$history_import['status'],'Historical activity import reuses provider duplicate handling');
 $job=Jobs::start('2018-01-01','2018-01-01',array('running','walking'));
 $verify(!is_wp_error($job)&&array('running','walking')===$job['types']&&array()===$job['measurements'],'Persist immutable historical sport selection');
 Jobs::control($job['id'],'cancel');
 $points=Workout::trackpoints($w->id,$owner);
 $svg=Admin_Workouts::activity_route_svg($points);
 $verify(false!==strpos($svg,'Recorded activity GPS route map')&&1===substr_count($svg,'<polyline'),'Share accessible map style');
 $gap=clone $points[1];$gap->latitude=null;$gap->longitude=null;
 $verify(''===Admin_Workouts::activity_route_svg(array($points[0],$gap,$points[2])),'Do not draw a route across invalid GPS gap');
 ob_start();Admin_Land_Activities::render($w,$owner);$html=ob_get_clean();
 $verify(false!==strpos($html,'GPS Route')&&false===strpos($html,'SWOLF')&&false===strpos($html,'Create Post'),'Land detail has map and summary without swim tools or publishing');
 echo 'SWIMLOG_LAND_DETAIL_HTML_BASE64='.base64_encode($html)."\n";
 ob_start();Admin_Land_Activities::render($walk_w,$owner);$indoor=ob_get_clean();
 $verify(false!==strpos($indoor,'No usable GPS route'),'Indoor detail does not invent map');
 $die=static function(){return static function(){throw new RuntimeException('expected owner denial');};};add_filter('wp_die_handler',$die);
 wp_set_current_user($users['other']);$denied=false;
 try{Admin_Land_Activities::render($w,$owner);}catch(RuntimeException $e){$denied='expected owner denial'===$e->getMessage();}
 $verify($denied,'Different owner cannot render land route');
 remove_filter('swimlog_land_activity_imports_enabled',$enable);
 $verify(!Land_Activity::enabled()&&array('lap_swimming','open_water_swimming')===Land_Activity::garmin_types(),'Gate restored; existing swim defaults unchanged');
 WP_CLI::success('Running/walking foundation passed '.$checks.' assertions: gated import, owner persistence, sport-safe duplicates, rebuild, discovery/history, map gaps and privacy.');
}finally{
 remove_filter('swimlog_land_activity_imports_enabled',$enable);if($http)remove_filter('pre_http_request',$http,10);if($die)remove_filter('wp_die_handler',$die);
 foreach($users as $id){wp_set_current_user($id);$rows=$wpdb->get_results($wpdb->prepare('SELECT id FROM %i WHERE user_id=%d',Database::table('workouts'),$id));foreach($rows as $row)Workout::delete((int)$row->id,$id);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($id);}
 foreach($paths as $path)if(is_file($path))unlink($path);if(is_dir($directory)){foreach(glob($directory.'/*') as $file)if(is_file($file))unlink($file);rmdir($directory);}
 update_option('swimlog_private_storage_dir',$storage);if(null===$docroot)unset($_SERVER['DOCUMENT_ROOT']);else $_SERVER['DOCUMENT_ROOT']=$docroot;wp_set_current_user($original);
}
