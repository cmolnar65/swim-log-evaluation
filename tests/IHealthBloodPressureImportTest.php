<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\IHealth_Blood_Pressure_Import as Importer;
use SwimLogEvaluation\Health_Measurement_Store as Store;

require_once dirname( __DIR__ ) . '/includes/class-ihealth-blood-pressure-import.php';
require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';

final class IHealthBloodPressureImportTest extends TestCase {
    public function test_adapter_maps_only_pressure_pulse_time_and_private_identity() {
        $adapted = Importer::adapt_record( array(
            'DataID' => 'synthetic-bp-1', 'MDate' => '1793620800', 'HP' => 121, 'LP' => 79, 'HR' => 64,
            'BPL' => 1, 'IsArr' => 1, 'Lat' => 123, 'Lon' => 456, 'Note' => 'must-not-be-retained',
            'DataSource' => 'must-not-be-retained', 'TimeZone' => 'must-not-be-retained', 'measurement_time' => 'must-not-be-retained',
        ), 'mmHg' );

        $this->assertSame( '2026-11-02', $adapted['day'] );
        $this->assertSame( 'synthetic-bp-1', $adapted['observation']['external_id'] );
        $this->assertSame( array(
            'systolic' => array( 'value' => '121', 'unit' => 'mmHg' ),
            'diastolic' => array( 'value' => '79', 'unit' => 'mmHg' ),
            'pulse' => array( 'value' => '64', 'unit' => 'bpm' ),
        ), $adapted['observation']['values'] );
        $this->assertSame( '2026-11-02T12:00:00Z', $adapted['observation']['observed_at_utc'] );
        $this->assertSame( 'ihealth-personal-bp', $adapted['observation']['provenance']['source_format'] );
        $this->assertArrayNotHasKey( 'Note', $adapted['observation'] );
        $this->assertArrayNotHasKey( 'Lat', $adapted['observation'] );
        $expected_values = $adapted['observation']['values'];
        ksort( $expected_values );
        $this->assertSame( $expected_values, Store::normalize_values( 'blood_pressure', $adapted['observation']['values'] ) );
    }

    public function test_pulse_is_optional_but_pressure_pair_and_explicit_mm_hg_are_required() {
        $record = array( 'DataID' => 'synthetic-bp-2', 'MDate' => '1793620800', 'HP' => '120', 'LP' => '80' );
        $adapted = Importer::adapt_record( $record, 'mmHg' );
        $this->assertArrayNotHasKey( 'pulse', $adapted['observation']['values'] );
        $this->assertNull( Importer::adapt_record( $record, '0' ) );
        $this->assertNull( Importer::adapt_record( array_merge( $record, array( 'HP' => 'invalid' ) ), 'mmHg' ) );
        $this->assertNull( Importer::adapt_record( array_merge( $record, array( 'LP' => 0 ) ), 'mmHg' ) );
        $this->assertNull( Importer::adapt_record( array_merge( $record, array( 'DataID' => '../invalid' ) ), 'mmHg' ) );
        $this->assertTrue( is_wp_error( Store::normalize_values( 'blood_pressure', array( 'systolic' => array( 'value' => 120, 'unit' => 'kPa' ), 'diastolic' => array( 'value' => 80, 'unit' => 'kPa' ) ) ) ) );
    }

    public function test_request_window_is_limited_to_ninety_days_and_twenty_pages() {
        $request = Importer::request( '2026-10-01', '2026-11-02', 2 );
        $this->assertIsArray( $request );
        $this->assertSame( 2, $request['page_index'] );
        $this->assertSame( 'default', $request['locale'] );
        $this->assertGreaterThan( $request['start_time'], $request['end_time'] );
        $this->assertTrue( is_wp_error( Importer::request( '2026-01-01', '2026-04-01', 1 ) ) );
        $this->assertTrue( is_wp_error( Importer::request( '2026-11-03', '2026-11-03', 1 ) ) );
        $this->assertTrue( is_wp_error( Importer::request( '2026-11-01', '2026-11-02', 21 ) ) );
    }
}
