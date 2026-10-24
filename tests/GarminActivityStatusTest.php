<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Admin;

final class GarminActivityStatusTest extends TestCase {
 private function state($row,$activityId=12345){
  global $wpdb;
  $wpdb=new class($row){
   public $prefix='wp_';private $row;
   public function __construct($row){$this->row=$row;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($query){return $this->row;}
  };
  $admin=new Admin();
  $method=new ReflectionMethod(Admin::class,'garmin_activity_import_state');
  $method->setAccessible(true);
  return $method->invoke($admin,42,$activityId);
 }
 public function test_complete_garmin_import_is_reported_as_imported(){
  $state=$this->state((object)array('workout_id'=>77,'import_status'=>'complete'));
  $this->assertSame('imported',$state['status']);
  $this->assertSame(77,$state['workout_id']);
 }
 public function test_pending_garmin_import_is_reported_for_review(){
  $state=$this->state((object)array('workout_id'=>88,'import_status'=>'pending'));
  $this->assertSame('pending',$state['status']);
  $this->assertSame(88,$state['workout_id']);
 }
 public function test_unassociated_activity_is_not_marked_imported(){
  $state=$this->state(null);
  $this->assertSame('none',$state['status']);
  $this->assertSame(0,$state['workout_id']);
 }
 public function test_other_persisted_state_is_recorded_not_imported(){
  $state=$this->state((object)array('workout_id'=>0,'import_status'=>'failed'));
  $this->assertSame('recorded',$state['status']);
 }
}
