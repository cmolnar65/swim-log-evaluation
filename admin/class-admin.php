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
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage swimmer configuration.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$uid = get_current_user_id();
		$saved = false;
		$error = '';
		if ( isset( $_POST['swimlog_privacy_action'] ) && 'save' === sanitize_key( wp_unslash( $_POST['swimlog_privacy_action'] ) ) ) {
			check_admin_referer( 'swimlog_save_privacy' );
			$public = isset( $_POST['public_results'] ) ? '1' : '0';
			$public_routes = isset( $_POST['public_ows_routes'] ) ? '1' : '0';
			$rest_api = isset( $_POST['rest_api_access'] ) ? '1' : '0';
			$birthdate = isset( $_POST['birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['birthdate'] ) ) : '';
			$training_volume_months = isset( $_POST['training_volume_months'] ) ? absint( wp_unslash( $_POST['training_volume_months'] ) ) : 3;
			$training_volume_months = max( 1, min( 24, $training_volume_months ) );
			if ( '' !== $birthdate ) {
				$parsed_birthdate = \DateTimeImmutable::createFromFormat( '!Y-m-d', $birthdate, wp_timezone() );
				$birthdate_errors = \DateTimeImmutable::getLastErrors();
				$valid_birthdate = $parsed_birthdate && ( false === $birthdate_errors || ( 0 === $birthdate_errors['warning_count'] && 0 === $birthdate_errors['error_count'] ) ) && $parsed_birthdate->format( 'Y-m-d' ) === $birthdate;
				$today = new \DateTimeImmutable( 'today', wp_timezone() );
				if ( ! $valid_birthdate || $parsed_birthdate > $today ) {
					$error = __( 'Enter a valid date of birth that is not in the future.', 'chriss-swim-training-progress-evaluation' );
				}
			}
			if ( '' === $error ) {
				if ( '' === $birthdate ) {
					delete_user_meta( $uid, 'swimlog_birthdate' );
				} else {
					update_user_meta( $uid, 'swimlog_birthdate', $birthdate );
				}
				update_user_meta( $uid, 'swimlog_public_results', $public );
				update_user_meta( $uid, 'swimlog_public_ows_routes', $public_routes );
				update_user_meta( $uid, 'swimlog_rest_api_access', $rest_api );
				update_user_meta( $uid, 'swimlog_training_volume_months', $training_volume_months );
				$saved = true;
			}
		}
		$value = get_user_meta( $uid, 'swimlog_public_results', true );
		if ( '' === $value ) {
			$value = get_option( 'swimlog_public_results_default', '0' );
		}
		$birthdate = (string) get_user_meta( $uid, 'swimlog_birthdate', true );
		$public_routes = (string) get_user_meta( $uid, 'swimlog_public_ows_routes', true );
		$rest_api = (string) get_user_meta( $uid, 'swimlog_rest_api_access', true );
		$training_volume_months = (int) get_user_meta( $uid, 'swimlog_training_volume_months', true );
		if ( $training_volume_months < 1 || $training_volume_months > 24 ) {
			$training_volume_months = 3;
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-age-group.php';
		$current_age_group = Age_Group::for_user( $uid );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Swimmer configuration saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_privacy' ); ?>
				<input type="hidden" name="swimlog_privacy_action" value="save">
				<h2><?php esc_html_e( 'Swimmer Information', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="swimlog-birthdate"><?php esc_html_e( 'Date of Birth', 'chriss-swim-training-progress-evaluation' ); ?></label></th>
						<td><input id="swimlog-birthdate" type="date" name="birthdate" value="<?php echo esc_attr( $birthdate ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"><p class="description"><?php esc_html_e( 'Private. Used only to calculate your swimming age group. Your date of birth is not displayed by public swim output.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php if ( $current_age_group ) : ?><p><strong><?php esc_html_e( 'Current Age Group:', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php echo esc_html( $current_age_group['label'] ); ?></p><?php endif; ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="swimlog-training-volume-months"><?php esc_html_e( 'Training Volume Period', 'chriss-swim-training-progress-evaluation' ); ?></label></th>
						<td><input id="swimlog-training-volume-months" type="number" name="training_volume_months" min="1" max="24" step="1" value="<?php echo esc_attr( $training_volume_months ); ?>"> <?php esc_html_e( 'months', 'chriss-swim-training-progress-evaluation' ); ?><p class="description"><?php esc_html_e( 'Pool Training Volume by Stroke uses this rolling period. Choose from 1 to 24 months; the default is 3 months.', 'chriss-swim-training-progress-evaluation' ); ?></p></td>
					</tr>
				</table>
				<hr>
				<h2><?php esc_html_e( 'Privacy', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<p><?php esc_html_e( 'Your swim results are private unless you explicitly enable public results. This controls the public swim shortcodes; it does not change who can manage your workouts in WordPress administration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Public Results', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_results" value="1" <?php checked( $value, '1' ); ?>> <?php esc_html_e( 'Allow my swim results to be displayed by public swim shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><?php esc_html_e( 'Default is OFF. When OFF, you can still preview your own shortcode output while logged in. Administrators can also preview private results, but private results are not made public.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Public Open-Water Routes', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_ows_routes" value="1" <?php checked( $public_routes, '1' ); ?>> <?php esc_html_e( 'Allow GPS route shapes from my open-water workouts to appear in public workout shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><strong><?php esc_html_e( 'Default is OFF.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'GPS routes can reveal where you swim, including start and finish locations. This setting is separate from Public Results and must be enabled explicitly.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'REST API Access', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="rest_api_access" value="1" <?php checked( $rest_api, '1' ); ?>> <?php esc_html_e( 'Allow authenticated read-only access to my swim workout data through the swimlog/v1 REST API.', 'chriss-swim-training-progress-evaluation' ); ?></label> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-api-help' ) ); ?>"><?php esc_html_e( 'API Help / Documentation', 'chriss-swim-training-progress-evaluation' ); ?></a><p class="description"><strong><?php esc_html_e( 'Default is OFF.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'This permission is separate from Public Results and Public Open-Water Routes. API requests are limited to the authenticated swimmer and never expose preserved source files or open-water GPS coordinates/trackpoints.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ) ); ?>
			</form>
		</div>
		<?php
	}
	public function api_help() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) wp_die( esc_html__( 'You do not have permission to view the REST API documentation.', 'chriss-swim-training-progress-evaluation' ) );
		$base = rest_url( 'swimlog/v1' );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'SwimLog REST API Documentation', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'The swimlog/v1 API provides authenticated, read-only access to the currently authenticated swimmer’s normalized workout data. REST API Access must first be enabled on Swimmer Configuration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-privacy' ) ); ?>">&larr; <?php esc_html_e( 'Return to Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
			<h2><?php esc_html_e( 'Authentication and permission', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Requests must authenticate as a WordPress user who has permission to view their own swim results and who has explicitly enabled REST API Access. The API always uses the authenticated WordPress user as the swimmer; a request cannot select another swimmer.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php esc_html_e( 'For external clients, use a WordPress-supported authentication method appropriate to your site, such as an Application Password over HTTPS. Do not place credentials in a public URL.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Routes', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Method', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Endpoint', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Purpose', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><tr><td><code>GET</code></td><td><code><?php echo esc_html( $base . '/workouts' ); ?></code></td><td><?php esc_html_e( 'List the authenticated swimmer’s workouts.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>GET</code></td><td><code><?php echo esc_html( $base . '/workouts/{id}' ); ?></code></td><td><?php esc_html_e( 'Return one owned workout with normalized detail.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr></tbody></table>
			<h2><?php esc_html_e( 'Workout list parameters', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Parameter', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Meaning', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><tr><td><code>page</code></td><td><?php esc_html_e( 'Page number; minimum 1.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>per_page</code></td><td><?php esc_html_e( 'Results per page; default 20, maximum 100.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>workout_type</code></td><td><code>pool</code> / <code>open_water</code></td></tr><tr><td><code>date_from</code></td><td><?php esc_html_e( 'Beginning of the requested date range.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>date_to</code></td><td><?php esc_html_e( 'End of the requested date range.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr></tbody></table>
			<h2><?php esc_html_e( 'Returned data', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Workout summaries include the workout ID/type/timestamps, saved location name, distance and timing values, pool information when applicable, primary stroke, heart-rate/cadence summaries, and recording-device manufacturer/model. A single pool workout also includes normalized laps, lengths, and derived performances. A single open-water workout includes normalized laps, derived performances, and non-geographic analytics/splits.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Privacy exclusions', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'The API does not expose preserved source files or paths, import records, workout notes, open-water latitude/longitude, or GPS trackpoints. Enabling Public Open-Water Routes does not change the REST API: geographic route data remains excluded.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Examples', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><code>GET <?php echo esc_html( $base . '/workouts?per_page=10&workout_type=pool' ); ?></code></p><p><code>GET <?php echo esc_html( $base . '/workouts/123' ); ?></code></p>
			<h2><?php esc_html_e( 'Errors', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Unauthenticated requests, users without the required own-results capability, and swimmers who have not enabled REST API Access are denied by WordPress REST permissions. Requesting a workout that does not belong to the authenticated swimmer returns Workout not found (404).', 'chriss-swim-training-progress-evaluation' ); ?></p>
		</div><?php
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
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to manage swim training settings.','chriss-swim-training-progress-evaluation'));$saved=false;$settings_error=null;$rebuild=null;$rebuild_error=null;
		if(isset($_POST['swimlog_rebuild_action'])&&'rebuild'===sanitize_key(wp_unslash($_POST['swimlog_rebuild_action']))){
			check_admin_referer('swimlog_rebuild_performances');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
			$rebuild=Evaluator::rebuild_all_users();if(is_wp_error($rebuild)){$rebuild_error=$rebuild->get_error_message();$rebuild=null;}
		}
		if(isset($_POST['swimlog_settings_action'])&&'save'===sanitize_key(wp_unslash($_POST['swimlog_settings_action']))){
			check_admin_referer('swimlog_save_settings');
			$course=sanitize_key(wp_unslash($_POST['default_course_unit']??'m'));if(!in_array($course,array('m','yd'),true))$course='m';
			$count=max(1,min(50,absint(wp_unslash($_POST['default_event_count']??5))));
			$types = array();
			if ( isset( $_POST['allowed_upload_types'] ) && is_array( $_POST['allowed_upload_types'] ) ) {
				$submitted_types = array_map( 'sanitize_key', wp_unslash( $_POST['allowed_upload_types'] ) );
				$types = array_values( array_intersect( $submitted_types, array( 'fit', 'csv', 'gpx', 'tcx' ) ) );
			}
			if(!$types)$types=array('fit','csv','gpx','tcx');
			$storage_dir=isset($_POST['private_storage_dir'])?sanitize_text_field(wp_unslash($_POST['private_storage_dir'])):'';
			if(defined('SWIMLOG_PRIVATE_STORAGE_DIR')){
				$storage_dir=trim((string)SWIMLOG_PRIVATE_STORAGE_DIR);
			}else{
				require_once SWIMLOG_EVALUATION_DIR.'includes/class-importer.php';
				if($storage_dir!==''){
					$storage_check=Importer::validate_storage_directory($storage_dir,true);
					if(is_wp_error($storage_check))$settings_error=$storage_check->get_error_message();else $storage_dir=$storage_check;
				}
			}
			if(!$settings_error){
				update_option('swimlog_default_course_unit',$course);update_option('swimlog_default_event_count',$count);update_option('swimlog_allowed_upload_types',array_values(array_unique($types)));
				if(!defined('SWIMLOG_PRIVATE_STORAGE_DIR'))update_option('swimlog_private_storage_dir',$storage_dir);
				// v0.2 preservation remains enabled; destructive uninstall is deliberately not exposed here.
				update_option('swimlog_data_preservation','1');update_option('swimlog_public_results_default','0');$saved=true;
			}
		}
		$course=get_option('swimlog_default_course_unit','m');$count=(int)get_option('swimlog_default_event_count',5);$types=(array)get_option('swimlog_allowed_upload_types',array('fit','csv','gpx','tcx'));
		$storage_locked=defined('SWIMLOG_PRIVATE_STORAGE_DIR');$storage_dir=$storage_locked?trim((string)SWIMLOG_PRIVATE_STORAGE_DIR):trim((string)get_option('swimlog_private_storage_dir',''));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Training Settings','chriss-swim-training-progress-evaluation'); ?></h1><?php if($saved):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?><?php if($settings_error):?><div class="notice notice-error"><p><?php echo esc_html($settings_error); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_save_settings'); ?><input type="hidden" name="swimlog_settings_action" value="save"><h2><?php esc_html_e('Defaults','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation">
		<tr><th><label for="swimlog-default-course"><?php esc_html_e('Default course','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-default-course" name="default_course_unit"><option value="m" <?php selected($course,'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($course,'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select></td></tr>
		<tr><th><label for="swimlog-event-count"><?php esc_html_e('Upcoming event count','chriss-swim-training-progress-evaluation'); ?></label></th><td><input id="swimlog-event-count" type="number" min="1" max="50" name="default_event_count" value="<?php echo esc_attr($count); ?>"></td></tr></table>
		<h2><?php esc_html_e('Upload Controls','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Allowed workout files','chriss-swim-training-progress-evaluation'); ?></th><td><label><input type="checkbox" name="allowed_upload_types[]" value="fit" <?php checked(in_array('fit',$types,true)); ?>> FIT</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="csv" <?php checked(in_array('csv',$types,true)); ?>> CSV</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="gpx" <?php checked(in_array('gpx',$types,true)); ?>> GPX</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="tcx" <?php checked(in_array('tcx',$types,true)); ?>> TCX</label><p class="description"><?php esc_html_e('At least one type must remain enabled. File content is still validated during import.','chriss-swim-training-progress-evaluation'); ?></p></td></tr>
		<tr><th><label for="swimlog-private-storage"><?php esc_html_e('Private Workout Storage Directory','chriss-swim-training-progress-evaluation'); ?></label></th><td><input id="swimlog-private-storage" class="regular-text code" type="text" name="private_storage_dir" value="<?php echo esc_attr($storage_dir); ?>" <?php disabled($storage_locked); ?>><p class="description"><?php esc_html_e('Absolute server filesystem path outside the public web root. WordPress must be able to create or write to this directory. Original FIT, FIT.GZ, ZIP, CSV, GPX, and TCX uploads are preserved here unchanged.','chriss-swim-training-progress-evaluation'); ?></p><?php if($storage_locked):?><p class="description"><strong><?php esc_html_e('This path is locked by SWIMLOG_PRIVATE_STORAGE_DIR in server configuration.','chriss-swim-training-progress-evaluation'); ?></strong></p><?php elseif($storage_dir===''):?><p class="description"><?php esc_html_e('If left blank, the plugin will try to derive a private directory one level above the web document root.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?></td></tr></table>
		<h2><?php esc_html_e('Privacy and Data Preservation','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Public Results default','chriss-swim-training-progress-evaluation'); ?></th><td><strong><?php esc_html_e('OFF','chriss-swim-training-progress-evaluation'); ?></strong><p class="description"><?php esc_html_e('New swimmers remain private until they explicitly enable Public Results on their Privacy page.','chriss-swim-training-progress-evaluation'); ?></p></td></tr><tr><th><?php esc_html_e('Historical data','chriss-swim-training-progress-evaluation'); ?></th><td><strong><?php esc_html_e('Preserve data','chriss-swim-training-progress-evaluation'); ?></strong><p class="description"><?php esc_html_e('Deactivation, normal updates, and derived-performance rebuilds do not delete workout history, original source files, normalized laps or lengths, locations, or events. Data preservation is mandatory in v0.2.','chriss-swim-training-progress-evaluation'); ?></p></td></tr></table>
		<?php submit_button(__('Save Settings','chriss-swim-training-progress-evaluation')); ?></form>
		<hr><h2><?php esc_html_e('Rebuild Derived Performances','chriss-swim-training-progress-evaluation'); ?></h2>
		<p><?php esc_html_e('Recalculate all derived performances and personal-best flags from the authoritative normalized workout history for every swimmer. Use this after evaluator changes or when calculated results need to be regenerated.','chriss-swim-training-progress-evaluation'); ?></p>
		<div class="notice notice-info inline"><p><strong><?php esc_html_e('Historical data is protected.','chriss-swim-training-progress-evaluation'); ?></strong> <?php esc_html_e('This operation replaces only derived performance records. It does not delete or modify original FIT/CSV/GPX/TCX source files, imports, workouts, laps, lengths, locations, events, or swimmer privacy settings.','chriss-swim-training-progress-evaluation'); ?></p></div>
		<?php if($rebuild_error):?><div class="notice notice-error inline"><p><?php echo esc_html($rebuild_error); ?></p></div><?php endif;?>
		<?php if($rebuild):?><div class="notice notice-success inline"><p><?php /* translators: 1: swimmers rebuilt, 2: workouts processed, 3: derived performances generated, 4: previous derived rows replaced. */ echo esc_html(sprintf(__('Rebuild complete: %1$d swimmers, %2$d workouts processed, and %3$d derived performances generated. %4$d previous derived performance rows were replaced.','chriss-swim-training-progress-evaluation'),$rebuild['users'],$rebuild['workouts'],$rebuild['performances'],$rebuild['deleted'])); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_rebuild_performances'); ?><input type="hidden" name="swimlog_rebuild_action" value="rebuild"><?php submit_button(__('Rebuild Derived Performances','chriss-swim-training-progress-evaluation'),'secondary','submit',false,array('onclick'=>"return confirm('".esc_js(__('Rebuild all derived performances and personal-best flags? Historical workout and source data will not be changed.','chriss-swim-training-progress-evaluation'))."');")); ?></form>
		</div><?php
	}
}
