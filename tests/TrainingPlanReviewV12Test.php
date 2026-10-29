<?php
use PHPUnit\Framework\TestCase;
final class TrainingPlanReviewV12Test extends TestCase {
    public function test_review_requires_explicit_stated_total_and_matching_unit() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-review.php' );
        foreach ( array( 'unit_mismatch', 'mismatch', 'No explicit stated total detected.', 'Multiple stated totals detected', "'ready' => 'match' ===" ) as $term ) {
            $this->assertStringContainsString( $term, $s );
        }
    }
    public function test_review_screen_displays_original_instructions_and_difference() {
        $s = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        foreach ( array( 'Training_Plan_Review::assess', 'Original instructions', 'Stated total:', 'Calculated total:', 'Difference:', 'Requires manual review before approval or import.' ) as $term ) {
            $this->assertStringContainsString( $term, $s );
        }
    }
}
