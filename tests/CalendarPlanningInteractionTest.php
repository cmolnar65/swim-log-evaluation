<?php
use PHPUnit\Framework\TestCase;

final class CalendarPlanningInteractionTest extends TestCase {
 public function test_calendar_management_uses_authenticated_ajax_not_public_or_portability_rest(){
  $plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');$rest=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("wp_ajax_swimlog_calendar_plan",$plugin);
  $this->assertStringContainsString("Blocks', 'calendar_plan_action'",$plugin);
  $this->assertStringNotContainsString('/calendar/planned-workouts',$rest);
  $this->assertStringNotContainsString('CREATABLE',$rest);$this->assertStringNotContainsString('EDITABLE',$rest);$this->assertStringNotContainsString('DELETABLE',$rest);
 }
 public function test_ajax_handler_requires_nonce_and_owner_management_capability(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$start=strpos($code,'public static function calendar_plan_action');$end=strpos($code,'public static function render_calendar',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString("check_ajax_referer( 'swimlog_calendar_plan', 'nonce' )",$body);
  $this->assertStringContainsString("'swimlog_manage_own_workouts'",$body);$this->assertStringContainsString("'swimlog_manage_all_users'",$body);
  foreach(array('Planned_Workout::save( $user_id, $data, $id )','Planned_Workout::duplicate( $id, $user_id','Planned_Workout::set_status( $id, $user_id','Planned_Workout::delete( $id, $user_id )')as$needle)$this->assertStringContainsString($needle,$body);
  $this->assertStringContainsString("\$data['notes'] = \$existing ? \$existing->notes : '';",$body);
 }
 public function test_service_duplicate_copies_prescription_and_private_notes_server_side(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');$start=strpos($code,'public static function duplicate');$end=strpos($code,'/** Mark a plan',$start);$body=substr($code,$start,$end-$start);
  $this->assertStringContainsString('self::get_for_user( absint( $id ), absint( $user_id ) )',$body);
  foreach(array("'workout_instructions' => \$plan->workout_instructions","'notes' => \$plan->notes","'title' => \$plan->title")as$needle)$this->assertStringContainsString($needle,$body);
  $this->assertStringContainsString('return self::save( $user_id, $data );',$body);
 }
 public function test_calendar_client_never_receives_or_requests_private_notes(){
  $view=strtolower(file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js'));
  $this->assertStringContainsString('swimlog_calendar_event_result',$view);
  $this->assertStringNotContainsString('plan.notes',$view);
  $this->assertStringNotContainsString('workout.notes',$view);
  $this->assertStringContainsString('swimlogcalendarsettings',$view);
  $this->assertStringContainsString('swimlog_calendar_plan',$view);
 }
 public function test_calendar_site_date_controls_past_planning() {
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $service=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');
  $this->assertStringContainsString('data-site-today=', $blocks);
  $this->assertStringContainsString("wp_date( 'Y-m-d', null, wp_timezone() )", $blocks);
  $this->assertStringContainsString('key < root.dataset.siteToday', $view);
  $this->assertStringContainsString('target.min = root.dataset.siteToday', $view);
  $this->assertStringContainsString('swimlog_planned_workout_past_date', $service);
 }
 public function test_empty_calendar_dates_offer_planning_in_server_month_and_dynamic_views(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('data-calendar-plan-date=',$blocks);$this->assertStringContainsString('function planDateButton( key, root )',$view);
  $this->assertGreaterThanOrEqual(3,substr_count($view,'planDateButton( key, root )'));$this->assertStringContainsString('function showPlanEditor',$view);
 }
 public function test_calendar_plan_editor_supports_drag_drop_cross_month_clipboard_and_mobile_actions(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $this->assertStringContainsString("'Workout Plan / Workout Instructions', 'workout_instructions', 'textarea'",$view);
  foreach(array("[ 'edit', 'Edit' ]","[ 'duplicate', 'Duplicate' ]","[ 'skip', 'Mark Skipped' ]","[ 'cancel', 'Cancel Plan' ]","[ 'delete', 'Delete' ]","dataset.calendarPlanAction = 'restore'")as$needle)$this->assertStringContainsString($needle,$view);
  foreach(array('dragstart','dragover','drop','draggable','contextmenu','showMoveDate','showDateActions','pastePlan','data-calendar-move-form',"'move-date', 'Move to Date'","'copy', 'Copy Workout'","'cut', 'Cut Workout'")as$needle)$this->assertStringContainsString($needle,$view);
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $service=file_get_contents(dirname(__DIR__).'/includes/class-planned-workout.php');
  $this->assertStringContainsString('Planned_Workout::move( $id, $user_id, $date )',$blocks);
  $this->assertStringContainsString('public static function move( $id, $user_id, $date )',$service);
  $this->assertStringContainsString('Only unmatched planned workouts can be moved.',$service);
  $this->assertStringContainsString('swimlog_planned_workout_move_date',$service);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.2'",$main);
 }
}
