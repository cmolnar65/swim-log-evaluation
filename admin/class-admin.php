<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers the Swim Log wp-admin shell and capability-gated pages. */
final class Admin {
	public function register_menu() {
		add_menu_page(
			__( 'Swim Log', 'swim-log-evaluation' ),
			__( 'Swim Log', 'swim-log-evaluation' ),
			'swimlog_view_own_results',
			'swimlog-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-chart-line',
			56
		);

		$this->submenu( 'swimlog-dashboard', __( 'Dashboard', 'swim-log-evaluation' ), 'swimlog_view_own_results', 'swimlog-dashboard', 'dashboard' );
		$this->submenu( 'swimlog-dashboard', __( 'Workouts', 'swim-log-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-workouts', 'workouts' );
		$this->submenu( 'swimlog-dashboard', __( 'Upload Workout', 'swim-log-evaluation' ), 'swimlog_upload_workouts', 'swimlog-upload', 'upload' );
		$this->submenu( 'swimlog-dashboard', __( 'Personal Bests', 'swim-log-evaluation' ), 'swimlog_view_own_results', 'swimlog-personal-bests', 'personal_bests' );
		$this->submenu( 'swimlog-dashboard', __( 'Events', 'swim-log-evaluation' ), 'swimlog_manage_own_events', 'swimlog-events', 'events' );
		$this->submenu( 'swimlog-dashboard', __( 'Locations', 'swim-log-evaluation' ), 'swimlog_manage_own_locations', 'swimlog-locations', 'locations' );
		$this->submenu( 'swimlog-dashboard', __( 'Privacy', 'swim-log-evaluation' ), 'swimlog_view_own_results', 'swimlog-privacy', 'privacy' );

		if ( current_user_can( 'swimlog_manage_settings' ) ) {
			$this->submenu( 'swimlog-dashboard', __( 'Shortcodes', 'swim-log-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcodes', 'shortcodes' );
			$this->submenu( 'swimlog-dashboard', __( 'Settings', 'swim-log-evaluation' ), 'swimlog_manage_settings', 'swimlog-settings', 'settings' );
		}
	}

	private function selected_user_id(){
		$uid=get_current_user_id();if(current_user_can('swimlog_manage_all_users')&&!empty($_GET['swimmer_id'])){$requested=absint($_GET['swimmer_id']);if($requested&&get_userdata($requested))$uid=$requested;}return$uid;
	}
	private function swimmer_selector($user_id,$page){
		if(!current_user_can('swimlog_manage_all_users'))return;$users=get_users(array('fields'=>array('ID','display_name'),'orderby'=>'display_name'));?><form method="get" class="swimlog-swimmer-selector"><input type="hidden" name="page" value="<?php echo esc_attr($page); ?>"><label for="swimlog-swimmer"><?php esc_html_e('Swimmer','swim-log-evaluation'); ?></label> <select id="swimlog-swimmer" name="swimmer_id"><?php foreach($users as$u):?><option value="<?php echo esc_attr($u->ID); ?>" <?php selected($user_id,$u->ID); ?>><?php echo esc_html($u->display_name.' (#'.$u->ID.')'); ?></option><?php endforeach;?></select> <?php submit_button(__('View','swim-log-evaluation'),'secondary','',false); ?></form><?php
	}
	private function admin_css(){?><style>.swimlog-admin .swimlog-table-wrap{overflow-x:auto}.swimlog-admin .swimlog-swimmer-selector{margin:12px 0 18px}.swimlog-admin .swimlog-swimmer-selector label{font-weight:600}.swimlog-admin .swimlog-match-grid{display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:16px}.swimlog-admin .swimlog-actions{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:782px){.swimlog-admin .swimlog-match-grid{grid-template-columns:1fr}.swimlog-admin table.widefat{min-width:680px}.swimlog-admin .swimlog-table-wrap{margin-right:0}.swimlog-admin input[type=date],.swimlog-admin select{max-width:100%}}</style><?php}
	private function submenu( $parent, $title, $capability, $slug, $method ) {
		add_submenu_page( $parent, $title, $title, $capability, $slug, array( $this, $method ) );
	}

	private function page( $title, $description, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}
		?>
		<div class="wrap swimlog-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	public function dashboard() {
		if(!current_user_can('swimlog_view_own_results'))wp_die(esc_html__('You do not have permission to view these results.','swim-log-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-event.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';
		$user_id=$this->selected_user_id();$this->admin_css();$this->swimmer_selector($user_id,'swimlog-dashboard');$course=sanitize_key($_GET['course']??get_option('swimlog_default_course_unit','m'));if(!in_array($course,array('m','yd'),true))$course='m';
		$recent=Workout::recent_for_user($user_id,5);$latest=$recent[0]??null;$pbs=Records::personal_bests($user_id,$course);$event_count=max(1,min(50,(int)get_option('swimlog_default_event_count',5)));$events=Event::upcoming_for_user($user_id,$event_count);
		$strokes=array('FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'),'UNKNOWN'=>__('Unknown','swim-log-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Log Dashboard','swim-log-evaluation'); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-upload')); ?>"><?php esc_html_e('Upload Workout','swim-log-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Add Event','swim-log-evaluation'); ?></a></p>
		<h2><?php esc_html_e('Latest Swim','swim-log-evaluation'); ?></h2><?php if(!$latest):?><p><?php esc_html_e('No workouts have been imported yet.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><tbody><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><td><?php echo esc_html(wp_date('F j, Y g:i a',strtotime($latest->workout_start))); ?></td></tr><tr><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><td><?php echo esc_html($latest->location_name?:'—'); ?></td></tr><tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><td><?php echo esc_html(null!==$latest->original_distance?$latest->original_distance.' '.$latest->original_distance_unit:($latest->total_distance_m?$latest->total_distance_m.' m':'—')); ?></td></tr><tr><th><?php esc_html_e('Elapsed','swim-log-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($latest->elapsed_time_ms)); ?></td></tr></tbody></table><p><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$latest->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View latest workout','swim-log-evaluation'); ?></a></p><?php endif;?>
		<h2><?php esc_html_e('Personal Best Summary','swim-log-evaluation'); ?></h2><p><a class="button <?php echo $course==='m'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=m')); ?>"><?php esc_html_e('Meters','swim-log-evaluation'); ?></a> <a class="button <?php echo $course==='yd'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=yd')); ?>"><?php esc_html_e('Yards','swim-log-evaluation'); ?></a> <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course='.$course)); ?>"><?php esc_html_e('View all personal bests','swim-log-evaluation'); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Overall','swim-log-evaluation'); ?></th><th><?php esc_html_e('Freestyle','swim-log-evaluation'); ?></th><th><?php esc_html_e('Breaststroke','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach(array(50,100,200,500,800,1500) as $d):?><tr><th><?php echo esc_html($d.' '.$course); ?></th><?php foreach(array('OVERALL','FR','BR') as $s):$p=$pbs[$d][$s]??null;?><td><?php echo $p?esc_html(Workout::format_duration($p->duration_ms)):'—'; ?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
		<h2><?php esc_html_e('Upcoming Events','swim-log-evaluation'); ?></h2><?php if(!$events):?><p><?php esc_html_e('No upcoming events are scheduled.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Event','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Current PB','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($events as $e):$pb=Records::exact_personal_best($user_id,(int)$e->distance_value,$e->course_unit,$e->stroke);?><tr><td><?php echo esc_html(wp_date('F j, Y',strtotime($e->event_date.' 12:00:00'))); ?><?php if($e->event_time):?><br><small><?php echo esc_html(wp_date(get_option('time_format','g:i a'),strtotime($e->event_date.' '.$e->event_time))); ?></small><?php endif;?></td><td><?php echo esc_html($e->event_name); ?><?php if($e->location_name):?><br><small><?php echo esc_html($e->location_name); ?></small><?php endif;?></td><td><?php echo esc_html($e->distance_value.' '.$e->course_unit); ?></td><td><?php echo esc_html($strokes[$e->stroke]??$e->stroke); ?></td><td><?php echo $pb?esc_html(Workout::format_duration($pb->duration_ms)):'—'; ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		<h2><?php esc_html_e('Recent Workouts','swim-log-evaluation'); ?></h2><?php if(!$recent):?><p><?php esc_html_e('No workout history is available yet.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Elapsed','swim-log-evaluation'); ?></th><th><?php esc_html_e('Action','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($recent as $w):?><tr><td><?php echo esc_html(wp_date('M j, Y',strtotime($w->workout_start))); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','swim-log-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		</div><?php
	}
	public function workouts() {
		if(!current_user_can('swimlog_manage_own_workouts')) wp_die(esc_html__('You do not have permission to access this Swim Log page.','swim-log-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-location.php';
		$user_id=$this->selected_user_id();$this->admin_css();$this->swimmer_selector($user_id,'swimlog-workouts'); $detail=absint($_GET['workout_id']??0);
		$strokes=array('FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'),'UNKNOWN'=>__('Unknown','swim-log-evaluation'));

		if($detail){
			$w=Workout::get_for_user($detail,$user_id);
			if(!$w) wp_die(esc_html__('Workout not found.','swim-log-evaluation'));
			$lengths=Workout::lengths($detail,$user_id); $perfs=Workout::performances($detail,$user_id); $imports=Workout::imports($detail,$user_id);
			?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Workout Details','swim-log-evaluation'); ?></h1><p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-workouts')); ?>">&larr; <?php esc_html_e('Back to Workouts','swim-log-evaluation'); ?></a></p>
			<table class="widefat striped"><tbody>
			<tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><td><?php echo esc_html(wp_date('F j, Y g:i a',strtotime($w->workout_start))); ?></td></tr>
			<tr><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><td><?php echo esc_html($w->location_name?:'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td></tr>
			<tr><th><?php esc_html_e('Pool','swim-log-evaluation'); ?></th><td><?php echo esc_html(null!==$w->original_pool_length?$w->original_pool_length.' '.$w->pool_length_unit:'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Elapsed','swim-log-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td></tr>
			<tr><th><?php esc_html_e('Primary stroke','swim-log-evaluation'); ?></th><td><?php echo esc_html($strokes[$w->primary_stroke]??($w->primary_stroke?:'—')); ?></td></tr>
			</tbody></table>
			<h2><?php esc_html_e('Workout Performances','swim-log-evaluation'); ?></h2>
			<?php if(!$perfs):?><p><?php esc_html_e('No evaluated performances are stored for this workout yet.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Time','swim-log-evaluation'); ?></th><th><?php esc_html_e('Current PB','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($perfs as $p):?><tr><td><?php echo esc_html($p->distance_value.' '.$p->course_unit); ?></td><td><?php echo esc_html($strokes[$p->stroke]??$p->stroke); ?></td><td><?php echo esc_html(Workout::format_duration($p->duration_ms)); ?></td><td><?php echo $p->is_personal_best?esc_html__('Yes','swim-log-evaluation'):'—'; ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<h2><?php esc_html_e('Source Imports','swim-log-evaluation'); ?></h2><?php if(!$imports):?><p><?php esc_html_e('No source import records are attached.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Type','swim-log-evaluation'); ?></th><th><?php esc_html_e('Original file','swim-log-evaluation'); ?></th><th><?php esc_html_e('Status','swim-log-evaluation'); ?></th><th><?php esc_html_e('Imported','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($imports as $i):?><tr><td><?php echo esc_html(strtoupper($i->source_type)); ?></td><td><?php echo esc_html($i->original_filename); ?></td><td><?php echo esc_html($i->import_status); ?></td><td><?php echo esc_html(wp_date('F j, Y g:i a',strtotime($i->imported_at))); ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<h2><?php esc_html_e('Normalized Lengths and Rest','swim-log-evaluation'); ?></h2><?php if(!$lengths):?><p><?php esc_html_e('No normalized length records are stored.','swim-log-evaluation'); ?></p><?php else:?><details><summary><?php echo esc_html(sprintf(__('Show %d length/rest records','swim-log-evaluation'),count($lengths))); ?></summary><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e('Type','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Time','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($lengths as $l):?><tr><td><?php echo esc_html($l->sequence_no); ?></td><td><?php echo esc_html($l->length_type); ?></td><td><?php echo esc_html($l->distance_m.' m'); ?></td><td><?php echo esc_html($strokes[$l->stroke]??($l->stroke?:'—')); ?></td><td><?php echo esc_html(Workout::format_duration($l->elapsed_time_ms)); ?></td></tr><?php endforeach;?></tbody></table></details><?php endif;?>
			</div><?php return;
		}

		$filters=array('date_from'=>sanitize_text_field($_GET['date_from']??''),'date_to'=>sanitize_text_field($_GET['date_to']??''),'location_id'=>absint($_GET['location_id']??0),'course_unit'=>sanitize_key($_GET['course_unit']??''),'stroke'=>strtoupper(sanitize_key($_GET['stroke']??'')));
		$paged=max(1,absint($_GET['paged']??1)); $result=Workout::query_for_user($user_id,$filters,$paged,20); $locations=Location::all_for_user($user_id);
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Workouts','swim-log-evaluation'); ?></h1><p><?php esc_html_e('Browse your normalized swim workout history.','swim-log-evaluation'); ?></p>
		<form method="get"><input type="hidden" name="page" value="swimlog-workouts"><label><?php esc_html_e('From','swim-log-evaluation'); ?> <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"></label> <label><?php esc_html_e('To','swim-log-evaluation'); ?> <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>"></label> <select name="location_id"><option value="0"><?php esc_html_e('All locations','swim-log-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($filters['location_id'],$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select> <select name="course_unit"><option value=""><?php esc_html_e('All courses','swim-log-evaluation'); ?></option><option value="m" <?php selected($filters['course_unit'],'m'); ?>><?php esc_html_e('Meters','swim-log-evaluation'); ?></option><option value="yd" <?php selected($filters['course_unit'],'yd'); ?>><?php esc_html_e('Yards','swim-log-evaluation'); ?></option></select> <select name="stroke"><option value=""><?php esc_html_e('All strokes','swim-log-evaluation'); ?></option><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($filters['stroke'],$code); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select> <?php submit_button(__('Filter','swim-log-evaluation'),'secondary','',false); ?></form>
		<?php if(!$result['rows']):?><p><?php esc_html_e('No workouts found. Upload a FIT or CSV workout to begin your history.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Course','swim-log-evaluation'); ?></th><th><?php esc_html_e('Elapsed','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Action','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($result['rows'] as $w):?><tr><td><?php echo esc_html(wp_date('F j, Y g:i a',strtotime($w->workout_start))); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html($w->pool_length_unit?:'—'); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><?php echo esc_html($strokes[$w->primary_stroke]??($w->primary_stroke?:'—')); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','swim-log-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table>
		<?php if($result['pages']>1):?><p class="tablenav-pages"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$result['pages']))); ?></p><?php endif; endif;?></div><?php
	}
	public function upload() {
		if(!current_user_can('swimlog_upload_workouts'))wp_die(esc_html__('You do not have permission to upload workouts.','swim-log-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-location.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-fit-importer.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-csv-importer.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-importer.php';
		$user_id=get_current_user_id();$locations=Location::all_for_user($user_id);$results=array();$errors=array();$pending=array();

		if(isset($_POST['swimlog_match_action'])&&$_POST['swimlog_match_action']==='resolve'){
			check_admin_referer('swimlog_resolve_match');$import_id=absint($_POST['import_id']??0);$choice=sanitize_key($_POST['match_choice']??'');$loc=absint($_POST['location_id']??0);
			$res=Importer::resolve_pending($import_id,$user_id,$choice,$loc);if(is_wp_error($res))$errors[]=$res->get_error_message();else$results[]=$res;
		}
		if(isset($_POST['swimlog_upload_action'])&&'import'===$_POST['swimlog_upload_action']){
			check_admin_referer('swimlog_upload_workout');$loc=absint($_POST['location_id']??0);$files=$_FILES['workout_files']??array();
			if(empty($files['name']))$errors[]=__('Choose at least one FIT or CSV file.','swim-log-evaluation');
			else{$items=array();foreach((array)$files['name'] as $i=>$name){$items[]=array('name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0);}usort($items,function($x,$y){return(strtolower(pathinfo($x['name'],PATHINFO_EXTENSION))==='fit'?-1:1);});
				foreach($items as $file){$res=Importer::import_upload($user_id,$file,$loc);if(is_wp_error($res))$errors[]=$file['name'].': '.$res->get_error_message();elseif(!empty($res['pending']))$pending[]=$res;else$results[]=$res;}
			}
		}
		foreach(Importer::pending_all_for_user($user_id) as $row){$already=false;foreach($pending as $p){if((int)$p['import_id']===(int)$row->id){$already=true;break;}}if(!$already)$pending[]=array('pending'=>true,'import_id'=>(int)$row->id,'candidate_workout_id'=>(int)$row->workout_id,'source'=>$row->source_type,'workout'=>null);}
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Upload Workout','swim-log-evaluation'); ?></h1>
		<p><?php esc_html_e('Upload FORM CSV files, FIT pool-swim files, or both files from the same workout. High-confidence matches attach automatically. Probable matches require your confirmation before any workout history is changed.','swim-log-evaluation'); ?></p>
		<?php foreach($errors as $e):?><div class="notice notice-error"><p><?php echo esc_html($e); ?></p></div><?php endforeach;?>
		<?php foreach($results as $res):?><div class="notice notice-success"><p><?php echo esc_html($res['attached']?__('Source file attached to an existing matching workout.','swim-log-evaluation'):__('Workout imported successfully.','swim-log-evaluation')); ?> <a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$res['workout_id']),admin_url('admin.php'))); ?>"><?php esc_html_e('View workout','swim-log-evaluation'); ?></a></p></div><?php endforeach;?>
		<?php foreach($pending as $p):
			$row=Importer::pending_for_user($p['import_id'],$user_id);if(!$row)continue;$nw=$p['workout'];$review_error='';
			if(!$nw){if(!is_file($row->stored_path)){$review_error=__('The preserved source file is missing. This pending review cannot be completed until the source is restored.','swim-log-evaluation');}else{$parser=$row->source_type==='fit'?new FIT_Importer():new CSV_Importer();$reparsed=$parser->parse($row->stored_path);if(is_wp_error($reparsed))$review_error=sprintf(__('The preserved %1$s source could not be reparsed: %2$s','swim-log-evaluation'),strtoupper($row->source_type),$reparsed->get_error_message());else{$valid=Importer::validate($reparsed);if(is_wp_error($valid))$review_error=$valid->get_error_message();else$nw=$reparsed['workout'];}}}
			$saved_location=Importer::pending_location($user_id,$p['import_id']);
			?><div class="notice notice-warning inline swimlog-match-review"><h2><?php esc_html_e('Possible matching workout','swim-log-evaluation'); ?></h2>
			<p><strong><?php echo esc_html(sprintf(__('Uploaded source: %s','swim-log-evaluation'),strtoupper($row->source_type))); ?></strong> <?php echo esc_html(sprintf(__('Preserved for review on %s.','swim-log-evaluation'),wp_date('F j, Y g:i a',strtotime($row->imported_at)))); ?></p>
			<?php if($review_error):?><div class="notice notice-error inline"><p><?php echo esc_html($review_error); ?></p><?php if($row->error_message):?><p><?php echo esc_html(sprintf(__('Last resolution error: %s','swim-log-evaluation'),$row->error_message)); ?></p><?php endif;?></div><?php continue;endif;?>
			<p><?php esc_html_e('This source may belong to the existing workout below. Compare the values before choosing. Nothing is attached until you confirm.','swim-log-evaluation'); ?></p>
			<?php $start_delta=abs(strtotime($nw['workout_start'])-strtotime($row->candidate_start));$distance_delta=abs((float)($nw['total_distance_m']??0)-(float)$row->candidate_distance_m);$elapsed_delta=abs((int)($nw['elapsed_time_ms']??0)-(int)$row->candidate_elapsed_ms); ?>
			<div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Field','swim-log-evaluation'); ?></th><th><?php esc_html_e('Existing workout','swim-log-evaluation'); ?></th><th><?php esc_html_e('Uploaded source','swim-log-evaluation'); ?></th><th><?php esc_html_e('Difference','swim-log-evaluation'); ?></th></tr></thead><tbody>
			<tr><th><?php esc_html_e('Start','swim-log-evaluation'); ?></th><td><?php echo esc_html(wp_date('F j, Y g:i:s a',strtotime($row->candidate_start))); ?></td><td><?php echo esc_html(wp_date('F j, Y g:i:s a',strtotime($nw['workout_start']))); ?></td><td><?php echo esc_html(number_format_i18n($start_delta).' s'); ?></td></tr>
			<tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><td><?php echo esc_html($row->candidate_original_distance.' '.$row->candidate_distance_unit); ?></td><td><?php echo esc_html($nw['original_distance'].' '.$nw['original_distance_unit']); ?></td><td><?php echo esc_html(number_format_i18n($distance_delta,3).' m'); ?></td></tr>
			<tr><th><?php esc_html_e('Pool','swim-log-evaluation'); ?></th><td><?php echo esc_html($row->candidate_pool_length.' '.$row->candidate_course); ?></td><td><?php echo esc_html($nw['original_pool_length'].' '.$nw['pool_length_unit']); ?></td><td><?php echo esc_html(($row->candidate_course===$nw['pool_length_unit'])?__('Same course','swim-log-evaluation'):__('Different course','swim-log-evaluation')); ?></td></tr>
			<tr><th><?php esc_html_e('Elapsed','swim-log-evaluation'); ?></th><td><?php echo esc_html(number_format((int)$row->candidate_elapsed_ms/1000,3).' s'); ?></td><td><?php echo esc_html(number_format((int)$nw['elapsed_time_ms']/1000,3).' s'); ?></td><td><?php echo esc_html(number_format($elapsed_delta/1000,3).' s'); ?></td></tr></tbody></table></div>
			<?php if($row->error_message):?><div class="notice notice-error inline"><p><?php echo esc_html(sprintf(__('Last resolution attempt: %s','swim-log-evaluation'),$row->error_message)); ?></p></div><?php endif;?>
			<form method="post" style="margin-top:12px"><?php wp_nonce_field('swimlog_resolve_match'); ?><input type="hidden" name="swimlog_match_action" value="resolve"><input type="hidden" name="import_id" value="<?php echo esc_attr($p['import_id']); ?>">
			<p><label for="swimlog-pending-location-<?php echo esc_attr($p['import_id']); ?>"><strong><?php esc_html_e('Location','swim-log-evaluation'); ?></strong></label> <select id="swimlog-pending-location-<?php echo esc_attr($p['import_id']); ?>" name="location_id"><option value="0"><?php esc_html_e('No location selected','swim-log-evaluation'); ?></option><?php foreach($locations as $review_location):?><option value="<?php echo esc_attr($review_location->id); ?>" <?php selected($saved_location,$review_location->id); ?>><?php echo esc_html($review_location->name); ?></option><?php endforeach;?></select></p>
			<div class="swimlog-actions"><button class="button button-primary" name="match_choice" value="attach"><?php esc_html_e('Attach to existing workout','swim-log-evaluation'); ?></button><button class="button" name="match_choice" value="separate"><?php esc_html_e('Import as separate workout','swim-log-evaluation'); ?></button></div>
			<p class="description"><?php esc_html_e('Attach combines this source with the existing workout. Import separately creates another workout. The preserved source remains pending if resolution fails.','swim-log-evaluation'); ?></p></form></div><?php endforeach;?>
		<form method="post" enctype="multipart/form-data"><?php wp_nonce_field('swimlog_upload_workout'); ?><input type="hidden" name="swimlog_upload_action" value="import"><table class="form-table" role="presentation"><tr><th><label for="swimlog-workout-files"><?php esc_html_e('Workout files','swim-log-evaluation'); ?></label></th><td><input required id="swimlog-workout-files" type="file" name="workout_files[]" accept=".fit,.csv" multiple><p class="description"><?php esc_html_e('FIT and CSV only. Maximum 10 MB per file. CSV support in this version is limited to FORM swim-export CSV files.','swim-log-evaluation'); ?></p></td></tr>
		<tr><th><label for="swimlog-upload-location"><?php esc_html_e('Location','swim-log-evaluation'); ?></label></th><td><select id="swimlog-upload-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','swim-log-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select></td></tr></table><?php submit_button(__('Upload and Import','swim-log-evaluation')); ?></form></div><?php
	}
	public function personal_bests() {
		if(!current_user_can('swimlog_view_own_results'))wp_die(esc_html__('You do not have permission to view these results.','swim-log-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
		$user_id=$this->selected_user_id();$this->admin_css();$this->swimmer_selector($user_id,'swimlog-personal-bests');$course=sanitize_key($_GET['course']??get_option('swimlog_default_course_unit','m'));if(!in_array($course,array('m','yd'),true))$course='m';$pbs=Records::personal_bests($user_id,$course);$history_distance=absint($_GET['history_distance']??0);$history_stroke=strtoupper(sanitize_key($_GET['history_stroke']??''));$progression=($history_distance&&in_array($history_stroke,array('FR','BR','BACK','FLY','MIXED'),true))?Records::progression($user_id,$history_distance,$course,$history_stroke):array();
		$cols=array('OVERALL'=>__('Overall','swim-log-evaluation'),'FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Personal Bests','swim-log-evaluation'); ?></h1><p><a class="button <?php echo $course==='m'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course=m')); ?>"><?php esc_html_e('Meters','swim-log-evaluation'); ?></a> <a class="button <?php echo $course==='yd'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course=yd')); ?>"><?php esc_html_e('Yards','swim-log-evaluation'); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><?php foreach($cols as $label):?><th><?php echo esc_html($label); ?></th><?php endforeach;?></tr></thead><tbody>
		<?php foreach(Evaluator::DISTANCES as $d):?><tr><th><?php echo esc_html($d.' '.$course); ?></th><?php foreach($cols as $code=>$label):$p=$pbs[$d][$code]??null;?><td><?php if($p):?><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$p->workout_id),admin_url('admin.php'))); ?>"><?php echo esc_html(Workout::format_duration($p->duration_ms)); ?></a><br><small><?php echo esc_html(wp_date('M j, Y',strtotime($p->achieved_at))); ?></small><?php else:?>&mdash;<?php endif;?></td><?php endforeach;?></tr><?php endforeach;?>
		</tbody></table>
		<h2><?php esc_html_e('PB Progression','swim-log-evaluation'); ?></h2><form method="get"><input type="hidden" name="page" value="swimlog-personal-bests"><input type="hidden" name="course" value="<?php echo esc_attr($course); ?>"><?php if(current_user_can('swimlog_manage_all_users')):?><input type="hidden" name="swimmer_id" value="<?php echo esc_attr($user_id); ?>"><?php endif;?><select name="history_distance"><option value="0"><?php esc_html_e('Distance','swim-log-evaluation'); ?></option><?php foreach(Evaluator::DISTANCES as$d):?><option value="<?php echo esc_attr($d); ?>" <?php selected($history_distance,$d); ?>><?php echo esc_html($d.' '.$course); ?></option><?php endforeach;?></select> <select name="history_stroke"><option value=""><?php esc_html_e('Stroke','swim-log-evaluation'); ?></option><?php foreach(array('FR'=>'Freestyle','BR'=>'Breaststroke','BACK'=>'Backstroke','FLY'=>'Butterfly','MIXED'=>'Mixed') as$code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($history_stroke,$code); ?>><?php echo esc_html__($label,'swim-log-evaluation'); ?></option><?php endforeach;?></select> <?php submit_button(__('Show progression','swim-log-evaluation'),'secondary','',false); ?></form>
		<?php if($history_distance&&$history_stroke):?><?php if(!$progression):?><p><?php esc_html_e('No progression records found for that distance and stroke.','swim-log-evaluation'); ?></p><?php else:?><div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Time','swim-log-evaluation'); ?></th><th><?php esc_html_e('Workout','swim-log-evaluation'); ?></th></tr></thead><tbody><?php foreach($progression as$p):?><tr><td><?php echo esc_html(wp_date('F j, Y',strtotime($p->achieved_at))); ?></td><td><?php echo esc_html(Workout::format_duration($p->duration_ms)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$p->workout_id,'swimmer_id'=>$user_id),admin_url('admin.php'))); ?>"><?php esc_html_e('View workout','swim-log-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?><?php endif;?>
		</div><?php
	}
	public function events() {
		if ( ! current_user_can( 'swimlog_manage_own_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$error = null;
		if ( isset( $_POST['swimlog_event_action'] ) && 'save' === $_POST['swimlog_event_action'] ) {
			check_admin_referer( 'swimlog_save_event' );
			$id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
			$result = Event::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) $error = $result->get_error_message();
			else { wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','saved'=>1 ), admin_url('admin.php') ) ); exit; }
		}
		if ( isset( $_POST['swimlog_event_action'] ) && 'delete' === $_POST['swimlog_event_action'] ) {
			check_admin_referer( 'swimlog_delete_event' );
			$result=Event::delete(absint($_POST['event_id'] ?? 0),$user_id);
			if(is_wp_error($result)||!$result) $error=is_wp_error($result)?$result->get_error_message():__( 'The event could not be deleted.', 'swim-log-evaluation' );
			else { wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','deleted'=>1 ), admin_url('admin.php') ) ); exit; }
		}

		$view=sanitize_key($_GET['view'] ?? 'upcoming');
		if(!in_array($view,array('upcoming','past','all'),true)) $view='upcoming';
		$edit_id=absint($_GET['edit'] ?? 0);
		$editing=$edit_id?Event::get_for_user($edit_id,$user_id):null;
		$events=Event::all_for_user($user_id,$view);
		$locations=Location::all_for_user($user_id);
		$strokes=array('FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'));
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e('Events','swim-log-evaluation'); ?></h1>
			<p><?php esc_html_e('Manage upcoming and historical swim events. Past events remain in your history.','swim-log-evaluation'); ?></p>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event saved.','swim-log-evaluation'); ?></p></div><?php endif; ?>
			<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event deleted.','swim-log-evaluation'); ?></p></div><?php endif; ?>
			<?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

			<h2><?php echo $editing?esc_html__('Edit Event','swim-log-evaluation'):esc_html__('Add Event','swim-log-evaluation'); ?></h2>
			<form method="post"><?php wp_nonce_field('swimlog_save_event'); ?><input type="hidden" name="swimlog_event_action" value="save"><input type="hidden" name="event_id" value="<?php echo esc_attr($editing?$editing->id:0); ?>">
			<table class="form-table" role="presentation">
			<tr><th><label for="swimlog-event-name"><?php esc_html_e('Event name','swim-log-evaluation'); ?></label></th><td><input required class="regular-text" maxlength="191" id="swimlog-event-name" name="event_name" value="<?php echo esc_attr($editing?$editing->event_name:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-date"><?php esc_html_e('Date','swim-log-evaluation'); ?></label></th><td><input required type="date" id="swimlog-event-date" name="event_date" value="<?php echo esc_attr($editing?$editing->event_date:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-time"><?php esc_html_e('Time','swim-log-evaluation'); ?></label></th><td><input type="time" id="swimlog-event-time" name="event_time" value="<?php echo esc_attr($editing&&$editing->event_time?substr($editing->event_time,0,5):''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-location"><?php esc_html_e('Location','swim-log-evaluation'); ?></label></th><td><select id="swimlog-event-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','swim-log-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($editing?$editing->location_id:0,$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-distance"><?php esc_html_e('Distance','swim-log-evaluation'); ?></label></th><td><select required id="swimlog-event-distance" name="distance_value"><?php foreach(Event::DISTANCES as $d):?><option value="<?php echo esc_attr($d); ?>" <?php selected($editing?$editing->distance_value:0,$d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select> <select required name="course_unit"><option value="m" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'m'); ?>><?php esc_html_e('Meters','swim-log-evaluation'); ?></option><option value="yd" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'yd'); ?>><?php esc_html_e('Yards','swim-log-evaluation'); ?></option></select></td></tr>
			<tr><th><label for="swimlog-event-stroke"><?php esc_html_e('Stroke','swim-log-evaluation'); ?></label></th><td><select required id="swimlog-event-stroke" name="stroke"><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($editing?$editing->stroke:'FR',$code); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-notes"><?php esc_html_e('Notes','swim-log-evaluation'); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-event-notes" name="notes"><?php echo esc_textarea($editing?$editing->notes:''); ?></textarea></td></tr>
			</table><?php submit_button($editing?__('Update Event','swim-log-evaluation'):__('Add Event','swim-log-evaluation')); ?><?php if($editing):?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Cancel','swim-log-evaluation'); ?></a><?php endif; ?></form>

			<h2><?php esc_html_e('Your Events','swim-log-evaluation'); ?></h2>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=upcoming')); ?>"><?php esc_html_e('Upcoming','swim-log-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=past')); ?>"><?php esc_html_e('Past','swim-log-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=all')); ?>"><?php esc_html_e('All','swim-log-evaluation'); ?></a></p>
			<?php if(empty($events)):?><p><?php esc_html_e('No events found for this view.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Event','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><th><?php esc_html_e('Actions','swim-log-evaluation'); ?></th></tr></thead><tbody>
			<?php foreach($events as $event): $locname='—'; foreach($locations as $loc){if((int)$loc->id===(int)$event->location_id){$locname=$loc->name;break;}} ?><tr><td><?php echo esc_html(wp_date('F j, Y',strtotime($event->event_date.' 12:00:00'))); ?><?php if($event->event_time) echo '<br>'.esc_html(wp_date(get_option('time_format','g:i a'),strtotime($event->event_date.' '.$event->event_time))); ?></td><td><?php echo esc_html($event->event_name); ?></td><td><?php echo esc_html($event->distance_value.' '.$event->course_unit); ?></td><td><?php echo esc_html($strokes[$event->stroke]??$event->stroke); ?></td><td><?php echo esc_html($locname); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-events','view'=>$view,'edit'=>$event->id),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit','swim-log-evaluation'); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field('swimlog_delete_event'); ?><input type="hidden" name="swimlog_event_action" value="delete"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><button class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this event?','swim-log-evaluation')); ?>');"><?php esc_html_e('Delete','swim-log-evaluation'); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div><?php
	}
	public function locations() {
		if ( ! current_user_can( 'swimlog_manage_own_locations' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$error = null;

		if ( isset( $_POST['swimlog_location_action'] ) && 'save' === $_POST['swimlog_location_action'] ) {
			check_admin_referer( 'swimlog_save_location' );
			$id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
			$result = Location::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		if ( isset( $_POST['swimlog_location_action'] ) && 'delete' === $_POST['swimlog_location_action'] ) {
			check_admin_referer( 'swimlog_delete_location' );
			$id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
			$result = Location::delete( $id, $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				$error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The location could not be deleted.', 'swim-log-evaluation' );
			} else {
				wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $edit_id ? Location::get_for_user( $edit_id, $user_id ) : null;
		$locations = Location::all_for_user( $user_id );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Locations', 'swim-log-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Manage your pool and swim locations. Editing a location does not change the pool information stored with historical workouts.', 'swim-log-evaluation' ); ?></p>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location saved.', 'swim-log-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location deleted. Historical workout snapshots were preserved.', 'swim-log-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Location', 'swim-log-evaluation' ) : esc_html__( 'Add Location', 'swim-log-evaluation' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_location' ); ?>
				<input type="hidden" name="swimlog_location_action" value="save">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="swimlog-location-name"><?php esc_html_e( 'Name', 'swim-log-evaluation' ); ?></label></th><td><input class="regular-text" required id="swimlog-location-name" name="name" type="text" maxlength="191" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="swimlog-pool-length"><?php esc_html_e( 'Pool length', 'swim-log-evaluation' ); ?></label></th><td><input id="swimlog-pool-length" name="pool_length" type="number" min="0.001" step="0.001" value="<?php echo esc_attr( $editing ? $editing->pool_length : '' ); ?>"> <select name="pool_unit" aria-label="<?php esc_attr_e( 'Pool length unit', 'swim-log-evaluation' ); ?>"><option value=""><?php esc_html_e( 'Select unit', 'swim-log-evaluation' ); ?></option><option value="m" <?php selected( $editing ? $editing->pool_unit : '', 'm' ); ?>><?php esc_html_e( 'Meters', 'swim-log-evaluation' ); ?></option><option value="yd" <?php selected( $editing ? $editing->pool_unit : '', 'yd' ); ?>><?php esc_html_e( 'Yards', 'swim-log-evaluation' ); ?></option></select></td></tr>
					<tr><th scope="row"><label for="swimlog-location-notes"><?php esc_html_e( 'Notes', 'swim-log-evaluation' ); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-location-notes" name="notes"><?php echo esc_textarea( $editing ? $editing->notes : '' ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update Location', 'swim-log-evaluation' ) : __( 'Add Location', 'swim-log-evaluation' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-locations' ) ); ?>"><?php esc_html_e( 'Cancel', 'swim-log-evaluation' ); ?></a><?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Your Locations', 'swim-log-evaluation' ); ?></h2>
			<?php if ( empty( $locations ) ) : ?>
				<p><?php esc_html_e( 'No locations have been added yet.', 'swim-log-evaluation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Notes', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'swim-log-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $locations as $location ) : ?>
					<tr><td><?php echo esc_html( $location->name ); ?></td><td><?php echo null !== $location->pool_length ? esc_html( $location->pool_length . ' ' . $location->pool_unit ) : '&mdash;'; ?></td><td><?php echo esc_html( $location->notes ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-locations', 'edit' => $location->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'swim-log-evaluation' ); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_delete_location' ); ?><input type="hidden" name="swimlog_location_action" value="delete"><input type="hidden" name="location_id" value="<?php echo esc_attr( $location->id ); ?>"><button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this location? Historical workout snapshots will remain unchanged.', 'swim-log-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'swim-log-evaluation' ); ?></button></form></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
	public function shortcodes() {
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to access this Swim Log page.','swim-log-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Shortcodes','swim-log-evaluation'); ?></h1><p><?php esc_html_e('Swim Log public output is display-only and private by default. Without a user attribute, the current page or post author is used. There is no logged-in-viewer fallback.','swim-log-evaluation'); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Shortcode','swim-log-evaluation'); ?></th><th><?php esc_html_e('Purpose','swim-log-evaluation'); ?></th></tr></thead><tbody>
		<tr><td><code>[swimlog_latest]</code></td><td><?php esc_html_e('Latest swim summary.','swim-log-evaluation'); ?></td></tr><tr><td><code>[swimlog_bests]</code></td><td><?php esc_html_e('Native-course personal best table. Supports user, course, stroke, distance, and title attributes.','swim-log-evaluation'); ?></td></tr><tr><td><code>[swimlog_upcoming_events]</code></td><td><?php esc_html_e('Upcoming events with exact matching current PB. Supports user, count, and title attributes.','swim-log-evaluation'); ?></td></tr><tr><td><code>[swimlog_workout id="123"]</code></td><td><?php esc_html_e('One public workout summary and evaluated workout bests. Ownership is derived from the workout.','swim-log-evaluation'); ?></td></tr><tr><td><code>[swimlog_log]</code></td><td><?php esc_html_e('Training log. With no period attribute it shows the current month. Use month="Sep" with optional year="2025" for a monthly workout list, or year="2026" by itself for monthly totals across a year. Meter and yard totals remain separate. Supports user and title attributes.','swim-log-evaluation'); ?></td></tr>
		</tbody></table><h2><?php esc_html_e('Examples','swim-log-evaluation'); ?></h2><p><code>[swimlog_bests course="m" stroke="BR" distance="200"]</code><br><code>[swimlog_upcoming_events count="3" title="no"]</code><br><code>[swimlog_latest user="123"]</code><br><code>[swimlog_log]</code><br><code>[swimlog_log month="Sep"]</code><br><code>[swimlog_log month="Sep" year="2025"]</code><br><code>[swimlog_log year="2026"]</code></p></div><?php
	}
	public function privacy() {
		if(!current_user_can('swimlog_view_own_results'))wp_die(esc_html__('You do not have permission to manage Swim Log privacy.','swim-log-evaluation'));
		$uid=get_current_user_id();$saved=false;
		if(isset($_POST['swimlog_privacy_action'])&&$_POST['swimlog_privacy_action']==='save'){
			check_admin_referer('swimlog_save_privacy');$public=isset($_POST['public_results'])?'1':'0';update_user_meta($uid,'swimlog_public_results',$public);$saved=true;
		}
		$value=get_user_meta($uid,'swimlog_public_results',true);if($value==='')$value=get_option('swimlog_public_results_default','0');
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Log Privacy','swim-log-evaluation'); ?></h1><?php if($saved):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Privacy setting saved.','swim-log-evaluation'); ?></p></div><?php endif;?>
		<p><?php esc_html_e('Your swim results are private unless you explicitly enable public results. This controls the public Swim Log shortcodes; it does not change who can manage your workouts in WordPress administration.','swim-log-evaluation'); ?></p>
		<form method="post"><?php wp_nonce_field('swimlog_save_privacy'); ?><input type="hidden" name="swimlog_privacy_action" value="save"><table class="form-table" role="presentation"><tr><th scope="row"><?php esc_html_e('Public Results','swim-log-evaluation'); ?></th><td><label><input type="checkbox" name="public_results" value="1" <?php checked($value,'1'); ?>> <?php esc_html_e('Allow my swim results to be displayed by Swim Log public shortcodes.','swim-log-evaluation'); ?></label><p class="description"><?php esc_html_e('Default is OFF. When OFF, you can still preview your own shortcode output while logged in. Administrators can also preview private results, but private results are not made public.','swim-log-evaluation'); ?></p></td></tr></table><?php submit_button(__('Save Privacy','swim-log-evaluation')); ?></form></div><?php
	}
	public function settings() {
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to manage Swim Log settings.','swim-log-evaluation'));$saved=false;$rebuild=null;$rebuild_error=null;
		if(isset($_POST['swimlog_rebuild_action'])&&$_POST['swimlog_rebuild_action']==='rebuild'){
			check_admin_referer('swimlog_rebuild_performances');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
			$rebuild=Evaluator::rebuild_all_users();if(is_wp_error($rebuild)){$rebuild_error=$rebuild->get_error_message();$rebuild=null;}
		}
		if(isset($_POST['swimlog_settings_action'])&&$_POST['swimlog_settings_action']==='save'){
			check_admin_referer('swimlog_save_settings');
			$course=sanitize_key($_POST['default_course_unit']??'m');if(!in_array($course,array('m','yd'),true))$course='m';
			$count=max(1,min(50,absint($_POST['default_event_count']??5)));
			$types=array();foreach((array)($_POST['allowed_upload_types']??array()) as$t){$t=sanitize_key($t);if(in_array($t,array('fit','csv'),true))$types[]=$t;}if(!$types)$types=array('fit','csv');
			update_option('swimlog_default_course_unit',$course);update_option('swimlog_default_event_count',$count);update_option('swimlog_allowed_upload_types',array_values(array_unique($types)));
			// v0.1 preservation remains enabled; destructive uninstall is deliberately not exposed here.
			update_option('swimlog_data_preservation','1');update_option('swimlog_public_results_default','0');$saved=true;
		}
		$course=get_option('swimlog_default_course_unit','m');$count=(int)get_option('swimlog_default_event_count',5);$types=(array)get_option('swimlog_allowed_upload_types',array('fit','csv'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Log Settings','swim-log-evaluation'); ?></h1><?php if($saved):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.','swim-log-evaluation'); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_save_settings'); ?><input type="hidden" name="swimlog_settings_action" value="save"><h2><?php esc_html_e('Defaults','swim-log-evaluation'); ?></h2><table class="form-table" role="presentation">
		<tr><th><label for="swimlog-default-course"><?php esc_html_e('Default course','swim-log-evaluation'); ?></label></th><td><select id="swimlog-default-course" name="default_course_unit"><option value="m" <?php selected($course,'m'); ?>><?php esc_html_e('Meters','swim-log-evaluation'); ?></option><option value="yd" <?php selected($course,'yd'); ?>><?php esc_html_e('Yards','swim-log-evaluation'); ?></option></select></td></tr>
		<tr><th><label for="swimlog-event-count"><?php esc_html_e('Upcoming event count','swim-log-evaluation'); ?></label></th><td><input id="swimlog-event-count" type="number" min="1" max="50" name="default_event_count" value="<?php echo esc_attr($count); ?>"></td></tr></table>
		<h2><?php esc_html_e('Upload Controls','swim-log-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Allowed workout files','swim-log-evaluation'); ?></th><td><label><input type="checkbox" name="allowed_upload_types[]" value="fit" <?php checked(in_array('fit',$types,true)); ?>> FIT</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="csv" <?php checked(in_array('csv',$types,true)); ?>> CSV</label><p class="description"><?php esc_html_e('At least one type must remain enabled. File content is still validated during import.','swim-log-evaluation'); ?></p></td></tr></table>
		<h2><?php esc_html_e('Privacy and Data Preservation','swim-log-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Public Results default','swim-log-evaluation'); ?></th><td><strong><?php esc_html_e('OFF','swim-log-evaluation'); ?></strong><p class="description"><?php esc_html_e('New swimmers remain private until they explicitly enable Public Results on their Privacy page.','swim-log-evaluation'); ?></p></td></tr><tr><th><?php esc_html_e('Historical data','swim-log-evaluation'); ?></th><td><strong><?php esc_html_e('Preserve data','swim-log-evaluation'); ?></strong><p class="description"><?php esc_html_e('Deactivation, normal updates, and derived-performance rebuilds do not delete workout history, original source files, normalized laps or lengths, locations, or events. Data preservation is mandatory in v0.1.','swim-log-evaluation'); ?></p></td></tr></table>
		<?php submit_button(__('Save Settings','swim-log-evaluation')); ?></form>
		<hr><h2><?php esc_html_e('Rebuild Derived Performances','swim-log-evaluation'); ?></h2>
		<p><?php esc_html_e('Recalculate all derived performances and personal-best flags from the authoritative normalized workout history for every swimmer. Use this after evaluator changes or when calculated results need to be regenerated.','swim-log-evaluation'); ?></p>
		<div class="notice notice-info inline"><p><strong><?php esc_html_e('Historical data is protected.','swim-log-evaluation'); ?></strong> <?php esc_html_e('This operation replaces only derived performance records. It does not delete or modify original FIT/CSV source files, imports, workouts, laps, lengths, locations, events, or swimmer privacy settings.','swim-log-evaluation'); ?></p></div>
		<?php if($rebuild_error):?><div class="notice notice-error inline"><p><?php echo esc_html($rebuild_error); ?></p></div><?php endif;?>
		<?php if($rebuild):?><div class="notice notice-success inline"><p><?php echo esc_html(sprintf(__('Rebuild complete: %1$d swimmers, %2$d workouts processed, and %3$d derived performances generated. %4$d previous derived performance rows were replaced.','swim-log-evaluation'),$rebuild['users'],$rebuild['workouts'],$rebuild['performances'],$rebuild['deleted'])); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_rebuild_performances'); ?><input type="hidden" name="swimlog_rebuild_action" value="rebuild"><?php submit_button(__('Rebuild Derived Performances','swim-log-evaluation'),'secondary','submit',false,array('onclick'=>"return confirm('".esc_js(__('Rebuild all derived performances and personal-best flags? Historical workout and source data will not be changed.','swim-log-evaluation'))."');")); ?></form>
		</div><?php
	}
}
