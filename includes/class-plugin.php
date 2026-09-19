<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin bootstrap. */
final class Plugin {
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
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
