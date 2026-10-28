<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
require_once dirname( __DIR__ ) . '/includes/class-structured-workout-parser.php';

final class StructuredWorkoutParserV12Test extends TestCase {
    private function example() {
        return "**Workout: Freestyle / Breaststroke Endurance Ladder**\n\n"
            . "**Main Set — 1,700 meters**\n\n"
            . "**Ladder 1**\n- 6 × (25 FR + 25 BR)\n- Pace: Easy\n- Rest: 10 seconds after each repetition\n- Total: 300 meters\n\n"
            . "**Ladder 2**\n- 6 × (50 FR + 50 BR)\n- Pace: Moderate\n- Rest: 15 seconds after each repetition\n- Total: 600 meters\n\n"
            . "**Ladder 3**\n- 4 × (100 FR + 100 BR)\n- Pace: Moderate to Threshold\n- Rest: 30 seconds after each repetition\n- Total: 800 meters\n\n"
            . "**Workout Summary**\n- Total Distance: 1,700 meters\n- Freestyle: 850 meters\n- Breaststroke: 850 meters\n- Equipment: None\n- Focus: Aerobic endurance.";
    }
    public function test_written_ladder_preserves_repeats_and_stroke_totals() {
        $result = \SwimLogEvaluation\Structured_Workout_Parser::parse( $this->example() );
        $this->assertFalse( is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
        $this->assertSame( 1700.0, $result['totals']['distance'] );
        $this->assertSame( 850.0, $result['totals']['by_stroke']['FR'] );
        $this->assertSame( 850.0, $result['totals']['by_stroke']['BR'] );
        $this->assertSame( 32, $result['totals']['expanded_steps'] );
        $this->assertCount( 3, $result['document']['sections'][0]['children'] );
        $this->assertSame( 'ladder', $result['document']['sections'][0]['children'][0]['type'] );
        $this->assertSame( '10 seconds after each repetition', $result['document']['sections'][0]['children'][0]['rungs'][0]['rest'] );
    }
    public function test_ambiguous_text_is_not_silently_converted() {
        $text = "Main Set — 100 meters\nSwim a hard 100";
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout_Parser::parse( $text ) ) );
    }
    public function test_conflicting_units_and_incorrect_totals_are_rejected() {
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout_Parser::parse( $this->example(), 'yd' ) ) );
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout_Parser::parse( str_replace( 'Total: 600 meters', 'Total: 700 meters', $this->example() ) ) ) );
    }
    public function test_no_unit_is_not_guessed() {
        $this->assertTrue( is_wp_error( \SwimLogEvaluation\Structured_Workout_Parser::parse( "Ladder 1\n6 x (25 FR + 25 BR)" ) ) );
        $result = \SwimLogEvaluation\Structured_Workout_Parser::parse( "Ladder 1\n6 x (25 FR + 25 BR)", 'yd' );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertSame( 'yd', $result['unit'] );
    }
}
