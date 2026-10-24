<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin bootstrap. */
final class Plugin {
	public function run() {
		add_filter( 'upload_mimes', array( $this, 'allow_fit_upload_mime' ) );
		add_action( 'swimlog_garmin_scheduled_sync', array( $this, 'run_garmin_scheduled_sync' ), 10, 1 );
		if ( is_admin() ) {
			require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin.php';
			$admin = new Admin();
			add_action( 'admin_menu', array( $admin, 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
			add_action( 'admin_post_swimlog_garmin_download_fit', array( $admin, 'handle_garmin_fit_download' ) );
			add_action( 'admin_init', array( $admin, 'handle_workout_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_event_actions' ) );
			add_action( 'admin_init', array( $admin, 'handle_location_actions' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-shortcodes.php';
		Shortcodes::register();
		add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
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


	public function maybe_upgrade_schema() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$installed = get_option( 'swimlog_db_version', '0.0.0' );
		if ( version_compare( $installed, SWIMLOG_EVALUATION_DB_VERSION, '>=' ) ) {
			return;
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		if ( Database::install_schema() ) {
			update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION );
		}
	}
}