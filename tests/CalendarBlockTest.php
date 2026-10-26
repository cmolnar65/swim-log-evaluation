<?php
use PHPUnit\Framework\TestCase;

final class CalendarBlockTest extends TestCase {
 public function test_calendar_is_native_dynamic_server_rendered_block(){
  $metadata=json_decode(file_get_contents(dirname(__DIR__).'/blocks/calendar/block.json'),true);
  $editor=file_get_contents(dirname(__DIR__).'/blocks/calendar/index.js');
  $this->assertSame('swimlog/calendar',$metadata['name']);
  $this->assertSame(3,$metadata['apiVersion']);
  $this->assertSame('swimlog-block-calendar',$metadata['editorScript']);
  $this->assertSame('swimlog-calendar',$metadata['style']);
  $this->assertFalse($metadata['supports']['html']);
  $this->assertStringContainsString("block: 'swimlog/calendar'",$editor);
  $this->assertStringContainsString('ServerSideRender',$editor);
  $this->assertStringContainsString('return null',$editor);
 }
 public function test_calendar_renderer_uses_calendar_service_and_initial_month(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $this->assertStringContainsString('Calendar::for_user( $user_id',$blocks);
  $this->assertStringContainsString("modify( 'last day of this month' )",$blocks);
  $this->assertStringContainsString('swimlog-calendar-grid',$blocks);
  $this->assertStringContainsString('swimlog-calendar-weekday',$blocks);
 }
 public function test_calendar_owner_resolution_matches_block_policy_without_viewer_fallback(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $start=strpos($blocks,'private static function calendar_owner');
  $end=strpos($blocks,'private static function calendar_allowed',$start);
  $body=substr($blocks,$start,$end-$start);
  $this->assertStringContainsString("attributes['user']",$body);
  $this->assertStringContainsString('get_post()',$body);
  $this->assertStringNotContainsString('get_current_user_id',$body);
  $editor=file_get_contents(dirname(__DIR__).'/blocks/calendar/index.js');
  $this->assertStringContainsString('page or post author',$editor);
  $this->assertStringContainsString('no logged-in-viewer fallback',$editor);
 }
 public function test_calendar_privacy_is_owner_or_manage_all_users_only(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $start=strpos($blocks,'private static function calendar_allowed');
  $end=strpos($blocks,'private static function calendar_month_html',$start);
  $body=substr($blocks,$start,$end-$start);
  $this->assertStringContainsString('! is_user_logged_in()',$body);
  $this->assertStringContainsString("'swimlog_view_own_results'",$body);
  $this->assertStringContainsString("'swimlog_manage_all_users'",$body);
  $this->assertStringNotContainsString('swimlog_public_results',$body);
  $this->assertStringNotContainsString('swimlog_public_ows_routes',$body);
  $this->assertStringNotContainsString('swimlog_rest_api_access',$body);
 }
 public function test_calendar_month_honors_service_first_day_and_keeps_events_distinct(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  foreach(array("'sunday' => array( 'Sun'","'monday' => array( 'Mon'","'saturday' => array( 'Sat'") as $needle)$this->assertStringContainsString($needle,$blocks);
  $this->assertStringContainsString('swimlog-calendar-workout',$blocks);
  $this->assertStringContainsString('swimlog-calendar-event',$blocks);
 }
 public function test_calendar_block_does_not_add_shortcode_or_schema(){
  $shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $this->assertStringNotContainsString('swimlog_calendar',$shortcodes);
  $this->assertStringContainsString("define( 'SWIMLOG_EVALUATION_DB_VERSION', '0.9' );",$plugin);
 }
}
