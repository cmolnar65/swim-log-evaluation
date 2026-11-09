<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Health_Connect_Import;
use SwimLogEvaluation\Health_Connect_Tokens;
use SwimLogEvaluation\Health_Measurement_Store;

require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';
require_once dirname( __DIR__ ) . '/includes/class-health-connect-tokens.php';
require_once dirname( __DIR__ ) . '/includes/class-health-connect-import.php';

final class HealthConnectTestDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $tokens = array();
	public function replace( $table, $data ) { $this->tokens[(int) $data['owner_id']] = $data; return 1; }
	public function delete( $table, $where ) { $owner = (int) $where['owner_id']; if ( ! isset( $this->tokens[$owner] ) ) return 0; unset( $this->tokens[$owner] ); return 1; }
	public function prepare( $sql, ...$args ) { return json_encode( array( $sql, $args ) ); }
	public function get_row( $prepared ) {
		list( $sql, $args ) = json_decode( $prepared, true );
		foreach ( $this->tokens as $owner => $data ) {
			if ( false !== strpos( $sql, 'token_hash = %s' ) && hash_equals( $data['token_hash'], $args[1] ) ) return (object) array( 'owner_id' => $owner );
			if ( false !== strpos( $sql, 'owner_id = %d' ) && (int) $owner === (int) $args[1] ) return (object) array( 'owner_id' => $owner );
		}
		return null;
	}
	public function get_var( $prepared ) { $row = $this->get_row( $prepared ); return $row ? $row->owner_id : null; }
}

final class HealthConnectReceiverTest extends TestCase {
	public function test_tokens_are_random_hashed_and_revocable_per_owner() {
		global $wpdb, $swimlog_test_current_user_id, $swimlog_test_capabilities;
		$had_wpdb = isset( $wpdb );
		$previous_wpdb = $had_wpdb ? $wpdb : null;
		$had_user_id = isset( $swimlog_test_current_user_id );
		$previous_user_id = $had_user_id ? $swimlog_test_current_user_id : null;
		$had_capabilities = isset( $swimlog_test_capabilities );
		$previous_capabilities = $had_capabilities ? $swimlog_test_capabilities : null;
		try {
			$wpdb = new HealthConnectTestDB();
			$swimlog_test_current_user_id = 42;
			$swimlog_test_capabilities = array( 'swimlog_manage_own_workouts' => true );
			$token = Health_Connect_Tokens::issue( 42 );
			$this->assertTrue( is_string( $token ) && 0 === strpos( $token, 'slhc_' ) );
			$this->assertTrue( is_string( $token ) && $token !== $wpdb->tokens[42]['token_hash'] );
			$this->assertSame( 42, Health_Connect_Tokens::owner_for_token( $token ) );
			$this->assertSame( 0, Health_Connect_Tokens::owner_for_token( 'not-a-token' ) );
			$this->assertTrue( Health_Connect_Tokens::revoke( 42 ) );
			$this->assertSame( 0, Health_Connect_Tokens::owner_for_token( $token ) );
		} finally {
			if ( $had_wpdb ) $wpdb = $previous_wpdb;
			else unset( $wpdb );
			if ( $had_user_id ) $swimlog_test_current_user_id = $previous_user_id;
			else unset( $swimlog_test_current_user_id );
			if ( $had_capabilities ) $swimlog_test_capabilities = $previous_capabilities;
			else unset( $swimlog_test_capabilities );
		}
	}

	public function test_receiver_accepts_only_versioned_bounded_batches_and_rejects_unsupported_sports() {
		$empty = Health_Connect_Import::receive( 42, array( 'contract_version' => 1, 'batch_id' => 'batch-0001', 'workouts' => array(), 'measurements' => array() ) );
		$this->assertSame( 1, $empty['contract_version'] );
		$this->assertSame( array(), $empty['results']['workouts'] );
		$invalid = Health_Connect_Import::receive( 42, array( 'contract_version' => 1, 'batch_id' => 'batch-0002', 'workouts' => array( array( 'record_id' => 'synthetic', 'origin' => 'test.app', 'activity' => 'rowing', 'start_time' => '2026-01-01T10:00:00Z', 'end_time' => '2026-01-01T10:30:00Z' ) ), 'measurements' => array() ) );
		$this->assertSame( 'rejected', $invalid['results']['workouts'][0]['status'] );
		$this->assertSame( 'swimlog_hc_workout', $invalid['results']['workouts'][0]['code'] );
		$bad_date = Health_Connect_Import::receive( 42, array( 'contract_version' => 1, 'batch_id' => 'batch-0004', 'workouts' => array( array( 'record_id' => 'synthetic', 'origin' => 'test.app', 'activity' => 'pool_swimming', 'start_time' => '2026-02-31T10:00:00Z', 'end_time' => '2026-02-31T10:30:00Z' ) ), 'measurements' => array() ) );
		$this->assertSame( 'rejected', $bad_date['results']['workouts'][0]['status'] );
		$this->assertInstanceOf( \WP_Error::class, Health_Connect_Import::receive( 42, array( 'contract_version' => 2, 'batch_id' => 'batch-0003', 'workouts' => array(), 'measurements' => array() ) ) );
	}

	public function test_health_connect_measurements_accept_normalized_lean_mass_but_not_unknown_units() {
		$good = Health_Measurement_Store::normalize_values( 'body_composition', array( 'lean_body_mass' => array( 'value' => '48.25', 'unit' => 'kg' ) ) );
		$this->assertSame( '48.25', $good['lean_body_mass']['value'] );
		$this->assertInstanceOf( \WP_Error::class, Health_Measurement_Store::normalize_values( 'body_composition', array( 'lean_body_mass' => array( 'value' => '48.25', 'unit' => 'lb' ) ) ) );
	}

	public function test_health_connect_storage_is_owner_private_and_supports_private_routes_and_cycling() {
		$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-health-connect-import.php' );
		$this->assertStringContainsString( "'visibility' => 'private'", $db );
		$this->assertStringContainsString( '\'owner_id\' => $owner', $db );
		$this->assertStringContainsString( "'cycling' => 'cycling'", $db );
		$this->assertStringContainsString( "Database::table( 'trackpoints' )", $db );
		$this->assertStringContainsString( "'details_json' => null === $details", $db );
		$this->assertStringContainsString( "'average_heart_rate' => null", $db );
		$workout = file_get_contents( dirname( __DIR__ ) . '/includes/class-workout.php' );
		$this->assertStringContainsString( 'health_connect_heart_rate_samples', $workout );
		$this->assertStringContainsString( 'health_connect_activities', $workout );
	}

	public function test_workout_detail_validation_preserves_laps_segments_route_and_summary() {
		$method = new ReflectionMethod( Health_Connect_Import::class, 'workout_details' );
		$method->setAccessible( true );
		$start = array( 'utc' => '2026-11-01T10:00:00.000000Z' );
		$end = array( 'utc' => '2026-11-01T11:00:00.000000Z' );
		$details = array(
			'laps' => array( array( 'start_time' => '2026-11-01T10:00:00Z', 'end_time' => '2026-11-01T10:15:00Z', 'distance_m' => '2500' ) ),
			'segments' => array( array( 'start_time' => '2026-11-01T10:00:00Z', 'end_time' => '2026-11-01T10:15:00Z', 'segment_type' => 7, 'segment_label' => 'biking' ) ),
			'route' => array( array( 'time' => '2026-11-01T10:00:01Z', 'latitude' => 40.0, 'longitude' => -105.0, 'altitude_m' => 1600.0, 'horizontal_accuracy_m' => 4.0 ) ),
			'total_calories_kcal' => '300', 'active_calories_kcal' => '250', 'elevation_gain_m' => '125', 'floors_climbed' => '3', 'average_speed_mps' => '4.0', 'maximum_speed_mps' => '5.0',
			'speed_samples' => array( array( 'time' => '2026-11-01T10:00:02Z', 'meters_per_second' => 4.0 ) ),
			'step_cadence_samples' => array( array( 'time' => '2026-11-01T10:00:03Z', 'value' => 160.0 ) ),
			'cycling_cadence_samples' => array( array( 'time' => '2026-11-01T10:00:03Z', 'value' => 82.0 ) ),
			'power_samples' => array( array( 'time' => '2026-11-01T10:00:03Z', 'value' => 210.0 ) ),
		);
		$clean = $method->invoke( null, $details, $start, $end );
		$this->assertSame( 1, count( $clean['laps'] ) );
		$this->assertSame( 1, count( $clean['segments'] ) );
		$this->assertSame( 1, count( $clean['route'] ) );
		$this->assertSame( '300', $clean['total_calories_kcal'] );
		$this->assertSame( '250', $clean['active_calories_kcal'] );
		$this->assertSame( 1, count( $clean['power_samples'] ) );
		$this->assertSame( 'biking', $clean['segments'][0]['segment_label'] );
		$details['route'][0]['latitude'] = 91;
		$this->assertFalse( $method->invoke( null, $details, $start, $end ) );
		$details['route'][0]['latitude'] = 40;
		$details['power_samples'][0]['value'] = 5001;
		$this->assertFalse( $method->invoke( null, $details, $start, $end ) );
	}
	public function test_health_connect_workout_insert_uses_existing_schema_columns() {
		$import = file_get_contents( dirname( __DIR__ ) . '/includes/class-health-connect-import.php' );
		$schema = file_get_contents( dirname( __DIR__ ) . '/includes/class-database.php' );
		$this->assertSame( 1, preg_match( '/\$data = array\((.*?)\);/s', $import, $data_match ) );
		$this->assertSame( 1, preg_match( '/CREATE TABLE \$workouts \((.*?)\) \$charset_collate;/s', $schema, $schema_match ) );
		preg_match_all( "/'([a-z_]+)'\\s*=>/", $data_match[1], $insert_keys );
		preg_match_all( '/^\\s*([a-z_]+)\\s+(?:bigint|varchar|decimal|smallint|text|datetime|int|tinyint)\\b/im', $schema_match[1], $schema_columns );
		$this->assertSame( array(), array_values( array_diff( $insert_keys[1], $schema_columns[1] ) ), 'Health Connect workout insert contains a column missing from the workouts table.' );
	}

}
