<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
require_once dirname( __DIR__ ) . '/includes/class-structured-workout-store.php';
require_once dirname( __DIR__ ) . '/includes/class-planned-structure-link.php';

final class PlannedStructureLinkV12Test extends TestCase {
    public function test_link_service_exposes_owner_scoped_operations() {
        $this->assertTrue( method_exists( \SwimLogEvaluation\Planned_Structure_Link::class, 'for_plan' ) );
        $this->assertTrue( method_exists( \SwimLogEvaluation\Planned_Structure_Link::class, 'attach' ) );
        $this->assertTrue( method_exists( \SwimLogEvaluation\Planned_Structure_Link::class, 'detach' ) );
        $this->assertSame( '_swimlog_planned_workout_id', \SwimLogEvaluation\Planned_Structure_Link::META_KEY );
    }
    public function test_missing_plan_cannot_be_linked() {
        global $wpdb;
        $previous = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare( $query ) { return $query; }
            public function get_row() { return null; }
        };
        try {
            $result = \SwimLogEvaluation\Planned_Structure_Link::for_plan( 999999, 42 );
            $this->assertTrue( is_wp_error( $result ) );
            $this->assertSame( 'swimlog_plan_not_found', $result->get_error_code() );
        } finally { $wpdb = $previous; }
    }
}
