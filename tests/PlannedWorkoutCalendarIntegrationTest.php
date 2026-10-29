<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Calendar;

final class PlannedWorkoutCalendarIntegrationTest extends TestCase {
	private function range() { return array( 'start'=>'2026-11-01','end'=>'2026-11-30','end_exclusive'=>'2026-12-01','start_datetime'=>'2026-11-01 00:00:00','end_exclusive_datetime'=>'2026-12-01 00:00:00','timezone'=>'America/Denver' ); }
	public function test_calendar_model_normalizes_planned_workout_without_device_or_notes_fields() {
		$plan=(object)array('id'=>7,'planned_date'=>'2026-11-03','planned_start_time'=>'06:30:00','title'=>'Breaststroke Main Set','workout_type'=>'pool','planned_distance_m'=>'2500','planned_duration_ms'=>3600000,'pool_length_m'=>'22.86','pool_course'=>'yd','primary_stroke'=>'BR','workout_instructions'=>"200 FR Warmup\n5x100 BR",'notes'=>'private contextual note','status'=>'planned','completed_workout_id'=>null);
		$data=Calendar::assemble_rows($this->range(),array(),array(),'sunday',array($plan));
		$this->assertCount(1,$data['planned_workouts']);$row=$data['planned_workouts'][0];
		$this->assertSame('planned_workout',$row['kind']);$this->assertSame("200 FR Warmup\n5x100 BR",$row['workout_instructions']);
		$this->assertArrayNotHasKey('notes',$row);$this->assertArrayNotHasKey('device_model',$row);$this->assertArrayNotHasKey('source_type',$row);
	}
	public function test_calendar_service_reads_plans_through_owner_scoped_range_service() {
		$source=file_get_contents(dirname(__DIR__).'/includes/class-calendar.php');
		$this->assertStringContainsString("require_once SWIMLOG_EVALUATION_DIR . 'includes/class-planned-workout.php'",$source);
		$this->assertStringContainsString('Planned_Workout::range_for_user(',$source);
		$this->assertStringContainsString("'planned_workouts' => \$planned_rows",$source);
	}
	public function test_server_month_and_client_month_week_agenda_render_planned_workouts_distinctly() {
		$blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$css=file_get_contents(dirname(__DIR__).'/assets/css/calendar.css');
		$this->assertStringContainsString("foreach ( \$data['planned_workouts'] as \$planned_workout )",$blocks);
		$this->assertStringContainsString('calendar_planned_workout_html',$blocks);
		$this->assertStringContainsString('data-calendar-planned-workout=',$blocks);
		$this->assertGreaterThanOrEqual(3,substr_count($view,'data.planned_workouts || []'));
		$this->assertStringContainsString('swimlog-calendar-planned-workout',$view);
		$this->assertStringContainsString('button.swimlog-calendar-planned-workout',$css);
	}
	public function test_planned_workout_detail_exposes_complete_instructions_in_calendar() {
		$view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
		$this->assertStringContainsString('function showPlannedWorkoutDetail',$view);
		$this->assertStringContainsString("label.textContent = 'Workout Instructions'",$view);
		$this->assertStringContainsString('instructions.textContent = plan.workout_instructions',$view);
		$this->assertStringContainsString("event.target.closest( '[data-calendar-planned-workout]' )",$view);
	}
	public function test_phase_four_does_not_change_database_schema_version_or_calendar_privacy() {
		$main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');$blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
		$this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.3'",$main);
		$start=strpos($blocks,'private static function calendar_allowed');$end=strpos($blocks,'private static function calendar_month_html',$start);$body=substr($blocks,$start,$end-$start);
		$this->assertStringContainsString("'swimlog_view_own_results'",$body);$this->assertStringContainsString("'swimlog_manage_all_users'",$body);$this->assertStringNotContainsString('swimlog_public_results',$body);
	}
}
