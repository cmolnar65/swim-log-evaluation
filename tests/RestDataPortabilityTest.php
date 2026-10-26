<?php
use PHPUnit\Framework\TestCase;

final class RestDataPortabilityTest extends TestCase {
	public function test_expanded_routes_are_read_only_and_share_existing_permission_gate() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		foreach ( array(
			'/personal-bests',
			'/progression',
			'/events',
			'/analytics/pool',
			'/analytics/training-trends',
		) as $route ) {
			$this->assertStringContainsString( "'" . $route . "'", $code );
		}
		$this->assertGreaterThanOrEqual( 7, substr_count( $code, "'methods'=>\\WP_REST_Server::READABLE" ) );
		$this->assertGreaterThanOrEqual( 7, substr_count( $code, "'permission_callback'=>array(__CLASS__,'permission')" ) );
		$this->assertStringNotContainsString( 'CREATABLE', $code );
		$this->assertStringNotContainsString( 'EDITABLE', $code );
		$this->assertStringNotContainsString( 'DELETABLE', $code );
	}

	public function test_pool_workout_detail_exposes_preserved_normalized_fields() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		foreach ( array(
			"'source_type'",
			"'original_pool_length'",
			"'start_offset_ms'",
			"'source_lap_index'",
			"'stroke_count'",
			"'source_length_index'",
			"'workout_id'",
			"'is_personal_best'",
		) as $field ) {
			$this->assertStringContainsString( $field, $code );
		}
	}

	public function test_portability_routes_use_existing_service_layers() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		$this->assertStringContainsString( 'Records::personal_bests($uid,$course)', $code );
		$this->assertStringContainsString( 'Records::progression($uid,$distance,$course,$stroke)', $code );
		$this->assertStringContainsString( 'Event::upcoming_for_user($uid,$limit)', $code );
		$this->assertStringContainsString( 'Workout::event_readiness($uid,$event,Workout::EVENT_READINESS_LOOKBACK_DAYS)', $code );
		$this->assertStringContainsString( 'Pool_Analytics::for_user(get_current_user_id(),$filters)', $code );
		$this->assertStringContainsString( 'Training_Trends::for_user(get_current_user_id(),$months)', $code );
	}

	public function test_api_keeps_private_source_and_geographic_data_excluded() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		foreach ( array(
			'Workout::trackpoints',
			'Workout::imports',
			"'latitude'",
			"'longitude'",
			"'stored_path'",
			"'original_filename'",
			"'notes'",
		) as $private ) {
			$this->assertStringNotContainsString( $private, $code );
		}
	}

	public function test_api_help_and_readme_document_expansion() {
		$help = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-settings.php' );
		$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' );
		foreach ( array(
			'/personal-bests',
			'/progression',
			'/events',
			'/analytics/pool',
			'/analytics/training-trends',
		) as $route ) {
			$this->assertStringContainsString( $route, $help );
		}
		$this->assertStringContainsString( 'per-length stroke counts', $help );
		$this->assertStringContainsString( 'REST API and data-portability surface', $readme );
		$this->assertStringContainsString( 'No write/create/delete REST methods are added', $readme );
		$this->assertStringContainsString( 'requires no database schema change', $readme );
	}
}
