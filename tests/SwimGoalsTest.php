<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Swim_Goals;

final class SwimGoalsTest extends TestCase {
	public function test_period_goal_keeps_selected_unit_and_normalizes_yards_for_aggregation() {
		$goal = Swim_Goals::normalize_input( array(
			'goal_type' => Swim_Goals::PERIOD,
			'course_unit' => 'yd',
			'target_distance' => '1000',
			'start_date' => '2026-11-01',
			'end_date' => '2026-11-30',
			'title' => 'November swim goal',
		) );
		$this->assertIsArray( $goal );
		$this->assertSame( 'yd', $goal['course_unit'] );
		$this->assertSame( 914.4, $goal['target_distance_m'] );
		$this->assertSame( '2026-11-01', $goal['start_date'] );
		$this->assertSame( '2026-11-30', $goal['end_date'] );
		$this->assertNull( $goal['target_distance_value'] );
	}

	public function test_date_range_single_workout_option_uses_existing_goal_type_storage() {
		$goal = Swim_Goals::normalize_input( array(
			'goal_type' => Swim_Goals::PERIOD,
			'single_workout' => '1',
			'course_unit' => 'yd',
			'target_distance' => '5000',
			'start_date' => '2026-11-01',
			'end_date' => '2026-11-30',
		) );
		$this->assertIsArray( $goal );
		$this->assertSame( Swim_Goals::SINGLE_WORKOUT, $goal['goal_type'] );
		$this->assertSame( 4572.0, $goal['target_distance_m'] );
		$this->assertLessThanOrEqual( 24, strlen( Swim_Goals::SINGLE_WORKOUT ) );
	}

	public function test_single_performance_goal_uses_exact_course_and_accepts_any_stroke() {
		$goal = Swim_Goals::normalize_input( array(
			'goal_type' => Swim_Goals::SINGLE,
			'course_unit' => 'm',
			'target_distance_value' => '1500',
			'target_pace_ms' => '120000',
			'start_date' => '2026-11-01',
			'end_date' => '2026-12-01',
		) );
		$this->assertIsArray( $goal );
		$this->assertSame( 1500, $goal['target_distance_value'] );
		$this->assertSame( 'm', $goal['course_unit'] );
		$this->assertSame( '', $goal['stroke'] );
		$this->assertNull( $goal['target_distance_m'] );
	}

	public function test_invalid_or_reversed_goal_dates_and_unsupported_performance_distances_are_rejected() {
		$invalid_date = Swim_Goals::normalize_input( array( 'goal_type' => Swim_Goals::PERIOD, 'course_unit' => 'm', 'target_distance' => '1000', 'start_date' => '2026-02-30', 'end_date' => '2026-03-01' ) );
		$this->assertTrue( is_wp_error( $invalid_date ) );	
		$reversed = Swim_Goals::normalize_input( array( 'goal_type' => Swim_Goals::PERIOD, 'course_unit' => 'm', 'target_distance' => '1000', 'start_date' => '2026-11-30', 'end_date' => '2026-11-01' ) );
		$this->assertTrue( is_wp_error( $reversed ) );

		$unsupported = Swim_Goals::normalize_input( array( 'goal_type' => Swim_Goals::SINGLE, 'course_unit' => 'm', 'target_distance_value' => '125', 'target_pace_ms' => '120000', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30' ) );
		$this->assertTrue( is_wp_error( $unsupported ) );
	}

	public function test_goal_storage_and_progress_queries_are_owner_scoped_and_private() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-swim-goals.php' );
		$this->assertStringContainsString( 'WHERE id = %d AND user_id = %d', $source );
		$this->assertStringContainsString( 'WHERE user_id = %d ORDER BY start_date DESC', $source );
		$this->assertStringContainsString( "workout_type IN ('pool','open_water')", $source );
		$this->assertStringContainsString( "p.course_unit = %s AND p.achieved_at >= %s AND p.achieved_at < %s", $source );
		$this->assertStringNotContainsString( "AND p.stroke = %s", $source );
		$this->assertStringContainsString( 'AND user_id = %d', $source );
	}

	public function test_goal_management_uses_owner_capability_and_nonce_and_is_not_public() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-goals.php' );
		$rest = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_own_workouts' )", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'swimlog_goal_action' )", $admin );
		$this->assertStringContainsString( "current_user_can( 'swimlog_manage_all_users' )", $admin );
		$this->assertStringNotContainsString( "'/goals'", $rest );
	}
}
