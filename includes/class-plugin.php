<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin bootstrap. */
final class Plugin {
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'swim-log-evaluation',
			false,
			dirname( plugin_basename( SWIMLOG_EVALUATION_FILE ) ) . '/languages'
		);
	}
}
