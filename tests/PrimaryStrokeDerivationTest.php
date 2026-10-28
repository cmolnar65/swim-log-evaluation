<?php
use PHPUnit\Framework\TestCase;

final class PrimaryStrokeDerivationTest extends TestCase {
    public function test_dominant_stroke_is_weighted_by_recorded_distance() {
        $parsed = array(
            'workout' => array( 'workout_type' => 'pool', 'primary_stroke' => null ),
            'lengths' => array(
                array( 'length_type' => 'active', 'stroke' => 'BR', 'distance_m' => 25 ),
                array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 50 ),
                array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 25 ),
                array( 'length_type' => 'rest', 'stroke' => 'BR', 'distance_m' => 100 ),
                array( 'length_type' => 'active', 'stroke' => 'MIXED', 'distance_m' => 200 ),
                array( 'length_type' => 'active', 'stroke' => 'UNKNOWN', 'distance_m' => 200 ),
            ),
        );
        $result = \SwimLogEvaluation\Importer::derive_primary_stroke( $parsed );
        $this->assertSame( 'FR', $result['workout']['primary_stroke'] );
    }

    public function test_missing_reliable_stroke_does_not_invent_a_primary_stroke() {
        $parsed = array(
            'workout' => array( 'workout_type' => 'pool', 'primary_stroke' => null ),
            'lengths' => array(
                array( 'length_type' => 'rest', 'stroke' => 'FR', 'distance_m' => 25 ),
                array( 'length_type' => 'active', 'stroke' => 'UNKNOWN', 'distance_m' => 25 ),
            ),
        );
        $this->assertNull( \SwimLogEvaluation\Importer::derive_primary_stroke( $parsed )['workout']['primary_stroke'] );
    }

    public function test_open_water_workout_is_not_assigned_a_pool_length_stroke() {
        $parsed = array(
            'workout' => array( 'workout_type' => 'open_water', 'primary_stroke' => null ),
            'lengths' => array( array( 'length_type' => 'active', 'stroke' => 'FR', 'distance_m' => 25 ) ),
        );
        $this->assertNull( \SwimLogEvaluation\Importer::derive_primary_stroke( $parsed )['workout']['primary_stroke'] );
    }

    public function test_import_and_rebuild_share_the_same_primary_stroke_derivation() {
        $code = file_get_contents( dirname( __DIR__ ) . '/includes/class-importer.php' );
        $this->assertStringContainsString( '$result = self::derive_primary_stroke( $result );', $code );
        $this->assertStringContainsString( 'self::parse_source($row->source_type,$row->stored_path)', $code );
    }
}
