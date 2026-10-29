<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Training_Plan_Parser as Parser;
use SwimLogEvaluation\Training_Plan_Review as Review;
require_once dirname( __DIR__ ) . '/includes/class-structured-workout.php';
require_once dirname( __DIR__ ) . '/includes/class-training-plan-parser.php';
require_once dirname( __DIR__ ) . '/includes/class-training-plan-review.php';
final class TrainingPlanReviewApprovalV12Test extends TestCase {
    private function candidate() {
        return Parser::parse( "Workout 1\n100 yards free\nCoach annotation\nTotal: 200 yards" )['workouts'][0];
    }
    private function input() {
        $doc = $this->candidate()['document'];
        $doc['sections'][0]['children'][0]['distance'] = 200;
        return array( 'reviewed' => '1', 'document' => wp_json_encode( $doc ), 'total' => '200', 'unit' => 'yd' );
    }
    public function test_manual_review_accepts_explicitly_corrected_structure_and_retains_original_evidence() {
        $candidate = $this->candidate();
        $this->assertFalse( Review::assess( $candidate )['ready'] );
        $result = Review::approve( $candidate, $this->input() );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertSame( 200.0, $result['calculated_distance'] );
        $this->assertSame( $candidate['source_lines'], $result['manual_review']['source_lines'] );
        $this->assertNotEmpty( $result['manual_review']['original_flags'] );
    }
    public function test_selection_is_not_approval_and_approval_is_not_total_validation() {
        foreach ( array( 'reviewed' => '', 'total' => '100', 'unit' => 'm', 'document' => '{bad json' ) as $key => $value ) {
            $input = $this->input(); $input[$key] = $value;
            $this->assertInstanceOf( WP_Error::class, Review::approve( $this->candidate(), $input ) );
        }
        $this->assertInstanceOf( WP_Error::class, Review::approve( $this->candidate(), array() ) );
    }
    public function test_missing_stated_total_requires_explicit_reviewed_distance_and_unit() {
        $candidate = Parser::parse( "Workout 1\n100 yards free" )['workouts'][0];
        $input = $this->input();
        $result = Review::approve( $candidate, $input );
        $this->assertFalse( is_wp_error( $result ) );
        $this->assertNull( $result['manual_review']['original_stated_total'] );
        foreach ( array( 'NaN', '-1', '0', '200 yards', '2e2' ) as $invalid ) {
            $input['total'] = $invalid;
            $this->assertInstanceOf( WP_Error::class, Review::approve( $candidate, $input ) );
        }
    }
}
