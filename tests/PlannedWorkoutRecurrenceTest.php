<?php
use PHPUnit\Framework\TestCase;
final class PlannedWorkoutRecurrenceTest extends TestCase {
 public function test_recurrence_materializes_independent_plans_through_existing_duplicate_and_save_service(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$start=strpos($code,'public static function recur');$end=strpos($code,'private static function valid_date',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('self::get_for_user( absint( $id ), absint( $user_id ) )',$body);
  $this->assertStringContainsString('self::duplicate( $plan->id, $user_id',$body);
  $this->assertStringNotContainsString('completed_workout_id',$body);
  $this->assertStringContainsString('366',$body);
 }
 public function test_duplicate_week_uses_owner_scoped_range_and_independent_duplicates(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$start=strpos($code,'public static function duplicate_week');$end=strpos($code,'public static function recur',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('self::range_for_user( $user_id',$body);
  $this->assertStringContainsString('self::duplicate( $plan->id, $user_id',$body);
 }
 public function test_ajax_keeps_recurrence_behind_existing_nonce_and_capability_boundary(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$start=strpos($code,'public static function calendar_plan_action');$end=strpos($code,'public static function render_calendar',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString("check_ajax_referer( 'swimlog_calendar_plan', 'nonce' )",$body);
  $this->assertStringContainsString("Planned_Workout::recur( \$id, \$user_id, \$weekdays, \$end_date )",$body);
  $this->assertStringContainsString("Planned_Workout::duplicate_week( \$user_id, \$source_start, \$target_start )",$body);
 }
 public function test_calendar_offers_repeat_without_private_notes_or_rest_writes(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$rest=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("[ 'repeat', 'Repeat' ]",$view);$this->assertStringContainsString('function showRecurrenceEditor',$view);$this->assertStringContainsString("plan_action: 'recur'",$view);
  $this->assertStringNotContainsString('plan.notes',strtolower($view));$this->assertStringNotContainsString('workout.notes',strtolower($view));$this->assertStringNotContainsString('CREATABLE',$rest);$this->assertStringNotContainsString('EDITABLE',$rest);$this->assertStringNotContainsString('DELETABLE',$rest);
 }
 public function test_phase_eight_does_not_change_database_schema_version(){
  $main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');$this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.4'",$main);
 }
}
