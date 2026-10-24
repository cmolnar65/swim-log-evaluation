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
 public function test_location_distance_geometry_at_499_500_and_501_meter_boundaries(){
  $degrees_per_meter=180.0/(M_PI*6371000.0);
  $this->assertEqualsWithDelta(499.0,\SwimLogEvaluation\Location::distance_m(0.0,0.0,499.0*$degrees_per_meter,0.0),0.01);
  $this->assertEqualsWithDelta(500.0,\SwimLogEvaluation\Location::distance_m(0.0,0.0,500.0*$degrees_per_meter,0.0),0.01);
  $this->assertEqualsWithDelta(501.0,\SwimLogEvaluation\Location::distance_m(0.0,0.0,501.0*$degrees_per_meter,0.0),0.01);
 }
 public function test_location_distance_handles_longitude_and_identical_coordinates(){
  $degrees_per_meter=180.0/(M_PI*6371000.0);
  $this->assertEqualsWithDelta(0.0,\SwimLogEvaluation\Location::distance_m(39.7294,-104.8319,39.7294,-104.8319),0.0001);
  $equator=\SwimLogEvaluation\Location::distance_m(0.0,0.0,0.0,500.0*$degrees_per_meter);
  $this->assertEqualsWithDelta(500.0,$equator,0.01);
  $this->assertGreaterThan(0.0,\SwimLogEvaluation\Location::distance_m(39.7294,-104.8319,39.7295,-104.8319));
 }
 public function test_location_matching_keeps_inclusive_radius_and_strict_ambiguity_boundary(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-location.php');
  $this->assertStringContainsString('$d<=$max_m',$code);
  $this->assertStringContainsString("<50.0)return null",$code);
  $this->assertStringNotContainsString("<=50.0)return null",$code);
 }
 public function test_scheduled_garmin_sync_attempts_ows_with_required_location(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-garmin-personal-api.php');
  $this->assertStringContainsString("self::import_activity(\$user_id,\$id,0,'open_water_swimming'===\$type)",$code);
  $this->assertStringContainsString("'swimlog_ows_location_review'===\$import->get_error_code()",$code);
 }
}
