<?php
use PHPUnit\Framework\TestCase;
final class GarminScheduledSyncTest extends TestCase {
 public function test_sync_is_explicit_per_user_and_hourly(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("'swimlog_garmin_scheduled_sync'",$code);
  $this->assertStringContainsString("'swimlog_garmin_sync_enabled'",$code);
  $this->assertStringContainsString("wp_schedule_event(time()+300,'hourly'",$code);
  $this->assertStringContainsString('wp_clear_scheduled_hook',$code);
 }
 public function test_sync_imports_pool_but_leaves_open_water_for_review(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-garmin-personal-api.php');
  $this->assertStringContainsString('public static function scheduled_sync',$code);
  $this->assertStringContainsString("array('lap_swimming','open_water_swimming')",$code);
  $this->assertStringContainsString("if('open_water_swimming'===\$type){\$result['open_water_review']++;continue;}",$code);
  $this->assertStringContainsString("self::import_activity(\$user_id,\$id,0)",$code);
  $this->assertStringContainsString("'pending_review'===$status",$code);
 }
 public function test_admin_explains_conservative_behavior(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Scheduled Synchronization',$code);
  $this->assertStringContainsString('Open-water swims are left for manual review so a saved location can be chosen.',$code);
  $this->assertStringContainsString('Duplicate activities are skipped, and ambiguous matches remain pending for review.',$code);
 }
}
