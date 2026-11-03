<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Database schema and table-name services.
 *
 * Schema is upgraded in place with WordPress dbDelta. No SQL foreign keys are
 * used; relationships are maintained by plugin code for WordPress/dbDelta compatibility.
 */
final class Database {
	/** Request-only cache: never share private rows between HTTP requests. */
	private static $reads = array();
	private static $transaction = false;
	private static $observing = false;

	/** Observe core/plugin writes too, including post metadata and transaction SQL. */
	public static function observe_query( $sql ) {
		if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql ) ) {
			self::$reads = array();
		}
		if ( preg_match( '/^\s*(START\s+TRANSACTION|BEGIN)\b/i', $sql ) ) self::$transaction = true;
		if ( preg_match( '/^\s*(COMMIT|ROLLBACK)\b/i', $sql ) ) self::$transaction = false;
		return $sql;
	}

	private static function observe_writes() {
		if ( ! self::$observing && function_exists( 'add_filter' ) ) {
			add_filter( 'query', array( __CLASS__, 'observe_query' ), PHP_INT_MAX );
			self::$observing = true;
		}
	}

	private static function copy_result( $value ) {
		if ( is_object( $value ) ) return clone $value;
		if ( is_array( $value ) ) return array_map( array( __CLASS__, 'copy_result' ), $value );
		return $value;
	}

	/**
	 * Execute a caller-prepared custom-table SELECT, preserving wpdb output modes.
	 * Cache only within this request, bounded to 128 results of at most 1,000 rows. Every observed write
	 * invalidates it; transactions/locking reads bypass it. Failed queries are not
	 * cached. Cached objects are copied so callers cannot change subsequent reads.
	 * Core has no CRUD API for these normalized custom tables.
	 */
	public static function read( $method, $sql, ...$args ) {
		global $wpdb;
		if ( ! in_array( $method, array( 'get_results', 'get_row', 'get_col', 'get_var' ), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported database read method.' );
		}
		self::observe_writes();
		$cacheable = self::$observing && ! self::$transaction && preg_match( '/^\s*SELECT\b/i', $sql ) && ! preg_match( '/FOR\s+UPDATE|LOCK\s+IN\s+SHARE|SQL_NO_CACHE|GET_LOCK\\s*\\(|RELEASE_LOCK\\s*\\(|@@(?:session\\.)?in_transaction|RAND\s*\(|NOW\s*\(/i', $sql );
		$key = spl_object_hash( $wpdb ) . ':' . (string) $wpdb->prefix . ':' . md5( serialize( array( $method, $sql, $args ) ) );
		if ( $cacheable && array_key_exists( $key, self::$reads ) ) return self::copy_result( self::$reads[ $key ] );
		// Prepared SQL is supplied by domain services; custom-table reads share the bounded cache above.
		switch ( $method ) {
			case 'get_results':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared custom-table SQL; request caching and invalidation are implemented in this method.
				$result = $wpdb->get_results( $sql, ...$args );
				break;
			case 'get_row':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared custom-table SQL; request caching and invalidation are implemented in this method.
				$result = $wpdb->get_row( $sql, ...$args );
				break;
			case 'get_col':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared custom-table SQL; request caching and invalidation are implemented in this method.
				$result = $wpdb->get_col( $sql, ...$args );
				break;
			case 'get_var':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared custom-table SQL; request caching and invalidation are implemented in this method.
				$result = $wpdb->get_var( $sql, ...$args );
				break;
		}
		if ( $cacheable && empty( $wpdb->last_error ) && ( ! is_array( $result ) || count( $result ) <= 1000 ) ) {
			if ( count( self::$reads ) >= 128 ) array_shift( self::$reads );
			self::$reads[ $key ] = self::copy_result( $result );
		}
		return $result;
	}

	/** Preserve wpdb mutation return values and invalidate before every attempt. */
	public static function write( $method, ...$args ) {
		global $wpdb;
		if ( ! in_array( $method, array( 'insert', 'update', 'delete', 'replace', 'query' ), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported database write method.' );
		}
		self::observe_writes();
		self::$reads = array();
		if ( 'query' === $method ) self::observe_query( $args[0] );
		// Core has no CRUD API for normalized custom tables. Invalidation precedes all writes.
		switch ( $method ) {
			case 'insert':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- wpdb CRUD escapes field values; query receives prepared statements or fixed transaction commands. Cache invalidated above.
				return $wpdb->insert( ...$args );
			case 'update':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- wpdb CRUD escapes field values; query receives prepared statements or fixed transaction commands. Cache invalidated above.
				return $wpdb->update( ...$args );
			case 'delete':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- wpdb CRUD escapes field values; query receives prepared statements or fixed transaction commands. Cache invalidated above.
				return $wpdb->delete( ...$args );
			case 'replace':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- wpdb CRUD escapes field values; query receives prepared statements or fixed transaction commands. Cache invalidated above.
				return $wpdb->replace( ...$args );
			case 'query':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- wpdb CRUD escapes field values; query receives prepared statements or fixed transaction commands. Cache invalidated above.
				return $wpdb->query( ...$args );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'swimlog_' . $name;
	}

	public static function required_tables() {
		return array( 'imports', 'locations', 'workouts', 'manual_workouts', 'laps', 'lengths', 'trackpoints', 'performances', 'events', 'planned_workouts', 'health_reports', 'health_measurements', 'health_report_members', 'health_connect_tokens', 'health_connect_activities', 'health_connect_heart_rate_samples', 'workout_visibility', 'goals' );
	}

	public static function schema_is_complete() {
		global $wpdb;
		foreach ( self::required_tables() as $name ) {
			$table = self::table( $name );
			$found = self::read( 'get_var',  $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found !== $table ) {
				return false;
			}
		}
		$goal_columns = self::read( 'get_col', $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table( 'goals' ) ) );
		if ( array_diff( array( 'id', 'user_id', 'goal_type', 'title', 'course_unit', 'target_distance_m', 'target_distance_value', 'target_pace_ms', 'stroke', 'start_date', 'end_date', 'created_at', 'updated_at' ), (array) $goal_columns ) ) return false;
		$goal_indexes = self::read( 'get_results', $wpdb->prepare( 'SHOW INDEX FROM %i', self::table( 'goals' ) ) );
		$goal_index_columns = array();
		foreach ( (array) $goal_indexes as $index ) {
			if ( 'user_goal_range' === $index->Key_name ) $goal_index_columns[ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		ksort( $goal_index_columns );
		if ( array( 'user_id', 'start_date', 'end_date' ) !== array_values( $goal_index_columns ) ) return false;
		$visibility_columns = self::read('get_col',$wpdb->prepare('SHOW COLUMNS FROM %i',self::table('workout_visibility')));
        if(array_diff(array('workout_id','owner_id','visibility','route_public','revision','updated_at_gmt'),(array)$visibility_columns))return false;
        $visibility_primary=self::read('get_results',$wpdb->prepare("SHOW INDEX FROM %i WHERE Key_name='PRIMARY'",self::table('workout_visibility')));
        if(count($visibility_primary)!==1||$visibility_primary[0]->Column_name!=='workout_id')return false;
        $activity_columns = self::read( 'get_col', $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table( 'workouts' ) ) );
		if ( ! in_array( 'activity_environment', (array) $activity_columns, true ) ) return false;
		foreach ( self::health_schema_columns() as $name => $required ) {
			$columns = self::read( 'get_col', $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table( $name ) ) );
			if ( array_diff( $required, (array) $columns ) ) {
				return false;
			}
		}
		foreach ( self::health_connect_schema_columns() as $name => $required ) {
			$columns = self::read( 'get_col', $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table( $name ) ) );
			if ( array_diff( $required, (array) $columns ) ) return false;
		}
		foreach ( array( 'health_connect_activities' => array( 'owner_source_key' => array( array( 'user_id', 'source_key_hash' ), true ), 'owner_workout' => array( array( 'user_id', 'workout_id' ), true ), 'owner_start' => array( array( 'user_id', 'start_utc' ), false ) ), 'health_connect_heart_rate_samples' => array( 'owner_source_key' => array( array( 'user_id', 'source_key_hash' ), true ), 'owner_workout_time' => array( array( 'user_id', 'workout_id', 'sample_utc' ), false ) ) ) as $name => $expected ) {
			$indexes = self::read( 'get_results', $wpdb->prepare( 'SHOW INDEX FROM %i', self::table( $name ) ) );
			foreach ( $expected as $key => $definition ) {
				$actual = array();
				foreach ( (array) $indexes as $index ) if ( $key === $index->Key_name && ( $definition[1] === ( 0 === (int) $index->Non_unique ) ) && null === $index->Sub_part ) $actual[ (int) $index->Seq_in_index ] = $index->Column_name;
				ksort( $actual );
				if ( array_values( $actual ) !== $definition[0] ) return false;
			}
		}
		$token_indexes = self::read( 'get_results', $wpdb->prepare( 'SHOW INDEX FROM %i', self::table( 'health_connect_tokens' ) ) );
		foreach ( array( 'PRIMARY' => array( 'owner_id' ), 'token_hash' => array( 'token_hash' ) ) as $key => $expected_columns ) {
			$actual = array();
			foreach ( (array) $token_indexes as $index ) if ( $key === $index->Key_name && 0 === (int) $index->Non_unique && null === $index->Sub_part ) $actual[ (int) $index->Seq_in_index ] = $index->Column_name;
			ksort( $actual );
			if ( array_values( $actual ) !== $expected_columns ) return false;
		}
		foreach ( array( 'health_reports', 'health_measurements', 'health_report_members' ) as $name ) {
			$expected = 'health_report_members' === $name ? array( 'user_id', 'report_id', 'measurement_id' ) : array( 'user_id', 'identity_hash' );
			$key = 'health_report_members' === $name ? 'owner_report_measurement' : 'owner_identity';
			$indexes = self::read( 'get_results', $wpdb->prepare( 'SHOW INDEX FROM %i', self::table( $name ) ) );
			$actual = array();
			foreach ( (array) $indexes as $index ) {
				if ( $key === $index->Key_name && 0 === (int) $index->Non_unique && null === $index->Sub_part ) {
					$actual[ (int) $index->Seq_in_index ] = $index->Column_name;
				}
			}
			ksort( $actual );
			if ( array_values( $actual ) !== $expected ) {
				return false;
			}
		}
		return true;
	}

	/** Required measurement columns: failed partial upgrades must not advance the version. */
	private static function health_schema_columns() {
		return array(
			'health_reports' => array( 'id', 'user_id', 'provider', 'category', 'provider_date', 'identity_hash', 'content_hash', 'summary_json', 'context_json', 'fetched_at_utc', 'created_at_utc', 'updated_at_utc' ),
			'health_measurements' => array( 'id', 'user_id', 'provider', 'category', 'record_role', 'external_id', 'identity_hash', 'content_hash', 'observed_at_utc', 'source_local_time', 'source_offset_seconds', 'calendar_date', 'date_basis', 'values_json', 'provenance_json', 'created_at_utc', 'updated_at_utc' ),
			'health_report_members' => array( 'id', 'user_id', 'report_id', 'measurement_id', 'created_at_utc' ),
		);
	}

	private static function health_connect_schema_columns() {
		return array(
			'health_connect_tokens' => array( 'owner_id', 'token_hash', 'created_at_utc' ),
			'health_connect_activities' => array( 'id', 'user_id', 'workout_id', 'source_key_hash', 'content_hash', 'source_origin', 'start_utc', 'end_utc', 'start_local', 'end_local', 'start_offset_seconds', 'end_offset_seconds', 'possible_duplicate', 'created_at_utc', 'updated_at_utc' ),
			'health_connect_heart_rate_samples' => array( 'id', 'user_id', 'workout_id', 'source_key_hash', 'sample_utc', 'beats_per_minute', 'source_origin', 'created_at_utc' ),
		);
	}

	public static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$imports      = self::table( 'imports' );
		$locations    = self::table( 'locations' );
		$workouts     = self::table( 'workouts' );
		$manual_workouts = self::table( 'manual_workouts' );
		$laps         = self::table( 'laps' );
		$lengths      = self::table( 'lengths' );
		$trackpoints  = self::table( 'trackpoints' );
		$performances = self::table( 'performances' );
		$events       = self::table( 'events' );
		$planned_workouts = self::table( 'planned_workouts' );
		$goals = self::table( 'goals' );

		$health_reports = self::table( 'health_reports' );
		$health_measurements = self::table( 'health_measurements' );
		$health_report_members = self::table( 'health_report_members' );
		$health_connect_tokens = self::table( 'health_connect_tokens' );
		$health_connect_activities = self::table( 'health_connect_activities' );
		$health_connect_heart_rate_samples = self::table( 'health_connect_heart_rate_samples' );

		$visibility = self::table('workout_visibility');
        $sql = array();
        $sql[] = "CREATE TABLE $visibility (
            workout_id bigint(20) unsigned NOT NULL,
            owner_id bigint(20) unsigned NOT NULL,
            visibility varchar(20) NOT NULL DEFAULT 'private',
            route_public tinyint(1) NOT NULL DEFAULT 0,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            updated_at_gmt datetime NOT NULL,
            PRIMARY KEY  (workout_id),
            KEY owner_visibility (owner_id,visibility)
        ) ENGINE=InnoDB $charset_collate;";

		$sql[] = "CREATE TABLE $imports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			workout_id bigint(20) unsigned NULL,
			source_type varchar(20) NOT NULL,
			source_provider varchar(20) NULL,
			external_activity_id varchar(191) NULL,
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
			UNIQUE KEY user_provider_activity (user_id,source_provider,external_activity_id),
			KEY import_status (import_status)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $locations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			pool_length decimal(8,3) NULL,
			pool_unit varchar(10) NULL,
			pool_length_m decimal(8,3) NULL,
			latitude decimal(10,7) NULL,
			longitude decimal(10,7) NULL,
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
			authoritative_import_id bigint(20) unsigned NULL,
			source_type varchar(20) NOT NULL DEFAULT 'import',
			workout_type varchar(20) NOT NULL DEFAULT 'pool',
			activity_environment varchar(10) NULL,
			workout_start datetime NOT NULL,
			workout_end datetime NULL,
			total_distance_m decimal(10,3) NULL,
			original_distance decimal(10,3) NULL,
			original_distance_unit varchar(10) NULL,
			elapsed_time_ms bigint(20) unsigned NULL,
			moving_time_ms bigint(20) unsigned NULL,
			average_heart_rate smallint(5) unsigned NULL,
			max_heart_rate smallint(5) unsigned NULL,
			average_cadence decimal(8,3) NULL,
			max_cadence decimal(8,3) NULL,
			pool_length_m decimal(8,3) NULL,
			original_pool_length decimal(8,3) NULL,
			pool_length_unit varchar(10) NULL,
			primary_stroke varchar(20) NULL,
			avg_swolf decimal(8,2) NULL,
 equivalent_steps bigint(20) unsigned NULL,
 steps_active_ms bigint(20) unsigned NULL,
 steps_rate smallint(5) unsigned NULL,
 steps_method varchar(40) NULL,
 steps_version varchar(16) NULL,
			device_manufacturer varchar(100) NULL,
			device_model varchar(100) NULL,
			source_activity_id varchar(191) NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_source_type (user_id,source_type),
			KEY user_workout_type (user_id,workout_type),
			KEY user_workout_start (user_id,workout_start),
			KEY location_id (location_id),
			KEY authoritative_import_id (authoritative_import_id),
			KEY user_source_activity (user_id,source_activity_id)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $manual_workouts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workout_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			schema_version varchar(32) NOT NULL,
			source_document longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY workout_id (workout_id),
			KEY user_id (user_id)
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
			stroke_count smallint(5) unsigned NULL,
			swolf_score int(10) unsigned NULL,
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

		$sql[] = "CREATE TABLE $trackpoints (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workout_id bigint(20) unsigned NOT NULL,
			sequence_no int(10) unsigned NOT NULL,
			recorded_at datetime NULL,
			latitude decimal(10,7) NULL,
			longitude decimal(10,7) NULL,
			distance_m decimal(12,3) NULL,
			heart_rate smallint(5) unsigned NULL,
			cadence decimal(8,3) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY workout_sequence (workout_id,sequence_no),
			KEY workout_id (workout_id),
			KEY workout_recorded_at (workout_id,recorded_at)
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
			result_time_ms bigint(20) unsigned NULL,
			placement int(10) unsigned NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_event_date (user_id,event_date),
			KEY user_distance_course_stroke (user_id,distance_value,course_unit,stroke),
			KEY location_id (location_id)
		) $charset_collate;";


		$sql[] = "CREATE TABLE $planned_workouts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			planned_date date NOT NULL,
			planned_start_time time NULL,
			title varchar(191) NOT NULL,
			workout_type varchar(20) NOT NULL DEFAULT 'pool',
			planned_distance_m decimal(10,3) NULL,
			planned_duration_ms bigint(20) unsigned NULL,
			pool_length_m decimal(8,3) NULL,
			pool_course varchar(10) NULL,
			primary_stroke varchar(20) NULL,
			workout_instructions longtext NULL,
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'planned',
			completed_workout_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_planned_date (user_id,planned_date),
			KEY user_status_date (user_id,status,planned_date),
			UNIQUE KEY completed_workout_id (completed_workout_id)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $goals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			goal_type varchar(24) NOT NULL,
			title varchar(191) NOT NULL,
			course_unit varchar(10) NOT NULL,
			target_distance_m decimal(12,3) NULL,
			target_distance_value int(10) unsigned NULL,
			target_pace_ms bigint(20) unsigned NULL,
			stroke varchar(20) NOT NULL DEFAULT '',
			start_date date NOT NULL,
			end_date date NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_goal_range (user_id,start_date,end_date),
			KEY user_goal_type (user_id,goal_type)
		) $charset_collate;";
		$sql[] = "CREATE TABLE $health_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			provider varchar(32) NOT NULL,
			category varchar(32) NOT NULL,
			provider_date date NOT NULL,
			identity_hash char(64) NOT NULL,
			content_hash char(64) NOT NULL,
			summary_json longtext NOT NULL,
			context_json longtext NOT NULL,
			fetched_at_utc datetime NOT NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_identity (user_id,identity_hash),
			KEY owner_day (user_id,provider_date),
			KEY owner_source_day (user_id,provider,category,provider_date)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $health_measurements (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			provider varchar(32) NOT NULL,
			category varchar(32) NOT NULL,
			record_role varchar(32) NOT NULL,
			external_id varchar(191) NULL,
			identity_hash char(64) NOT NULL,
			content_hash char(64) NOT NULL,
			observed_at_utc varchar(32) NULL,
			source_local_time varchar(40) NULL,
			source_offset_seconds int(11) NULL,
			calendar_date date NULL,
			date_basis varchar(32) NOT NULL,
			values_json longtext NOT NULL,
			provenance_json longtext NOT NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_identity (user_id,identity_hash),
			KEY owner_day (user_id,calendar_date),
			KEY owner_source_time (user_id,provider,category,observed_at_utc)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $health_report_members (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			report_id bigint(20) unsigned NOT NULL,
			measurement_id bigint(20) unsigned NOT NULL,
			created_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_report_measurement (user_id,report_id,measurement_id),
			KEY owner_measurement (user_id,measurement_id)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $health_connect_tokens (
			owner_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			created_at_utc datetime NOT NULL,
			PRIMARY KEY  (owner_id),
			UNIQUE KEY token_hash (token_hash)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $health_connect_activities (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			workout_id bigint(20) unsigned NOT NULL,
			source_key_hash char(64) NOT NULL,
			content_hash char(64) NOT NULL,
			source_origin varchar(191) NOT NULL,
			start_utc varchar(32) NOT NULL,
			end_utc varchar(32) NOT NULL,
			start_local varchar(40) NOT NULL,
			end_local varchar(40) NOT NULL,
			start_offset_seconds int(11) NOT NULL,
			end_offset_seconds int(11) NOT NULL,
			possible_duplicate tinyint(1) NOT NULL DEFAULT 0,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_source_key (user_id,source_key_hash),
			UNIQUE KEY owner_workout (user_id,workout_id),
			KEY owner_start (user_id,start_utc)
		) $charset_collate;";

		$sql[] = "CREATE TABLE $health_connect_heart_rate_samples (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			workout_id bigint(20) unsigned NOT NULL,
			source_key_hash char(64) NOT NULL,
			sample_utc varchar(32) NOT NULL,
			beats_per_minute smallint(5) unsigned NOT NULL,
			source_origin varchar(191) NOT NULL,
			created_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_source_key (user_id,source_key_hash),
			KEY owner_workout_time (user_id,workout_id,sample_utc)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		if(!self::schema_is_complete())return false;
        require_once __DIR__.'/class-workout-visibility.php';
        return Workout_Visibility::migrate();
	}
}



