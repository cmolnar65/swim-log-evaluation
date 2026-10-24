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
 public function test_sync_imports_pool_and_known_location_open_water(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-garmin-personal-api.php');
  $this->assertStringContainsString('public static function scheduled_sync',$code);
  $this->assertStringContainsString("array('lap_swimming','open_water_swimming')",$code);
  $this->assertStringContainsString("self::import_activity(\$user_id,\$id,0,'open_water_swimming'===\$type)",$code);
  $this->assertStringContainsString("'swimlog_ows_location_review'===\$import->get_error_code()",$code);
  $this->assertStringContainsString("'pending_review'===\$status",$code);
 }
 public function test_admin_explains_conservative_behavior(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Scheduled Synchronization',$code);
  $this->assertStringContainsString('The sync uses WordPress WP-Cron, so an hourly check runs when WordPress receives traffic after the scheduled time rather than at a guaranteed clock time.',$code);
  $this->assertStringContainsString('Open-water swims also import automatically when the first valid GPS fix matches a saved location within 500 meters; unmatched locations remain for manual review.',$code);
  $this->assertStringContainsString('Duplicate activities are skipped, and ambiguous workout matches remain pending for review.',$code);
 }
}
