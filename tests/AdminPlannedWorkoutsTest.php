<?php
use PHPUnit\Framework\TestCase;

final class AdminPlannedWorkoutsTest extends TestCase {
	public function test_main_admin_registers_planned_workout_menu_and_delegates() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
		$this->assertStringContainsString( "'swimlog-planned-workouts', 'planned_workouts'", $admin );
		$this->assertStringContainsString( 'public function handle_planned_workout_actions()', $admin );
		$this->assertStringContainsString( 'Admin_Planned_Workouts::handle_actions();', $admin );
		$this->assertStringContainsString( 'Admin_Planned_Workouts::page();', $admin );
		$this->assertStringContainsString( "array( \$admin, 'handle_planned_workout_actions' )", $plugin );
	}

	public function test_component_enforces_capability_nonces_and_owner_scoped_service() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-planned-workouts.php' );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_workouts' )", $source );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_save_planned_workout' )", $source );
		$this->assertStringContainsString( "'swimlog_status_planned_workout_' . \$id", $source );
		$this->assertStringContainsString( "'swimlog_delete_planned_workout_' . \$id", $source );
		$this->assertStringContainsString( 'Planned_Workout::save( $user_id, wp_unslash( $_POST ), $id )', $source );
		$this->assertStringContainsString( 'Planned_Workout::set_status( $id, $user_id, $status )', $source );
		$this->assertStringContainsString( 'Planned_Workout::delete( $id, $user_id )', $source );
		$this->assertStringContainsString( 'Planned_Workout::get_for_user( $edit_id, $user_id )', $source );
		$this->assertStringContainsString( 'Planned_Workout::all_for_user( $user_id )', $source );
	}

	public function test_form_contains_required_phase_three_fields_and_preserves_multiline_instructions() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-planned-workouts.php' );
		foreach ( array( 'planned_date', 'planned_start_time', 'title', 'workout_type', 'planned_distance_m', 'planned_duration_ms', 'pool_length_m', 'pool_course', 'primary_stroke', 'workout_instructions', 'notes' ) as $field ) {
			$this->assertStringContainsString( 'name="' . $field . '"', $source );
		}
		$this->assertStringContainsString( 'Workout Plan / Workout Instructions', $source );
		$this->assertStringContainsString( 'esc_textarea( $editing ? $editing->workout_instructions', $source );
		$this->assertStringContainsString( 'white-space:pre-wrap', $source );
	}

	public function test_management_actions_include_edit_skip_cancel_restore_and_delete() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-planned-workouts.php' );
		foreach ( array( 'value="skip"', 'value="cancel"', 'value="restore"', 'value="delete"', "'edit' => \$plan->id" ) as $needle ) {
			$this->assertStringContainsString( $needle, $source );
		}
	}

	public function test_service_provides_owner_scoped_management_listing_without_schema_change() {
		$service = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
		$main = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
		$this->assertStringContainsString( 'public static function all_for_user( $user_id )', $service );
		$this->assertStringContainsString( 'WHERE user_id = %d ORDER BY planned_date DESC', $service );
		$this->assertStringContainsString( "SWIMLOG_EVALUATION_DB_VERSION', '1.1'", $main );
	}
}
