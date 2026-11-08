<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Admin_UI;
use SwimLogEvaluation\Admin_Planned_Workouts as Plans;
require_once dirname( __DIR__ ) . '/admin/class-admin-ui.php';
require_once dirname( __DIR__ ) . '/admin/class-admin-planned-workouts.php';

final class AdminUsabilityTest extends TestCase {
    public function test_duration_round_trip_keeps_exact_milliseconds() {
        foreach ( array( 1, 999, 1000, 83456, 2700123, 3600000, 4500000, 86400123 ) as $ms ) {
            $this->assertSame( $ms, Admin_UI::duration_ms( Admin_UI::duration_text( $ms ) ) );
        }
        $this->assertSame( 2700000, Admin_UI::duration_ms( '45:00' ) );
        $this->assertSame( 4500000, Admin_UI::duration_ms( '1:15:00' ) );
        $this->assertNull( Admin_UI::duration_ms( '' ) );
        $this->assertSame( '', Admin_UI::duration_text( null ) );
    }
    public function test_invalid_or_ambiguous_duration_is_rejected() {
        foreach ( array( '45', '45:99', '1:70:00', '0:00', '-1:30', '1:23.1234', '1e3', array() ) as $value ) $this->assertTrue( is_wp_error( Admin_UI::duration_ms( $value ) ) );
    }
    public function test_native_units_convert_at_admin_boundary_without_changing_legacy_fields() {
        $result = Plans::normalize_form( array( 'planned_duration' => '45:00.123', 'planned_distance' => '2000', 'planned_distance_unit' => 'yd', 'pool_length_native' => '25', 'pool_course' => 'yd' ) );
        $this->assertSame( 2700123, $result['planned_duration_ms'] );
        $this->assertEqualsWithDelta( 1828.8, $result['planned_distance_m'], 0.000001 );
        $this->assertEqualsWithDelta( 22.86, $result['pool_length_m'], 0.000001 );
        $this->assertSame( '2000', Admin_UI::native_distance( $result['planned_distance_m'], 'yd' ) );
        $this->assertSame( '25', Admin_UI::native_distance( $result['pool_length_m'], 'yd' ) );
        $legacy = array( 'planned_distance_m' => 1234.567, 'pool_length_m' => 22.86, 'planned_duration_ms' => 123456 );
        $this->assertSame( $legacy, Plans::normalize_form( $legacy ) );
        $empty = Plans::normalize_form( array( 'planned_duration' => '', 'planned_distance' => '', 'pool_length_native' => '' ) );
        $this->assertNull( $empty['planned_duration_ms'] ); $this->assertNull( $empty['planned_distance_m'] ); $this->assertNull( $empty['pool_length_m'] );
    }
    public function test_bad_units_and_values_do_not_silently_become_meters() {
        foreach ( array( array( 'planned_distance' => '25', 'planned_distance_unit' => 'guess' ), array( 'pool_length_native' => '25', 'pool_course' => '' ), array( 'planned_distance' => array(), 'planned_distance_unit' => 'm' ), array( 'planned_distance' => '-1', 'planned_distance_unit' => 'm' ), array( 'planned_distance' => '1e999', 'planned_distance_unit' => 'm' ) ) as $input ) $this->assertTrue( is_wp_error( Plans::normalize_form( $input ) ) );
    }
    public function test_request_local_recovery_whitelists_fields_without_mutating_records() {
        $record = (object) array( 'title' => 'Saved', 'notes' => 'Saved notes', 'user_id' => 42 );
        $draft = Admin_UI::form_record( $record, array( 'title' => 'Attempted', 'notes' => "Line 1\nLine 2", 'user_id' => 99, 'id' => 7 ), array( 'title' => '', 'notes' => '' ) );
        $this->assertSame( 'Attempted', $draft->title ); $this->assertSame( "Line 1\nLine 2", $draft->notes );
        $this->assertFalse( property_exists( $draft, 'user_id' ) ); $this->assertFalse( property_exists( $draft, 'id' ) );
        $this->assertSame( 'Saved', $record->title );
        $this->assertSame( 'Saved', Admin_UI::form_record( $record, array( 'title' => array( 'nested' ) ), array( 'title' => '' ) )->title );
    }
}
