<?php
use PHPUnit\Framework\TestCase;

final class AdminShellConsolidationTest extends TestCase {
	public function test_admin_shell_uses_shared_ui_helpers_and_remains_thin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';", $admin );
		$this->assertStringContainsString( 'Admin_UI::selected_user_id()', $admin );
		$this->assertStringContainsString( 'Admin_UI::swimmer_selector(', $admin );
		$this->assertStringContainsString( 'Admin_UI::display_measurement(', $admin );
		$this->assertStringNotContainsString( 'private function selected_user_id(', $admin );
		$this->assertStringNotContainsString( 'private function swimmer_selector(', $admin );
		$this->assertStringNotContainsString( 'private function display_measurement(', $admin );
		$this->assertStringNotContainsString( 'private function page(', $admin );
		$this->assertLessThan( 22000, strlen( $admin ) );
	}

	public function test_shared_admin_ui_component_owns_common_helpers() {
		$ui = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-ui.php' );
		$this->assertStringContainsString( 'final class Admin_UI', $ui );
		$this->assertStringContainsString( 'public static function selected_user_id()', $ui );
		$this->assertStringContainsString( 'public static function swimmer_selector( $user_id, $page )', $ui );
		$this->assertStringContainsString( 'public static function display_measurement( $value, $unit )', $ui );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_all_users' )", $ui );
	}

	public function test_extracted_components_use_shared_helpers_instead_of_copying_them() {
		foreach ( array(
			'class-admin-analytics.php',
			'class-admin-manual-workout.php',
			'class-admin-personal-bests.php',
			'class-admin-workouts.php',
		) as $file ) {
			$source = file_get_contents( dirname( __DIR__ ) . '/admin/' . $file );
			$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';", $source );
			$this->assertStringNotContainsString( 'private static function selected_user_id(', $source );
			$this->assertStringNotContainsString( 'private static function swimmer_selector(', $source );
		}
	}
}
