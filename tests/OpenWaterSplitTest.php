<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Workout;

final class OpenWaterSplitTest extends TestCase {
 private function analytics($distances,$total=3000){
  global $wpdb;
  $base=strtotime('2026-10-23 08:00:00');
  $points=array();
  foreach($distances as $i=>$distance)$points[]=(object)array('sequence_no'=>$i+1,'recorded_at'=>date('Y-m-d H:i:s',$base+$i*600),'latitude'=>39.7,'longitude'=>-104.9,'distance_m'=>$distance,'heart_rate'=>130,'cadence'=>30);
  $workout=(object)array('id'=>501,'user_id'=>42,'workout_type'=>'open_water','total_distance_m'=>$total,'elapsed_time_ms'=>1800000,'moving_time_ms'=>1750000);
  $wpdb=new class($workout,$points){
   public $prefix='wp_';private $w;private $p;private $calls=0;
   public function __construct($w,$p){$this->w=$w;$this->p=$p;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($q){return $this->w;}
   public function get_results($q){return $this->p;}
  };
  return Workout::open_water_analytics(501,42);
 }
 public function test_analytics_query_fetches_only_required_trackpoint_fields(){
  $source=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString('SELECT recorded_at,latitude,longitude,distance_m,cadence FROM',$source);
  $this->assertStringNotContainsString("distance_points = array()",$source);
  $this->assertStringContainsString("timezone = wp_timezone()",$source);
 }
 public function test_monotonic_track_produces_expected_kilometer_splits(){
  $a=$this->analytics(array(0,1000,2000,3000));
  $this->assertCount(3,$a['splits']);
  $this->assertSame(array(1000,2000,3000),array_column($a['splits'],'distance_m'));
  $this->assertSame(600000,$a['splits'][0]['split_time_ms']);
 }
 public function test_distance_plateau_does_not_create_duplicate_or_zero_split(){
  $a=$this->analytics(array(0,900,900,1100,2000,3000));
  $this->assertCount(3,$a['splits']);
  $this->assertSame(array(1000,2000,3000),array_column($a['splits'],'distance_m'));
  foreach($a['splits'] as $s)$this->assertGreaterThan(0,$s['split_time_ms']);
 }
 public function test_distance_regression_uses_first_progress_crossing_only(){
  $a=$this->analytics(array(0,800,1200,1050,1600,2100,3000));
  $this->assertCount(3,$a['splits']);
  $this->assertSame(array(1000,2000,3000),array_column($a['splits'],'distance_m'));
  $this->assertGreaterThan(0,$a['splits'][1]['split_time_ms']);
  $this->assertGreaterThan($a['splits'][0]['cumulative_time_ms'],$a['splits'][1]['cumulative_time_ms']);
 }
 public function test_splits_stop_at_reachable_trackpoint_distance(){
  $a=$this->analytics(array(0,900,1800,2400),3000);
  $this->assertCount(2,$a['splits']);
  $this->assertSame(array(1000,2000),array_column($a['splits'],'distance_m'));
 }
}
