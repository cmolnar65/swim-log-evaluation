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
 public function test_sequence_gap_is_hard_boundary(){
  $rows=[$this->row(1,1),$this->row(2,2),$this->row(4,4),$this->row(5,5)];$c=$this->candidates($rows);$this->assertEmpty(array_filter($c,fn($x)=>$x['distance']===100));
 }
 public function test_wrong_length_distance_is_hard_boundary(){
  $rows=[$this->row(1,1),$this->row(2,2),$this->row(3,3,'active','FR',20000,50),$this->row(4,4),$this->row(5,5)];$c=$this->candidates($rows);$this->assertEmpty(array_filter($c,fn($x)=>$x['distance']===100));
 }
 public function test_large_timestamp_gap_alone_does_not_break_block(){
  $a=$this->row(1,1);$b=$this->row(2,2);$b->start_offset_ms=3600000;$c=$this->candidates([$a,$b]);$this->assertNotEmpty(array_filter($c,fn($x)=>$x['distance']===50));
 }
 public function test_exact_millisecond_duration_is_preserved(){
  $a=$this->row(1,1,'active','FR',18765);$b=$this->row(2,2,'active','FR',19041);$c=$this->candidates([$a,$b]);$f=array_values(array_filter($c,fn($x)=>$x['distance']===50));$this->assertSame(37806,$f[0]['duration']);
 }
 public function test_1650_and_3300_meter_targets_are_native(){
  $rows=[];for($i=1;$i<=132;$i++)$rows[]=$this->row($i,$i);$c=$this->candidates($rows);$d=array_unique(array_column($c,'distance'));$this->assertContains(1650,$d);$this->assertContains(3300,$d);
 }
 public function test_zero_duration_length_is_hard_boundary(){
  $rows=[$this->row(1,1),$this->row(2,2,'active','FR',0),$this->row(3,3),$this->row(4,4)];$c=$this->candidates($rows);$this->assertEmpty(array_filter($c,fn($x)=>$x['distance']===100));
 }
 public function test_rest_duration_is_never_included_in_candidate(){
  $rows=[$this->row(1,1,'active','FR',10000),$this->row(2,2,'active','FR',11000),$this->row(3,3,'rest',null,60000,0),$this->row(4,4,'active','FR',12000),$this->row(5,5,'active','FR',13000)];
  $c=$this->candidates($rows);$f=array_values(array_filter($c,fn($x)=>$x['distance']===50));$this->assertCount(2,$f);$this->assertSame([21000,25000],array_column($f,'duration'));
 }
 public function test_unknown_contamination_beats_mixed_label(){
  $rows=[$this->row(1,1,'active','FR'),$this->row(2,2,'active','BR'),$this->row(3,3,'active','UNKNOWN'),$this->row(4,4,'active','FR')];$c=$this->candidates($rows);$h=array_values(array_filter($c,fn($x)=>$x['distance']===100));$this->assertSame('UNKNOWN',$h[0]['stroke']);
 }
 public function test_overshoot_does_not_create_interpolated_target(){
  $rows=[$this->row(1,1,'active','FR',20000,30),$this->row(2,2,'active','FR',20000,30)];$c=$this->candidates($rows,'m',0);$this->assertEmpty(array_filter($c,fn($x)=>$x['distance']===50));
 }
}

