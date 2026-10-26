<?php
use PHPUnit\Framework\TestCase;

final class AdminEventsLocationsExtractionTest extends TestCase {
	public function test_main_admin_keeps_public_event_and_location_callbacks_as_delegates() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		foreach ( array( 'handle_location_actions', 'handle_event_actions', 'events', 'locations' ) as $method ) {
			$this->assertStringContainsString( 'public function ' . $method . '()', $admin );
			$this->assertStringContainsString( 'Admin_Events_Locations::' . $method . '();', $admin );
		}
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';", $admin );
	}

	public function test_events_and_locations_implementation_moved_out_of_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringNotContainsString( "check_admin_referer( 'swimlog_save_location' );", $admin );
		$this->assertStringNotContainsString( "check_admin_referer( 'swimlog_save_event' );", $admin );
		$this->assertStringNotContainsString( 'Manage upcoming and historical swim events.', $admin );
		$this->assertStringNotContainsString( 'Manage your pool and swim locations.', $admin );
		$this->assertLessThan( 180000, strlen( $admin ) );
	}

	public function test_extracted_component_owns_actions_permissions_nonces_and_ui() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-events-locations.php' );
		$this->assertStringContainsString( 'final class Admin_Events_Locations', $source );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_locations' )", $source );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_events' )", $source );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_save_location' );", $source );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_delete_location' );", $source );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_save_event' );", $source );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_delete_event' );", $source );
		$this->assertStringContainsString( 'Location::save(', $source );
		$this->assertStringContainsString( 'Location::delete(', $source );
		$this->assertStringContainsString( 'Event::save(', $source );
		$this->assertStringContainsString( 'Event::delete(', $source );
	}

	public function test_menu_registration_still_points_to_existing_admin_methods() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "'swimlog-events', 'events'", $admin );
		$this->assertStringContainsString( "'swimlog-locations', 'locations'", $admin );
	}
}
