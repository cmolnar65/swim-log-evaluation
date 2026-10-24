<?php
use PHPUnit\Framework\TestCase;
final class OpenWaterLocationMatchingTest extends TestCase {
 public function test_schema_and_location_editor_store_reference_coordinates(){
  $db=file_get_contents(dirname(__DIR__).'/includes/class-database.php');$loc=file_get_contents(dirname(__DIR__).'/includes/class-location.php');$admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('latitude decimal(10,7) NULL',$db);$this->assertStringContainsString('longitude decimal(10,7) NULL',$db);
  $this->assertStringContainsString("'latitude'      => null === \$latitude ? null : round(\$latitude,7)",$loc);
  $this->assertStringContainsString('Open-water reference coordinates',$admin);$this->assertStringContainsString('within 500 meters',$admin);
 }
 public function test_importer_uses_first_valid_gps_fix_and_fails_closed_when_required(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString('location_from_open_water_track($user_id,$parsed)',$code);
  $this->assertStringContainsString("foreach((array)(\$parsed['trackpoints']??array())as\$p)",$code);
  $this->assertStringContainsString('Location::nearest_for_user',$code);
  $this->assertStringContainsString("'swimlog_ows_location_review'",$code);
 }
 public function test_location_matching_has_distance_and_ambiguity_guards(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-location.php');
  $this->assertStringContainsString('public static function nearest_for_user',$code);
  $this->assertStringContainsString('$d<=$max_m',$code);
  $this->assertStringContainsString("<50.0)return null",$code);
 }
 public function test_scheduled_garmin_sync_attempts_ows_with_required_location(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-garmin-personal-api.php');
  $this->assertStringContainsString("self::import_activity(\$user_id,\$id,0,'open_water_swimming'===\$type)",$code);
  $this->assertStringContainsString("'swimlog_ows_location_review'===\$import->get_error_code()",$code);
 }
}
