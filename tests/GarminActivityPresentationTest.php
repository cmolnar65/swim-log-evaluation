<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Admin;

final class GarminActivityPresentationTest extends TestCase {
 private function rowState($importStatus,$matchStatus){
  if(in_array($importStatus,array('imported','pending','recorded'),true))return $importStatus;
  if('exact'===$matchStatus)return 'exact-match';
  if('similar'===$matchStatus)return 'possible-match';
  return 'new';
 }
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
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Exact match — not yet associated',$source);
  $this->assertStringContainsString('Possible match — not yet associated',$source);
  $this->assertStringContainsString('New — not imported',$source);
  $this->assertStringNotContainsString('swimlog-garmin-match-',$source);
 }
}
