<?php
use PHPUnit\Framework\TestCase;

final class GarminBatchLocationTest extends TestCase {
 public function test_batch_location_is_validated_and_only_applied_to_open_water(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("name=\"garmin_batch_location_id\"",$source);
  $this->assertStringContainsString('Location::get_for_user( $garmin_batch_location_id, $user_id )',$source);
  $this->assertStringContainsString("'open_water_swimming' === " + '$selected_type ? $garmin_batch_location_id : 0',$source);
  $this->assertStringContainsString('Garmin_Personal_API::import_activity( $user_id, $selected_id, $location_id )',$source);
 }
 public function test_batch_form_explains_pool_imports_ignore_location(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Applied only to selected open-water activities. Pool imports ignore this setting.',$source);
 }
}
