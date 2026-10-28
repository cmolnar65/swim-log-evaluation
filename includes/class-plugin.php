<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin bootstrap. */
final class Plugin {
	public function run() {
		// Database is a foundational dependency for workout, event, planned-workout,
		// calendar, shortcode, block, and administration services. Load it before
		// any of those services can be invoked through WordPress hooks or admin pages.
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-planned-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-structured-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-structured-workout-store.php';
		add_action( 'init', array( 'SwimLogEvaluation\\Structured_Workout_Store', 'register' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-layout-templates.php';
		Layout_Templates::register();
		add_filter( 'upload_mimes', array( $this, 'allow_fit_upload_mime' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_api' ) );
		add_action( 'swimlog_garmin_scheduled_sync', array( $this, 'run_garmin_scheduled_sync' ), 10, 1 );
		if ( is_admin() ) {
			require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin.php';
			$admin = new Admin();
			add_action( 'admin_menu', array( $admin, 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
			add_action( 'admin_post_swimlog_garmin_download_fit', array( $admin, 'handle_garmin_fit_download' ) );
			add_action( 'admin_init', array( $admin, 'handle_workout_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_manual_workout_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_planned_workout_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_structured_workout_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_event_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_location_actions' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-shortcodes.php';
		Shortcodes::register();
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-blocks.php';
		add_action( 'init', array( 'SwimLogEvaluation\\Blocks', 'register' ) );
		add_action( 'wp_ajax_swimlog_calendar_event_result', array( 'SwimLogEvaluation\\Blocks', 'calendar_event_result_action' ) );
		add_action( 'wp_ajax_swimlog_calendar_plan', array( 'SwimLogEvaluation\\Blocks', 'calendar_plan_action' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_calendar_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_calendar_style' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
	}

	public function register_calendar_style() {
		wp_register_style( 'swimlog-calendar', SWIMLOG_EVALUATION_URL . 'assets/css/calendar.css', array(), SWIMLOG_EVALUATION_VERSION );
		wp_localize_script( 'swimlog-calendar-view', 'wpApiSettings', array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
		wp_localize_script( 'swimlog-calendar-view', 'swimlogCalendarSettings', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'planNonce' => wp_create_nonce( 'swimlog_calendar_plan' ), 'eventNonce' => wp_create_nonce( 'swimlog_calendar_event_result' ) ) );
	}

	public function register_rest_api() {
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-rest-api.php';
		REST_API::register();
	}

	public function run_garmin_scheduled_sync( $user_id ) {
		$user_id=absint($user_id);if(!$user_id||'1'!==(string)get_user_meta($user_id,'swimlog_garmin_sync_enabled',true))return;
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-garmin-personal-api.php';
		Garmin_Personal_API::scheduled_sync($user_id);
	}

	public static function set_garmin_sync_schedule( $user_id, $enabled ) {
		$user_id=absint($user_id);$hook='swimlog_garmin_scheduled_sync';$args=array($user_id);$next=wp_next_scheduled($hook,$args);
		if($enabled){update_user_meta($user_id,'swimlog_garmin_sync_enabled','1');if(!$next)wp_schedule_event(time()+300,'hourly',$hook,$args);}
		else{delete_user_meta($user_id,'swimlog_garmin_sync_enabled');if($next)wp_clear_scheduled_hook($hook,$args);}
	}


	/**
	 * Allow validated Garmin/FORM FIT source files to be preserved by WordPress.
	 *
	 * FIT files are still restricted by the plugin's own extension, size, parser,
	 * CRC, and normalized-workout validation before they are preserved.
	 */
	public function allow_fit_upload_mime( $mimes ) {
		if ( current_user_can( 'swimlog_upload_workouts' ) ) {
			$mimes['fit'] = 'application/octet-stream';
			$mimes['gpx'] = 'application/gpx+xml';
			$mimes['tcx'] = 'application/vnd.garmin.tcx+xml';
		}

		return $mimes;
	}



	private static function migrate_upgrade_settings( $installed_version ) {
		$allowed = get_option( 'swimlog_allowed_upload_types', null );
		if ( version_compare( (string) $installed_version, '0.7', '<' ) && array( 'fit', 'csv' ) === $allowed ) {
			update_option( 'swimlog_allowed_upload_types', array( 'fit', 'csv', 'gpx', 'tcx' ) );
		}
	}


	public function maybe_upgrade_schema() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$installed = get_option( 'swimlog_db_version', '0.0.0' );
		if ( version_compare( $installed, SWIMLOG_EVALUATION_DB_VERSION, '>=' ) ) {
			self::maybe_backfill_swolf();
 self::maybe_backfill_equivalent_steps();
			return;
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		if ( Database::install_schema() ) {
			self::migrate_upgrade_settings( $installed );
			update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION );
			self::maybe_backfill_swolf();
 self::maybe_backfill_equivalent_steps();
		}
	}
 private static function maybe_backfill_swolf() {
 require_once SWIMLOG_EVALUATION_DIR . 'includes/class-swolf.php';
 Swolf::backfill( 50 );
 }
 private static function maybe_backfill_equivalent_steps() {
 require_once SWIMLOG_EVALUATION_DIR . 'includes/class-equivalent-steps.php';
 Equivalent_Steps::backfill( 50 );
 }

}