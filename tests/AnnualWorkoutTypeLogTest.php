<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Shortcodes;

final class AnnualWorkoutTypeLogTest extends TestCase {
 public function test_annual_log_separates_pool_and_open_water_totals(){
  global $wpdb;
  $wpdb=new class {
   public $prefix='wp_';
   public function prepare($sql,...$args){
    foreach($args as$arg){$replacement=is_numeric($arg)?(string)$arg:"'".str_replace("'","''",(string)$arg)."'";$sql=preg_replace('/%[dsf]/',$replacement,$sql,1);}
    return $sql;
   }
   public function get_results($sql){
    if(strpos($sql,'GROUP BY MONTH(workout_start)')===false)return array();
    if(strpos($sql,"workout_type='pool'")!==false)return array(
     (object)array('month_num'=>1,'workout_count'=>2,'meters'=>3000,'yards'=>0,'elapsed_ms'=>3600000),
     (object)array('month_num'=>2,'workout_count'=>1,'meters'=>0,'yards'=>2000,'elapsed_ms'=>1800000)
    );
    if(strpos($sql,"workout_type='open_water'")!==false)return array(
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
