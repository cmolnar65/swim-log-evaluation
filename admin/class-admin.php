<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers the swim training wp-admin shell and capability-gated pages. */
final class Admin {
	public function register_menu() {
		add_menu_page(
			__( 'Swim Training', 'chriss-swim-training-progress-evaluation' ),
			__( 'Swim Training', 'chriss-swim-training-progress-evaluation' ),
			'swimlog_view_own_results',
			'swimlog-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-chart-line',
			56
		);

		$this->submenu( 'swimlog-dashboard', __( 'Dashboard', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-dashboard', 'dashboard' );
		$this->submenu( 'swimlog-dashboard', __( 'Workouts', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-workouts', 'workouts' );
		$this->submenu( 'swimlog-dashboard', __( 'Training Volume', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-training-volume', 'training_volume' );
		$this->submenu( 'swimlog-dashboard', __( 'Training Trends', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-training-trends', 'training_trends' );
		$this->submenu( 'swimlog-dashboard', __( 'Pool Analytics', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-pool-analytics', 'pool_analytics' );
		$this->submenu( 'swimlog-dashboard', __( 'Upload Workout', 'chriss-swim-training-progress-evaluation' ), 'swimlog_upload_workouts', 'swimlog-upload', 'upload' );
		$this->submenu( 'swimlog-dashboard', __( 'Add Manual Workout', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-manual-workout', 'manual_workout' );
		$this->submenu( 'swimlog-dashboard', __( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-personal-bests', 'personal_bests' );
		$this->submenu( 'swimlog-dashboard', __( 'Events', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_events', 'swimlog-events', 'events' );
		$this->submenu( 'swimlog-dashboard', __( 'Locations', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_locations', 'swimlog-locations', 'locations' );
		$this->submenu( 'swimlog-dashboard', __( 'Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-privacy', 'privacy' );
		$this->submenu( 'swimlog-dashboard', __( 'Garmin Connect', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-garmin', 'garmin' );
		add_submenu_page( null, __( 'REST API Documentation', 'chriss-swim-training-progress-evaluation' ), __( 'REST API Documentation', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-api-help', array( $this, 'api_help' ) );

		if ( current_user_can( 'swimlog_manage_settings' ) ) {
			$this->submenu( 'swimlog-dashboard', __( 'Shortcodes', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcodes', 'shortcodes' );
			add_submenu_page( null, __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcode-bests', array( $this, 'shortcode_bests' ) );
			foreach(array('latest'=>'Latest','upcoming-events'=>'Upcoming Events','workout'=>'Workout','log'=>'Training Log','progress'=>'Progress')as$slug=>$label)add_submenu_page(null,sprintf(/* translators: %s: shortcode feature name. */ __('%s Shortcode','chriss-swim-training-progress-evaluation'),$label),sprintf(/* translators: %s: shortcode feature name. */ __('%s Shortcode','chriss-swim-training-progress-evaluation'),$label),'swimlog_manage_settings','swimlog-shortcode-'.$slug,array($this,'shortcode_'.str_replace('-','_',$slug)));
			$this->submenu( 'swimlog-dashboard', __( 'Settings', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-settings', 'settings' );
		}
	}

	private function selected_user_id(){
		$uid=get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin swimmer selector; no state is changed.
		if(current_user_can('swimlog_manage_all_users')&&!empty($_GET['swimmer_id'])){$requested=absint(wp_unslash($_GET['swimmer_id']));if($requested&&get_userdata($requested))$uid=$requested;}return$uid;
	}
	private function swimmer_selector($user_id,$page){
		if(!current_user_can('swimlog_manage_all_users'))return;$users=get_users(array('fields'=>array('ID','display_name'),'orderby'=>'display_name'));?><form method="get" class="swimlog-swimmer-selector"><input type="hidden" name="page" value="<?php echo esc_attr($page); ?>"><label for="swimlog-swimmer"><?php esc_html_e('Swimmer','chriss-swim-training-progress-evaluation'); ?></label> <select id="swimlog-swimmer" name="swimmer_id"><?php foreach($users as$u):?><option value="<?php echo esc_attr($u->ID); ?>" <?php selected($user_id,$u->ID); ?>><?php echo esc_html($u->display_name.' (#'.$u->ID.')'); ?></option><?php endforeach;?></select> <?php submit_button(__('View','chriss-swim-training-progress-evaluation'),'secondary','',false); ?></form><?php
	}
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'swimlog' ) ) {
			return;
		}

		$admin_css = SWIMLOG_EVALUATION_DIR . 'assets/css/admin.css';
		wp_enqueue_style(
			'swimlog-admin',
			SWIMLOG_EVALUATION_URL . 'assets/css/admin.css',
			array(),
			is_file( $admin_css ) ? SWIMLOG_EVALUATION_VERSION . '.' . filemtime( $admin_css ) : SWIMLOG_EVALUATION_VERSION
		);
		$admin_js = SWIMLOG_EVALUATION_DIR . 'assets/js/admin.js';
		if ( is_file( $admin_js ) ) {
			wp_enqueue_script(
				'swimlog-admin',
				SWIMLOG_EVALUATION_URL . 'assets/js/admin.js',
				array(),
				SWIMLOG_EVALUATION_VERSION . '.' . filemtime( $admin_js ),
				true
			);
		}
	}
	private function submenu( $parent, $title, $capability, $slug, $method ) {
		add_submenu_page( $parent, $title, $title, $capability, $slug, array( $this, $method ) );
	}

	private function display_measurement( $value, $unit ) {
		if ( null === $value || '' === $value ) return '—';
		$number = (float) $value;
		$display = abs( $number - round( $number ) ) < 0.0005
			? number_format_i18n( $number, 0 )
			: rtrim( rtrim( number_format_i18n( $number, 3 ), '0' ), '.' );
		return $display . ( $unit ? ' ' . $unit : '' );
	}


	private function page( $title, $description, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}
		?>
		<div class="wrap swimlog-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	public function dashboard() {
		if(!current_user_can('swimlog_view_own_results'))wp_die(esc_html__('You do not have permission to view these results.','chriss-swim-training-progress-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-event.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';
		$user_id=$this->selected_user_id();$this->swimmer_selector($user_id,'swimlog-dashboard');
		$course = get_option('swimlog_default_course_unit','m');
		if ( isset( $_GET['course'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only course filter.
			$course = sanitize_key( wp_unslash( $_GET['course'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}if(!in_array($course,array('m','yd'),true))$course='m';
		$recent=Workout::recent_for_user($user_id,5);$latest=$recent[0]??null;$pbs=Records::personal_bests($user_id,$course);$event_count=max(1,min(50,(int)get_option('swimlog_default_event_count',5)));$events=Event::upcoming_for_user($user_id,$event_count);
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'),'UNKNOWN'=>__('Unknown','chriss-swim-training-progress-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Training Dashboard','chriss-swim-training-progress-evaluation'); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-upload')); ?>"><?php esc_html_e('Upload Workout','chriss-swim-training-progress-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-manual-workout')); ?>"><?php esc_html_e('Add Manual Workout','chriss-swim-training-progress-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Add Event','chriss-swim-training-progress-evaluation'); ?></a></p>
		<h2><?php esc_html_e('Latest Swim','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$latest):?><p><?php esc_html_e('No workouts have been imported yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><tbody><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(mysql2date('F j, Y g:i a',$latest->workout_start,false)); ?></td></tr><tr><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($latest->location_name?:'—'); ?></td></tr><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$latest->original_distance?$latest->original_distance.' '.$latest->original_distance_unit:($latest->total_distance_m?$latest->total_distance_m.' m':'—')); ?></td></tr><tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($latest->elapsed_time_ms)); ?></td></tr></tbody></table><p><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$latest->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View latest workout','chriss-swim-training-progress-evaluation'); ?></a></p><?php endif;?>
		<h2><?php esc_html_e('Personal Best Summary','chriss-swim-training-progress-evaluation'); ?></h2><p><a class="button <?php echo $course==='m'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=m')); ?>"><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></a> <a class="button <?php echo $course==='yd'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=yd')); ?>"><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></a> <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course='.$course)); ?>"><?php esc_html_e('View all personal bests','chriss-swim-training-progress-evaluation'); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Overall','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Freestyle','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Breaststroke','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach(array(50,100,200,500,800,1500) as $d):?><tr><th><?php echo esc_html($d.' '.$course); ?></th><?php foreach(array('OVERALL','FR','BR') as $s):$p=$pbs[$d][$s]??null;?><td><?php echo $p?esc_html(Workout::format_duration($p->duration_ms)):'—'; ?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
		<h2><?php esc_html_e('Upcoming Events','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$events):?><p><?php esc_html_e('No upcoming events are scheduled.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('Readiness is descriptive, not predictive. Recent training uses the last 42 days and only exact course/stroke matches.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Event','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Current PB','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent matching volume','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent distance evidence','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($events as $e):$pb=Records::exact_personal_best($user_id,(int)$e->distance_value,$e->course_unit,$e->stroke);$ready=Workout::event_readiness($user_id,$e,Workout::EVENT_READINESS_LOOKBACK_DAYS);?><tr><td><?php echo esc_html(wp_date('F j, Y',(new \DateTimeImmutable($e->event_date.' 12:00:00',wp_timezone()))->getTimestamp(),wp_timezone())); ?><?php if($e->event_time):?><br><small><?php echo esc_html(wp_date( get_option( 'time_format', 'g:i a' ), ( new \DateTimeImmutable( $e->event_date . ' ' . $e->event_time, wp_timezone() ) )->getTimestamp(), wp_timezone() )); ?></small><?php endif;?></td><td><?php echo esc_html($e->event_name); ?><?php if($e->location_name):?><br><small><?php echo esc_html($e->location_name); ?></small><?php endif;?></td><td><?php echo esc_html($e->distance_value.' '.$e->course_unit); ?></td><td><?php echo esc_html($strokes[$e->stroke]??$e->stroke); ?></td><td><?php echo $pb?esc_html(Workout::format_duration($pb->duration_ms)):'—'; ?></td><td><?php if(!empty($ready)):?><?php echo esc_html($this->display_measurement((float)$ready['distance_native'],$e->course_unit)); ?><br><small><?php echo esc_html(sprintf(/* translators: 1: matching workout count, 2: active length count. */ __('%1$d matching workouts / %2$d active lengths','chriss-swim-training-progress-evaluation'),(int)$ready['workout_count'],(int)$ready['length_count'])); ?></small><?php else:?>—<?php endif;?></td><td><?php if(!empty($ready['longest_recent_distance'])):?><strong><?php echo $ready['event_distance_or_longer']?esc_html__('Event distance or longer recorded','chriss-swim-training-progress-evaluation'):esc_html__('Shorter than event distance','chriss-swim-training-progress-evaluation'); ?></strong><br><small><?php echo esc_html(sprintf(/* translators: 1: stroke, 2: distance, 3: course unit, 4: duration. */ __('Longest recent evaluated %1$s: %2$d %3$s in %4$s','chriss-swim-training-progress-evaluation'),$strokes[$e->stroke]??$e->stroke,(int)$ready['longest_recent_distance'],$e->course_unit,Workout::format_duration($ready['longest_recent_duration_ms']))); ?></small><?php else:?><?php esc_html_e('No recent evaluated matching-distance evidence','chriss-swim-training-progress-evaluation'); ?><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		<h2><?php esc_html_e('Recent Workouts','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$recent):?><p><?php esc_html_e('No workout history is available yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Workout #','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Action','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($recent as $w):?><tr><td><?php echo esc_html((int)$w->id); ?></td><td><?php echo esc_html(wp_date('M j, Y',(new \DateTimeImmutable($w->workout_start,wp_timezone()))->getTimestamp(),wp_timezone())); ?></td><td><?php echo esc_html('open_water'===($w->workout_type??'pool')?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','chriss-swim-training-progress-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		</div><?php
	}
	public function workouts() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-workouts.php';
		Admin_Workouts::workouts();
	}
	public function training_volume() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::training_volume();
	}
	public function handle_manual_workout_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-manual-workout.php';
		Admin_Manual_Workout::handle_manual_workout_actions();
	}

	public function manual_workout() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-manual-workout.php';
		Admin_Manual_Workout::manual_workout();
	}

	public function training_trends() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::training_trends();
	}
	public function pool_analytics() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::pool_analytics();
	}
	public function upload() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-workouts.php';
		Admin_Workouts::upload();
	}
	public function personal_bests() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-personal-bests.php';
		Admin_Personal_Bests::personal_bests();
	}

	public function handle_workout_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-workouts.php';
		Admin_Workouts::handle_workout_actions();
	}
	public function handle_location_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::handle_location_actions();
	}


	public function handle_event_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::handle_event_actions();
	}

	public function events() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::events();
	}
	public function locations() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::locations();
	}
	public function shortcodes() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcodes();
	}

	public function shortcode_latest() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_latest();
	}

	public function shortcode_upcoming_events() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_upcoming_events();
	}

	public function shortcode_workout() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_workout();
	}

	public function shortcode_log() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_log();
	}

	public function shortcode_progress() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_progress();
	}

	public function shortcode_bests() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_bests();
	}

	public function privacy() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-settings.php';
		Admin_Settings::privacy();
	}
	public function api_help() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-settings.php';
		Admin_Settings::api_help();
	}
	public function handle_garmin_fit_download() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';
		Admin_Garmin::handle_garmin_fit_download();
	}

	public function garmin() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';
		Admin_Garmin::garmin();
	}

	public function settings() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-settings.php';
		Admin_Settings::settings();
	}