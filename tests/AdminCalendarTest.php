<?php
use PHPUnit\Framework\TestCase;
final class AdminCalendarTest extends TestCase {
 public function test_admin_menu_hosts_shared_calendar_renderer(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("'swimlog-calendar', 'calendar'",$admin);
  $this->assertStringContainsString('Blocks::render_calendar_for_user( $user_id )',$admin);
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$this->assertStringContainsString("wp_enqueue_style( 'swimlog-calendar' )",$blocks);$this->assertStringContainsString("wp_enqueue_script( 'swimlog-calendar-view' )",$blocks);
 }
 public function test_shared_calendar_has_date_jump_and_week_navigation_controls(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('data-calendar-go-date',$blocks);$this->assertStringContainsString('data-calendar-go',$blocks);
  $this->assertStringContainsString('data-calendar-view="month"',$blocks);$this->assertStringContainsString('data-calendar-view="week"',$blocks);
  $this->assertStringContainsString("button.dataset.calendarAction === 'previous' ? -7 : 7",$view);
  $this->assertStringContainsString("root.dataset.firstDay || 'sunday'",$view);
 }
 public function test_week_duplicate_reuses_existing_secured_ajax_action(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');$blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $this->assertStringContainsString('data-calendar-duplicate-week',$view);$this->assertStringContainsString("plan_action: 'duplicate_week'",$view);
  $this->assertStringContainsString('Planned_Workout::duplicate_week( $user_id, $source_start, $target_start )',$blocks);
  $this->assertStringContainsString("check_ajax_referer( 'swimlog_calendar_plan', 'nonce' )",$blocks);
 }
 public function test_admin_and_frontend_register_same_calendar_assets(){
  $plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("add_action( 'wp_enqueue_scripts', array( \$this, 'register_calendar_style' ) )",$plugin);
  $this->assertStringContainsString("add_action( 'admin_enqueue_scripts', array( \$this, 'register_calendar_style' ) )",$plugin);
 }
 public function test_phase_nine_a_does_not_change_database_version(){
  $main=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');$this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '1.2'",$main);
 }
}
