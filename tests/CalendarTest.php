<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Calendar;

final class CalendarTest extends TestCase {
	private function range() {
		return array(
			'start' => '2026-10-01',
			'end' => '2026-10-31',
			'end_exclusive' => '2026-11-01',
			'start_datetime' => '2026-10-01 00:00:00',
			'end_exclusive_datetime' => '2026-11-01 00:00:00',
			'timezone' => 'America/Denver',
		);
	}

	private function workout( $overrides = array() ) {
		return (object) array_merge(
			array(
				'id' => 11,
				'user_id' => 42,
				'workout_type' => 'pool',
				'source_type' => 'garmin',
				'workout_start' => '2026-10-05 06:15:00',
				'workout_end' => '2026-10-05 07:15:00',
				'location_name' => 'Community Pool',
				'total_distance_m' => 2560.32,
				'original_distance' => 2800,
				'original_distance_unit' => 'yd',
				'elapsed_time_ms' => 3600000,
				'moving_time_ms' => 3300000,
				'original_pool_length' => 25,
				'pool_length_unit' => 'yd',
				'primary_stroke' => 'MIXED',
				'notes' => 'Private note',
				'device_model' => 'Watch',
			),
			$overrides
		);
	}

	private function event( $overrides = array() ) {
		return (object) array_merge(
			array(
				'id' => 7,
				'user_id' => 42,
				'event_name' => 'October Meet',
				'event_date' => '2026-10-18',
				'event_time' => '09:00:00',
				'location_name' => 'Competition Pool',
				'distance_value' => 100,
				'course_unit' => 'm',
				'stroke' => 'BR',
				'notes' => 'Private event note',
			),
			$overrides
		);
	}

	public function test_calendar_model_normalizes_existing_workouts_and_events() {
		$data = Calendar::assemble_rows( $this->range(), array( $this->workout() ), array( $this->event() ) );
		$this->assertSame( '2026-10-01', $data['range']['start'] );
		$this->assertSame( '2026-10-31', $data['range']['end'] );
		$this->assertSame( 'America/Denver', $data['range']['timezone'] );
		$this->assertSame( 'workout', $data['workouts'][0]['kind'] );
		$this->assertSame( 'pool', $data['workouts'][0]['workout_type'] );
		$this->assertSame( 2800.0, $data['workouts'][0]['original_distance'] );
		$this->assertSame( 'yd', $data['workouts'][0]['pool_length_unit'] );
		$this->assertSame( 'event', $data['events'][0]['kind'] );
		$this->assertSame( 'October Meet', $data['events'][0]['event_name'] );
		$this->assertSame( 'BR', $data['events'][0]['stroke'] );
	}

	public function test_manual_and_imported_workouts_receive_the_same_calendar_fields() {
		$imported = $this->workout( array( 'id' => 1, 'source_type' => 'garmin' ) );
		$manual = $this->workout( array( 'id' => 2, 'source_type' => 'manual' ) );
		$data = Calendar::assemble_rows( $this->range(), array( $imported, $manual ), array() );
		$imported_keys = array_keys( $data['workouts'][0] );
		$manual_keys = array_keys( $data['workouts'][1] );
		$this->assertSame( $imported_keys, $manual_keys );
		$this->assertArrayNotHasKey( 'source_type', $data['workouts'][0] );
		$this->assertArrayNotHasKey( 'source_type', $data['workouts'][1] );
	}

	public function test_calendar_model_excludes_private_and_device_source_details() {
		$data = Calendar::assemble_rows( $this->range(), array( $this->workout() ), array( $this->event() ) );
		foreach ( array( 'user_id', 'source_type', 'notes', 'device_model', 'device_manufacturer', 'latitude', 'longitude' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $data['workouts'][0], 'workout: ' . $key );
		}
		foreach ( array( 'user_id', 'notes', 'latitude', 'longitude' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $data['events'][0], 'event: ' . $key );
		}
	}

	public function test_missing_optional_values_remain_null_instead_of_being_manufactured() {
		$data = Calendar::assemble_rows(
			$this->range(),
			array( $this->workout( array(
				'total_distance_m' => null,
				'original_distance' => null,
				'elapsed_time_ms' => null,
				'moving_time_ms' => null,
				'primary_stroke' => null,
			) ) ),
			array()
		);
		$this->assertNull( $data['workouts'][0]['total_distance_m'] );
		$this->assertNull( $data['workouts'][0]['original_distance'] );
		$this->assertNull( $data['workouts'][0]['elapsed_time_ms'] );
		$this->assertNull( $data['workouts'][0]['moving_time_ms'] );
		$this->assertNull( $data['workouts'][0]['primary_stroke'] );
	}

	public function test_invalid_and_reversed_ranges_are_rejected_before_data_access() {
		$invalid = Calendar::for_user( 42, '2026-02-30', '2026-03-01' );
		$this->assertTrue( is_wp_error( $invalid ) );
		$this->assertSame( 'swimlog_calendar_date', $invalid->get_error_code() );

		$reversed = Calendar::for_user( 42, '2026-10-31', '2026-10-01' );
		$this->assertTrue( is_wp_error( $reversed ) );
		$this->assertSame( 'swimlog_calendar_range', $reversed->get_error_code() );
	}

	public function test_calendar_week_start_is_swimmer_specific_and_constrained() {
		global $swimlog_test_user_meta;
		$swimlog_test_user_meta = array(
			42 => array( 'swimlog_calendar_first_day' => 'saturday' ),
			43 => array( 'swimlog_calendar_first_day' => 'monday' ),
			44 => array( 'swimlog_calendar_first_day' => 'tuesday' ),
		);
		$this->assertSame( 'saturday', Calendar::first_day_for_user( 42 ) );
		$this->assertSame( 'monday', Calendar::first_day_for_user( 43 ) );
		$this->assertSame( 'sunday', Calendar::first_day_for_user( 44 ) );
		$this->assertSame( 'sunday', Calendar::first_day_for_user( 45 ) );
		$swimlog_test_user_meta = array();
	}

	public function test_weekly_summaries_use_shared_recorded_metric_rules() {
		$workouts = array(
			$this->workout( array( 'id' => 1, 'workout_start' => '2026-10-04 06:00:00', 'total_distance_m' => 2000, 'moving_time_ms' => 3000000, 'elapsed_time_ms' => 3600000 ) ),
			$this->workout( array( 'id' => 2, 'workout_start' => '2026-10-05 06:00:00', 'workout_type' => 'open_water', 'total_distance_m' => 3000, 'moving_time_ms' => 0, 'elapsed_time_ms' => 4000000 ) ),
		);
		$data = Calendar::assemble_rows( $this->range(), $workouts, array(), 'sunday' );
		$this->assertSame( 'sunday', $data['range']['first_day'] );
		$this->assertCount( 5, $data['weeks'] );
		$this->assertSame( '2026-10-04', $data['weeks'][1]['start'] );
		$this->assertSame( '2026-10-10', $data['weeks'][1]['end'] );
		$this->assertSame( 2, $data['weeks'][1]['workout_count'] );
		$this->assertSame( 5000.0, $data['weeks'][1]['distance_m'] );
		$this->assertSame( 7000000, $data['weeks'][1]['active_time_ms'] );
		$this->assertSame( 2000.0, $data['weeks'][1]['pool_distance_m'] );
		$this->assertSame( 3000.0, $data['weeks'][1]['open_water_distance_m'] );
		$this->assertSame( 'recorded/normalized', $data['metric_metadata']['distance_m']['provenance'] );
		$this->assertSame( 'moving time when present, otherwise elapsed time', $data['metric_metadata']['active_time_ms']['source'] );
		$this->assertArrayNotHasKey( 'workouts_per_week', $data['weeks'][1] );
	}

	public function test_week_boundaries_honor_saturday_sunday_and_monday_preferences() {
		$workouts = array(
			$this->workout( array( 'id' => 1, 'workout_start' => '2026-10-03 06:00:00', 'total_distance_m' => 1000 ) ),
			$this->workout( array( 'id' => 2, 'workout_start' => '2026-10-04 06:00:00', 'total_distance_m' => 2000 ) ),
			$this->workout( array( 'id' => 3, 'workout_start' => '2026-10-05 06:00:00', 'total_distance_m' => 3000 ) ),
		);
		$saturday = Calendar::weekly_summaries( $this->range(), $workouts, 'saturday' );
		$sunday = Calendar::weekly_summaries( $this->range(), $workouts, 'sunday' );
		$monday = Calendar::weekly_summaries( $this->range(), $workouts, 'monday' );
		$this->assertSame( 6000.0, $saturday[1]['distance_m'] );
		$this->assertSame( 5000.0, $sunday[1]['distance_m'] );
		$this->assertSame( 3000.0, $monday[1]['distance_m'] );
	}

	public function test_weekly_summaries_include_empty_calendar_weeks_without_fabricated_metrics() {
		$weeks = Calendar::weekly_summaries( $this->range(), array(), 'monday' );
		$this->assertCount( 5, $weeks );
		foreach ( $weeks as $week ) {
			$this->assertSame( 0, $week['workout_count'] );
			$this->assertSame( 0.0, $week['distance_m'] );
			$this->assertSame( 0, $week['active_time_ms'] );
			$this->assertSame( 0.0, $week['pool_distance_m'] );
			$this->assertSame( 0.0, $week['open_water_distance_m'] );
		}
	}

	public function test_range_queries_are_owner_scoped_and_use_half_open_indexable_boundaries() {
		$workout = file_get_contents( dirname( __DIR__ ) . '/includes/class-workout.php' );
		$event = file_get_contents( dirname( __DIR__ ) . '/includes/class-event.php' );
		$this->assertStringContainsString( 'public static function range_for_user', $workout );
		$this->assertStringContainsString( 'w.user_id = %d AND w.workout_start >= %s AND w.workout_start < %s', $workout );
		$this->assertStringNotContainsString( 'DATE(w.workout_start)', substr( $workout, strpos( $workout, 'public static function range_for_user' ), 1500 ) );
		$this->assertStringContainsString( 'public static function range_for_user', $event );
		$this->assertStringContainsString( 'e.user_id = %d AND e.event_date >= %s AND e.event_date < %s', $event );
	}

	public function test_calendar_service_does_not_create_or_write_calendar_storage() {
		$calendar = file_get_contents( dirname( __DIR__ ) . '/includes/class-calendar.php' );
		$database = file_get_contents( dirname( __DIR__ ) . '/includes/class-database.php' );
		$this->assertStringContainsString( 'Workout::range_for_user', $calendar );
		$this->assertStringContainsString( 'Event::range_for_user', $calendar );
		$this->assertStringNotContainsString( '\\$wpdb', $calendar );
		$this->assertStringNotContainsString( "'calendar'", $database );
		$this->assertStringNotContainsString( 'swimlog_calendar', $database );
	}

	public function test_phase_one_does_not_change_database_version() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
		$this->assertStringContainsString( "define( 'SWIMLOG_EVALUATION_DB_VERSION', '0.9' );", $plugin );
	}
}
