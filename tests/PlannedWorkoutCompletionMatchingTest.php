<?php
use PHPUnit\Framework\TestCase;
final class PlannedWorkoutCompletionMatchingTest extends TestCase {
 public function test_candidate_matching_is_owner_scoped_same_day_and_same_type(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$start=strpos($code,'public static function completion_candidates');$end=strpos($code,'/** Associate exactly one',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('self::get_for_user( $id, $user_id )',$body);
  $this->assertStringContainsString("Workout::range_for_user( \$user_id, \$plan->planned_date . ' 00:00:00'",$body);
  $this->assertStringContainsString("(string) \$workout->workout_type !== (string) \$plan->workout_type",$body);
  $this->assertStringContainsString('distance_difference_m',$body);$this->assertStringContainsString('duration_difference_ms',$body);
  $this->assertStringNotContainsString('associate_completed_workout',$body);
 }
 public function test_association_requires_owned_actual_workout_and_rejects_reuse(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$start=strpos($code,'public static function associate_completed_workout');$end=strpos($code,'public static function clear_completed_workout',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('Workout::get_for_user( $workout_id, $user_id )',$body);
  $this->assertStringContainsString('completed_workout_id = %d AND id <> %d',$body);
  $this->assertStringContainsString("'status' => 'completed', 'completed_workout_id' => \$workout_id",$body);
 }
 public function test_calendar_requires_explicit_confirmation_before_association(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('function showCompletionCandidates',$view);
  $this->assertStringContainsString('No match is made until you confirm it.',$view);
  $this->assertStringContainsString("choose.textContent = 'Confirm Match'",$view);
  $this->assertStringContainsString("plan_action: 'associate'",$view);
  $this->assertStringNotContainsString("plan_action: 'associate', id: plan.id",$view);
 }
 public function test_ajax_matching_uses_existing_nonce_capability_boundary_and_service(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$start=strpos($code,'public static function calendar_plan_action');$end=strpos($code,'public static function render_calendar',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString("check_ajax_referer( 'swimlog_calendar_plan', 'nonce' )",$body);
  $this->assertStringContainsString("'swimlog_manage_own_workouts'",$body);$this->assertStringContainsString("'swimlog_manage_all_users'",$body);
  $this->assertStringContainsString('Planned_Workout::completion_candidates( $id, $user_id )',$body);
  $this->assertStringContainsString('Planned_Workout::associate_completed_workout( $id, $user_id, $workout_id )',$body);
  $this->assertStringContainsString('Planned_Workout::clear_completed_workout( $id, $user_id )',$body);
 }
 public function test_matching_does_not_change_actual_workout_or_schema(){
  $service=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $start=strpos($service,'public static function associate_completed_workout');$end=strpos($service,'public static function clear_completed_workout',$start);$body=substr($service,$start,$end-$start);
  $this->assertStringNotContainsString("Database::table( 'workouts' )",$body);
  $this->assertStringNotContainsString('$wpdb->update( $workouts',$body);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.1'",$main);
 }
}
