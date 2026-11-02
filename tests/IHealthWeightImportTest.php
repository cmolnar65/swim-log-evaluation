<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\IHealth_Weight_Import as Importer;
use SwimLogEvaluation\Health_Measurement_Store as Store;

require_once dirname( __DIR__ ) . '/includes/class-ihealth-weight-import.php';
require_once dirname( __DIR__ ) . '/includes/class-health-measurement-store.php';

final class IHealthWeightImportTest extends TestCase {
    public function test_weight_adapter_normalizes_only_confirmed_weight_units_and_keeps_source_names() {
        $record = Importer::adapt_record( array(
            'DataID' => 'synthetic-record-1', 'MDate' => '1793620800', 'WeightUnit' => '1', 'WeightValue' => '176.37',
            'BMI' => '22.4', 'BoneValue' => '6.1', 'FatValue' => '18.2', 'WaterValue' => '55.0', 'MuscaleValue' => '62.3', 'VFR' => '8', 'DCI' => '1900',
            'Note' => 'must-not-be-stored', 'DataSource' => 'must-not-be-stored',
        ) );
        $this->assertSame( 'body_composition', $record['category'] );
        $this->assertSame( 'lbs', $record['observation']['provenance']['source_fields']['WeightValue']['unit'] );
        $this->assertSame( 'unknown', $record['observation']['provenance']['source_fields']['BMI']['unit'] );
        $this->assertEqualsWithDelta( 80, (float) $record['observation']['values']['weight']['value'], 0.001 );
        $this->assertSame( 'ihealth-personal-weight', $record['observation']['provenance']['source_format'] );
        $this->assertArrayNotHasKey( 'Note', $record['observation']['provenance']['source_fields'] );
        $this->assertArrayNotHasKey( 'DataSource', $record['observation']['provenance']['source_fields'] );
        $this->assertSame( '2026-11-02', $record['day'] );
    }

    public function test_unknown_weight_unit_stays_raw_and_does_not_create_a_normalized_weight() {
        $record = Importer::adapt_record( array( 'DataID' => 'synthetic-record-2', 'MDate' => '1793620800', 'WeightUnit' => 'unexpected', 'WeightValue' => 176.37 ) );
        $this->assertSame( 'body_composition', $record['category'] );
        $this->assertSame( array(), $record['observation']['values'] );
        $this->assertSame( 'unknown', $record['observation']['provenance']['source_fields']['WeightValue']['unit'] );
    }

    public function test_raw_only_body_composition_is_private_source_provenance_not_normalized_values() {
        $record = Importer::adapt_record( array( 'DataID' => 'synthetic-record-3', 'MDate' => '1793620800', 'WeightUnit' => 'unknown', 'WeightValue' => 176.37, 'BMI' => 22.4 ) );
        $normalized = Store::normalize_observation( $record['category'], $record['observation'] );
        $this->assertIsArray( $normalized );
        $this->assertSame( array(), $normalized['values'] );
        $this->assertSame( 'unknown', $normalized['provenance']['source_fields']['BMI']['unit'] );
        $tampered = $record['observation'];
        $tampered['provenance']['source_fields']['UnverifiedClinicalField'] = array( 'value' => '10', 'unit' => 'unknown' );
        $this->assertTrue( is_wp_error( Store::normalize_observation( 'body_composition', $tampered ) ) );
    }

    public function test_malformed_source_records_are_ignored() {
        $this->assertNull( Importer::adapt_record( array( 'DataID' => 'synthetic', 'MDate' => 'not-a-time', 'WeightUnit' => 'kg', 'WeightValue' => 80 ) ) );
        $this->assertNull( Importer::adapt_record( array( 'DataID' => '../unsafe', 'MDate' => '1793620800', 'WeightUnit' => 'kg', 'WeightValue' => 80 ) ) );
        $this->assertNull( Importer::adapt_record( array( 'DataID' => 'synthetic', 'MDate' => '1793620800', 'WeightUnit' => 'kg', 'WeightValue' => 'not-numeric' ) ) );
    }

    public function test_manual_request_window_and_page_are_bounded() {
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
