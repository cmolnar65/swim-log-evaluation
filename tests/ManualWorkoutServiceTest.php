<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Manual_Workout;

final class ManualWorkoutServiceTest extends TestCase {
 private function document(){
  return array(
   'workout'=>array('workout_start'=>'2026-10-25 08:00:00','location_id'=>0,'original_pool_length'=>25,'pool_length_unit'=>'yd','original_distance'=>null,'elapsed_time_ms'=>3600000,'notes'=>'Manual practice'),
   'segments'=>array(
    array('type'=>'active','repetitions'=>2,'distance_per_rep'=>50,'stroke'=>'BR','timing_type'=>'measured_rep','rep_times_ms'=>array(42000,43000),'interval_ms'=>60000),
    array('type'=>'rest','timing_type'=>'unknown','rest_time_ms'=>30000),
    array('type'=>'active','repetitions'=>1,'distance_per_rep'=>100,'stroke'=>'FR','timing_type'=>'interval','interval_ms'=>120000)
   )
  );
 }
 public function test_validation_preserves_timing_provenance(){
  $v=Manual_Workout::validate_document($this->document());
  $this->assertFalse(is_wp_error($v));
  $this->assertSame('measured_rep',$v['segments'][0]['timing_type']);
  $this->assertSame(array(42000,43000),$v['segments'][0]['rep_times_ms']);
  $this->assertSame('interval',$v['segments'][2]['timing_type']);
  $this->assertSame(120000,$v['segments'][2]['interval_ms']);
 }
 public function test_normalization_never_turns_rep_or_interval_times_into_length_elapsed_times(){
  $n=Manual_Workout::normalize_document($this->document());
  $this->assertFalse(is_wp_error($n));
  $this->assertSame('manual',$n['workout']['source_type']);
  $this->assertNull($n['workout']['authoritative_import_id']);
  $active=array_values(array_filter($n['lengths'],function($l){return 'active'===$l['length_type'];}));
  $this->assertCount(8,$active);
  foreach($active as $length)$this->assertNull($length['elapsed_time_ms']);
  $this->assertEqualsWithDelta(200.0,$n['workout']['original_distance'],0.001);
 }
 public function test_measured_set_time_stays_at_set_level_and_is_not_divided(){
  $d=$this->document();
  $d['segments']=array(array('type'=>'active','repetitions'=>4,'distance_per_rep'=>100,'stroke'=>'BR','timing_type'=>'measured_set','set_time_ms'=>440000));
  $n=Manual_Workout::normalize_document($d);
  $this->assertSame(440000,$n['laps'][0]['elapsed_time_ms']);
  foreach($n['lengths'] as $length)$this->assertNull($length['elapsed_time_ms']);
 }
 public function test_unknown_timing_remains_null(){
  $d=$this->document();
  $d['segments']=array(array('type'=>'active','repetitions'=>1,'distance_per_rep'=>50,'stroke'=>'FR','timing_type'=>'unknown'));
  $n=Manual_Workout::normalize_document($d);
  $this->assertNull($n['laps'][0]['elapsed_time_ms']);
  foreach($n['lengths'] as $length)$this->assertNull($length['elapsed_time_ms']);
 }
 public function test_invalid_measured_rep_count_is_rejected(){
  $d=$this->document(); $d['segments'][0]['rep_times_ms']=array(42000);
  $e=Manual_Workout::validate_document($d);
  $this->assertTrue(is_wp_error($e));
  $this->assertSame('swimlog_manual_rep_count',$e->get_error_code());
 }
 public function test_service_contract_is_transactional_and_separate_from_importer(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-manual-workout.php');
  $this->assertStringContainsString("query( 'START TRANSACTION' )",$code);
  $this->assertStringContainsString("query( 'ROLLBACK' )",$code);
  $this->assertStringContainsString("query( 'COMMIT' )",$code);
  $this->assertStringContainsString("'manual' !== (string) \$existing->source_type",$code);
  $this->assertStringContainsString("Database::table( 'manual_workouts' )",$code);
  $this->assertStringNotContainsString("Database::table( 'imports' )",$code);
  $this->assertStringContainsString("Database::table( 'performances' )",$code);
 }
}
