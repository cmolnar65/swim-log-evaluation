<?php
use PHPUnit\Framework\TestCase;

final class SwolfAndEventResultTest extends TestCase {
    public function test_swolf_excludes_rest_drills_and_missing_counts() {
        $this->assertSame( 40, \SwimLogEvaluation\Swolf::length_score( array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 25, 'elapsed_time_ms' => 22000, 'stroke_count' => 18 ) ) );
        $this->assertNull( \SwimLogEvaluation\Swolf::length_score( array( 'length_type' => 'rest', 'stroke' => 'FR', 'distance_m' => 25, 'elapsed_time_ms' => 22000, 'stroke_count' => 18 ) ) );
        $this->assertNull( \SwimLogEvaluation\Swolf::length_score( array( 'length_type' => 'active', 'stroke' => 'MIXED', 'distance_m' => 25, 'elapsed_time_ms' => 22000, 'stroke_count' => 18 ) ) );
        $this->assertNull( \SwimLogEvaluation\Swolf::length_score( array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 25, 'elapsed_time_ms' => 22000, 'stroke_count' => null ) ) );
    }

    public function test_workout_average_uses_only_valid_length_scores() {
        $parsed = array( 'workout' => array( 'workout_type' => 'pool' ), 'lengths' => array(
            array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 25, 'elapsed_time_ms' => 22000, 'stroke_count' => 18 ),
            array( 'length_type' => 'active', 'stroke' => 'BR', 'distance_m' => 25, 'elapsed_time_ms' => 30000, 'stroke_count' => 20 ),
            array( 'length_type' => 'rest', 'stroke' => null, 'distance_m' => 0, 'elapsed_time_ms' => 10000, 'stroke_count' => null ),
        ) );
        $result = \SwimLogEvaluation\Swolf::populate_parsed( $parsed );
        $this->assertSame( 45.0, $result['workout']['avg_swolf'] );
        $this->assertSame( 40, $result['lengths'][0]['swolf_score'] );
        $this->assertSame( 50, $result['lengths'][1]['swolf_score'] );
        $this->assertNull( $result['lengths'][2]['swolf_score'] );
    }

    public function test_open_water_has_no_artificial_swolf() {
        $result = \SwimLogEvaluation\Swolf::populate_parsed( array( 'workout' => array( 'workout_type' => 'open_water' ), 'lengths' => array() ) );
        $this->assertNull( $result['workout']['avg_swolf'] );
    }

    public function test_schema_contains_nullable_swolf_and_race_result_fields() {
        $schema = file_get_contents( dirname( __DIR__ ) . '/includes/class-database.php' );
        foreach ( array( 'avg_swolf decimal(8,2) NULL', 'swolf_score int(10) unsigned NULL', 'result_time_ms bigint(20) unsigned NULL', 'placement int(10) unsigned NULL' ) as $field ) {
            $this->assertStringContainsString( $field, $schema );
        }
    }

    public function test_event_notes_are_loaded_only_by_authenticated_ajax() {
        $calendar = file_get_contents( dirname( __DIR__ ) . '/includes/class-calendar.php' );
        $blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
        $this->assertStringNotContainsString( "'notes' => (string) ( \$event->notes", $calendar );
        $this->assertStringContainsString( 'check_ajax_referer( \'swimlog_calendar_event_result\'', $blocks );
        $this->assertStringContainsString( 'Event::get_for_user( $id, $user_id )', $blocks );
    }
}
