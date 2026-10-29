<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Structured_Workout_Export as Export;
use SwimLogEvaluation\Structured_Workout_FIT as Fit;
use SwimLogEvaluation\Recorded_Workout_Structure as Conversion;
require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
require_once dirname( __DIR__ ) . '/includes/class-structured-workout-export.php';
require_once dirname( __DIR__ ) . '/includes/class-structured-workout-fit.php';
require_once dirname( __DIR__ ) . '/includes/class-recorded-workout-structure.php';
final class StructuredTransfersV12Test extends TestCase {
    private function document() {
        return array( 'unit' => 'yd', 'sections' => array( array( 'type' => 'section', 'label' => 'Main Set', 'rest' => '0', 'children' => array(
            array( 'type' => 'repeat', 'count' => 3, 'rest' => '0', 'children' => array(
                array( 'type' => 'step', 'distance' => 100, 'stroke' => 'FR', 'rest' => '0', 'pace' => 'on 2:00' ),
                array( 'type' => 'step', 'distance' => 50, 'stroke' => 'BR', 'rest' => '15', 'pace' => '1:30/100yd' ),
            ) ),
            array( 'type' => 'step', 'distance' => 25, 'stroke' => 'FL', 'drill' => true, 'equipment' => array( 'Fins' ) ),
        ) ) ) );
    }
    public function test_text_preserves_groups_units_timing_equipment_and_total() {
        $text = Export::text( 'Synthetic prescription', $this->document() );
        foreach ( array( '3 x (', '100 yd FR', 'on 2:00', '1:30/100yd', 'Rest: 15', 'equipment: Fins', 'Total: 475 yd', 'Rest: unspecified' ) as $value ) $this->assertStringContainsString( $value, $text );
    }
    public function test_fit_semantics_repeat_sendoff_pace_strokes_and_unknown_rest() {
        $compiled = Fit::compile( $this->document() );
        $this->assertFalse( is_wp_error( $compiled ) );
        $steps = $compiled['steps'];
        $this->assertCount( 7, $steps );
        $this->assertSame( 9144, $steps[0]['duration_value'] );
        $this->assertSame( 28, $steps[1]['duration_type'] );
        $this->assertSame( 120000, $steps[1]['duration_value'] );
        $this->assertSame( 2, $steps[2]['target_value'] );
        $this->assertSame( 1016, $steps[2]['speed'] );
        $this->assertSame( 6, $steps[4]['duration_type'] );
        $this->assertSame( 0, $steps[4]['duration_value'] );
        $this->assertSame( 3, $steps[4]['target_value'] );
        $this->assertSame( 4, $steps[5]['target_value'] );
        $this->assertSame( 1, $steps[5]['equipment'] );
        $this->assertSame( 5, $steps[6]['duration_type'] );
        $fit = Fit::encode( 'Synthetic prescription', $this->document(), 22.86 );
        $this->assertIsString( $fit );
        $this->assertSame( '.FIT', substr( $fit, 8, 4 ) );
    }
    public function test_fit_rejects_ambiguous_targets_and_excessive_notes_without_truncating() {
        $doc = $this->document(); $doc['sections'][0]['children'][0]['children'][0]['pace'] = 'fast';
        $this->assertInstanceOf( WP_Error::class, Fit::compile( $doc ) );
        $this->assertStringContainsString( 'fast', Export::text( 'Original target', $doc ) );
        $doc = $this->document(); $doc['sections'][0]['description'] = str_repeat( 'x', 500 );
        $this->assertInstanceOf( WP_Error::class, Fit::compile( $doc ) );
        $this->assertStringContainsString( str_repeat( 'x', 500 ), Export::text( 'Original description', $doc ) );
    }
    public function test_recorded_conversion_preserves_evidence_without_inventing_targets_or_notes() {
        $w = (object) array( 'workout_type' => 'pool', 'pool_length_unit' => 'yd', 'total_distance_m' => 45.72, 'notes' => 'PRIVATE SENTINEL' );
        $rows = array(
            (object) array( 'length_type' => 'active', 'distance_m' => 22.86, 'stroke' => 'FR', 'elapsed_time_ms' => 20000 ),
            (object) array( 'length_type' => 'rest', 'distance_m' => 0, 'elapsed_time_ms' => 15000 ),
            (object) array( 'length_type' => 'active', 'distance_m' => 22.86, 'stroke' => 'BACK', 'elapsed_time_ms' => null ),
        );
        $result = Conversion::from_rows( $w, $rows );
        $this->assertFalse( is_wp_error( $result ) );
        $nodes = $result['document']['sections'][0]['children'];
        $this->assertSame( 25.0, $nodes[0]['distance'] );
        $this->assertSame( '15', $nodes[0]['rest'] );
        $this->assertNull( $nodes[0]['pace'] );
        $this->assertSame( 'BK', $nodes[1]['stroke'] );
        $this->assertNull( $nodes[1]['rest'] );
        $this->assertStringNotContainsString( 'PRIVATE SENTINEL', json_encode( $result ) );
        $rows[0]->stroke = 'UNKNOWN';
        $this->assertInstanceOf( WP_Error::class, Conversion::from_rows( $w, $rows ) );
    }
    public function test_conversion_rejects_totals_only_and_partial_evidence() {
        $w = (object) array( 'workout_type' => 'pool', 'pool_length_unit' => 'm', 'total_distance_m' => 100 );
        $this->assertInstanceOf( WP_Error::class, Conversion::from_rows( $w, array() ) );
        $row = (object) array( 'length_type' => 'active', 'distance_m' => 25, 'stroke' => 'FR', 'elapsed_time_ms' => 20000 );
        $this->assertInstanceOf( WP_Error::class, Conversion::from_rows( $w, array( $row ) ) );
    }
    public function test_fit_rejects_conflicting_sendoff_and_unrepresentable_speed() {
        $doc = $this->document();
        $doc['sections'][0]['children'][0]['children'][0]['rest'] = '10';
        $this->assertInstanceOf( WP_Error::class, Fit::compile( $doc ) );
        $doc = $this->document();
        $doc['sections'][0]['children'][0]['children'][0]['pace'] = '0:00.0000001/100m';
        $this->assertInstanceOf( WP_Error::class, Fit::compile( $doc ) );
    }
    public function test_conversion_never_turns_partly_unknown_rest_into_a_known_target() {
        $w = (object) array( 'workout_type' => 'pool', 'pool_length_unit' => 'm', 'total_distance_m' => 25 );
        $rows = array(
            (object) array( 'length_type' => 'active', 'distance_m' => 25, 'stroke' => 'FR' ),
            (object) array( 'length_type' => 'rest', 'elapsed_time_ms' => null ),
            (object) array( 'length_type' => 'rest', 'elapsed_time_ms' => 10000 ),
        );
        $result = Conversion::from_rows( $w, $rows );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertNull( $result['document']['sections'][0]['children'][0]['rest'] );
        $w->workout_type = 'open_water';
        $this->assertInstanceOf( WP_Error::class, Conversion::from_rows( $w, $rows ) );
    }

    public function test_bundled_pdf_renderer_executes_on_each_supported_php_version() {
        $pdf = Export::pdf( 'Synthetic préparation & endurance', $this->document() );
        $this->assertFalse( is_wp_error( $pdf ), is_wp_error( $pdf ) ? $pdf->get_error_message() : '' );
        $this->assertIsString( $pdf );
        $this->assertSame( '%PDF-', substr( $pdf, 0, 5 ) );
        $this->assertStringContainsString( '%%EOF', $pdf );
    }

}
