<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Shortcodes;

final class PublicWorkoutTypeTest extends TestCase {
 private function db($workout,$analytics=false){
  return new class($workout,$analytics) {
   public $prefix='wp_';private $workout;private $analytics;
   public function __construct($w,$a){$this->workout=$w;$this->analytics=$a;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($q){
    $sql=is_array($q)?$q['sql']:$q;
    if(strpos($sql,'SELECT id,user_id')!==false)return(object)array('id'=>$this->workout->id,'user_id'=>$this->workout->user_id);
    if(strpos($sql,'SELECT w.*, l.name AS location_name')!==false)return$this->workout;
    return null;
   }
   public function get_results($q){
    $sql=is_array($q)?$q['sql']:$q;
    if(strpos($sql,'FROM wp_swimlog_performances')!==false)return array();
    if(strpos($sql,'FROM wp_swimlog_trackpoints')!==false)return $this->analytics?:array();
    return array();
   }
  };
 }
 private function workout($type){
  return(object)array('id'=>501,'user_id'=>42,'workout_type'=>$type,'workout_start'=>'2026-10-23 08:00:00','location_name'=>'Test Lake','original_distance'=>1000,'original_distance_unit'=>'m','total_distance_m'=>1000,'original_pool_length'=>$type==='pool'?25:null,'pool_length_unit'=>$type==='pool'?'m':null,'elapsed_time_ms'=>1800000,'moving_time_ms'=>$type==='open_water'?1750000:null,'average_heart_rate'=>$type==='open_water'?132:null,'max_heart_rate'=>$type==='open_water'?151:null,'average_cadence'=>$type==='open_water'?31.5:null,'max_cadence'=>$type==='open_water'?38:null,'primary_stroke'=>$type==='pool'?'FR':null);
 }
 public function test_open_water_workout_uses_open_water_fields_not_pool_placeholder(){
  global $wpdb;$wpdb=$this->db($this->workout('open_water'));
  $html=Shortcodes::workout(array('id'=>501,'title'=>'no'));
  $this->assertStringContainsString('Open Water',$html);
  $this->assertStringContainsString('Moving',$html);
  $this->assertStringContainsString('Average pace / 100 m',$html);
  $this->assertStringNotContainsString('<dt>Pool</dt>',$html);
 }
 public function test_public_open_water_workout_exposes_safe_analytics_without_gps_route(){
  $source=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString("esc_html__('Active time'",$source);
  $this->assertStringContainsString("esc_html__('Heart rate'",$source);
  $this->assertStringContainsString("esc_html__('Cadence'",$source);
  $this->assertStringContainsString("esc_html__('1 km Splits'",$source);
  $this->assertStringContainsString("self::public_routes_allowed(\$uid)",$source);
  $this->assertStringContainsString("Workout::trackpoints(\$id,\$uid)",$source);
  $this->assertStringNotContainsString("gps_point_count",$source);
 }
 public function test_pool_workout_keeps_pool_field(){
  global $wpdb;$wpdb=$this->db($this->workout('pool'));
  $html=Shortcodes::workout(array('id'=>501,'title'=>'no'));
  $this->assertStringContainsString('<dd>Pool</dd>',$html);
  $this->assertStringContainsString('<dt>Pool</dt><dd>25 m</dd>',$html);
  $this->assertStringNotContainsString('Moving pace / 100 m',$html);
 }
}
