<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\REST_API;

final class CalendarRestTest extends TestCase {
 protected function setUp(): void {
  global $swimlog_test_logged_in,$swimlog_test_current_user_id,$swimlog_test_capabilities,$swimlog_test_user_meta;
  $swimlog_test_logged_in=true;$swimlog_test_current_user_id=42;
  $swimlog_test_capabilities=array('swimlog_view_own_results'=>true);
  $swimlog_test_user_meta=array();
 }
 protected function tearDown(): void {
  global $swimlog_test_logged_in,$swimlog_test_current_user_id,$swimlog_test_capabilities,$swimlog_test_user_meta;
  $swimlog_test_logged_in=null;$swimlog_test_current_user_id=null;$swimlog_test_capabilities=array();$swimlog_test_user_meta=array();
 }
 private function request($params=array()){return new class($params){private $p;public function __construct($p){$this->p=$p;}public function get_param($k){return $this->p[$k]??null;}};}

 public function test_calendar_permission_allows_authenticated_owner_without_external_rest_opt_in(){
  $this->assertTrue(REST_API::calendar_permission($this->request(array('user'=>42))));
 }
 public function test_calendar_permission_denies_anonymous_access(){
  global $swimlog_test_logged_in;$swimlog_test_logged_in=false;
  $this->assertFalse(REST_API::calendar_permission($this->request(array('user'=>42))));
 }
 public function test_calendar_permission_denies_another_swimmer(){
  $this->assertFalse(REST_API::calendar_permission($this->request(array('user'=>43))));
 }
 public function test_calendar_permission_allows_manage_all_users_capability(){
  global $swimlog_test_capabilities;$swimlog_test_capabilities['swimlog_manage_all_users']=true;
  $this->assertTrue(REST_API::calendar_permission($this->request(array('user'=>43))));
 }
 public function test_public_result_settings_do_not_grant_calendar_access(){
  global $swimlog_test_user_meta;
  $swimlog_test_user_meta[43]=array('swimlog_public_results'=>'1','swimlog_public_ows_routes'=>'1','swimlog_rest_api_access'=>'1');
  $this->assertFalse(REST_API::calendar_permission($this->request(array('user'=>43))));
 }
 public function test_calendar_route_is_read_only_and_uses_separate_permission_callback(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("'/calendar'", $code);
  $this->assertStringContainsString("'callback'=>array(__CLASS__,'calendar')", $code);
  $this->assertStringContainsString("'permission_callback'=>array(__CLASS__,'calendar_permission')", $code);
  $this->assertStringContainsString('Calendar::for_user($uid,$start,$end)', $code);
 }
 public function test_calendar_permission_does_not_use_public_or_external_rest_preferences(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $start=strpos($code,'public static function calendar_permission');
  $end=strpos($code,'public static function workouts',$start);
  $body=substr($code,$start,$end-$start);
  $this->assertStringNotContainsString('swimlog_rest_api_access',$body);
  $this->assertStringNotContainsString('swimlog_public_results',$body);
  $this->assertStringNotContainsString('swimlog_public_ows_routes',$body);
 }
 public function test_calendar_endpoint_exposes_no_trackpoints_gps_notes_or_device_fields(){
  $calendar=file_get_contents(dirname(__DIR__).'/includes/class-calendar.php');
  foreach(array('trackpoints','latitude','longitude','notes','device_model','device_manufacturer','source_type') as $private){
   $this->assertStringNotContainsString("'".$private."' =>",$calendar,$private);
  }
 }
 public function test_calendar_range_limit_is_bounded(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("format('%a')>366",$code);
  $this->assertStringContainsString('swimlog_calendar_range_too_large',$code);
 }
 public function test_calendar_rest_phase_does_not_define_calendar_schema(){
  $database=file_get_contents(dirname(__DIR__).'/includes/class-database.php');
  $this->assertStringNotContainsString("self::table( 'calendar' )",$database);
 }
}
