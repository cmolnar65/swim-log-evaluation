<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Calendar_Measurements;
require_once dirname( __DIR__ ) . '/includes/class-calendar-measurements.php';

final class CalendarMeasurementsTest extends TestCase {
    protected function tearDown(): void {
        global $swimlog_test_logged_in, $swimlog_test_current_user_id, $swimlog_test_capabilities;
        $swimlog_test_logged_in = null;
        $swimlog_test_current_user_id = null;
        $swimlog_test_capabilities = array();
    }
    public function test_health_access_requires_current_owner_and_both_capabilities() {
        global $swimlog_test_logged_in, $swimlog_test_current_user_id, $swimlog_test_capabilities;
        $swimlog_test_logged_in = true;
        $swimlog_test_current_user_id = 42;
        $swimlog_test_capabilities = array( 'swimlog_view_own_results' => true, 'swimlog_manage_own_workouts' => true, 'swimlog_manage_all_users' => true );
        $this->assertTrue( Calendar_Measurements::authorized( 42 ) );
        $this->assertFalse( Calendar_Measurements::authorized( 43 ) );
        $this->assertFalse( Calendar_Measurements::authorized( '42' ) );
        unset( $swimlog_test_capabilities['swimlog_manage_own_workouts'] );
        $this->assertFalse( Calendar_Measurements::authorized( 42 ) );
        $swimlog_test_logged_in = false;
        $this->assertFalse( Calendar_Measurements::authorized( 42 ) );
    }
    public function test_calendar_grid_ranges_are_bounded_and_half_open_across_dst() {
        $range = Calendar_Measurements::range( '2026-10-01', '2026-11-11' );
        $this->assertSame( '2026-11-12', $range['end_exclusive'] );
        $this->assertSame( '2026-10-01', $range['start'] );
        $range = Calendar_Measurements::range( '2026-10-01', '2026-12-01' );
        $this->assertSame( '2026-12-02', $range['end_exclusive'] );
    }
    public function test_invalid_reversed_or_oversized_ranges_are_rejected() {
        foreach ( array( array( '2026-02-30', '2026-03-01' ), array( '2026-10-02', '2026-10-01' ), array( '2026-10-01', '2026-12-02' ), array( array(), '2026-10-01' ), array( '2026-1-01', '2026-01-02' ) ) as $range ) {
            $this->assertTrue( is_wp_error( Calendar_Measurements::range( ...$range ) ) );
        }
    }
}
