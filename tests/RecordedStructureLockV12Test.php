<?php
use PHPUnit\Framework\TestCase;
final class RecordedStructureLockV12Test extends TestCase {
    public function test_store_checks_owner_and_completed_association_before_mutation() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-structured-workout-store.php' );
        $this->assertStringContainsString('public static function is_locked( $id, $owner_id )', $s);
        $this->assertStringContainsString('self::get( $id, $owner_id )', $s);
        $this->assertStringContainsString('Planned_Workout::get_for_user( $plan_id, $owner_id )', $s);
        $this->assertStringContainsString('! empty( $plan->completed_workout_id )', $s);
        $this->assertStringContainsString("'swimlog_structure_locked'", $s);
    }
    public function test_unlink_and_replacement_cannot_bypass_lock() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-structure-link.php' );
        $this->assertSame( 2, substr_count( $s, 'Structured_Workout_Store::is_locked(' ) );
        $this->assertStringContainsString('A recorded-swim prescription cannot be unlinked.', $s);
        $this->assertStringContainsString('A recorded-swim prescription cannot be replaced.', $s);
    }
    public function test_regeneration_and_editor_respect_locked_prescription() {
        $p = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-planned-workouts.php' );
        $e = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString("Structured_Workout_Store::is_locked( \$linked['id'], \$user_id )", $p);
        $this->assertStringContainsString("if ( ! \$locked ) : ?><form", $e);
        $this->assertStringContainsString('View locked', $e);
    }
    public function test_duplicates_remain_independent() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
        $this->assertStringContainsString("Structured_Workout_Store::save( \$user_id, \$source_structure['title'], \$source_structure['document'] )", $s);
    }
}
