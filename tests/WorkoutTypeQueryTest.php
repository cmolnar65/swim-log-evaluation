<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Workout;

final class WorkoutTypeQueryTest extends TestCase {
 private function db(){
  return new class {
   public $prefix='wp_'; public $queries=array();
   public function prepare($sql,...$args){
    $i=0;
    return preg_replace_callback('/%[dfs]/',function($m)use(&$i,$args){
     $v=$args[$i++];
     if($m[0]==='%d')return (string)(int)$v;
     if($m[0]==='%f')return (string)(float)$v;
     return "'".str_replace("'","''",(string)$v)."'";
    },$sql);
   }
   public function get_results($sql){$this->queries[]=$sql;return array();}
   public function get_var($sql){$this->queries[]=$sql;return 0;}
  };
 }
 public function test_query_for_user_accepts_pool_filter(){
  global $wpdb;$wpdb=$this->db();
  Workout::query_for_user(42,array('workout_type'=>'pool'),1,20);
  $this->assertStringContainsString("w.workout_type='pool'",implode("\n",$wpdb->queries));
 }
 public function test_query_for_user_accepts_open_water_filter(){
  global $wpdb;$wpdb=$this->db();
  Workout::query_for_user(42,array('workout_type'=>'open_water'),1,20);
  $this->assertStringContainsString("w.workout_type='open_water'",implode("\n",$wpdb->queries));
 }
 public function test_invalid_query_filter_preserves_all_types(){
  global $wpdb;$wpdb=$this->db();
  Workout::query_for_user(42,array('workout_type'=>'invalid'),1,20);
  $this->assertStringNotContainsString('workout_type=',implode("\n",$wpdb->queries));
 }
 public function test_recent_defaults_to_all_and_can_filter(){
  global $wpdb;$wpdb=$this->db();
  Workout::recent_for_user(42,5);
  $this->assertStringNotContainsString('workout_type=',$wpdb->queries[0]);
  $wpdb=$this->db();
  Workout::recent_for_user(42,5,'open_water');
  $this->assertStringContainsString("w.workout_type='open_water'",$wpdb->queries[0]);
 }
 public function test_month_defaults_to_all_and_can_filter(){
  global $wpdb;$wpdb=$this->db();
  Workout::for_month(42,2026,10);
  $this->assertStringNotContainsString('workout_type=',$wpdb->queries[0]);
  $wpdb=$this->db();
  Workout::for_month(42,2026,10,'pool');
  $this->assertStringContainsString("w.workout_type='pool'",$wpdb->queries[0]);
 }
 public function test_year_totals_defaults_to_all_and_can_filter(){
  global $wpdb;$wpdb=$this->db();
  Workout::totals_by_month_for_year(42,2026);
  $this->assertStringNotContainsString('workout_type=',$wpdb->queries[0]);
  $wpdb=$this->db();
  Workout::totals_by_month_for_year(42,2026,'open_water');
  $this->assertStringContainsString("workout_type='open_water'",$wpdb->queries[0]);
 }
}
