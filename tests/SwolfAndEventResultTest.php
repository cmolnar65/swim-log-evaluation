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

    public function test_event_result_access_is_owner_scoped_and_not_public() {
        $plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
        $blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
        $calendar = file_get_contents( dirname( __DIR__ ) . '/includes/class-calendar.php' );
        $start = strpos( $blocks, 'public static function calendar_event_result_action()' );
        $end = strpos( $blocks, 'public static function calendar_plan_action()', $start );
        $handler = substr( $blocks, $start, $end - $start );
        $this->assertStringContainsString( "wp_ajax_swimlog_calendar_event_result", $plugin );
        $this->assertStringNotContainsString( "wp_ajax_nopriv_swimlog_calendar_event_result", $plugin );
        $this->assertStringContainsString( 'is_user_logged_in()', $handler );
        $this->assertStringContainsString( "check_ajax_referer( 'swimlog_calendar_event_result', 'nonce' )", $handler );
        $this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_workouts' )", $handler );
        $this->assertStringContainsString( "current_user_can( 'swimlog_manage_all_users' )", $handler );
        $this->assertStringContainsString( 'Event::get_for_user( $id, $user_id )', $handler );
        $this->assertStringContainsString( 'Event::save_result( $id, $user_id, $time, $place, $notes )', $handler );
        $event_row = substr( $calendar, strpos( $calendar, 'private static function event_row(' ) );
        $this->assertStringNotContainsString( "'notes'", $event_row );
        $this->assertStringNotContainsString( "'result_time_ms'", $event_row );
    }

    public function test_private_event_notes_are_not_workout_or_plan_notes() {
        $view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
        $this->assertStringContainsString( "form.dataset.calendarEventResultForm = '1'", $view );
        $this->assertStringContainsString( "requestEventResult( root, calendarEvent.id, 'read'", $view );
        $this->assertStringContainsString( "requestEventResult( root, form.dataset.eventId, 'save'", $view );
        $this->assertStringNotContainsString( 'workout.notes', strtolower( $view ) );
        $this->assertStringNotContainsString( 'plan.notes', strtolower( $view ) );
        $this->assertStringNotContainsString( 'calendarEvent.notes', $view );
    }

    public function test_event_notes_are_loaded_only_by_authenticated_ajax() {
        $calendar = file_get_contents( dirname( __DIR__ ) . '/includes/class-calendar.php' );
        $blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
        $this->assertStringNotContainsString( "'notes' => (string) ( \$event->notes", $calendar );
        $this->assertStringContainsString( 'check_ajax_referer( \'swimlog_calendar_event_result\'', $blocks );
        $this->assertStringContainsString( 'Event::get_for_user( $id, $user_id )', $blocks );
    }
}
