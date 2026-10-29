<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Training_Plan_Parser as Parser;
use SwimLogEvaluation\Training_Plan_Review as Review;
use SwimLogEvaluation\Structured_Workout as Structure;
require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
require_once dirname( __DIR__ ) . '/includes/class-training-plan-parser.php';
require_once dirname( __DIR__ ) . '/includes/class-training-plan-review.php';
/** Invented notation samples only; never copy confidential plan text into Git. */
final class TrainingPlanCompactNotationV12Test extends TestCase {
    public function test_table_heading_repeat_metadata_columns_implicit_units_and_subtotals() {
        $text = "WEEK 1 of 6 Swim #1 of 24\tRepeat 3x (rest :15 btw reps)\t450\n"
            . "Skills: balance\t50 EZ choice\nFitness: endurance\t100 kick on board as 25 breast + 25 flutter\nEquipment: fins,\nsnorkel (optional)\n"
            . "1x (rest :15 btw reps)\t200\n5x20: ODD up-down breast; EVEN free, steady\n100 back, easy\n"
            . "Repeat 2x (rest :10 btw reps)\t300\n3x25 breast kick on board\n75 free, steady\n"
            . "1x (go when ready)\t50\n50 EZ choice\nTOTAL\t1,000 yds";
        $candidate = Parser::parse( $text )['workouts'][0];
        $this->assertSame( 1000.0, $candidate['calculated_distance'] );
        $this->assertSame( 'yd', $candidate['unit'] );
        $this->assertSame( array(), $candidate['flags'] );
        $this->assertTrue( Review::assess( $candidate )['ready'] );
        $sections = $candidate['document']['sections'];
        $this->assertCount( 4, $sections );
        $this->assertSame( 3, $sections[0]['children'][0]['count'] );
        $this->assertStringContainsString( 'optional', $sections[0]['description'] );
        $this->assertSame( 'go when ready', $sections[3]['description'] );
    }
    public function test_odd_even_order_drill_flags_and_remainder_are_not_collapsed() {
        $candidate = Parser::parse( "Workout 1\n5x20: solid: ODD up-down breast, EVEN free\nTOTAL 100 yards" )['workouts'][0];
        $nodes = $candidate['document']['sections'][0]['children'][0]['children'];
        $this->assertSame( 2, $nodes[0]['count'] );
        $this->assertSame( 'BR', $nodes[0]['children'][0]['stroke'] );
        $this->assertTrue( $nodes[0]['children'][0]['drill'] );
        $this->assertSame( 'FR', $nodes[0]['children'][1]['stroke'] );
        $this->assertFalse( $nodes[0]['children'][1]['drill'] );
        $this->assertSame( 'BR', $nodes[1]['stroke'] );
        $this->assertSame( 100.0, $candidate['calculated_distance'] );
    }
    public function test_split_lengths_keep_outer_distance_and_rest_only_at_end_of_repetition() {
        $candidate = Parser::parse( "Workout 1\nRepeat 2x (rest :15 btw reps) 200\n100 swim as 25 up-down breast + 25 back\nTOTAL 200 yds" )['workouts'][0];
        $this->assertSame( 200.0, $candidate['calculated_distance'] );
        $split = $candidate['document']['sections'][0]['children'][0]['children'][0];
        $this->assertSame( '0', $split['children'][0]['children'][1]['rest'] );
        $this->assertSame( ':15', $split['children'][2]['rest'] );
        $this->assertSame( 'BK', $split['children'][2]['stroke'] );
        $this->assertFalse( $split['children'][2]['drill'] );
    }
    public function test_section_and_workout_mismatches_remain_reviewable_without_padding() {
        $candidate = Parser::parse( "Workout 1\nRepeat 2x (rest :15 btw reps) 250\n100 free\nTOTAL 300 yards" )['workouts'][0];
        $this->assertSame( 200.0, $candidate['calculated_distance'] );
        $this->assertNotEmpty( $candidate['flags'] );
        $this->assertSame( 'mismatch', Review::assess( $candidate )['status'] );
        $this->assertFalse( Review::assess( $candidate )['ready'] );
    }
    public function test_missing_units_unknown_prose_and_nondivisible_splits_are_not_guessed() {
        foreach ( array( "Workout 1\n50 free", "Workout 1\n75 swim as 25 breast + 25 back\nTOTAL 75 yards", "Workout 1\n100 mystery free instruction\nTOTAL 100 yards" ) as $text ) {
            $candidate = Parser::parse( $text )['workouts'][0];
            $this->assertNull( $candidate['document'] );
            $this->assertNotEmpty( $candidate['flags'] );
        }
    }
    public function test_page_furniture_does_not_become_a_swimming_instruction() {
        $candidate = Parser::parse( "Training Plan for Synthetic Skills\nWEEK WORKOUT SWIM SETS SWIM TRACKER\nWorkout 1\n100 free\nTOTAL 100 yds\n2\nU.S. Masters Swimming/LaneMate Training Plan 2" )['workouts'][0];
        $this->assertSame( array(), $candidate['flags'] );
        $this->assertTrue( Review::assess( $candidate )['ready'] );
    }
    public function test_single_workout_without_number_needs_one_total_and_explicit_boundary_review() {
        $result = Parser::parse( "Skills: balance\n50 EZ choice\nTOTAL 50 yards" );
        $this->assertCount( 1, $result['workouts'] );
        $this->assertSame( 50.0, $result['workouts'][0]['calculated_distance'] );
        $this->assertFalse( Review::assess( $result['workouts'][0] )['ready'] );
    }
}
