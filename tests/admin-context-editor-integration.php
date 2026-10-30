<?php
// Current-owner synthetic records only; disposable WordPress/MariaDB validation.
use SwimLogEvaluation\{Admin_UI as UI,Admin_Dashboard as Dash,Admin_Workouts as Workouts_UI,Admin_Analytics as Analytics_UI,Admin_Personal_Bests as PB_UI,Admin_Manual_Workout as Manual_UI,Admin_Structured_Workouts as Editor_UI,Admin_Planned_Workouts as Plans_UI,Workout,Database,Structured_Workout_Store as Store,Planned_Workout as Plans,Planned_Structure_Link as Link};
foreach ( array( 'ui','dashboard','workouts','analytics','personal-bests','manual-workout','structured-workouts','planned-workouts','admin' ) as $file ) {
    $path = 'admin' === $file ? 'admin/class-admin.php' : 'admin/class-admin-' . $file . '.php'; require_once SWIMLOG_EVALUATION_DIR . $path;
}
foreach ( array( 'database','workout','location','event','structured-workout','structured-workout-store','planned-workout','planned-structure-link','blocks' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'includes/class-' . $file . '.php';
$old_user=get_current_user_id();$get=$_GET;$post=$_POST;$request=$_REQUEST;$screen=$GLOBALS['current_screen']??null;$uri=$_SERVER['REQUEST_URI']??null;
$users=array();$workouts=array();$structures=array();$plans=array();$checks=0;
$verify=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$set=static function($page,$args=array(),$fields=array()){$_GET=array_merge(array('page'=>$page),$args);$_POST=$fields;$_REQUEST=array_merge($_GET,$_POST);$_SERVER['REQUEST_URI']='/wp-admin/admin.php?'.http_build_query($_GET);};
$render=static function($callback){ob_start();try{call_user_func($callback);return ob_get_contents();}finally{ob_end_clean();}};
$dom=static function($html){$doc=new DOMDocument();$old=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);return new DOMXPath($doc);};
try{
 require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';require_once ABSPATH.'wp-admin/includes/screen.php';set_current_screen('dashboard');
 foreach(array('admin','swimmer')as$name){$id=wp_create_user('swimlog_context_'.$name.'_'.wp_generate_password(8,false),wp_generate_password(32));if(is_wp_error($id))throw new RuntimeException('Create context fixture');$users[$name]=(int)$id;foreach(array('swimlog_view_own_results','swimlog_manage_own_workouts')as$cap)(new WP_User($id))->add_cap($cap);}
 $admin=$users['admin'];$owner=$users['swimmer'];(new WP_User($admin))->add_cap('swimlog_manage_all_users');wp_set_current_user($admin);
 $args=UI::context_args($owner);$set('swimlog-dashboard',$args);$verify($owner===UI::selected_user_id(),'Valid signed administrator selection is honored');
 $set('swimlog-dashboard',array('swimmer_id'=>$owner));$verify($admin===UI::selected_user_id(),'Missing selector nonce falls back to current user');
 $set('swimlog-dashboard',array('swimmer_id'=>$owner,'_swimlog_swimmer_nonce'=>'invalid'));$verify($admin===UI::selected_user_id(),'Invalid selector nonce remains denied');
 $set('swimlog-dashboard',$args);
 foreach(UI::context_pages()as$page){parse_str((string)wp_parse_url(UI::url(array('page'=>$page),$owner),PHP_URL_QUERY),$q);$set($page,$q);$verify($owner===UI::selected_user_id(),'Signed URL round trip: '.$page);}
 foreach(array('swimlog-garmin','swimlog-structured-workouts','swimlog-planned-workouts','swimlog-privacy')as$page){parse_str((string)wp_parse_url(UI::url(array('page'=>$page,'swimmer_id'=>$owner),$owner),PHP_URL_QUERY),$q);$verify(!isset($q['swimmer_id'])&&!isset($q['_swimlog_swimmer_nonce']),'Owner-only page does not inherit context: '.$page);}
 // Populate enough swimming records to exercise pagination and source/detail links.
 global $wpdb;
 for($i=0;$i<22;$i++){$row=array('user_id'=>$owner,'workout_type'=>'pool','workout_start'=>wp_date('Y-m-d').' 08:00:00','total_distance_m'=>100,'original_distance'=>100,'original_distance_unit'=>'m','pool_length_m'=>25,'original_pool_length'=>25,'pool_length_unit'=>'m','elapsed_time_ms'=>120000,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));Database::write('insert',Database::table('workouts'),$row);$workouts[]=(int)$wpdb->insert_id;}
 $pages=array('DASHBOARD'=>array('swimlog-dashboard',array(Dash::class,'render')),'WORKOUTS'=>array('swimlog-workouts',array(Workouts_UI::class,'workouts')),'BESTS'=>array('swimlog-personal-bests',array(PB_UI::class,'personal_bests')),'TRENDS'=>array('swimlog-training-trends',array(Analytics_UI::class,'training_trends')),'ANALYTICS'=>array('swimlog-pool-analytics',array(Analytics_UI::class,'pool_analytics')),'MANUAL'=>array('swimlog-manual-workout',array(Manual_UI::class,'manual_workout')));
 $fixtures=array();
 foreach($pages as$name=>$page){$set($page[0],$args);$html=$render($page[1]);$fixtures[$name]=$html;$xp=$dom($html);$verify(false!==strpos($html,'Viewing swimmer:')&&false!==strpos($html,'(#'.$owner.')'),'Visible context on '.$name);
  foreach($xp->query('//form[@method="get"]')as$form){$query=array();foreach($xp->query('.//input',$form)as$input)if($input->getAttribute('name'))$query[$input->getAttribute('name')]=$input->getAttribute('value');foreach($xp->query('.//select[@name]',$form)as$select){$chosen=$xp->query('.//option[@selected]',$select)->item(0)??$xp->query('.//option',$select)->item(0);if($chosen)$query[$select->getAttribute('name')]=$chosen->getAttribute('value');}if(!isset($query['page']))continue;$set($query['page'],$query);$verify($owner===UI::selected_user_id(),'GET filter keeps signed context on '.$name);}
  foreach($xp->query('//a[@href]')as$link){parse_str((string)wp_parse_url(html_entity_decode($link->getAttribute('href')),PHP_URL_QUERY),$q);if(!in_array($q['page']??'',UI::context_pages(),true))continue;if(false!==strpos($link->textContent,'Return to my records'))continue;$set($q['page'],$q);$verify($owner===UI::selected_user_id(),'Link keeps context on '.$name);}
 }
 $set('swimlog-workouts',array_merge($args,array('workout_id'=>$workouts[0])));$details=$render(array(Workouts_UI::class,'workouts'));$xp=$dom($details);
 foreach($xp->query('//a[@href]')as$link){parse_str((string)wp_parse_url(html_entity_decode($link->getAttribute('href')),PHP_URL_QUERY),$q);if(!in_array($q['page']??'',UI::context_pages(),true)||false!==strpos($link->textContent,'Return to my records'))continue;$set($q['page'],$q);$verify($owner===UI::selected_user_id(),'Workout detail/back/edit links preserve selected swimmer');}
 $set('swimlog-calendar',$args);$calendar=SwimLogEvaluation\Blocks::render_calendar_for_user($owner);$xp=$dom($calendar);$base=$xp->query('//*[@data-workout-detail-url]')->item(0)->getAttribute('data-workout-detail-url');parse_str((string)wp_parse_url($base.$workouts[0],PHP_URL_QUERY),$q);$set('swimlog-workouts',$q);$verify($owner===UI::selected_user_id(),'Calendar detail link carries valid swimmer context');
 $verify(false!==strpos($calendar,'data-health-enabled="0"'),'Administrator calendar does not acquire another owner measurement access');
 wp_set_current_user($owner);$set('swimlog-workouts',array('swimmer_id'=>$admin,'_swimlog_swimmer_nonce'=>wp_create_nonce('swimlog_swimmer_selector')));$verify($owner===UI::selected_user_id()&&array()===UI::context_args($admin),'Ordinary swimmer cannot select another owner');
 // Actual private editor and locked/regeneration previews.
 $doc=array('version'=>1,'unit'=>'m','sections'=>array(array('type'=>'section','label'=>'Synthetic main set','children'=>array(array('type'=>'step','distance'=>100,'stroke'=>'FR'),array('type'=>'step','distance'=>50,'stroke'=>'BR','equipment'=>array('Fins','Custom buoy'),'rest'=>'0')))));
 $id=Store::save($owner,'Synthetic accessible workout',$doc);$verify(!is_wp_error($id),'Save editor fixture');$structures[]=$id;$set('swimlog-structured-workouts',array('edit'=>$id));$fixtures['EDITOR']=$render(array(Editor_UI::class,'page'));
 $plan=Plans::save($owner,array('title'=>'Synthetic regeneration','planned_date'=>wp_date('Y-m-d',time()+86400),'workout_type'=>'pool','pool_course'=>'m','workout_instructions'=>'2 x 100 BR'));$verify(!is_wp_error($plan),'Save preview plan');$plans[]=$plan;$verify(!is_wp_error(Link::attach($plan,$id,$owner)),'Link preview structure');
 $before=Store::get($id,$owner)['document'];$set('swimlog-planned-workouts',array('edit'=>$plan),array('swimlog_planned_workout_action'=>'preview_regeneration','planned_workout_id'=>$plan,'_wpnonce'=>wp_create_nonce('swimlog_regenerate_structure_'.$plan)));Plans_UI::handle_actions();$fixtures['REGEN']=$render(array(Plans_UI::class,'page'));
 $verify(false!==strpos($fixtures['REGEN'],'Review complete proposed workout')&&false!==strpos($fixtures['REGEN'],'Breaststroke')&&false===strpos($fixtures['REGEN'],'Review complete proposed structure (JSON)'),'Regeneration review is a human-readable full prescription');
 $verify($before===Store::get($id,$owner)['document'],'Preview never overwrites the saved prescription');
 update_post_meta($id,Store::META_RECORDED,1);$set('swimlog-structured-workouts',array('edit'=>$id));$fixtures['LOCKED']=$render(array(Editor_UI::class,'page'));
 $verify(false!==strpos($fixtures['LOCKED'],'Freestyle')&&false!==strpos($fixtures['LOCKED'],'Breaststroke')&&false!==strpos($fixtures['LOCKED'],'Custom buoy')&&false!==strpos($fixtures['LOCKED'],'none (continuous)'),'Locked preview retains strokes, equipment and explicit zero rest');
 $verify(false===strpos($fixtures['LOCKED'],'id="swimlog-structure-editor"')&&is_wp_error(Store::save($owner,'Attempted replacement',$doc,$id)),'Locked preview stays read-only and source protection is intact');
 foreach($fixtures as$name=>$html)echo 'SWIMLOG_CONTEXT_'.$name.'_HTML_BASE64='.base64_encode($html)."\n";
 WP_CLI::success('Administrator context and readable prescription previews passed '.$checks.' assertions.');
}finally{
 wp_set_current_user($users['swimmer']??0);foreach($plans as$id)Plans::delete($id,$users['swimmer']);foreach($structures as$id)wp_delete_post($id,true);foreach($workouts as$id)Workout::delete($id,$users['swimmer']);require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)wp_delete_user($id);
 $_GET=$get;$_POST=$post;$_REQUEST=$request;$GLOBALS['current_screen']=$screen;if(null===$uri)unset($_SERVER['REQUEST_URI']);else $_SERVER['REQUEST_URI']=$uri;wp_set_current_user($old_user);
}
