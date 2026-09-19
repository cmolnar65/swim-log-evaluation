<?php
/**
 * Plugin Name: Swim Log and Evaluation
 * Description: Import, normalize, evaluate, and display swimming workout history and personal bests.
 * Version: 0.1.0-dev
 * Author: Christopher Molnar
 * Author Email: cmolnar65@gmail.com
 * License: GPL-2.0-or-later
 * Text Domain: swim-log-evaluation
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SWIMLOG_EVALUATION_VERSION', '0.1.0-dev' );
define( 'SWIMLOG_EVALUATION_DB_VERSION', '0.1.0' );
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
