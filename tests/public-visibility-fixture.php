<?php
/** Minimal SQL recorder for public renderer unit tests; real SQL/migrations run in wp-env. */
final class PublicVisibilityFixture {
 public $prefix='wp_';public $queries=array();private $rows;
 public function __construct($rows){$this->rows=$rows;}
 public function esc_like($s){return$s;}
 public function prepare($sql,...$args){foreach($args as$arg)$sql=preg_replace('/%[idsf]/',is_numeric($arg)?(string)$arg:"'".$arg."'",$sql,1);return$sql;}
 public function get_var($sql){$this->queries[]=$sql;if(strpos($sql,'SHOW TABLES')===0)return'wp_swimlog_workout_visibility';if(strpos($sql,'SELECT visibility')===0)return'ready';if(strpos($sql,'COUNT(*)')!==false)return count($this->rows);return null;}
 public function get_row($sql){$this->queries[]=$sql;return strpos($sql,'SELECT w.*,v.route_public')===0?clone$this->rows[0]:null;}
 public function get_results($sql){$this->queries[]=$sql;return strpos($sql,'SELECT w.*,v.route_public')===0?$this->rows:array();}
 public static function row($type,$distance=1000,$unit='m'){
  return(object)array('id'=>501,'user_id'=>42,'workout_type'=>$type,'workout_start'=>'2026-01-01 08:00:00','original_distance'=>$distance,'original_distance_unit'=>$unit,'total_distance_m'=>$distance,'original_pool_length'=>$type==='pool'?25:null,'pool_length_unit'=>$type==='pool'?'m':null,'elapsed_time_ms'=>1800000,'moving_time_ms'=>$type==='open_water'?1750000:null,'primary_stroke'=>$type==='pool'?'FR':null,'route_public'=>0,'visibility'=>'public');
 }
}
