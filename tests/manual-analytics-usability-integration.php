<?php
// Disposable WordPress/MariaDB test. Synthetic records only.
use SwimLogEvaluation\{Admin_Manual_Workout as Manual_UI,Admin_Analytics as Analytics_UI,Admin_Workouts as Workouts_UI,Admin_UI,Manual_Workout,Workout,Database,Pool_Analytics};
foreach(array('ui','manual-workout','analytics','workouts')as$file)require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-'.$file.'.php';
foreach(array('database','workout','manual-workout','pool-analytics')as$file)require_once SWIMLOG_EVALUATION_DIR.'includes/class-'.$file.'.php';
$previous_user=get_current_user_id();$get=$_GET;$post=$_POST;$request=$_REQUEST;$screen=$GLOBALS['current_screen']??null;
$users=array();$ids=array();$fixtures=array();$checks=0;
$verify=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$set=static function($page,$args=array(),$fields=array()){$_GET=array_merge(array('page'=>$page),$args);$_POST=$fields;$_REQUEST=array_merge($_GET,$_POST);};
$reset=static function(){foreach(array('manual_workout_action_error'=>null,'manual_workout_form'=>array())as$name=>$value){$p=new ReflectionProperty(Manual_UI::class,$name);$p->setAccessible(true);$p->setValue(null,$value);}};
$render=static function($callback){ob_start();try{call_user_func($callback);return ob_get_contents();}finally{ob_end_clean();}};
$xpath=static function($html){$d=new DOMDocument();$old=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);return new DOMXPath($d);};
try{
 require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';require_once ABSPATH.'wp-admin/includes/screen.php';set_current_screen('dashboard');
 foreach(array('owner','other')as$name){$id=wp_create_user('swimlog_simple_'.$name.'_'.wp_generate_password(8,false),wp_generate_password(32));if(is_wp_error($id))throw new RuntimeException('Fixture user');$users[$name]=(int)$id;foreach(array('swimlog_view_own_results','swimlog_manage_own_workouts','swimlog_upload_workouts')as$cap)(new WP_User($id))->add_cap($cap);}
 $owner=$users['owner'];wp_set_current_user($owner);$set('swimlog-manual-workout');$reset();$fixtures['MANUAL']=$render(array(Manual_UI::class,'manual_workout'));$xp=$xpath($fixtures['MANUAL']);
 $verify(1===$xp->query('//tr[@class="swimlog-manual-set-row"]')->length,'New entry starts with one set row');
 $verify(0===$xp->query('//details[@class="swimlog-manual-sets"]/@open')->length,'Totals-only entry starts with sets collapsed');
 foreach($xp->query('//tr[@class="swimlog-manual-set-row"]//input | //tr[@class="swimlog-manual-set-row"]//select')as$control)$verify(0===strpos($control->getAttribute('aria-label'),'Set 1:'),'Every repeated control has a set-specific accessible name');
 $verify(1===$xp->query('//select[@name="course_unit" and @aria-label="Pool and distance unit"]')->length,'Pool unit has an accessible name');
 // Exercise the real POST controller through its redirect boundary.
 $redirect=static function($location){throw new RuntimeException('SWIMLOG_TEST_REDIRECT:'.$location);};add_filter('wp_redirect',$redirect);
 $fields=array('swimlog_manual_workout_action'=>'create','_wpnonce'=>wp_create_nonce('swimlog_create_manual_workout'),'workout_date'=>wp_date('Y-m-d'),'pool_length'=>'25','course_unit'=>'yd','total_distance'=>'1000','duration_minutes'=>'20.25','segment_repetitions'=>array(''),'segment_distance'=>array(''),'segment_stroke'=>array(''),'segment_timing_type'=>array('unknown'),'notes'=>'Synthetic totals only');
 $set('swimlog-manual-workout',array(),$fields);$redirected=false;try{Manual_UI::handle_manual_workout_actions();}catch(RuntimeException$e){if(0!==strpos($e->getMessage(),'SWIMLOG_TEST_REDIRECT:'))throw$e;$redirected=true;parse_str((string)wp_parse_url(substr($e->getMessage(),22),PHP_URL_QUERY),$q);$id=(int)($q['workout_id']??0);}finally{remove_filter('wp_redirect',$redirect);}
 $verify($redirected&&$id>0,'Totals-only POST saved and redirected to recorded workout');$ids[]=$id;$doc=Manual_Workout::get_document($id,$owner);
 $verify(!is_wp_error($doc)&&array()===$doc['segments'],'Blank optional row does not fabricate sets');
 $verify(1000.0===(float)$doc['workout']['original_distance']&&1215000===(int)$doc['workout']['elapsed_time_ms'],'Totals-only persistence preserves native distance and duration');
 $verify(is_wp_error(Manual_Workout::get_document($id,$users['other'])),'Manual records remain owner scoped');
 // Rejected input retains every attempted row and opens the disclosure.
 $fields['workout_date']='invalid';$fields['segment_repetitions']=array('2','1');$fields['segment_distance']=array('25','50');$fields['segment_stroke']=array('FR','BR');$fields['segment_timing_type']=array('measured_rep','interval');$fields['segment_rep_times']=array('1:45, 1:47','');$fields['segment_interval']=array('','2:00');
 $set('swimlog-manual-workout',array(),$fields);Manual_UI::handle_manual_workout_actions();$fixtures['RECOVERY']=$render(array(Manual_UI::class,'manual_workout'));$xp=$xpath($fixtures['RECOVERY']);
 $verify(2===$xp->query('//tr[@class="swimlog-manual-set-row"]')->length&&1===$xp->query('//details[@class="swimlog-manual-sets"]/@open')->length,'Rejected sets retain row count and stay visible');
 $verify(false!==strpos($fixtures['RECOVERY'],'1:45, 1:47')&&false!==strpos($fixtures['RECOVERY'],'2:00'),'Rejected timing values are preserved');
 $verify($doc===Manual_Workout::get_document($id,$owner),'Rejected create does not alter saved totals');$reset();
 // Populate genuine active lengths in separate pool lengths/courses/strokes.
 foreach(array(array(25,'m','FR'),array(50,'m','FR'),array(25,'yd','BR'))as$spec){$d=array('workout'=>array('workout_start'=>wp_date('Y-m-d',time()-86400).' 08:00:00','pool_length_unit'=>$spec[1],'original_pool_length'=>$spec[0]),'segments'=>array(array('type'=>'active','repetitions'=>2,'distance_per_rep'=>$spec[0],'stroke'=>$spec[2],'timing_type'=>'measured_rep','rep_times_ms'=>array(30000,31000))));$saved=Manual_Workout::save($owner,$d);$verify(!is_wp_error($saved),'Save measured pool fixture');$ids[]=$saved['workout_id'];}
 $set('swimlog-manual-workout',array('workout_id'=>$ids[1]));$fixtures['EDIT']=$render(array(Manual_UI::class,'manual_workout'));$verify(false!==strpos($fixtures['EDIT'],'swimlog-manual-sets" open'),'Existing recorded sets are shown when editing');$reset();
 $set('swimlog-pool-analytics',array('months'=>2,'course'=>'m'));$fixtures['POOL']=$render(array(Analytics_UI::class,'pool_analytics'));$xp=$xpath($fixtures['POOL']);
 $verify(6===$xp->query('(//table[@class="widefat striped"])[1]/thead/tr/th[not(@hidden)]')->length,'Pool summary defaults to six key columns');
 $data=Pool_Analytics::for_user($owner,array('months'=>2,'course'=>'m'));$verify(2===count($data['groups']),'Different meter pool lengths remain separate');
 $verify(false!==strpos($fixtures['POOL'],'Average pace / 100 m')&&false!==strpos($fixtures['POOL'],'not a universal fitness score'),'Metric units and SWOLF limits are explicit');
 $set('swimlog-pool-analytics',array('months'=>2,'course'=>'yd','stroke'=>'BR','additional_metrics'=>'1'));$fixtures['ADVANCED']=$render(array(Analytics_UI::class,'pool_analytics'));$xp=$xpath($fixtures['ADVANCED']);
 $verify(12===$xp->query('(//table[@class="widefat striped"])[1]/thead/tr/th[not(@hidden)]')->length,'All original metrics remain available');
 $verify(false!==strpos($fixtures['ADVANCED'],'Strokes / 25 yd')&&false!==strpos($fixtures['ADVANCED'],'Average pace / 100 yd'),'Yard comparison units remain native');
 foreach($xp->query('//nav[@class="swimlog-report-links"]/a')as$a){parse_str((string)wp_parse_url($a->getAttribute('href'),PHP_URL_QUERY),$q);$verify('2'===$q['months'],'Related reports retain period');if('swimlog-training-trends'!==$q['page'])$verify('yd'===$q['course']&&'BR'===$q['stroke'],'Related pool reports retain course and stroke');}
 update_user_meta($owner,'swimlog_training_volume_months',3);$set('swimlog-training-volume',array('months'=>2,'course'=>'yd','stroke'=>'BR'));$fixtures['VOLUME']=$render(array(Analytics_UI::class,'training_volume'));$xp=$xpath($fixtures['VOLUME']);
 $verify(1===$xp->query('//table[@class="widefat striped"]/tbody/tr')->length&&false!==strpos($fixtures['VOLUME'],'Breaststroke'),'Volume applies course/stroke filters');
 $verify(3===(int)get_user_meta($owner,'swimlog_training_volume_months',true),'Report period does not change saved preferences');
 $set('swimlog-training-volume',array('months'=>100));$bounded=$render(array(Analytics_UI::class,'training_volume'));$verify(false!==strpos($bounded,'value="24"'),'Volume period is bounded');
 $set('swimlog-training-trends',array('months'=>2));$fixtures['TRENDS']=$render(array(Analytics_UI::class,'training_trends'));$verify(false!==strpos($fixtures['TRENDS'],'Swimming Volume Trends')&&false===strpos($fixtures['TRENDS'],'Training Load'),'Trends accurately describe swimming volume');
 $set('swimlog-upload');$fixtures['UPLOAD']=$render(array(Workouts_UI::class,'upload'));$verify(false!==strpos($fixtures['UPLOAD'],'Running and walking require FIT')&&false!==strpos($fixtures['UPLOAD'],'GPX/TCX open-water swimming tracks'),'Upload help distinguishes sport/format support');
 wp_set_current_user($users['other']);$set('swimlog-training-volume');$other=$render(array(Analytics_UI::class,'training_volume'));$verify(false!==strpos($other,'No active pool lengths match these filters.'),'Other owner does not see fixture pool volume');
 foreach($fixtures as$name=>$html)echo 'SWIMLOG_SIMPLE_'.$name.'_HTML_BASE64='.base64_encode($html)."\n";
 WP_CLI::success('Manual entry and analytics usability passed '.$checks.' assertions.');
}finally{
 $reset();wp_set_current_user($users['owner']??0);foreach($ids as$id)Workout::delete($id,$users['owner']);require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)wp_delete_user($id);
 $_GET=$get;$_POST=$post;$_REQUEST=$request;$GLOBALS['current_screen']=$screen;wp_set_current_user($previous_user);
}
