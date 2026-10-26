<?php
use PHPUnit\Framework\TestCase;

final class AdminGarminExtractionTest extends TestCase {
	public function test_main_admin_keeps_public_garmin_hook_surface_as_delegates() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( 'public function handle_garmin_fit_download()', $admin );
		$this->assertStringContainsString( 'Admin_Garmin::handle_garmin_fit_download();', $admin );
		$this->assertStringContainsString( 'public function garmin()', $admin );
		$this->assertStringContainsString( 'Admin_Garmin::garmin();', $admin );
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';", $admin );
	}

	public function test_garmin_implementation_moved_out_of_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringNotContainsString( 'private function garmin_activity_import_state(', $admin );
		$this->assertStringNotContainsString( 'private function garmin_activity_match_status(', $admin );
		$this->assertStringNotContainsString( "Garmin Connect Personal API key saved securely.", $admin );
		$this->assertLessThan( 200000, strlen( $admin ) );
	}

	public function test_extracted_component_owns_garmin_helpers_actions_and_ui() {
		$garmin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-garmin.php' );
		$this->assertStringContainsString( 'final class Admin_Garmin', $garmin );
		$this->assertStringContainsString( 'private static function garmin_activity_import_state(', $garmin );
		$this->assertStringContainsString( 'private static function garmin_activity_match_status(', $garmin );
		$this->assertStringContainsString( 'public static function handle_garmin_fit_download()', $garmin );
		$this->assertStringContainsString( 'public static function garmin()', $garmin );
		$this->assertStringContainsString( 'self::garmin_activity_match_status(', $garmin );
		$this->assertStringContainsString( 'self::garmin_activity_import_state(', $garmin );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_garmin_download_fit' );", $garmin );
		$this->assertStringContainsString( "current_user_can( 'swimlog_view_own_results' )", $garmin );
	}

	public function test_plugin_bootstrap_still_registers_existing_admin_methods() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
		$this->assertStringContainsString( "array( \$admin, 'handle_garmin_fit_download' )", $plugin );
		$this->assertStringContainsString( "array( \$admin, 'register_menu' )", $plugin );
	}
}
