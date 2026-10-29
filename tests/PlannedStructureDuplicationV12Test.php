<?php
use PHPUnit\Framework\TestCase;

final class PlannedStructureDuplicationV12Test extends TestCase {
    public function test_duplicate_copies_structure_as_a_new_private_post_and_links_new_plan() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
        $start = strpos( $source, 'public static function duplicate(' );
        $end = strpos( $source, 'public static function duplicate_week(', $start );
        $body = substr( $source, $start, $end - $start );
        $this->assertStringContainsString( 'Planned_Structure_Link::for_plan( $plan->id, $user_id )', $body );
        $this->assertStringContainsString( "Structured_Workout::summarize( \$source_structure['document'] )", $body );
        $this->assertStringContainsString( "Structured_Workout_Store::save( \$user_id, \$source_structure['title'], \$source_structure['document'] )", $body );
        $this->assertStringContainsString( 'Planned_Structure_Link::attach( $copy_id, $copy_structure_id, $user_id )', $body );
        $this->assertStringNotContainsString( 'Planned_Structure_Link::attach( $copy_id, $source_structure[', $body );
    }

    public function test_failed_structure_copy_or_link_cleans_up_new_plan_and_post() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
        $start = strpos( $source, 'public static function duplicate(' );
        $end = strpos( $source, 'public static function duplicate_week(', $start );
        $body = substr( $source, $start, $end - $start );
        $this->assertStringContainsString( 'if ( is_wp_error( $copy_structure_id ) )', $body );
        $this->assertStringContainsString( 'wp_delete_post( $copy_structure_id, true )', $body );
        $this->assertSame( 2, substr_count( $body, 'self::delete( $copy_id, $user_id )' ) );
    }

    public function test_week_and_recurrence_use_same_independent_duplicate_path() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
        $start = strpos( $source, 'public static function duplicate_week(' );
        $end = strpos( $source, 'private static function valid_date(', $start );
        $body = substr( $source, $start, $end - $start );
        $this->assertSame( 2, substr_count( $body, 'self::duplicate( $plan->id, $user_id' ) );
        $this->assertStringNotContainsString( 'Structured_Workout_Store::save(', $body );
    }
}
