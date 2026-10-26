<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;

/**
 * Optional real-world corpus test.
 *
 * Set SWIMLOG_FIT_FIXTURE_DIR to a directory containing private/original FIT
 * files. The files are intentionally not committed to the public repository.
 */
final class FitCorpusTest extends TestCase {
 public function test_real_fit_corpus_invariants(){
  $dir=getenv('SWIMLOG_FIT_FIXTURE_DIR');
  if(!$dir||!is_dir($dir))$this->markTestSkipped('Set SWIMLOG_FIT_FIXTURE_DIR to run the private FIT corpus.');
  $files=glob(rtrim($dir,'/\\').'/*.fit');if(!$files)$this->markTestSkipped('No FIT files found in corpus directory.');
  foreach($files as$file){
   $r=(new FIT_Importer)->parse($file);
   $this->assertFalse(is_wp_error($r),basename($file).': '.(is_wp_error($r)?$r->get_error_message():''));
   $w=$r['workout'];$type=$w['workout_type']??'pool';
   $this->assertContains($type,array('pool','open_water'),basename($file));
   $this->assertGreaterThan(0,(float)$w['total_distance_m'],basename($file));
   $this->assertGreaterThan(0,(int)$w['elapsed_time_ms'],basename($file));
   if($type==='open_water'){
    $this->assertSame('m',$w['original_distance_unit'],basename($file));
    $this->assertNull($w['pool_length_m'],basename($file));
    $this->assertNull($w['pool_length_unit'],basename($file));
    $this->assertSame(array(),$r['lengths'],basename($file));
    $this->assertArrayHasKey('trackpoints',$r,basename($file));
    continue;
   }
   $this->assertContains($w['original_distance_unit'],array('m','yd'),basename($file));
   $this->assertGreaterThan(0,(float)$w['original_pool_length'],basename($file));
   $this->assertGreaterThan(0,(float)$w['original_distance'],basename($file));
   $this->assertNotEmpty($r['lengths'],basename($file));
   $active=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='active'));
   $this->assertNotEmpty($active,basename($file));
   $active_m=array_sum(array_column($active,'distance_m'));
   $workout_m=(float)$w['total_distance_m'];
   $this->assertEqualsWithDelta($workout_m,$active_m,0.05,basename($file).' active length distance must equal session distance');
   $active_ms=array_sum(array_map(fn($x)=>(int)($x['elapsed_time_ms']??0),$active));
   $this->assertLessThanOrEqual((int)$w['elapsed_time_ms']+1000,$active_ms,basename($file).' active time cannot materially exceed session elapsed time');
  }
 }
 public function test_non_form_25_yard_fixture(){
  $path=getenv('SWIMLOG_YARD_FIT_FIXTURE');
  if(!$path||!is_file($path))$this->markTestSkipped('Set SWIMLOG_YARD_FIT_FIXTURE to the unchanged private 22038489308_ACTIVITY.fit file.');
  $r=(new FIT_Importer)->parse($path);
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $w=$r['workout'];
  $this->assertSame('yd',$w['original_distance_unit']);
  $this->assertSame('yd',$w['pool_length_unit']);
  $this->assertEqualsWithDelta(25.0,(float)$w['original_pool_length'],0.001);
  $this->assertEqualsWithDelta(22.86,(float)$w['pool_length_m'],0.001);
  $this->assertEqualsWithDelta(1700.0,(float)$w['original_distance'],0.01);
  $this->assertEqualsWithDelta(1554.48,(float)$w['total_distance_m'],0.01);
  $this->assertEqualsWithDelta(2029308,(int)$w['elapsed_time_ms'],10);
  $this->assertCount(2,$r['laps']);
  $this->assertCount(69,$r['lengths']);
  $active=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='active'));
  $rest=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='rest'));
  $this->assertCount(68,$active);$this->assertCount(1,$rest);
  $fr=array_values(array_filter($active,fn($x)=>$x['stroke']==='FR'));
  $br=array_values(array_filter($active,fn($x)=>$x['stroke']==='BR'));
  $this->assertCount(34,$fr);$this->assertCount(34,$br);
  $this->assertEqualsWithDelta(1554.48,array_sum(array_column($active,'distance_m')),0.01);
 }
 public function test_private_apple_watch_2018_fixture(){
  $path=getenv('SWIMLOG_APPLE_FIT_FIXTURE');
  if(!$path||!is_file($path))$this->markTestSkipped('Set SWIMLOG_APPLE_FIT_FIXTURE to the unchanged private 2018 Apple Watch pool FIT file.');
  $r=(new FIT_Importer)->parse($path);
  $this->assertFalse(is_wp_error($r),is_wp_error($r)?$r->get_error_message():'');
  $w=$r['workout'];
  $this->assertEqualsWithDelta(25.0,(float)$w['original_pool_length'],0.001);
  $this->assertEqualsWithDelta(1850.0,(float)$w['total_distance_m'],0.01);
  $this->assertSame(125,(int)$w['average_heart_rate']);
  $this->assertSame(148,(int)$w['max_heart_rate']);
  $this->assertSame('Apple',$w['device_manufacturer']);
  $this->assertSame('Watch3,2',$w['device_model']);
  $this->assertCount(23,$r['laps']);
  $active=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='active'));
  $rest=array_values(array_filter($r['lengths'],fn($x)=>$x['length_type']==='rest'));
  $this->assertCount(74,$active);$this->assertCount(11,$rest);
  $this->assertCount(44,array_values(array_filter($active,fn($x)=>$x['stroke']==='FR')));
  $this->assertCount(30,array_values(array_filter($active,fn($x)=>$x['stroke']==='BR')));
  $this->assertSame(array(9,9,13,12),array_slice(array_map(fn($x)=>$x['stroke_count'], $active),0,4));
  $this->assertSame('2018-07-10 08:51:49',$w['workout_start']);
 }

}
