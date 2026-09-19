<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Database access and schema-version services. */
final class Database {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'swimlog_' . $name;
	}
}
