<?php
use PHPUnit\Framework\TestCase;
final class EquivalentStepsTest extends TestCase {
 public function test_pool_active_lengths_exclude_rest_and_use_181_rate() {
  $w=array('workout_type'=>'pool','moving_time_ms'=>900000);
  $l=array(array('length_type'=>'active','elapsed_time_ms'=>30000),array('length_type'=>'rest','elapsed_time_ms'=>30000),array('length_type'=>'active','elapsed_time_ms'=>30000));
  $r=\SwimLogEvaluation\Equivalent_Steps::calculate($w,$l);
  $this->assertSame(181,$r['equivalent_steps']);
  $this->assertSame(60000,$r['steps_active_ms']);
  $this->assertSame('pool_active_lengths',$r['steps_method']);
  $this->assertSame('1',$r['steps_version']);
 }
 public function test_open_water_uses_recorded_moving_time() {
  $r=\SwimLogEvaluation\Equivalent_Steps::calculate(array('workout_type'=>'open_water','moving_time_ms'=>120000));
  $this->assertSame(362,$r['equivalent_steps']);
  $this->assertSame('recorded_moving_time',$r['steps_method']);
 }
 public function test_missing_active_time_is_not_estimated_from_elapsed_rest() {
  $this->assertNull(\SwimLogEvaluation\Equivalent_Steps::calculate(array('workout_type'=>'pool','elapsed_time_ms'=>600000),array()));
  $this->assertNull(\SwimLogEvaluation\Equivalent_Steps::calculate(array('workout_type'=>'open_water','elapsed_time_ms'=>600000),array()));
 }
 public function test_custom_rate_and_provenance_persist_in_parsed_workout() {
  $r=\SwimLogEvaluation\Equivalent_Steps::calculate(array('workout_type'=>'pool'),array(array('length_type'=>'active','elapsed_time_ms'=>60000)),200);
  $this->assertSame(200,$r['equivalent_steps']);
  $p=\SwimLogEvaluation\Equivalent_Steps::populate_parsed(array('workout'=>array('workout_type'=>'pool'),'lengths'=>array(array('length_type'=>'active','elapsed_time_ms'=>60000))));
  $this->assertSame(181,$p['workout']['equivalent_steps']);
  $this->assertSame(181,$p['workout']['steps_rate']);
 }
 public function test_schema_and_authenticated_read_only_rest() {
  $schema=file_get_contents(dirname(__DIR__).'/includes/class-database.php');
  foreach(array('equivalent_steps bigint(20) unsigned NULL','steps_active_ms bigint(20) unsigned NULL','steps_rate smallint(5) unsigned NULL','steps_method varchar(40) NULL','steps_version varchar(16) NULL') as $field)$this->assertStringContainsString($field,$schema);
  $rest=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("'equivalent_steps'=>",$rest);
  $this->assertStringContainsString("'steps_method'=>",$rest);
  $this->assertStringContainsString("'permission_callback'=>array(__CLASS__,'permission')",$rest);
  $this->assertStringNotContainsString('CREATABLE',$rest);
 }
}
