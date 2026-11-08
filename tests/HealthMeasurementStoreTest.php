<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Health_Measurement_Store as Store;
require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';

final class HealthMeasurementStoreTest extends TestCase {
    private function sample( $overrides = array() ) {
        return array_merge( array(
            'role' => 'sample', 'observed_at_utc' => '2026-11-01T07:30:00.120000Z',
            'source_local_time' => '2026-11-01T01:30:00.120000', 'source_offset_seconds' => -21600,
            'calendar_date' => '2026-11-01', 'date_basis' => 'source_local',
            'values' => array( 'spo2' => array( 'value' => '097.000', 'unit' => '%' ) ),
        ), $overrides );
    }

    public function test_normalization_keeps_precision_and_removes_representational_duplicates() {
        $a = Store::normalize_observation( 'spo2', $this->sample() );
        $b = Store::normalize_observation( 'spo2', $this->sample( array( 'observed_at_utc' => '2026-11-01T07:30:00.12Z', 'source_local_time' => '2026-11-01T01:30:00.12', 'values' => array( 'spo2' => array( 'value' => 97, 'unit' => '%' ) ) ) ) );
        $this->assertSame( $a, $b );
        $this->assertSame( '97', $a['values']['spo2']['value'] );
        $this->assertSame( '2026-11-01T07:30:00.12Z', $a['observed_at_utc'] );
    }

    public function test_dst_repeated_local_hour_remains_two_distinct_instants() {
        $a = Store::normalize_observation( 'spo2', $this->sample() );
        $b = Store::normalize_observation( 'spo2', $this->sample( array( 'observed_at_utc' => '2026-11-01T08:30:00.120000Z', 'source_offset_seconds' => -25200 ) ) );
        $this->assertFalse( is_wp_error( $b ) );
        $this->assertSame( $a['source_local_time'], $b['source_local_time'] );
        $this->assertNotSame( $a['observed_at_utc'], $b['observed_at_utc'] );
    }

    public function test_unsupported_precision_and_invalid_dates_are_rejected() {
        foreach ( array( '2026-02-30T07:30:00Z', '2026-01-01T24:00:00Z', '2026-01-01T00:00:00.1234567Z', '2026-01-01T00:00:00', '1760000000' ) as $value ) {
            $this->assertTrue( is_wp_error( Store::timestamp( $value ) ) );
        }
    }

    public function test_unverified_offsets_and_calendar_attribution_are_rejected() {
        foreach ( array(
            array( 'source_offset_seconds' => -25200 ),
            array( 'calendar_date' => '2026-11-02' ),
            array( 'date_basis' => 'unresolved' ),
            array( 'calendar_date' => '2026-02-30' ),
            array( 'source_offset_seconds' => '0' ),
        ) as $override ) $this->assertTrue( is_wp_error( Store::normalize_observation( 'spo2', $this->sample( $override ) ) ) );
    }

    public function test_unknown_times_are_not_invented_and_need_stable_identity() {
        $row = $this->sample( array( 'observed_at_utc' => null, 'source_local_time' => null, 'source_offset_seconds' => null, 'calendar_date' => null, 'date_basis' => 'unresolved', 'external_id' => 'verified-source-id' ) );
        $a = Store::normalize_observation( 'spo2', $row );
        $this->assertNull( $a['observed_at_utc'] );
        $this->assertNull( $a['calendar_date'] );
        unset( $row['external_id'] );
        $this->assertTrue( is_wp_error( Store::normalize_observation( 'spo2', $row ) ) );
    }

    public function test_blood_pressure_requires_a_complete_pair_with_explicit_units() {
        $row = $this->sample( array( 'values' => array( 'systolic' => array( 'value' => 120, 'unit' => 'mmHg' ), 'diastolic' => array( 'value' => 80, 'unit' => 'mmHg' ) ) ) );
        $a = Store::normalize_observation( 'blood_pressure', $row );
        $this->assertSame( array( 'diastolic', 'systolic' ), array_keys( $a['values'] ) );
        unset( $row['values']['diastolic'] );
        $this->assertTrue( is_wp_error( Store::normalize_observation( 'blood_pressure', $row ) ) );
    }

    public function test_invalid_units_values_and_private_payload_fields_are_rejected() {
        foreach ( array(
            array( 'values' => array( 'spo2' => array( 'value' => 97, 'unit' => 'ratio' ) ) ),
            array( 'values' => array( 'spo2' => array( 'value' => 0, 'unit' => '%' ) ) ),
            array( 'values' => array( 'spo2' => array( 'value' => 101, 'unit' => '%' ) ) ),
            array( 'values' => array( 'spo2' => array( 'value' => INF, 'unit' => '%' ) ) ),
            array( 'provenance' => array( 'api_key' => 'do-not-store' ) ),
            array( 'provenance' => array( 'device' => str_repeat( 'x', 192 ) ) ),
            array( 'unexpected_field' => 'rejected' ),
            array( 'role' => 'daily_summary' ),
        ) as $override ) $this->assertTrue( is_wp_error( Store::normalize_observation( 'spo2', $this->sample( $override ) ) ) );
    }

    public function test_correction_requires_stable_identity_and_expected_content_hash() {
        $this->assertTrue( is_wp_error( Store::normalize_observation( 'spo2', $this->sample( array( 'confirmed_correction' => true ) ) ) ) );
        $this->assertTrue( is_wp_error( Store::normalize_observation( 'spo2', $this->sample( array( 'external_id' => 'id', 'confirmed_correction' => true ) ) ) ) );
        $this->assertFalse( is_wp_error( Store::normalize_observation( 'spo2', $this->sample( array( 'external_id' => 'id', 'confirmed_correction' => true, 'expected_content_hash' => str_repeat( 'a', 64 ) ) ) ) ) );
    }
}
