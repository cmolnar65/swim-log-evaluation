<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Plugin activation bootstrap. */
final class Activator {
	public static function activate() {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';

		Database::install_schema();

		update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION );

		if ( false === get_option( 'swimlog_public_results_default', false ) ) {
			add_option( 'swimlog_public_results_default', '0' );
		}
	}
}
