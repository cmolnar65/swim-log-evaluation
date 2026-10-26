<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Training_Trends;

final class TrainingTrendsTest extends TestCase {
	private function workout( $overrides = array() ) {
		return (object) array_merge(
			array(
				'id' => 1,
				'workout_start' => '2026-10-05 06:00:00',
				'workout_type' => 'pool',
				'total_distance_m' => 2000,
				'elapsed_time_ms' => 3600000,
				'moving_time_ms' => 3000000,
				'pool_length_unit' => 'm',
			),
			$overrides
		);
	}

	private function length( $overrides = array() ) {
		return (object) array_merge(
			array(
				'workout_id' => 1,
				'workout_start' => '2026-10-05 06:00:00',
				'pool_length_unit' => 'm',
				'distance_m' => 25,
				'elapsed_time_ms' => 20000,
				'stroke' => 'BR',
				'length_type' => 'active',
			),
			$overrides
		);
	}

	private function window() {
		return array(
			'months' => 1,
			'previous_start' => '2026-08-26 00:00:00',
			'current_start' => '2026-09-26 00:00:00',
			'end' => '2026-10-26 00:00:00',
		);
	}

	public function test_current_period_summarizes_pool_and_open_water_load() {
		$data = Training_Trends::aggregate_rows(
			array(
				$this->workout(),
				$this->workout( array( 'id' => 2, 'workout_start' => '2026-10-12 06:00:00', 'workout_type' => 'open_water', 'total_distance_m' => 3000, 'elapsed_time_ms' => 4000000, 'moving_time_ms' => 3900000, 'pool_length_unit' => null ) ),
			),
			array(),
			$this->window()
		);
		$this->assertSame( 2, $data['current']['workout_count'] );
		$this->assertSame( 5000.0, $data['current']['distance_m'] );
		$this->assertSame( 2000.0, $data['current']['pool_distance_m'] );
		$this->assertSame( 3000.0, $data['current']['open_water_distance_m'] );
		$this->assertSame( 6900000, $data['current']['active_time_ms'] );
		$this->assertSame( 1, $data['current']['pool_workouts'] );
		$this->assertSame( 1, $data['current']['open_water_workouts'] );
	}

	public function test_previous_equal_period_is_kept_out_of_current_trends() {
		$data = Training_Trends::aggregate_rows(
			array(
				$this->workout( array( 'id' => 10, 'workout_start' => '2026-09-05 06:00:00', 'total_distance_m' => 1000, 'moving_time_ms' => 1000000 ) ),
				$this->workout( array( 'id' => 11, 'workout_start' => '2026-10-05 06:00:00', 'total_distance_m' => 1500, 'moving_time_ms' => 1500000 ) ),
			),
			array(),
			$this->window()
		);
		$this->assertSame( 1, $data['current']['workout_count'] );
		$this->assertSame( 1, $data['previous']['workout_count'] );
		$this->assertSame( 1500.0, $data['current']['distance_m'] );
		$this->assertSame( 1000.0, $data['previous']['distance_m'] );
		$this->assertEqualsWithDelta( 50.0, $data['comparison']['distance_percent'], 0.001 );
		$this->assertCount( 1, $data['weekly'] );
		$this->assertCount( 1, $data['monthly'] );
	}

	public function test_frequency_uses_full_selected_period_not_first_to_last_workout() {
		$data = Training_Trends::aggregate_rows(
			array( $this->workout() ),
			array(),
			$this->window()
		);
		$expected = 1 / ( ( strtotime( '2026-10-26 00:00:00' ) - strtotime( '2026-09-26 00:00:00' ) ) / Training_Trends::WEEK_SECONDS );
		$this->assertEqualsWithDelta( $expected, $data['current']['workouts_per_week'], 0.0001 );
	}

	public function test_rolling_four_week_average_uses_available_weekly_buckets() {
		$workouts = array(
			$this->workout( array( 'id' => 1, 'workout_start' => '2026-09-28 06:00:00', 'total_distance_m' => 1000 ) ),
			$this->workout( array( 'id' => 2, 'workout_start' => '2026-10-05 06:00:00', 'total_distance_m' => 2000 ) ),
			$this->workout( array( 'id' => 3, 'workout_start' => '2026-10-12 06:00:00', 'total_distance_m' => 3000 ) ),
			$this->workout( array( 'id' => 4, 'workout_start' => '2026-10-19 06:00:00', 'total_distance_m' => 4000 ) ),
		);
		$data = Training_Trends::aggregate_rows( $workouts, array(), $this->window() );
		$this->assertCount( 4, $data['weekly'] );
		$this->assertEqualsWithDelta( 2500.0, $data['weekly'][3]['rolling_4_week_avg_distance_m'], 0.001 );
		$this->assertEqualsWithDelta( 1.0, $data['weekly'][3]['rolling_4_week_avg_workouts'], 0.001 );
	}

	public function test_pool_stroke_and_course_volume_preserve_native_units() {
		$data = Training_Trends::aggregate_rows(
			array(),
			array(
				$this->length(),
				$this->length( array( 'workout_id' => 2, 'pool_length_unit' => 'yd', 'distance_m' => 22.86, 'elapsed_time_ms' => 19000, 'stroke' => 'FR' ) ),
				$this->length( array( 'workout_id' => 3, 'distance_m' => 0, 'length_type' => 'rest', 'elapsed_time_ms' => 30000, 'stroke' => '' ) ),
			),
			$this->window()
		);
		$this->assertEqualsWithDelta( 25.0, $data['stroke_volume']['m']['BR']['distance_native'], 0.001 );
		$this->assertEqualsWithDelta( 25.0, $data['stroke_volume']['yd']['FR']['distance_native'], 0.001 );
		$this->assertSame( 20000, $data['stroke_volume']['m']['BR']['active_time_ms'] );
		$this->assertSame( 1, $data['pool_course']['m']['length_count'] );
		$this->assertSame( 1, $data['pool_course']['yd']['length_count'] );
	}

	public function test_zero_previous_period_returns_new_activity_instead_of_fake_percentage() {
		$data = Training_Trends::aggregate_rows( array( $this->workout() ), array(), $this->window() );
		$this->assertNull( $data['comparison']['distance_percent'] );
		$this->assertNull( $data['comparison']['workouts_percent'] );
	}

	public function test_queries_are_user_scoped_and_admin_explains_load_limits() {
		$service = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-trends.php' );
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$analytics_admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-analytics.php' );
		$this->assertStringContainsString( 'WHERE user_id=%d', $service );
		$this->assertStringContainsString( 'WHERE w.user_id=%d', $service );
		$this->assertStringContainsString( "w.workout_type='pool'", $service );
		$this->assertStringContainsString( "n.length_type='active'", $service );
		$this->assertStringContainsString( "'swimlog-training-trends', 'training_trends'", $admin );
		$this->assertStringContainsString( 'public function training_trends()', $admin );
		$this->assertStringContainsString( 'does not estimate physiological stress, recovery, fitness, or injury risk', $analytics_admin );
		$this->assertStringContainsString( 'Course and stroke tables preserve native meter/yard units.', $analytics_admin );
	}
}
