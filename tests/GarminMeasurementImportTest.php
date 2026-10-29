<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Garmin_Measurement_Import as Importer;
require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';
require_once dirname( __DIR__ ) . '/includes/class-garmin-measurement-import.php';

final class GarminMeasurementImportTest extends TestCase {
    public function test_range_is_strict_bounded_and_requires_supported_categories() {
        $this->assertCount( 7, Importer::range( '2026-01-01', '2026-01-07', array( 'weight' ) ) );
        foreach ( array(
            array( '2026-01-01', '2026-01-08', array( 'weight' ) ),
            array( '2026-02-30', '2026-03-01', array( 'weight' ) ),
            array( '2026-01-02', '2026-01-01', array( 'weight' ) ),
            array( '2099-01-01', '2099-01-01', array( 'weight' ) ),
            array( '2026-01-01', '2026-01-01', array() ),
            array( '2026-01-01', '2026-01-01', array( 'blood_pressure' ) ),
        ) as $args ) $this->assertTrue( is_wp_error( Importer::range( ...$args ) ) );
    }

    public function test_explicit_offsets_and_documented_gmt_are_normalized_without_guessing_epochs() {
        $this->assertSame( '2026-10-01T06:00:00.123Z', Importer::source_utc( '2026-10-01T00:00:00.123-0600' ) );
        $this->assertSame( '2026-10-01T06:00:00Z', Importer::source_utc( '2026-10-01T06:00:00', true ) );
        $this->assertTrue( is_wp_error( Importer::source_utc( '2026-10-01T06:00:00' ) ) );
        $this->assertTrue( is_wp_error( Importer::source_utc( 1790834400000, true ) ) );
        $this->assertTrue( is_wp_error( Importer::source_utc( '2026-10-01T00:00:00+14:30' ) ) );
        $this->assertTrue( is_wp_error( Importer::source_utc( '2026-02-30T00:00:00Z' ) ) );
    }

    public function test_weight_entries_keep_individual_readings_in_documented_kg() {
        $data = array(
            array( 'calendarDate' => '2026-10-01', 'timestampGMT' => '2026-10-01T06:00:00Z', 'date' => '2026-10-01T00:00:00', 'weight' => 82.125 ),
            array( 'calendarDate' => '2026-10-01', 'timestampGMT' => '2026-10-01T07:00:00Z', 'weight' => 82.125 ),
            array( 'calendarDate' => '2026-10-02', 'timestampGMT' => '2026-10-02T06:00:00Z', 'weight' => 81 ),
        );
        $result = Importer::normalize( 'weight', '2026-10-01', $data, '2026-10-02T12:00:00Z' );
        $this->assertCount( 2, $result['observations'] );
        $this->assertSame( 'kg', $result['observations'][0]['values']['weight']['unit'] );
        $this->assertSame( 1, $result['excluded'] );
        $this->assertSame( array(), $result['report']['summary'] );
    }

    public function test_composition_imports_bmi_without_guessing_other_units() {
        $data = array( array( 'calendarDate' => '2026-10-01', 'timestampGMT' => '2026-10-01T06:00:00Z', 'bmi' => 24.1, 'boneMass' => 4000, 'bodyFat' => 19 ) );
        $result = Importer::normalize( 'body_composition', '2026-10-01', $data, '2026-10-02T12:00:00Z' );
        $this->assertSame( array( 'bmi' ), array_keys( $result['observations'][0]['values'] ) );
        $this->assertSame( 2, $result['excluded'] );
    }

    public function test_spo2_preserves_provider_summary_roles_and_excludes_unverified_samples() {
        $data = array( 'calendarDate' => '2026-10-01', 'averageSpO2' => 96.4, 'lowestSpO2' => 91, 'latestSpO2' => 98, 'avgSleepSpO2' => 95.2, 'lastSevenDaysAvgSpO2' => 96.8, 'spO2SingleValues' => array( array( 1790834400000, 94 ), array( 1790838000000, 98 ) ), 'spO2HourlyAverages' => array( array( 1790834400000, 96 ) ), 'startTimestampGMT' => '2026-10-01T06:00:00', 'startTimestampLocal' => '2026-10-01T00:00:00', 'sleepStartTimestampGMT' => '2026-10-01T06:00:00' );
        $result = Importer::normalize( 'spo2', '2026-10-01', $data, '2026-10-02T12:00:00Z' );
        $this->assertSame( 96.4, $result['report']['summary']['average']['value'] );
        $this->assertSame( 96.8, $result['report']['summary']['seven_day_average']['value'] );
        $this->assertSame( 95.2, $result['report']['summary']['sleep_average']['value'] );
        $this->assertSame( '2026-10-01T06:00:00Z', $result['report']['context']['sleep_start_utc'] );
        $this->assertSame( '2026-10-01T00:00:00', $result['report']['context']['window_start_local'] );
        $this->assertCount( 0, $result['observations'] );
        $this->assertSame( 3, $result['excluded'] );
    }

    public function test_empty_and_unsupported_data_do_not_become_zero_measurements() {
        $empty = Importer::normalize( 'weight', '2026-10-01', array(), '2026-10-02T12:00:00Z' );
        $this->assertTrue( $empty['empty'] );
        $bad = Importer::normalize( 'spo2', '2026-10-01', array( 'calendarDate' => '2026-10-01', 'averageSpO2' => 0, 'latestSpO2' => 101 ), '2026-10-02T12:00:00Z' );
        $this->assertTrue( $bad['empty'] );
        $this->assertSame( 2, $bad['excluded'] );
        $this->assertTrue( is_wp_error( Importer::normalize( 'spo2', '2026-10-01', array( 'calendarDate' => '2026-10-02', 'averageSpO2' => 97 ), '2026-10-02T12:00:00Z' ) ) );
    }
}
