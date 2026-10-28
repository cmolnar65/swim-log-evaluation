<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-race-readiness.php';

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
		$this->submenu( 'swimlog-dashboard', __( 'Calendar', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-calendar', 'calendar' );
		$this->submenu( 'swimlog-dashboard', __( 'Planned Workouts', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-planned-workouts', 'planned_workouts' );
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




	public function dashboard() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-dashboard.php';
		Admin_Dashboard::render();
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

	public function handle_planned_workout_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-planned-workouts.php';
		Admin_Planned_Workouts::handle_actions();
	}

	public function calendar() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to manage the training calendar.', 'chriss-swim-training-progress-evaluation' ) );
		$user_id = Admin_UI::selected_user_id();
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'swimlog_manage_all_users' ) ) wp_die( esc_html__( 'You do not have permission to manage this swimmer calendar.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-blocks.php';
		?><div class="wrap swimlog-admin swimlog-admin-calendar"><h1><?php esc_html_e( 'Swim Training Calendar', 'chriss-swim-training-progress-evaluation' ); ?></h1><?php
		Admin_UI::swimmer_selector( $user_id, 'swimlog-calendar' );
		echo Blocks::render_calendar_for_user( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared calendar renderer returns escaped markup.
		?></div><?php
	}

	public function planned_workouts() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-planned-workouts.php';
		Admin_Planned_Workouts::page();
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
}
