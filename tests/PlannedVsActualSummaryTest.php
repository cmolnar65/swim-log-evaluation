<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Calendar;
final class PlannedVsActualSummaryTest extends TestCase {
 private function range(){return array('start'=>'2026-10-01','end'=>'2026-10-31','end_exclusive'=>'2026-11-01','start_datetime'=>'2026-10-01 00:00:00','end_exclusive_datetime'=>'2026-11-01 00:00:00','timezone'=>'America/Denver');}
 public function test_weekly_summary_reports_only_direct_planned_and_recorded_totals(){
  $workout=(object)array('id'=>11,'workout_type'=>'pool','workout_start'=>'2026-10-05 06:00:00','total_distance_m'=>2600,'moving_time_ms'=>3300000,'elapsed_time_ms'=>3600000);
  $plan=(object)array('id'=>7,'planned_date'=>'2026-10-05','planned_start_time'=>'06:00:00','title'=>'Plan','workout_type'=>'pool','planned_distance_m'=>2500,'planned_duration_ms'=>3600000,'pool_length_m'=>25,'pool_course'=>'m','primary_stroke'=>'MIXED','workout_instructions'=>'','status'=>'completed','completed_workout_id'=>11);
  $data=Calendar::assemble_rows($this->range(),array($workout),array(),'sunday',array($plan));$week=$data['weeks'][1];
  $this->assertSame(1,$week['workout_count']);$this->assertSame(2600.0,$week['distance_m']);$this->assertSame(1,$week['planned_workout_count']);$this->assertSame(2500.0,$week['planned_distance_m']);$this->assertSame(3600000,$week['planned_duration_ms']);$this->assertSame(1,$week['completed_planned_workout_count']);
  foreach(array('adherence','readiness','load','score','compliance')as$key)$this->assertArrayNotHasKey($key,$week);
 }
 public function test_matched_plan_exposes_signed_distance_and_duration_differences(){
  $workout=(object)array('id'=>11,'workout_type'=>'pool','workout_start'=>'2026-10-05 06:00:00','total_distance_m'=>2600,'moving_time_ms'=>3300000,'elapsed_time_ms'=>3600000);
  $plan=(object)array('id'=>7,'planned_date'=>'2026-10-05','planned_start_time'=>null,'title'=>'Plan','workout_type'=>'pool','planned_distance_m'=>2500,'planned_duration_ms'=>3600000,'pool_length_m'=>null,'pool_course'=>null,'primary_stroke'=>null,'workout_instructions'=>'','status'=>'completed','completed_workout_id'=>11);
  $row=Calendar::assemble_rows($this->range(),array($workout),array(),'sunday',array($plan))['planned_workouts'][0];$c=$row['comparison'];
  $this->assertSame(2500.0,$c['planned_distance_m']);$this->assertSame(2600.0,$c['actual_distance_m']);$this->assertSame(100.0,$c['distance_difference_m']);$this->assertSame(3600000,$c['planned_duration_ms']);$this->assertSame(3300000,$c['actual_duration_ms']);$this->assertSame(-300000,$c['duration_difference_ms']);
 }
 public function test_unmatched_plan_has_no_manufactured_actual_comparison(){
  $plan=(object)array('id'=>7,'planned_date'=>'2026-10-05','planned_start_time'=>null,'title'=>'Plan','workout_type'=>'pool','planned_distance_m'=>2500,'planned_duration_ms'=>null,'pool_length_m'=>null,'pool_course'=>null,'primary_stroke'=>null,'workout_instructions'=>'','status'=>'planned','completed_workout_id'=>null);
  $row=Calendar::assemble_rows($this->range(),array(),array(),'sunday',array($plan))['planned_workouts'][0];$this->assertNull($row['comparison']);
 }
 public function test_cancelled_plans_are_not_counted_as_weekly_planned_workouts(){
  $plan=(object)array('id'=>7,'planned_date'=>'2026-10-05','planned_start_time'=>null,'title'=>'Plan','workout_type'=>'pool','planned_distance_m'=>2500,'planned_duration_ms'=>3600000,'pool_length_m'=>null,'pool_course'=>null,'primary_stroke'=>null,'workout_instructions'=>'','status'=>'cancelled','completed_workout_id'=>null);
  $week=Calendar::assemble_rows($this->range(),array(),array(),'sunday',array($plan))['weeks'][1];$this->assertSame(0,$week['planned_workout_count']);$this->assertSame(0.0,$week['planned_distance_m']);$this->assertSame(0,$week['planned_duration_ms']);
 }
 public function test_calendar_ui_labels_comparisons_as_plain_math_not_training_scores(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$this->assertStringContainsString('Planned vs Actual',$view);$this->assertStringContainsString('m planned',$view);$this->assertStringContainsString('m actual',$view);
  foreach(array('adherence score','readiness','training load','compliance score')as$forbidden)$this->assertStringNotContainsString($forbidden,strtolower($view));
  $main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');$this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.0'",$main);
 }
}
