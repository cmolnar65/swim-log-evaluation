<?php
use PHPUnit\Framework\TestCase;

/** Lifecycle contracts: no destructive action for referenced prescriptions. */
final class StructuredWorkoutLifecycleV12Test extends TestCase {
    public function test_store_requires_ownership_and_preserves_referenced_workouts() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-structured-workout-store.php' );
        foreach ( array( 'get_current_user_id()', "current_user_can( 'swimlog_manage_own_workouts' )", 'Training_Plan_Collection::META_PLAN', 'Planned_Structure_Link::META_KEY', "'swimlog_structure_referenced'", 'wp_delete_post(', 'META_HIDDEN' ) as $required ) {
            $this->assertStringContainsString( $required, $source );
        }
    }
    public function test_admin_has_nonce_protected_actions_and_visibility_filters() {
        $source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        foreach ( array( "check_admin_referer( 'swimlog_structure_lifecycle' )", "wp_nonce_field( 'swimlog_structure_lifecycle' )", "'active' => 'Active'", "'hidden' => 'Hidden'", "'all' => 'All'", "'unhide' : 'hide'", 'Structured_Workout_Store::has_history(' ) as $required ) {
            $this->assertStringContainsString( $required, $source );
        }
    }
}
