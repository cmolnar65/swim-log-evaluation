<?php
// Synthetic independent activities and provider responses only.
use SwimLogEvaluation\{Database,Importer,Workout,Garmin_Personal_API as API,Admin_Garmin,Plugin};
foreach(array('database','importer','workout','location','garmin-personal-api','plugin')as$file)require_once SWIMLOG_EVALUATION_DIR.'includes/class-'.$file.'.php';
require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-garmin.php';
global $wpdb;$original=get_current_user_id();$owner=0;$path=null;$http=null;$die=null;$checks=0;$old_post=$_POST;$old_get=$_GET;$old_request=$_REQUEST;$storage=get_option('swimlog_private_storage_dir');$root=$_SERVER['DOCUMENT_ROOT']??null;
$verify=static function($ok,$message)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($message);};
try{
 $owner=wp_create_user('swimlog_match_sync_'.wp_generate_password(8,false),wp_generate_password(32));if(is_wp_error($owner))throw new RuntimeException('Create fixture owner');$owner=(int)$owner;
 $user=new WP_User($owner);foreach(array('swimlog_view_own_results','swimlog_manage_own_workouts','swimlog_upload_workouts')as$cap)$user->add_cap($cap);wp_set_current_user($owner);
 $_SERVER['DOCUMENT_ROOT']=ABSPATH;$dir=sys_get_temp_dir().'/swimlog-match-sync-'.wp_generate_uuid4();update_option('swimlog_private_storage_dir',$dir);
 $pool=array('user_id'=>$owner,'workout_type'=>'pool','workout_start'=>'2026-10-06 08:00:00','pool_length_m'=>22.86,'pool_length_unit'=>'yd','original_pool_length'=>25,'total_distance_m'=>5486.4,'original_distance'=>6000,'original_distance_unit'=>'yd','elapsed_time_ms'=>7980000,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));
 $verify(false!==Database::write('insert',Database::table('workouts'),$pool),'Seed independent pool swim');$pool_id=(int)$wpdb->insert_id;
 $path=wp_tempnam('swimlog-pool-match.csv');file_put_contents($path,"Swim Date,Start,End,Pool Size,Dist Yd\n2026-10-06,08:01:00,10:14:10,25 yd,6050\nSwim Date,Swim Time,Length Yd,Move Time,Strk\n".str_repeat("2026-10-06,08:01:00,25,33.01653,FR\n",242));
 $method=new ReflectionMethod(Importer::class,'import_file');$method->setAccessible(true);$result=$method->invoke(null,$owner,$path,'synthetic-pool.csv',filesize($path),'csv',0,array());
 if(is_wp_error($result))throw new RuntimeException('Synthetic pool import: '.$result->get_error_code());
 $verify(!is_wp_error($result)&&!empty($result['pending'])&&$pool_id===(int)$result['candidate_workout_id'],'Two-length device difference is held for explicit review');
 $verify(1===(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE user_id=%d',Database::table('workouts'),$owner)),'Pending source creates no second workout or double-counted distance');
 $verify('pending'===$wpdb->get_var($wpdb->prepare('SELECT import_status FROM %i WHERE id=%d',Database::table('imports'),$result['import_id'])),'Persist review status');
 $verify(5486.4===(float)Workout::get_for_user($pool_id,$owner)->total_distance_m,'Existing workout remains unchanged');
 $verify(array('lap_swimming','open_water_swimming')===API::scheduled_types($owner),'Legacy users keep pool and open-water defaults');
 $verify(!is_wp_error(API::save_key($owner,'synthetic-match-sync-key')),'Save synthetic credential');
 $save=static function($types,$nonce=null){$_GET=array();$_POST=array('swimlog_garmin_action'=>'save_sync','garmin_sync_enabled'=>'1','garmin_sync_types_present'=>'1','garmin_sync_types'=>$types,'_wpnonce'=>$nonce??wp_create_nonce('swimlog_save_garmin_sync'));$_REQUEST=$_POST;ob_start();try{Admin_Garmin::garmin();return ob_get_contents();}finally{ob_end_clean();}};
 $types=array('lap_swimming','open_water_swimming','running','walking');$html=$save($types);
 $verify($types===API::scheduled_types($owner),'Save and reload all four scheduled categories');
 foreach($types as$type)$verify(1===preg_match('/name="garmin_sync_types\[\]" value="'.preg_quote($type,'/').'"\s+checked/',$html),'All four explicit activity choices are checked after saving');
 $verify('1'===(string)get_user_meta($owner,'swimlog_garmin_sync_enabled',true)&&false!==wp_next_scheduled('swimlog_garmin_scheduled_sync',array($owner)),'Master enable creates hourly activity schedule');
 $activities=array();foreach($types as$i=>$type){$id=701+$i;$normalized=array('pool','open_water','running','walking')[$i];$row=$pool;$row['workout_type']=$normalized;$row['workout_start']='2026-10-07 08:00:00';Database::write('insert',Database::table('workouts'),$row);$wid=(int)$wpdb->insert_id;Database::write('insert',Database::table('imports'),array('user_id'=>$owner,'workout_id'=>$wid,'source_type'=>'fit','source_provider'=>'garmin','external_activity_id'=>(string)$id,'original_filename'=>'synthetic-'.$id.'.fit','file_hash'=>hash('sha256','synthetic-'.$id),'import_status'=>'complete','imported_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));$activities[]=array('activityId'=>$id,'activityType'=>array('typeKey'=>$type));}
 $http=static function($pre,$args,$url)use($activities){if(false!==strpos($url,'personal-api-key/token'))return array('response'=>array('code'=>200),'headers'=>array(),'body'=>'synthetic.match.token');if(false!==strpos($url,'search/activities'))return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode($activities));return new WP_Error('unexpected_http','Unexpected synthetic request');};add_filter('pre_http_request',$http,10,3);
 $sync=API::scheduled_sync($owner);$verify(!is_wp_error($sync)&&4===$sync['already']&&0===$sync['failed'],'Scheduled worker processes pool, open-water, running and walking selections');
 $save(array('walking'));$sync=API::scheduled_sync($owner);$verify(array('walking')===API::scheduled_types($owner)&&1===$sync['already'],'A walking-only choice excludes all unselected activities');
 $die=static function(){return static function(){throw new RuntimeException('expected nonce denial');};};add_filter('wp_die_handler',$die);$denied=false;try{$save($types,'invalid-nonce');}catch(RuntimeException $e){$denied='expected nonce denial'===$e->getMessage();}remove_filter('wp_die_handler',$die);$die=null;$verify($denied&&array('walking')===API::scheduled_types($owner),'Invalid nonce cannot alter scheduled preferences');
 $save(array());$verify(array('walking')===API::scheduled_types($owner),'Empty enabled selection rejected without overwriting preferences');
 WP_CLI::success('Pool review and four-sport scheduled synchronization passed '.$checks.' assertions.');
}finally{
 if($die)remove_filter('wp_die_handler',$die);if($http)remove_filter('pre_http_request',$http,10);if($owner){wp_set_current_user($owner);Plugin::set_garmin_sync_schedule($owner,false);foreach((array)$wpdb->get_results($wpdb->prepare('SELECT id FROM %i WHERE user_id=%d',Database::table('workouts'),$owner))as$row)Workout::delete((int)$row->id,$owner);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($owner);}
 if($path&&is_file($path))unlink($path);if(isset($dir)&&is_dir($dir)){foreach(glob($dir.'/*')as$file)if(is_file($file))unlink($file);rmdir($dir);}update_option('swimlog_private_storage_dir',$storage);if(null===$root)unset($_SERVER['DOCUMENT_ROOT']);else $_SERVER['DOCUMENT_ROOT']=$root;$_POST=$old_post;$_GET=$old_get;$_REQUEST=$old_request;wp_set_current_user($original);
}