<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;

final class FitNormalizationEnhancementsTest extends TestCase {
 private function normalize($session,$lengths,$activity=array()){
  $m=new ReflectionMethod(FIT_Importer::class,'normalize');$m->setAccessible(true);
  return $m->invoke(new FIT_Importer(),array(
   'session'=>$session,'laps'=>array(),'lengths'=>$lengths,'records'=>array(),'device'=>array(),'activity'=>$activity
  ));
 }

 public function test_length_stroke_count_is_normalized_for_active_lengths(){
  $session=array(2=>900000000,5=>5,6=>17,7=>30000,9=>2500,44=>2500,46=>0);
  $lengths=array(
   array(2=>900000000,3=>15000,5=>9,7=>0,12=>1),
   array(2=>900000015,3=>15000,5=>0,12=>0)
  );
  $r=$this->normalize($session,$lengths);
  $this->assertSame(9,$r['lengths'][0]['stroke_count']);
  $this->assertNull($r['lengths'][1]['stroke_count']);
  $this->assertSame('FR',$r['lengths'][0]['stroke']);
 }

 public function test_fit_activity_local_timestamp_controls_historical_local_time(){
  $start=900000000;$offset=-14400;
  $session=array(2=>$start,5=>5,6=>17,7=>30000,9=>2500,44=>2500,46=>0);
  $activity=array(array(253=>$start+60,5=>$start+60+$offset));
  $lengths=array(array(2=>$start,3=>30000,5=>10,7=>0,12=>1));
  $r=$this->normalize($session,$lengths,$activity);
  $expected=gmdate('Y-m-d H:i:s',$start+631065600+$offset);
  $this->assertSame($expected,$r['workout']['workout_start']);
  $this->assertSame($offset,$r['metadata']['fit_local_offset_seconds']);
  $this->assertSame(gmdate('Y-m-d H:i:s',$start+631065600+$offset+30),$r['workout']['workout_end']);
 }

 public function test_implausible_fit_local_offset_falls_back_to_wordpress_timezone(){
  $start=900000000;
  $session=array(2=>$start,5=>5,6=>17,7=>1000,9=>2500,44=>2500,46=>0);
  $activity=array(array(253=>$start,5=>$start+90000));
  $lengths=array(array(2=>$start,3=>1000,5=>10,7=>0,12=>1));
  $r=$this->normalize($session,$lengths,$activity);
  $this->assertNull($r['metadata']['fit_local_offset_seconds']);
  $this->assertSame(get_date_from_gmt(gmdate('Y-m-d H:i:s',$start+631065600),'Y-m-d H:i:s'),$r['workout']['workout_start']);
 }
}
