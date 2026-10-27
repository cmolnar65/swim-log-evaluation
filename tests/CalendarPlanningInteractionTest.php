<?php
use PHPUnit\Framework\TestCase;

final class CalendarPlanningInteractionTest extends TestCase {
 public function test_private_calendar_management_route_uses_separate_manage_permission(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("'/calendar/planned-workouts'",$code);
  $this->assertStringContainsString("'permission_callback'=>array(__CLASS__,'calendar_manage_permission')",$code);
  $start=strpos($code,'public static function calendar_manage_permission');$end=strpos($code,'public static function calendar_planned_workout',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString("'swimlog_manage_own_workouts'",$body);$this->assertStringContainsString("'swimlog_manage_all_users'",$body);
  foreach(array('swimlog_public_results','swimlog_public_ows_routes','swimlog_rest_api_access')as$forbidden)$this->assertStringNotContainsString($forbidden,$body);
 }
 public function test_calendar_mutations_delegate_to_owner_scoped_service(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  foreach(array('Planned_Workout::save($uid,$data,$id)','Planned_Workout::duplicate($id,$uid','Planned_Workout::set_status($id,$uid','Planned_Workout::delete($id,$uid)')as$needle)$this->assertStringContainsString($needle,$code);
  $this->assertStringNotContainsString('$wpdb->',$code);
 }
 public function test_service_duplicate_copies_prescription_but_creates_new_planned_record(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');
  $start=strpos($code,'public static function duplicate');$end=strpos($code,'/** Mark a plan',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('self::get_for_user( absint( $id ), absint( $user_id ) )',$body);
  foreach(array("'workout_instructions' => \$plan->workout_instructions","'notes' => \$plan->notes","'title' => \$plan->title")as$needle)$this->assertStringContainsString($needle,$body);
  $this->assertStringContainsString('return self::save( $user_id, $data );',$body);
 }
 public function test_empty_calendar_dates_offer_accessible_planning_in_server_month_and_dynamic_views(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('data-calendar-plan-date=',$blocks);$this->assertStringContainsString('function planDateButton( key )',$view);
  $this->assertGreaterThanOrEqual(3,substr_count($view,'planDateButton( key )'));
  $this->assertStringContainsString('function showPlanEditor',$view);
 }
 public function test_calendar_plan_editor_preserves_free_form_instructions_and_supports_actions(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString("'Workout Plan / Workout Instructions', 'workout_instructions', 'textarea'",$view);
  foreach(array("[ 'edit', 'Edit' ]","[ 'duplicate', 'Duplicate' ]","[ 'skip', 'Mark Skipped' ]","[ 'cancel', 'Cancel Plan' ]","[ 'delete', 'Delete' ]","dataset.calendarPlanAction = 'restore'")as$needle)$this->assertStringContainsString($needle,$view);
  $this->assertStringContainsString("method: 'POST'",$view);$this->assertStringContainsString("'X-WP-Nonce'",$view);$this->assertStringContainsString("credentials: 'same-origin'",$view);
 }
 public function test_phase_five_has_no_drag_drop_and_no_schema_change(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  foreach(array('dragstart','draggable','dropzone')as$needle)$this->assertStringNotContainsString($needle,$view);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.0'",$main);
 }
}
