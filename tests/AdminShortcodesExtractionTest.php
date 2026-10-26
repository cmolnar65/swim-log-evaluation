<?php
use PHPUnit\Framework\TestCase;

final class AdminShortcodesExtractionTest extends TestCase {
	public function test_main_admin_preserves_shortcode_callback_surface() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		foreach ( array( 'shortcodes', 'shortcode_latest', 'shortcode_upcoming_events', 'shortcode_workout', 'shortcode_log', 'shortcode_progress', 'shortcode_bests' ) as $method ) {
			$this->assertStringContainsString( 'public function ' . $method . '()', $admin );
			$this->assertStringContainsString( 'Admin_Shortcodes::' . $method . '();', $admin );
		}
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';", $admin );
	}

	public function test_shortcode_help_implementation_is_out_of_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringNotContainsString( 'private function shortcode_help_page(', $admin );
		$this->assertStringNotContainsString( 'private function common_shortcode_attributes(', $admin );
		$this->assertStringNotContainsString( 'Table Width and Page Layout', $admin );
		$this->assertLessThan( 165000, strlen( $admin ) );
	}

	public function test_extracted_component_owns_documentation_and_access_control() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-shortcodes.php' );
		$this->assertStringContainsString( 'final class Admin_Shortcodes', $source );
		$this->assertStringContainsString( "current_user_can('swimlog_manage_settings')", $source );
		$this->assertStringContainsString( 'private static function shortcode_help_page(', $source );
		$this->assertStringContainsString( 'private static function common_shortcode_attributes()', $source );
		$this->assertStringContainsString( '[swimlog_latest]', $source );
		$this->assertStringContainsString( '[swimlog_bests]', $source );
		$this->assertStringContainsString( '[swimlog_upcoming_events]', $source );
		$this->assertStringContainsString( '[swimlog_workout]', $source );
		$this->assertStringContainsString( '[swimlog_log]', $source );
		$this->assertStringContainsString( '[swimlog_progress]', $source );
	}
}
