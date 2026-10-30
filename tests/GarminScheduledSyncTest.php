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
  $this->assertStringContainsString("'swimlog_garmin_sync_last_attempt'",$code);
  $this->assertStringContainsString("'swimlog_garmin_sync_last_success'",$code);
  $this->assertStringContainsString("'swimlog_garmin_sync_last_error'",$code);
 }
 public function test_admin_explains_conservative_behavior(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin-garmin.php');
  $this->assertStringContainsString('Scheduled Synchronization',$code);
  $this->assertStringContainsString('The sync uses WordPress WP-Cron, so an hourly check runs when WordPress receives traffic after the scheduled time rather than at a guaranteed clock time.',$code);
  $this->assertStringContainsString('Open-water swims also import automatically when the first valid GPS fix matches a saved location within 500 meters; unmatched locations remain for manual review.',$code);
  $this->assertStringContainsString('Duplicate activities are skipped, and ambiguous workout matches remain pending for review.',$code);
  $this->assertStringContainsString('Last attempt:',$code);
  $this->assertStringContainsString('Last successful sync:',$code);
  $this->assertStringContainsString('Last error:',$code);
  $this->assertStringContainsString('Next scheduled check:',$code);
  $this->assertStringContainsString("wp_next_scheduled('swimlog_garmin_scheduled_sync',array(\$user_id))",$code);
 }
 public function test_all_four_scheduled_types_and_legacy_defaults_are_preserved(){
  global $swimlog_test_user_meta;
  $previous=$swimlog_test_user_meta;
  try{
   $swimlog_test_user_meta=array();
   $api='SwimLogEvaluation\\Garmin_Personal_API';
   require_once dirname(__DIR__).'/includes/class-garmin-personal-api.php';
   $this->assertSame(array('lap_swimming','open_water_swimming'),$api::scheduled_types(42));
   $swimlog_test_user_meta[42]['swimlog_garmin_land_sync_types']=array('walking');
   $this->assertSame(array('lap_swimming','open_water_swimming','walking'),$api::scheduled_types(42));
   $swimlog_test_user_meta[42]['swimlog_garmin_sync_types']=array('lap_swimming','open_water_swimming','running','walking','cycling');
   $this->assertSame(array('lap_swimming','open_water_swimming','running','walking'),$api::scheduled_types(42));
   $swimlog_test_user_meta[42]['swimlog_garmin_sync_types']=array('running');
   $this->assertSame(array('running'),$api::scheduled_types(42));
   $swimlog_test_user_meta[42]['swimlog_garmin_sync_types']=array();
   $this->assertSame(array(),$api::scheduled_types(42));
  }finally{$swimlog_test_user_meta=$previous;}
 }

}
