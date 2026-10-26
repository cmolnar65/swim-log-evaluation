<?php
use PHPUnit\Framework\TestCase;

final class AdminAnalyticsExtractionTest extends TestCase {
	public function test_main_admin_preserves_analytics_callback_surface() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		foreach ( array( 'training_volume', 'training_trends', 'pool_analytics' ) as $method ) {
			$this->assertStringContainsString( 'public function ' . $method . '()', $admin );
			$this->assertStringContainsString( 'Admin_Analytics::' . $method . '();', $admin );
		}
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';", $admin );
	}

	public function test_analytics_implementation_moved_out_of_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringNotContainsString( 'Pool Performance & Efficiency Analytics', $admin );
		$this->assertStringNotContainsString( 'Training Trends / Training Load', $admin );
		$this->assertStringNotContainsString( 'Workout::stroke_volume_for_user(', $admin );
		$this->assertLessThan( 140000, strlen( $admin ) );
	}

	public function test_extracted_component_owns_analytics_services_filters_and_provenance() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-analytics.php' );
		$this->assertStringContainsString( 'final class Admin_Analytics', $source );
		$this->assertStringContainsString( 'Workout::stroke_volume_for_user(', $source );
		$this->assertStringContainsString( 'Training_Trends::for_user(', $source );
		$this->assertStringContainsString( 'Pool_Analytics::for_user(', $source );
		$this->assertStringContainsString( 'does not estimate physiological stress, recovery, fitness, or injury risk', $source );
		$this->assertStringContainsString( 'Missing timing and stroke-count data are never estimated.', $source );
	}

	public function test_menu_slugs_remain_registered_by_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "'swimlog-training-volume', 'training_volume'", $admin );
		$this->assertStringContainsString( "'swimlog-training-trends', 'training_trends'", $admin );
		$this->assertStringContainsString( "'swimlog-pool-analytics', 'pool_analytics'", $admin );
	}
}
