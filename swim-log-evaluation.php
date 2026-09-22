<?php
/**
 * Plugin Name: Chris's Swim Training Progress and Evaluation
 * Description: Import, normalize, evaluate, and display swimming workout history and personal bests.
 * Version: 0.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Christopher Molnar
 * Author Email: cmolnar@cmolnar.com
 * License: GPL-2.0-or-later
 * Text Domain: chriss-swim-training-progress-evaluation
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SWIMLOG_EVALUATION_VERSION', '0.3' );
define( 'SWIMLOG_EVALUATION_DB_VERSION', '0.2' );
define( 'SWIMLOG_EVALUATION_FILE', __FILE__ );
define( 'SWIMLOG_EVALUATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIMLOG_EVALUATION_URL', plugin_dir_url( __FILE__ ) );

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-activator.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'SwimLogEvaluation\\Activator', 'activate' ) );

function swimlog_evaluation_run() {
	$plugin = new SwimLogEvaluation\Plugin();
	$plugin->run();
}
swimlog_evaluation_run();
