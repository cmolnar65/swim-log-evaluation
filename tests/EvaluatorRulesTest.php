<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Evaluator;

final class EvaluatorRulesTest extends TestCase {
 private function candidates($rows,$course='m',$pool=25){
  $w=(object)['original_pool_length'=>$pool];$m=new ReflectionMethod(Evaluator::class,'from_lengths');$m->setAccessible(true);return$m->invoke(null,$rows,$w,$course);
 }
 private function row($id,$seq,$type='active',$stroke='FR',$ms=20000,$meters=25){
  return(object)['id'=>$id,'sequence_no'=>$seq,'length_type'=>$type,'distance_m'=>$type==='active'?$meters:0,'elapsed_time_ms'=>$ms,'stroke'=>$type==='active'?$stroke:null,'start_offset_ms'=>($seq-1)*$ms];
 }
 public function test_rest_is_hard_boundary(){
  $rows=[];for($i=1;$i<=32;$i++)$rows[]=$this->row($i,$i);$rows[]=$this->row(33,33,'rest',null,49079,0);for($i=34;$i<=41;$i++)$rows[]=$this->row($i,$i);
  $c=$this->candidates($rows);$dist=array_unique(array_column($c,'distance'));sort($dist);
  $this->assertContains(50,$dist);$this->assertContains(100,$dist);$this->assertContains(200,$dist);$this->assertContains(500,$dist);$this->assertContains(800,$dist);$this->assertNotContains(1000,$dist);
 }
 public function test_mixed_strokes_classify_mixed(){
  $rows=[];for($i=1;$i<=4;$i++)$rows[]=$this->row($i,$i,'active',$i<=2?'FR':'BR');
  $c=$this->candidates($rows);$hundred=array_values(array_filter($c,fn($x)=>$x['distance']===100));$this->assertNotEmpty($hundred);$this->assertSame('MIXED',$hundred[0]['stroke']);
 }
 public function test_unknown_prevents_stroke_specific_classification(){
  $rows=[$this->row(1,1,'active','FR'),$this->row(2,2,'active','UNKNOWN')];$c=$this->candidates($rows);$this->assertSame('UNKNOWN',$c[0]['stroke']);
 }
 public function test_yard_native_distance_is_not_meter_pb(){
  $rows=[$this->row(1,1,'active','FR',20000,25*0.9144),$this->row(2,2,'active','FR',20000,25*0.9144)];$c=$this->candidates($rows,'yd',25);$this->assertSame('yd',$c[0]['course']);$this->assertSame(50,$c[0]['distance']);
 }
}
