<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin bootstrap. */
final class Plugin {
	public function run() {
		add_filter( 'upload_mimes', array( $this, 'allow_fit_upload_mime' ) );
		if ( is_admin() ) {
			require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin.php';
			$admin = new Admin();
			add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-shortcodes.php';
		Shortcodes::register();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
	}


	/**
	 * Allow validated Garmin/FORM FIT source files to be preserved by WordPress.
	 *
	 * FIT files are still restricted by Swim Log's own extension, size, parser,
	 * CRC, and normalized-workout validation before they are preserved.
	 */
	public function allow_fit_upload_mime( $mimes ) {
		if ( current_user_can( 'swimlog_upload_workouts' ) ) {
			$mimes['fit'] = 'application/octet-stream';
		}

		return $mimes;
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'swim-log-evaluation',
			false,
			dirname( plugin_basename( SWIMLOG_EVALUATION_FILE ) ) . '/languages'
		);
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
