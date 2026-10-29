<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';

final class StructuredWorkoutV12Test extends TestCase {
    private function step( $distance, $stroke ) {
        return array( 'type' => 'step', 'distance' => $distance, 'stroke' => $stroke );
    }
    private function ladder() {
        $rungs = array();
        foreach ( array( array( 25, 6 ), array( 50, 6 ), array( 100, 4 ) ) as $pair ) {
            $rungs[] = array( 'type' => 'repeat', 'count' => $pair[1], 'children' => array(
                $this->step( $pair[0], 'FR' ), $this->step( $pair[0], 'BR' ),
            ) );
        }
        return array( 'unit' => 'm', 'sections' => array( array( 'type' => 'section', 'label' => 'Main',
            'children' => array( array( 'type' => 'ladder', 'rest' => '0', 'rungs' => $rungs ) ) ) ) );
    }
    public function test_continuous_mixed_stroke_ladder_totals() {
        $result = \SwimLogEvaluation\Structured_Workout::summarize( $this->ladder() );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertSame( 1700.0, $result['totals']['distance'] );
        $this->assertSame( 850.0, $result['totals']['by_stroke']['FR'] );
        $this->assertSame( 850.0, $result['totals']['by_stroke']['BR'] );
        $this->assertSame( 32, $result['totals']['expanded_steps'] );
        $this->assertSame( '0', $result['document']['sections'][0]['children'][0]['rest'] );
    }
    public function test_rest_and_pace_do_not_change_ladder_identity() {
        $document = $this->ladder();
        $document['sections'][0]['children'][0]['rungs'][0]['rest'] = '15 seconds after rung';
        $document['sections'][0]['children'][0]['rungs'][0]['pace'] = 'Easy';
        $result = \SwimLogEvaluation\Structured_Workout::summarize( $document );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertSame( 'ladder', $result['document']['sections'][0]['children'][0]['type'] );
        $this->assertSame( 1700.0, $result['totals']['distance'] );
    }
    public function test_mixed_stroke_only_allowed_outside_ladders() {
        $document = $this->ladder();
        $document['sections'][0]['children'][0]['rungs'][0]['children'][0]['stroke'] = 'MX';
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout::normalize( $document ) ) );
        $document['sections'][0]['children'] = array( $this->step( 100, 'MX' ) );
        $result = \SwimLogEvaluation\Structured_Workout::summarize( $document );
        $this->assertSame( 100.0, $result['totals']['by_stroke']['MX'] );
    }
    public function test_butterfly_alias_and_unspecified_rest() {
        $document = array( 'unit' => 'yd', 'sections' => array( array( 'type' => 'section', 'label' => 'Warm-up',
            'children' => array( $this->step( 50, 'BU' ) ) ) ) );
        $result = \SwimLogEvaluation\Structured_Workout::summarize( $document );
        $this->assertSame( 'FL', $result['document']['sections'][0]['children'][0]['stroke'] );
        $this->assertNull( $result['document']['sections'][0]['children'][0]['rest'] );
        $this->assertSame( 'yd', $result['unit'] );
    }
    public function test_rejects_invalid_distance_and_repeat() {
        $document = $this->ladder();
        $document['sections'][0]['children'][0]['rungs'][0]['count'] = 0;
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout::normalize( $document ) ) );
        $document['sections'][0]['children'][0]['rungs'][0]['count'] = 6;
        $document['sections'][0]['children'][0]['rungs'][0]['children'][0]['distance'] = -25;
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout::normalize( $document ) ) );
    }
}
