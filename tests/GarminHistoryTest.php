<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Garmin_History as History;
require_once dirname( __DIR__ ) . '/includes/class-garmin-history.php';

final class GarminHistoryTest extends TestCase {
    public function test_full_year_and_leap_year_have_no_manual_range_limit() {
        $this->assertSame( array( 'start' => '2018-01-01', 'end' => '2018-12-31' ), History::range( '2018-01-01', '2018-12-31' ) );
        $this->assertSame( '2020-02-29', History::cursor( '2020-02-29', '2020-12-31' )['day'] );
    }
    public function test_impossible_reversed_future_and_nested_dates_are_rejected() {
        foreach ( array( array( '2018-02-29', '2018-03-01' ), array( '2018-12-31', '2018-01-01' ), array( '2099-01-01', '2099-12-31' ), array( array(), '2018-12-31' ), array( '2018-1-01', '2018-12-31' ) ) as $range ) $this->assertTrue( is_wp_error( History::range( ...$range ) ) );
    }
    public function test_completed_cursor_performs_no_requests_but_validates_categories() {
        global $swimlog_test_capabilities;
        $swimlog_test_capabilities = array( 'swimlog_manage_own_workouts' => true );
        try {
            $cursor = History::cursor( '2018-01-01', '2018-01-01' ); $cursor['done'] = true;
            $this->assertSame( array(), History::activity_step( 42, $cursor, array( 'lap_swimming' ) )['activities'] );
            $this->assertSame( array(), History::measurement_step( 42, $cursor, array( 'weight' ) )['results'] );
            $this->assertTrue( is_wp_error( History::activity_step( 42, $cursor, array( 'running' ) ) ) );
            $this->assertTrue( is_wp_error( History::measurement_step( 42, $cursor, array( 'blood_pressure' ) ) ) );
            $this->assertTrue( is_wp_error( History::activity_step( 43, $cursor, array( 'lap_swimming' ) ) ) );
            $cursor['offset'] = '0';
            $this->assertTrue( is_wp_error( History::activity_step( 42, $cursor, array( 'lap_swimming' ) ) ) );
        } finally { $swimlog_test_capabilities = array(); }
    }
}
