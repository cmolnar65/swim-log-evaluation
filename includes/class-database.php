<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Database schema and table-name services.
 *
 * Schema v0.1 Revision 1. No SQL foreign keys are used; relationships are
 * maintained by plugin code for WordPress/dbDelta compatibility.
 */
final class Database {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'swimlog_' . $name;
	}

	public static function required_tables() {
		return array( 'imports', 'locations', 'workouts', 'laps', 'lengths', 'performances', 'events' );
	}

	public static function schema_is_complete() {
		global $wpdb;
		foreach ( self::required_tables() as $name ) {
			$table = self::table( $name );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found !== $table ) {
				return false;
			}
		}
		return true;
	}

	public static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$imports      = self::table( 'imports' );
		$locations    = self::table( 'locations' );
		$workouts     = self::table( 'workouts' );
		$laps         = self::table( 'laps' );
		$lengths      = self::table( 'lengths' );
		$performances = self::table( 'performances' );
		$events       = self::table( 'events' );

		$sql = array();

		$sql[] = "CREATE TABLE $imports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			workout_id bigint(20) unsigned NULL,
			source_type varchar(20) NOT NULL,
			original_filename varchar(255) NOT NULL,
			stored_filename varchar(255) NULL,
			stored_path text NULL,
			file_hash char(64) NOT NULL,
			file_size bigint(20) unsigned NULL,
			parser_version varchar(32) NULL,
			import_status varchar(20) NOT NULL,
			error_message text NULL,
			imported_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY workout_id (workout_id),
			UNIQUE KEY user_file_hash (user_id,file_hash),
			KEY import_status (import_status)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $locations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			pool_length decimal(8,3) NULL,
			pool_unit varchar(10) NULL,
			pool_length_m decimal(8,3) NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_name (user_id,name)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $workouts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NULL,
			workout_start datetime NOT NULL,
			workout_end datetime NULL,
			total_distance_m decimal(10,3) NULL,
			original_distance decimal(10,3) NULL,
			original_distance_unit varchar(10) NULL,
			elapsed_time_ms bigint(20) unsigned NULL,
			moving_time_ms bigint(20) unsigned NULL,
			pool_length_m decimal(8,3) NULL,
			original_pool_length decimal(8,3) NULL,
			pool_length_unit varchar(10) NULL,
			primary_stroke varchar(20) NULL,
			device_manufacturer varchar(100) NULL,
			device_model varchar(100) NULL,
			source_activity_id varchar(191) NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_workout_start (user_id,workout_start),
			KEY location_id (location_id),
			KEY user_source_activity (user_id,source_activity_id)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $laps (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workout_id bigint(20) unsigned NOT NULL,
			sequence_no int(10) unsigned NOT NULL,
			start_time datetime NULL,
			start_offset_ms bigint(20) unsigned NULL,
			distance_m decimal(10,3) NULL,
			elapsed_time_ms bigint(20) unsigned NULL,
			moving_time_ms bigint(20) unsigned NULL,
			stroke varchar(20) NULL,
			source_lap_index int(11) NULL,
			raw_metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY workout_sequence (workout_id,sequence_no),
			KEY workout_id (workout_id)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $lengths (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workout_id bigint(20) unsigned NOT NULL,
			lap_id bigint(20) unsigned NULL,
			sequence_no int(10) unsigned NOT NULL,
			start_time datetime NULL,
			start_offset_ms bigint(20) unsigned NULL,
			distance_m decimal(8,3) NOT NULL,
			elapsed_time_ms bigint(20) unsigned NULL,
			stroke varchar(20) NULL,
			length_type varchar(20) NOT NULL,
			source_length_index int(11) NULL,
			raw_metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY workout_sequence (workout_id,sequence_no),
			KEY workout_id (workout_id),
			KEY lap_id (lap_id),
			KEY workout_stroke (workout_id,stroke)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $performances (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			workout_id bigint(20) unsigned NOT NULL,
			distance_value int(10) unsigned NOT NULL,
			course_unit varchar(10) NOT NULL,
			stroke varchar(20) NOT NULL,
			duration_ms bigint(20) unsigned NOT NULL,
			start_length_id bigint(20) unsigned NULL,
			end_length_id bigint(20) unsigned NULL,
			start_offset_ms bigint(20) unsigned NULL,
			end_offset_ms bigint(20) unsigned NULL,
			is_personal_best tinyint(1) NOT NULL DEFAULT 0,
			evaluation_version varchar(32) NOT NULL,
			achieved_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_distance_course (user_id,distance_value,course_unit),
			KEY user_stroke (user_id,stroke),
			KEY user_distance_course_stroke (user_id,distance_value,course_unit,stroke),
			KEY workout_id (workout_id),
			KEY user_pb (user_id,distance_value,course_unit,stroke,is_personal_best),
			KEY user_performance_time (user_id,distance_value,course_unit,stroke,duration_ms)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			event_name varchar(191) NOT NULL,
			event_date date NOT NULL,
			event_time time NULL,
			location_id bigint(20) unsigned NULL,
			distance_value int(10) unsigned NOT NULL,
			course_unit varchar(10) NOT NULL,
			stroke varchar(20) NOT NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_event_date (user_id,event_date),
			KEY user_distance_course_stroke (user_id,distance_value,course_unit,stroke),
			KEY location_id (location_id)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		return self::schema_is_complete();
	}
}

