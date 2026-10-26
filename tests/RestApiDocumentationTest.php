<?php
use PHPUnit\Framework\TestCase;

final class RestApiDocumentationTest extends TestCase {
	public function test_standalone_reference_covers_every_registered_route() {
		$api = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		$doc = file_get_contents( dirname( __DIR__ ) . '/REST-API.md' );
		preg_match_all( "/register_rest_route\(self::NS,'([^']+)'/", $api, $matches );
		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $route ) {
			$documented = preg_replace( '/\(\?P<id>\\\\d\+\)/', '{id}', $route );
			$this->assertStringContainsString( $documented, $doc, 'Missing REST route documentation for ' . $route );
		}
	}

	public function test_reference_documents_authentication_privacy_and_errors() {
		$doc = file_get_contents( dirname( __DIR__ ) . '/REST-API.md' );
		foreach ( array(
			'WordPress Application Password',
			'swimlog_view_own_results',
			'REST API Access',
			'get_current_user_id()',
			'401 Unauthorized',
			'403 Forbidden',
			'404 Not Found',
			'400 Bad Request',
			'open-water coordinates',
			'GPS trackpoints',
			'GET-only',
		) as $required ) {
			$this->assertStringContainsString( $required, $doc );
		}
	}

	public function test_reference_documents_portability_fields_and_analytics_semantics() {
		$doc = file_get_contents( dirname( __DIR__ ) . '/REST-API.md' );
		foreach ( array(
			'stroke_count',
			'source_length_index',
			'source_lap_index',
			'metric_metadata',
			'moving_estimated',
			'descriptive_only',
			'Race Readiness 2.0',
			'pace_consistency_cv',
			'rolling_4_week_avg_distance_m',
		) as $required ) {
			$this->assertStringContainsString( $required, $doc );
		}
	}

	public function test_readme_links_the_reference() {
		$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' );
		$this->assertStringContainsString( '[REST-API.md](REST-API.md)', $readme );
		$this->assertStringContainsString( 'complete developer reference', $readme );
	}
}
