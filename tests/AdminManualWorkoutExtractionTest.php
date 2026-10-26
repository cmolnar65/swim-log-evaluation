<?php
use PHPUnit\Framework\TestCase;

final class AdminManualWorkoutExtractionTest extends TestCase {
	public function test_main_admin_preserves_manual_workout_callback_surface() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		foreach ( array( 'handle_manual_workout_actions', 'manual_workout' ) as $method ) {
			$this->assertStringContainsString( 'public function ' . $method . '()', $admin );
			$this->assertStringContainsString( 'Admin_Manual_Workout::' . $method . '();', $admin );
		}
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-manual-workout.php';", $admin );
	}

	public function test_manual_workout_implementation_moved_out_of_main_admin() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringNotContainsString( 'Manual_Workout::save(', $admin );
		$this->assertStringNotContainsString( 'private static function manual_time_to_ms(', $admin );
		$this->assertStringNotContainsString( 'data-swimlog-manual-workout="1"', $admin );
		$this->assertLessThan( 120000, strlen( $admin ) );
	}

	public function test_extracted_component_owns_permissions_nonces_service_and_timing_provenance() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-manual-workout.php' );
		$this->assertStringContainsString( 'final class Admin_Manual_Workout', $source );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_workouts' )", $source );
		$this->assertStringContainsString( "'swimlog_update_manual_workout_' . \$workout_id", $source );
		$this->assertStringContainsString( "'swimlog_create_manual_workout'", $source );
		$this->assertStringContainsString( 'Manual_Workout::save( $user_id, $document, $workout_id )', $source );
		$this->assertStringContainsString( 'Manual_Workout::get_document( $workout_id, $user_id )', $source );
		$this->assertStringContainsString( 'private static function manual_time_to_ms(', $source );
		$this->assertStringContainsString( "'rep_times_ms' => \$rep_times_ms", $source );
	}

	public function test_extracted_component_declares_each_method_only_once() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-manual-workout.php' );
		preg_match_all( '/\\bfunction\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*\\(/', $source, $matches );
		$counts = array_count_values( $matches[1] );
		$duplicates = array_filter( $counts, function ( $count ) { return $count > 1; } );
		$this->assertSame( array(), $duplicates, 'Duplicate method declarations would cause a PHP fatal error.' );
	}

	public function test_menu_and_plugin_hooks_still_target_main_admin_callbacks() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
		$this->assertStringContainsString( "'swimlog-manual-workout', 'manual_workout'", $admin );
		$this->assertStringContainsString( "array( \$admin, 'handle_manual_workout_actions' )", $plugin );
	}
}
