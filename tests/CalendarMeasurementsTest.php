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

    public function test_sleep_period_uses_utc_elapsed_time_and_local_wake_date() {
        $context = array( 'sleep_start_utc' => '2026-11-01T04:00:00Z', 'sleep_end_utc' => '2026-11-01T14:00:00Z',
            'sleep_start_local' => '2026-10-31T22:00:00', 'sleep_end_local' => '2026-11-01T07:00:00' );
        $periods = Calendar_Measurements::sleep_periods( $context );
        $this->assertCount( 1, $periods );
        $this->assertSame( 36000, $periods[0]['duration_seconds'] ); // Fall-back: not nine wall-clock hours.
        $this->assertSame( '2026-11-01', $periods[0]['date'] );
        $this->assertSame( '2026-10-31T22:00:00', $periods[0]['start_local'] );
        foreach ( $context as $key => $value ) $context[ 'next_' . $key ] = $value;
        $this->assertCount( 1, Calendar_Measurements::sleep_periods( $context ) );
        $spring = array( 'sleep_start_utc' => '2026-03-08T05:00:00Z', 'sleep_end_utc' => '2026-03-08T13:00:00Z',
            'sleep_start_local' => '2026-03-07T22:00:00', 'sleep_end_local' => '2026-03-08T07:00:00' );
        $this->assertSame( 28800, Calendar_Measurements::sleep_periods( $spring )[0]['duration_seconds'] );
    }

    public function test_sleep_period_invalid_or_unattributable_windows_are_unavailable() {
        $base = array( 'sleep_start_utc' => '2026-10-01T05:00:00Z', 'sleep_end_utc' => '2026-10-01T12:00:00Z', 'sleep_end_local' => '2026-10-01T06:00:00' );
        $this->assertNull( Calendar_Measurements::sleep_periods( $base )[0]['start_local'] );
        foreach ( array( null, '', 'bad', '2026-10-01T05:00:00Z', '2026-09-30T12:00:00Z', '2026-10-03T12:00:00Z' ) as $end ) {
            $bad = $base; $bad['sleep_end_utc'] = $end;
            $this->assertSame( array(), Calendar_Measurements::sleep_periods( $bad ) );
        }
        unset( $base['sleep_end_local'] );
        $this->assertSame( array(), Calendar_Measurements::sleep_periods( $base ) );
        $this->assertSame( array(), Calendar_Measurements::sleep_periods( null ) );
    }
}
