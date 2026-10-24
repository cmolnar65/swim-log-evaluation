<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;
use SwimLogEvaluation\Evaluator;

final class OpenWaterRegressionTest extends TestCase {
 private function definition($local,$global,$fields){
  $body=chr(0).chr(0).pack('v',$global).chr(count($fields));
  foreach($fields as $field)$body.=chr($field[0]).chr($field[1]).chr($field[2]);
  return chr(0x40|$local).$body;
 }
 private function data($local,$values,$fields){
  $body=chr($local);
  foreach($fields as $field){
   $v=$values[$field[0]];
   $body.=$field[1]===1?chr($v):pack('V',$v);
  }
  return $body;
 }
 private function crc16($data){
  $crc=0;
  for($i=0,$n=strlen($data);$i<$n;$i++){
   $byte=ord($data[$i]);
   for($b=0;$b<8;$b++){
    $bit=$crc&1;$crc>>=1;
    if(($byte>>$b)&1)$bit^=1;
    if($bit)$crc^=0xA001;
   }
  }
  return $crc&0xffff;
 }
 private function fitTime($unix){return $unix-631065600;}
 private function semicircles($degrees){return (int)round($degrees*2147483648.0/180.0);}
 private function fixture($records=null){
  $session=array(array(2,4,6),array(5,1,2),array(6,1,2),array(7,4,6),array(8,4,6),array(9,4,6),array(16,1,2),array(17,1,2),array(18,1,2),array(19,1,2));
  $lap=array(array(2,4,6),array(7,4,6),array(8,4,6),array(9,4,6),array(24,1,2));
  $record=array(array(253,4,6),array(0,4,5),array(1,4,5),array(5,4,6),array(3,1,2),array(4,1,2));
  $start=$this->fitTime(strtotime('2026-10-23 14:00:00 UTC'));
  $data='';
  $data.=$this->definition(0,18,$session);
  $data.=$this->definition(1,19,$lap);
  $data.=$this->definition(2,20,$record);
  $data.=$this->data(1,array(2=>$start,7=>900000,8=>880000,9=>50000,24=>0),$lap);
  $data.=$this->data(1,array(2=>$start+900,7=>900000,8=>870000,9=>50000,24=>0),$lap);
  if(null===$records)$records=array(
   array(253=>$start,0=>$this->semicircles(39.7392),1=>$this->semicircles(-104.9903),5=>0,3=>120,4=>30),
   array(253=>$start+900,0=>$this->semicircles(39.7400),1=>$this->semicircles(-104.9800),5=>50000,3=>132,4=>32),
   array(253=>$start+1800,0=>$this->semicircles(39.7410),1=>$this->semicircles(-104.9700),5=>100000,3=>140,4=>34)
  );
  foreach($records as$values)$data.=$this->data(2,$values,$record);
  $data.=$this->data(0,array(2=>$start,5=>5,6=>18,7=>1800000,8=>1750000,9=>100000,16=>131,17=>145,18=>32,19=>36),$session);
  $header=chr(14).chr(0x10).pack('v',100).pack('V',strlen($data)).'.FIT';
  $header.=pack('v',$this->crc16($header));
  $file=$header.$data;
  $file.=pack('v',$this->crc16($file));
  $path=tempnam(sys_get_temp_dir(),'swimlog-ows-');
  file_put_contents($path,$file);
  return $path;
 }
 public function test_open_water_fit_normalizes_session_laps_and_gps(){
  $path=$this->fixture();
  try{$r=(new FIT_Importer)->parse($path);}finally{@unlink($path);}
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $w=$r['workout'];
  $this->assertSame('open_water',$w['workout_type']);
  $this->assertEqualsWithDelta(1000.0,(float)$w['total_distance_m'],0.001);
  $this->assertSame(1800000,(int)$w['elapsed_time_ms']);
  $this->assertSame(1750000,(int)$w['moving_time_ms']);
  $this->assertSame(131,(int)$w['average_heart_rate']);
  $this->assertSame(145,(int)$w['max_heart_rate']);
  $this->assertNull($w['pool_length_m']);
  $this->assertNull($w['pool_length_unit']);
  $this->assertSame(array(),$r['lengths']);
  $this->assertCount(2,$r['laps']);
  $this->assertEqualsWithDelta(500.0,(float)$r['laps'][0]['distance_m'],0.001);
  $this->assertSame(900000,(int)$r['laps'][1]['start_offset_ms']);
  $this->assertCount(3,$r['trackpoints']);
  $this->assertEqualsWithDelta(39.7392,(float)$r['trackpoints'][0]['latitude'],0.0001);
  $this->assertEqualsWithDelta(-104.9903,(float)$r['trackpoints'][0]['longitude'],0.0001);
  $this->assertEqualsWithDelta(1000.0,(float)$r['trackpoints'][2]['distance_m'],0.001);
  $this->assertSame(140,(int)$r['trackpoints'][2]['heart_rate']);
  $this->assertSame(18,$r['metadata']['fit_sub_sport']);
 }
 public function test_open_water_fit_preserves_sparse_recording_stream(){
  $start=$this->fitTime(strtotime('2026-10-23 14:00:00 UTC'));
  $records=array(
   array(253=>$start,0=>$this->semicircles(39.7392),1=>$this->semicircles(-104.9903),5=>0,3=>120,4=>30),
   array(253=>$start+1800,0=>$this->semicircles(39.7410),1=>$this->semicircles(-104.9700),5=>100000,3=>140,4=>34)
  );
  $path=$this->fixture($records);
  try{$r=(new FIT_Importer)->parse($path);}finally{@unlink($path);}
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $this->assertSame('open_water',$r['workout']['workout_type']);
  $this->assertCount(2,$r['trackpoints']);
  $this->assertSame(array(),$r['lengths']);
  $this->assertEqualsWithDelta(1000.0,(float)$r['trackpoints'][1]['distance_m'],0.001);
 }
 public function test_open_water_fit_preserves_distance_correction_for_analytics_layer(){
  $start=$this->fitTime(strtotime('2026-10-23 14:00:00 UTC'));
  $records=array(
   array(253=>$start,0=>$this->semicircles(39.7392),1=>$this->semicircles(-104.9903),5=>0,3=>120,4=>30),
   array(253=>$start+600,0=>$this->semicircles(39.7395),1=>$this->semicircles(-104.9870),5=>60000,3=>126,4=>31),
   array(253=>$start+900,0=>$this->semicircles(39.7397),1=>$this->semicircles(-104.9850),5=>55000,3=>128,4=>31),
   array(253=>$start+1800,0=>$this->semicircles(39.7410),1=>$this->semicircles(-104.9700),5=>100000,3=>140,4=>34)
  );
  $path=$this->fixture($records);
  try{$r=(new FIT_Importer)->parse($path);}finally{@unlink($path);}
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $this->assertCount(4,$r['trackpoints']);
  $this->assertEqualsWithDelta(600.0,(float)$r['trackpoints'][1]['distance_m'],0.001);
  $this->assertEqualsWithDelta(550.0,(float)$r['trackpoints'][2]['distance_m'],0.001);
  $this->assertEqualsWithDelta(1000.0,(float)$r['trackpoints'][3]['distance_m'],0.001);
  $this->assertSame(array(),$r['lengths']);
 }
 public function test_open_water_workout_never_generates_pool_performances(){
  global $wpdb;
  $wpdb=new class {
   public $prefix='wp_'; public $insert_calls=0; public $delete_calls=0;
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($prepared){return(object)array('id'=>501,'user_id'=>42,'workout_type'=>'open_water','pool_length_unit'=>null,'original_pool_length'=>null,'original_distance'=>1000,'elapsed_time_ms'=>1800000,'primary_stroke'=>null,'workout_start'=>'2026-10-23 08:00:00');}
   public function get_results($prepared){return array();}
   public function query($sql){return true;}
   public function delete($table,$where){$this->delete_calls++;return 1;}
   public function insert($table,$data){$this->insert_calls++;return 1;}
   public function update($table,$data,$where){return 1;}
   public function get_var($prepared){return null;}
  };
  $result=Evaluator::evaluate_workout(501,42);
  $this->assertSame(0,$result);
  $this->assertSame(0,$wpdb->insert_calls);
  $this->assertSame(0,$wpdb->delete_calls);
 }
}
