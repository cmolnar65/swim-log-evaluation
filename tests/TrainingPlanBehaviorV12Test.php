<?php
use PHPUnit\Framework\TestCase;

/**
 * Behavioral parser/reconciliation tests: exercise real production methods,
 * not just source-code string matching. No WordPress database required.
 */
final class TrainingPlanBehaviorV12Test extends TestCase {
    public static function setUpBeforeClass(): void {
        if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', '/' );
        if ( ! class_exists( 'WP_Error' ) ) {
            eval( 'class WP_Error { private $code; private $message; public function __construct($code,$message){$this->code=$code;$this->message=$message;} public function get_error_message(){return $this->message;} public function get_error_code(){return $this->code;} }' );
        }
        if ( ! function_exists( 'is_wp_error' ) ) {
            eval( 'function is_wp_error($value){return $value instanceof WP_Error;}' );
        }
        require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
        require_once dirname( __DIR__ ) . '/includes/class-training-plan-parser.php';
        require_once dirname( __DIR__ ) . '/includes/class-training-plan-review.php';
    }

    public function test_two_workouts_with_explicit_totals_and_nonconsecutive_weeks() {
        $text = "Week 1\nWorkout 1: Freestyle\n4 x 100 yards free\nTotal: 400 yards\nWeek 3\nWorkout 2: Breaststroke\n2 x 200 meters breaststroke\nTotal: 400 meters";
        $parsed = \SwimLogEvaluation\Training_Plan_Parser::parse( $text );
        $this->assertFalse( is_wp_error( $parsed ) );
        $this->assertCount( 2, $parsed['workouts'] );
        $this->assertSame( 1, $parsed['workouts'][0]['week'] );
        $this->assertSame( 3, $parsed['workouts'][1]['week'] );
        foreach ( $parsed['workouts'] as $candidate ) {
            $review = \SwimLogEvaluation\Training_Plan_Review::assess( $candidate );
            $this->assertTrue( $review['ready'], implode( '; ', $review['flags'] ) );
            $this->assertSame( 'match', $review['status'] );
            $this->assertEquals( 400, $review['calculated'] );
        }
    }

    public function test_mismatched_total_is_blocked() {
        $parsed = \SwimLogEvaluation\Training_Plan_Parser::parse( "Workout 1\n2 x 100 yards free\nTotal: 300 yards" );
        $review = \SwimLogEvaluation\Training_Plan_Review::assess( $parsed['workouts'][0] );
        $this->assertFalse( $review['ready'] );
        $this->assertSame( 'mismatch', $review['status'] );
        $this->assertEquals( -100, $review['difference'] );
    }

    public function test_ambiguous_line_is_not_silently_imported() {
        $parsed = \SwimLogEvaluation\Training_Plan_Parser::parse( "Workout 1\n200 yards free\nSwim easy until tired\nTotal: 200 yards" );
        $review = \SwimLogEvaluation\Training_Plan_Review::assess( $parsed['workouts'][0] );
        $this->assertFalse( $review['ready'] );
        $this->assertNotEmpty( $review['flags'] );
    }

    public function test_missing_boundaries_or_total_cannot_be_approved() {
        $missing = \SwimLogEvaluation\Training_Plan_Parser::parse( "Week 1\n200 yards free" );
        $this->assertCount( 0, $missing['workouts'] );
        $parsed = \SwimLogEvaluation\Training_Plan_Parser::parse( "Workout 1\n200 yards free" );
        $this->assertFalse( \SwimLogEvaluation\Training_Plan_Review::assess( $parsed['workouts'][0] )['ready'] );
    }

    public function test_mixed_units_and_numbering_gaps_require_review() {
        $parsed = \SwimLogEvaluation\Training_Plan_Parser::parse( "Workout 2\n100 yards free\n100 meters free\nTotal: 200 yards" );
        $review = \SwimLogEvaluation\Training_Plan_Review::assess( $parsed['workouts'][0] );
        $this->assertFalse( $review['ready'] );
        $this->assertNotEmpty( $review['flags'] );
    }
    public function test_usms_table_headings_from_all_four_uploaded_plans() {
        $samples = array(
            "WEEK 1 of 6 Swim #1 of 24    *3x: 50 free, 100 free, 150 free",
            "1 of 6          Swim #1 of 24    Do 1x (rest :10 for all):",
            "WEEK 1 of 6 Swim #1 of 24    Repeat 2x (rest :20 btw reps)",
            "1 of 6          Swim #1 of 24    Warm-up (rest :10 for all):",
        );
        foreach ( $samples as $heading ) {
            $parsed = \\SwimLogEvaluation\\Training_Plan_Parser::parse( $heading . "\\n200 yards free\\nTotal: 200 yards" );
            $this->assertCount( 1, $parsed['workouts'] );
            $this->assertSame( 1, $parsed['workouts'][0]['source_number'] );
            $this->assertSame( 1, $parsed['workouts'][0]['week'] );
        }
    }

}
