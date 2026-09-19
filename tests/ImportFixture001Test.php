<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;
use SwimLogEvaluation\CSV_Importer;
use SwimLogEvaluation\Importer;

final class ImportFixture001Test extends TestCase {
 private function fixture($name){$p=__DIR__.'/fixtures/'.$name;if(!is_file($p))$this->markTestSkipped('Original September 19 FORM fixture is not present: '.$name);return$p;}
 private function assertWorkout($r){
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $this->assertSame('m',$r['workout']['original_distance_unit']);
  $this->assertEqualsWithDelta(25.0,(float)$r['workout']['original_pool_length'],0.001);
  $this->assertEqualsWithDelta(3000.0,(float)$r['workout']['original_distance'],0.01);
  $this->assertEqualsWithDelta(3945671,(int)$r['workout']['elapsed_time_ms'],1000);
  $this->assertCount(152,$r['lengths']);
  $active=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='active'));
  $rest=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='rest'));
  $this->assertCount(120,$active);$this->assertCount(32,$rest);
  $fr=array_values(array_filter($active,fn($x)=>$x['stroke']==='FR'));$br=array_values(array_filter($active,fn($x)=>$x['stroke']==='BR'));
  $this->assertCount(74,$fr);$this->assertCount(46,$br);
  $this->assertEqualsWithDelta(1850,array_sum(array_column($fr,'distance_m')),0.01);
  $this->assertEqualsWithDelta(1150,array_sum(array_column($br,'distance_m')),0.01);
 }
 public function test_fit_fixture_001(){ $r=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));$this->assertWorkout($r);$this->assertCount(65,$r['laps']);$this->assertEqualsWithDelta(16591,(int)$r['lengths'][0]['elapsed_time_ms'],10); }
 public function test_csv_fixture_001(){ $r=(new CSV_Importer)->parse($this->fixture('form-2026-09-19.csv'));$this->assertWorkout($r); }
 public function test_fit_and_csv_agree_on_structural_totals(){
  $fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));$csv=(new CSV_Importer)->parse($this->fixture('form-2026-09-19.csv'));
  $this->assertSame(count($fit['lengths']),count($csv['lengths']));$this->assertEqualsWithDelta($fit['workout']['original_distance'],$csv['workout']['original_distance'],0.01);
 }
 public function test_august_26_empty_form_csv_is_rejected(){
  $path=$this->fixture('form-2026-08-26-empty.csv');
  $parsed=(new CSV_Importer)->parse($path);
  $this->assertFalse(is_wp_error($parsed),is_wp_error($parsed)?$parsed->get_error_message():'');
  $this->assertSame('csv',$parsed['source']);
  $this->assertSame('2026-08-26 10:20:19',$parsed['workout']['workout_start']);
  $this->assertSame('2026-08-26 11:07:03',$parsed['workout']['workout_end']);
  $this->assertEqualsWithDelta(25.0,(float)$parsed['workout']['original_pool_length'],0.001);
  $this->assertEmpty($parsed['lengths']);
  $result=Importer::validate($parsed);
  $this->assertTrue(is_wp_error($result));
  $this->assertSame('swimlog_csv_incomplete',$result->get_error_code());
 }
 public function test_garmin_export_csv_is_cleanly_rejected_in_v01(){
  $path=getenv('SWIMLOG_GARMIN_CSV_FIXTURE');
  if(!$path||!is_file($path))$this->markTestSkipped('Set SWIMLOG_GARMIN_CSV_FIXTURE to the unchanged private Garmin export CSV activity_22038489308.csv.');
  $result=(new CSV_Importer)->parse($path);
  $this->assertTrue(is_wp_error($result),'Garmin export CSV must not be interpreted as a FORM CSV in v0.1.');
  $this->assertSame('swimlog_csv_format',$result->get_error_code());
 }
}
