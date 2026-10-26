<?php
use PHPUnit\Framework\TestCase;

final class GarminActivityPresentationTest extends TestCase {
 private function rowState($importStatus,$matchStatus){
  if(in_array($importStatus,array('imported','pending','recorded'),true))return $importStatus;
  if('exact'===$matchStatus)return 'exact-match';
  if('similar'===$matchStatus)return 'possible-match';
  return 'new';
 }
 private function source(){return file_get_contents(dirname(__DIR__).'/admin/class-admin-garmin.php');}
 public function test_persisted_import_state_takes_precedence_over_match_state(){
  $this->assertSame('imported',$this->rowState('imported','exact'));
  $this->assertSame('pending',$this->rowState('pending','similar'));
  $this->assertSame('recorded',$this->rowState('recorded','exact'));
 }
 public function test_unassociated_matches_are_not_imported_states(){
  $this->assertSame('exact-match',$this->rowState('none','exact'));
  $this->assertSame('possible-match',$this->rowState('none','similar'));
  $this->assertSame('new',$this->rowState('none','none'));
 }
 public function test_admin_source_uses_unambiguous_status_labels(){
  $source=$this->source();
  $this->assertStringContainsString('Exact match — not yet associated',$source);
  $this->assertStringContainsString('Possible match — not yet associated',$source);
  $this->assertStringContainsString('New — not imported',$source);
  $this->assertStringNotContainsString('swimlog-garmin-match-',$source);
 }
 public function test_garmin_admin_loads_database_before_location_model(){
  $garmin=$this->source();
  $database=strpos($garmin,"includes/class-database.php");
  $location=strpos($garmin,"includes/class-location.php");
  $location_query=strpos($garmin,'Location::all_for_user');
  $this->assertNotFalse($database);
  $this->assertNotFalse($location);
  $this->assertNotFalse($location_query);
  $this->assertLessThan($location,$database);
  $this->assertLessThan($location_query,$location);
 }
}
