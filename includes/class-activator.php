<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Activation bootstrap.
 *
 * Database creation will be implemented against the frozen v0.1 Revision 1 schema.
 */
final class Activator {
	public static function activate() {
		if ( false === get_option( 'swimlog_db_version', false ) ) {
			add_option( 'swimlog_db_version', '0.0.0' );
		}
		if ( false === get_option( 'swimlog_public_results_default', false ) ) {
			add_option( 'swimlog_public_results_default', '0' );
		}
	}
}
