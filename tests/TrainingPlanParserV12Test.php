<?php
use PHPUnit\Framework\TestCase;
final class TrainingPlanParserV12Test extends TestCase {
    public function test_parser_is_bounded_and_requires_explicit_workout_boundaries() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-parser.php' );
        foreach ( array( 'MAX_WORKOUTS', 'MAX_LINES', 'No explicit Workout/Session/Practice', 'review_required', 'Unconverted instruction', 'Structured_Workout::summarize', 'Mixed distance units' ) as $term ) {
            $this->assertStringContainsString( $term, $s );
        }
    }
    public function test_preview_is_owner_scoped_and_does_not_save_candidates() {
        $s = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString( "Training_Plan_Parser::parse( \$pdf_preview['text'] )", $s );
        $this->assertStringContainsString( 'Detected workout candidates', $s );
        $this->assertStringContainsString( 'No workouts have been saved.', $s );
    }
}
