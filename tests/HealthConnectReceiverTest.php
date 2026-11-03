<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Health_Connect_Import;
use SwimLogEvaluation\Health_Connect_Tokens;
use SwimLogEvaluation\Health_Measurement_Store;

require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';
require_once dirname( __DIR__ ) . '/includes/class-health-connect-tokens.php';
require_once dirname( __DIR__ ) . '/includes/class-health-connect-import.php';

final class HealthConnectReceiverTest extends TestCase {
	public function test_tokens_are_random_hashed_and_revocable_per_owner() {
		global $swimlog_test_user_meta, $swimlog_test_current_user_id, $swimlog_test_capabilities;
		$swimlog_test_user_meta = array();
		$swimlog_test_current_user_id = 42;
		$swimlog_test_capabilities = array( 'swimlog_manage_own_workouts' => true );
		$token = Health_Connect_Tokens::issue( 42 );
		$this->assertTrue( is_string( $token ) && 0 === strpos( $token, 'slhc_' ) );
		$this->assertTrue( is_string( $token ) && $token !== $swimlog_test_user_meta[42][Health_Connect_Tokens::META] );
		$this->assertSame( 42, Health_Connect_Tokens::owner_for_token( $token ) );
		$this->assertSame( 0, Health_Connect_Tokens::owner_for_token( 'not-a-token' ) );
		$this->assertTrue( Health_Connect_Tokens::revoke( 42 ) );
		$this->assertSame( 0, Health_Connect_Tokens::owner_for_token( $token ) );
	}

	public function test_receiver_accepts_only_versioned_bounded_batches_and_rejects_unsupported_sports() {
		$empty = Health_Connect_Import::receive( 42, array( 'contract_version' => 1, 'batch_id' => 'batch-0001', 'workouts' => array(), 'measurements' => array() ) );
		$this->assertSame( 1, $empty['contract_version'] );
		$this->assertSame( array(), $empty['results']['workouts'] );
		$invalid = Health_Connect_Import::receive( 42, array( 'contract_version' => 1, 'batch_id' => 'batch-0002', 'workouts' => array( array( 'record_id' => 'synthetic', 'origin' => 'test.app', 'activity' => 'cycling', 'start_time' => '2026-01-01T10:00:00Z', 'end_time' => '2026-01-01T10:30:00Z' ) ), 'measurements' => array() ) );
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

	public function test_health_connect_storage_is_owner_private_and_does_not_accept_routes_or_cycling() {
		$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-health-connect-import.php' );
		$this->assertStringContainsString( "'visibility' => 'private'", $db );
		$this->assertStringContainsString( '\'owner_id\' => $owner', $db );
		$this->assertStringNotContainsString( "'cycling' =>", $db );
		$this->assertStringNotContainsString( 'trackpoints', $db );
		$this->assertStringContainsString( "'average_heart_rate' => null", $db );
		$workout = file_get_contents( dirname( __DIR__ ) . '/includes/class-workout.php' );
		$this->assertStringContainsString( 'health_connect_heart_rate_samples', $workout );
		$this->assertStringContainsString( 'health_connect_activities', $workout );
	}
}
