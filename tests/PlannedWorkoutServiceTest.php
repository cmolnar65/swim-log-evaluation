<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Planned_Workout;

final class PlannedWorkoutServiceTest extends TestCase {
	private function valid_data() {
		return array(
			'planned_date' => '2026-11-03',
			'planned_start_time' => '06:30',
			'title' => 'Breaststroke / Freestyle Main Set',
			'workout_type' => 'pool',
			'planned_distance_m' => 2500,
			'planned_duration_ms' => 3600000,
			'pool_length_m' => 22.86,
			'pool_course' => 'yd',
			'primary_stroke' => 'MIXED',
			'workout_instructions' => "200 FR Warmup at 1:40/m\nrest :20\nMain Set\n5x100 BR at 1:50 rest :23 each\nrest :30\n6x100 FR at 1:30 rest :20 each",
			'notes' => "Focus on breaststroke timing.\nKeep the freestyle controlled.",
		);
	}

	public function test_validation_preserves_multiline_free_form_prescription() {
		$data = $this->valid_data();
		$validated = Planned_Workout::validate( $data );
		$this->assertFalse( is_wp_error( $validated ) );
		$this->assertSame( $data['workout_instructions'], $validated['workout_instructions'] );
		$this->assertSame( $data['notes'], $validated['notes'] );
		$this->assertSame( '06:30:00', $validated['planned_start_time'] );
	}

	public function test_validation_rejects_invalid_core_fields_and_pool_fields_on_open_water() {
		foreach ( array(
			array( 'title' => '', 'code' => 'swimlog_planned_workout_title' ),
			array( 'planned_date' => '2026-02-30', 'code' => 'swimlog_planned_workout_date' ),
			array( 'planned_start_time' => '25:00', 'code' => 'swimlog_planned_workout_time' ),
			array( 'workout_type' => 'run', 'code' => 'swimlog_planned_workout_type' ),
			array( 'planned_distance_m' => -1, 'code' => 'swimlog_planned_workout_distance' ),
			array( 'planned_duration_ms' => 1.5, 'code' => 'swimlog_planned_workout_duration' ),
		) as $case ) {
			$data = array_merge( $this->valid_data(), $case );
			$expected = $data['code']; unset( $data['code'] );
			$error = Planned_Workout::validate( $data );
			$this->assertTrue( is_wp_error( $error ) );
			$this->assertSame( $expected, $error->get_error_code() );
		}
		$data = $this->valid_data();
		$data['workout_type'] = 'open_water';
		$error = Planned_Workout::validate( $data );
		$this->assertSame( 'swimlog_planned_workout_pool_fields', $error->get_error_code() );
	}

	public function test_service_contract_owner_scopes_every_persistence_operation() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
		$this->assertStringContainsString( 'WHERE id = %d AND user_id = %d', $code );
		$this->assertStringContainsString( 'WHERE user_id = %d AND planned_date >= %s AND planned_date < %s', $code );
		$this->assertStringContainsString( "array( 'id' => $id, 'user_id' => $user_id )", $code );
		$this->assertStringContainsString( "Workout::get_for_user( $workout_id, $user_id )", $code );
	}

	public function test_range_query_is_half_open_index_friendly_and_deterministic() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
		$start = strpos( $code, 'public static function range_for_user' );
		$end = strpos( $code, 'public static function save', $start );
		$body = substr( $code, $start, $end - $start );
		$this->assertStringContainsString( 'planned_date >= %s AND planned_date < %s', $body );
		$this->assertStringNotContainsString( 'DATE(', $body );
		$this->assertStringContainsString( 'planned_start_time ASC, id ASC', $body );
	}

	public function test_completed_status_requires_owned_recorded_workout_association() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-planned-workout.php' );
		$this->assertStringContainsString( "if ( 'completed' === $status )", $code );
		$this->assertStringContainsString( "'status' => 'completed', 'completed_workout_id' => $workout_id", $code );
		$this->assertStringContainsString( 'completed_workout_id = %d AND id <> %d', $code );
		$this->assertStringContainsString( "'status' => 'planned', 'completed_workout_id' => null", $code );
	}

	public function test_phase_two_does_not_change_database_schema_or_version() {
		$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-database.php' );
		$main = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
		$this->assertStringContainsString( "SWIMLOG_EVALUATION_DB_VERSION', '1.0'", $main );
		$this->assertSame( 1, substr_count( $db, 'CREATE TABLE $planned_workouts' ) );
	}
}
