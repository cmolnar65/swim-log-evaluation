<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Shortcodes;

final class AnnualWorkoutTypeLogTest extends TestCase {
 public function test_annual_log_separates_pool_and_open_water_totals(){
  global $wpdb;
  $wpdb=new class {
   public $prefix='wp_';
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_results($q){
    $sql=is_array($q)?$q['sql']:$q;$args=is_array($q)?$q['args']:array();
    $type=end($args);
    if(strpos($sql,'GROUP BY MONTH(workout_start)')===false)return array();
    if($type==='pool')return array(
     (object)array('month_num'=>1,'workout_count'=>2,'meters'=>3000,'yards'=>0,'elapsed_ms'=>3600000),
     (object)array('month_num'=>2,'workout_count'=>1,'meters'=>0,'yards'=>2000,'elapsed_ms'=>1800000)
    );
    if($type==='open_water')return array(
     (object)array('month_num'=>1,'workout_count'=>1,'meters'=>5000,'yards'=>0,'elapsed_ms'=>5400000)
    );
    return array();
   }
  };
  $html=Shortcodes::log(array('user'=>42,'year'=>2026,'title'=>'no'));
  $this->assertStringContainsString('Pool Workouts',$html);
  $this->assertStringContainsString('Pool Distance',$html);
  $this->assertStringContainsString('Open Water Workouts',$html);
  $this->assertStringContainsString('Open Water Distance',$html);
  $this->assertStringContainsString('3,000 m',$html);
  $this->assertStringContainsString('5,000 m',$html);
  $this->assertStringContainsString('2,000 yd',$html);
  $this->assertStringContainsString('2026 Total',$html);
 }
}
