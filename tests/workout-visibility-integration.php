<?php
// Synthetic fixtures only. Runs in the disposable WordPress staging environment.
use SwimLogEvaluation\{Database,Plugin,Workout,Workout_Visibility as V,Shortcodes,Blocks,REST_API,Importer,Manual_Workout,Planned_Workout,Recorded_Workout_Structure,Planned_Structure_Link};
foreach(array('workout','importer','workout-visibility','shortcodes','blocks','rest-api','manual-workout','recorded-workout-structure','planned-structure-link')as$file)require_once SWIMLOG_EVALUATION_DIR.'includes/class-'.$file.'.php';
global $wpdb;$original_prefix=$wpdb->prefix;$original_user=get_current_user_id();$original_version=get_option('swimlog_db_version');$default=get_option('swimlog_public_results_default',false);$users=array();$checks=0;$failure=null;$post=0;$die=null;$old_post=$_POST;$old_get=$_GET;$old_request=$_REQUEST;$old_screen=$GLOBALS['current_screen']??null;
$verify=static function($condition,$message)use(&$checks){++$checks;if(!$condition)throw new RuntimeException($message);};
$denied=static function($value)use($verify){$verify(is_wp_error($value),'Management must return an error');};
try{
 foreach(range(1,4)as$i){$u=wp_create_user('visibility_'.wp_generate_password(10,false),wp_generate_password(32));if(is_wp_error($u))throw new RuntimeException('fixture account');$users[]=(int)$u;$user=new WP_User($u);foreach(array('swimlog_manage_own_workouts','swimlog_view_own_results','swimlog_upload_workouts')as$cap)$user->add_cap($cap);}
 list($owner,$other,$no_consent,$manager)=$users;(new WP_User($manager))->add_cap('swimlog_manage_all_users');(new WP_User($manager))->add_cap('activate_plugins');
 $wpdb->prefix=$original_prefix.'visibility_'.strtolower(wp_generate_password(8,false)).'_';
 $verify(Database::install_schema(),'Fresh schema');$wtable=Database::table('workouts');$vtable=Database::table('workout_visibility');
 $seed=static function($uid,$type,$day=1)use($wpdb,$wtable){$ok=Database::write('insert',$wtable,array('user_id'=>$uid,'workout_type'=>$type,'workout_start'=>sprintf('2026-01-%02d 08:12:34',$day),'total_distance_m'=>1000,'original_distance'=>1000,'original_distance_unit'=>'m','original_pool_length'=>25,'pool_length_unit'=>'m','elapsed_time_ms'=>900000,'moving_time_ms'=>850000,'primary_stroke'=>'FR','notes'=>'SECRET_NOTES','device_model'=>'SECRET_DEVICE','created_at'=>'2026-01-01 00:00:00','updated_at'=>'2026-01-01 00:00:00'));if(false===$ok)throw new RuntimeException('seed');return(int)$wpdb->insert_id;};
 update_user_meta($owner,'swimlog_public_results','1');update_user_meta($owner,'swimlog_public_ows_routes','1');update_user_meta($other,'swimlog_public_results','0');update_option('swimlog_public_results_default','1');
 $pool=$seed($owner,'pool');$ows=$seed($owner,'open_water',2);$run=$seed($owner,'running',3);$walk=$seed($owner,'walking',4);$private=$seed($other,'pool',5);$implicit=$seed($no_consent,'pool',6);
 update_user_meta($owner,'_standalone_workout_public_'.$run,'1');update_user_meta($owner,'_swimlog_workout_photos_'.$pool,array(987654));
 // Simulate existing schema 1.4: old workouts exist; no canonical visibility records yet.
 Database::write('query',$wpdb->prepare('DELETE FROM %i',$vtable));update_option('swimlog_db_version','1.4');wp_set_current_user($manager);
 $failure=static function($sql)use($vtable){return false!==strpos($sql,'INSERT IGNORE INTO `'.$vtable.'`')&&false!==strpos($sql,'SELECT w.id')?'INVALID SYNTHETIC SNAPSHOT':$sql;};
 add_filter('query',$failure,1);$suppress=$wpdb->suppress_errors(true);(new Plugin())->maybe_upgrade_schema();$wpdb->suppress_errors($suppress);remove_filter('query',$failure,1);$failure=null;
 $verify('1.4'===get_option('swimlog_db_version'),'Failed migration withholds schema version');$verify(!V::ready(),'Failed migration fails closed');$verify(6===(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i',$wtable)),'Failed migration preserves history');
 (new Plugin())->maybe_upgrade_schema();$verify('1.9'===get_option('swimlog_db_version')&&V::ready(),'Retry completes schema 1.9');
 wp_set_current_user(0);$verify(V::is_public($pool)&&V::is_public($ows),'Explicit old swimming consent retains public swims');$verify(!V::is_public($run)&&!V::is_public($walk),'Old route/global consent does not publish land');$verify(!V::is_public($private)&&!V::is_public($implicit),'Private owner and site default never become consent');
 $verify(null===V::get_public($pool)['route'],'Pool route not grandfathered');$verify(is_array(V::get_public($ows)['route']),'Old explicit OWS route consent retained');
 wp_set_current_user($owner);$state=V::get_for_owner($run);$verify($state['pending_standalone_preference']&&!$state['effective_public'],'Provisional preference remains pending');$nonce=wp_create_nonce('swimlog_workout_visibility_'.$run);
 $denied(V::set_for_owner($run,array('visibility'=>'public','route_public'=>false),'invalid',$state['revision']));
 $denied(V::set_for_owner($run,array('visibility'=>'public','route_public'=>'1'),$nonce,$state['revision']));
 $denied(V::set_for_owner($run,array('visibility'=>'private','route_public'=>true),$nonce,$state['revision']));
 $state=V::adopt_standalone_preference($run,$nonce,$state['revision']);$verify(!is_wp_error($state)&&$state['effective_public']&&!$state['route_public']&&!$state['pending_standalone_preference'],'Explicit adoption publishes workout only and consumes pending preference');
 $denied(V::set_for_owner($run,array('visibility'=>'private','route_public'=>false),$nonce,$state['revision']-1));$denied(V::adopt_standalone_preference($walk,wp_create_nonce('swimlog_workout_visibility_'.$walk),0));
 $state=V::set_for_owner($run,array('visibility'=>'public','route_public'=>true),$nonce,$state['revision']);$verify(!is_wp_error($state)&&!$state['effective_route_public'],'Old swimming route permission cannot share running route');
 update_user_meta($owner,V::ROUTES_META,'1');$verify(V::get_for_owner($run)['effective_route_public'],'Explicit all-sport route consent plus route flag allows route');
 foreach(array(array(1,'2026-01-03 08:12:34',40,-105),array(2,'2026-01-03 08:12:35',40.001,-105.001),array(4,'2026-01-03 08:12:36',40.002,-105.002),array(5,'2026-01-03 08:12:37',40.003,-105.003))as$p)$verify(false!==Database::write('insert',Database::table('trackpoints'),array('workout_id'=>$run,'sequence_no'=>$p[0],'recorded_at'=>$p[1],'latitude'=>$p[2],'longitude'=>$p[3],'created_at'=>'2026-01-01 00:00:00')),'Synthetic route point');
 $dto=V::get_public($run);$verify(2===count($dto['route']['segments']),'GPS gap creates separate segments');$verify(array('latitude','longitude')===array_keys($dto['route']['segments'][0][0]),'No private route timestamps or sensors');
 foreach(array('notes','photo_ids','location_name','device_model','workout_start','user_id','heart_rate','source_type','raw_metadata')as$key)$verify(!array_key_exists($key,$dto),'Whitelist excludes '.$key);
 $state=V::set_for_owner($run,array('visibility'=>'public','route_public'=>false),$nonce,$state['revision']);$verify(null===V::get_public($run)['route'],'Revocation removes all route geometry');
 update_user_meta($owner,'swimlog_public_results','0');$verify(!V::is_public($run)&&0===V::query_public($owner)['total'],'Master switch blocks workout and counts');$verify('public'===V::get_for_owner($run)['visibility'],'Global switch preserves individual preference');update_user_meta($owner,'swimlog_public_results','1');
 $new=$seed($owner,'pool',7);$verify(V::created($new,$owner),'New workout receives explicit private state');$verify(V::migrate()&&!V::is_public($new),'Repeated upgrade cannot expose new private history');
 // Private faster PB must never determine public best/progression.
 foreach(array(array($pool,60000,0),array($new,30000,1))as$p)$verify(false!==Database::write('insert',Database::table('performances'),array('user_id'=>$owner,'workout_id'=>$p[0],'distance_value'=>100,'course_unit'=>'m','stroke'=>'FR','duration_ms'=>$p[1],'achieved_at'=>'2026-01-01 08:12:34','is_personal_best'=>$p[2],'evaluation_version'=>'synthetic','created_at'=>'2026-01-01 00:00:00')),'Synthetic performance');
 $verify(60000===(int)V::personal_bests($owner,'m')[100]['FR']->duration_ms,'Public PB ignores private faster flag');$verify(1===count(V::progression($owner,100,'m','FR')),'Public progression uses only eligible inputs');
 foreach(array(0,$other,$manager)as$actor){wp_set_current_user($actor);$verify(V::is_public($run),'Public read permitted for actor '.$actor);$verify(null===Workout::get_for_user($run,$owner),'Private land lookup stays owner only');$denied(V::get_for_owner($run));$denied(V::set_for_owner($run,array('visibility'=>'private','route_public'=>false),$nonce,$state['revision']));$denied(Workout::update_metadata($pool,$owner,0,'attack'));$denied(Workout::set_photos($pool,$owner,array()));$denied(Workout::delete($pool,$owner));$denied(Importer::rebuild_workout($pool,$owner));$denied(Manual_Workout::save($owner,array()));$denied(Manual_Workout::rebuild($pool,$owner));$denied(Recorded_Workout_Structure::confirm($pool,$owner,'hash','attack'));$denied(Planned_Workout::associate_completed_workout(1,$owner,$pool));$denied(Planned_Workout::clear_completed_workout(1,$owner));$denied(Planned_Structure_Link::attach(1,1,$owner));$denied(Planned_Structure_Link::detach(1,$owner));$denied(Planned_Structure_Link::preserve_recorded_lock(1,$owner));$denied(Importer::import_upload($owner,array()));
  $query=V::query_public($owner,array(),1,1);$verify(3===$query['total']&&3===$query['pages']&&1===count($query['workouts']),'Eligibility precedes pagination');
  $html=Shortcodes::workout(array('id'=>$pool,'title'=>'no'));$verify(false===strpos($html,'SECRET_'),'Shortcode excludes notes/device');$verify(false!==strpos(Shortcodes::workout(array('id'=>$new)),'unavailable'),'Owner/admin public embeds cannot bypass privacy');$verify(false!==strpos(Blocks::render_workout(array('workoutId'=>$new)),'unavailable'),'Dynamic block obeys same policy');
 }
 // Exercise the actual admin POST adapter, including valid attacker CSRF tokens and signed manager context.
 require_once ABSPATH.'wp-admin/includes/screen.php';require_once SWIMLOG_EVALUATION_DIR.'admin/class-admin-workouts.php';set_current_screen('dashboard');
 $die=static function(){return static function(){throw new RuntimeException('expected-http-denial');};};add_filter('wp_die_handler',$die);
 foreach(array($other,$manager)as$actor){wp_set_current_user($actor);
  foreach(array('save_metadata','rebuild','delete','create_post','visibility')as$operation){
   $_GET=array('page'=>'swimlog-workouts','workout_id'=>$pool,'swimmer_id'=>$owner,'_swimlog_swimmer_nonce'=>wp_create_nonce('swimlog_swimmer_selector'));
   $_POST=array('swimlog_workout_action'=>$operation,'notes'=>'ATTACK','_wpnonce'=>wp_create_nonce('swimlog_workout_visibility_'.$pool),'workout_public'=>'1','visibility_revision'=>1);$_REQUEST=array_merge($_GET,$_POST);$blocked=false;
   try{SwimLogEvaluation\Admin_Workouts::handle_workout_actions();}catch(RuntimeException $e){$blocked='expected-http-denial'===$e->getMessage();}
   $verify($blocked,'Admin adapter denies foreign '.$operation.' actor '.$actor);
  }
 }
 wp_set_current_user($owner);$_GET=array('page'=>'swimlog-workouts','workout_id'=>$pool);$_POST=array('swimlog_workout_action'=>'visibility','_wpnonce'=>'invalid','workout_public'=>'1','visibility_revision'=>1);$_REQUEST=array_merge($_GET,$_POST);$blocked=false;
 try{SwimLogEvaluation\Admin_Workouts::handle_workout_actions();}catch(RuntimeException $e){$blocked='expected-http-denial'===$e->getMessage();}$verify($blocked,'Owner admin POST rejects invalid nonce');
 remove_filter('wp_die_handler',$die);$die=null;$_POST=$old_post;$_GET=$old_get;$_REQUEST=$old_request;$GLOBALS['current_screen']=$old_screen;
 wp_set_current_user(0);$server=rest_get_server();REST_API::register();
 foreach(array('GET','POST','PUT','PATCH','DELETE')as$method){$request=new WP_REST_Request($method,'/swimlog/v1/public/workouts/'.$run);$response=rest_do_request($request);$verify(('GET'===$method?200:404)===$response->get_status(),'Public REST '.$method.' read-only contract');}
 $request=new WP_REST_Request('GET','/swimlog/v1/public/workouts/'.$new);$verify(404===rest_do_request($request)->get_status(),'Private public REST lookup indistinguishable from missing');$verify(false===REST_API::permission(),'Existing private REST authentication retained');
 wp_set_current_user($owner);$verify(Workout::get_for_user($run,$owner)!==null,'Owner private lookup retained');$verify(!is_wp_error(Workout::update_metadata($pool,$owner,0,'owner update')),'Owner metadata update retained');
 $post=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>$owner,'post_title'=>'Synthetic visibility dynamic embed','post_content'=>'<!-- wp:swimlog/workout {"workoutId":'.$pool.'} /-->'));
 $verify(!is_wp_error($post)&&$post>0,'Explicit separate post publication');$s=V::get_for_owner($pool);$s=V::set_for_owner($pool,array('visibility'=>'private','route_public'=>false),wp_create_nonce('swimlog_workout_visibility_'.$pool),$s['revision']);$verify(false!==strpos(do_blocks(get_post($post)->post_content),'unavailable'),'Published dynamic block follows revocation');$verify('publish'===get_post_status($post),'Visibility does not change post status');
 update_user_meta($owner,'_standalone_workout_public_'.$run,'1');$verify(true===Workout::delete($run,$owner),'Owner delete succeeds');$verify(!V::is_public($run)&&''===get_user_meta($owner,'_standalone_workout_public_'.$run,true),'Deletion removes visibility and provisional preference');$verify(0===(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE workout_id=%d',$vtable,$run)),'Canonical deletion cleanup');
 // A stale orphan grant must not follow an explicitly reused ID into a newly created record.
 Database::write('insert',$vtable,array('workout_id'=>$run,'owner_id'=>$owner,'visibility'=>'public','route_public'=>1,'revision'=>10,'updated_at_gmt'=>'2026-01-01 00:00:00'));
 $row=(array)$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$wtable,$walk));$row['id']=$run;Database::write('insert',$wtable,$row);$verify(V::created($run,$owner)&&!V::is_public($run),'Newly created reused ID clears orphan grant');
 $verify(true===Workout::delete($run,$owner),'Remove reused fixture');
 V::deleted_user($owner);$verify(0===(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE owner_id=%d',$vtable,$owner)),'User lifecycle removes canonical grants');
 $verify(V::migrate()&&!V::is_public($pool),'Migration retry preserves explicit revocation');
 WP_CLI::success('Canonical workout visibility passed '.$checks.' assertions.');
}finally{
 if($die)remove_filter('wp_die_handler',$die);$_POST=$old_post;$_GET=$old_get;$_REQUEST=$old_request;$GLOBALS['current_screen']=$old_screen;
 if($failure)remove_filter('query',$failure,1);if($post)wp_delete_post($post,true);
 foreach(Database::required_tables()as$table)Database::write('query',$wpdb->prepare('DROP TABLE IF EXISTS %i',Database::table($table)));
 $wpdb->prefix=$original_prefix;update_option('swimlog_db_version',$original_version);if(false===$default)delete_option('swimlog_public_results_default');else update_option('swimlog_public_results_default',$default);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$uid)wp_delete_user($uid);wp_set_current_user($original_user);
}
