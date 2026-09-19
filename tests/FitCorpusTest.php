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
   $w=$r['workout'];$this->assertContains($w['original_distance_unit'],array('m','yd'),basename($file));
   $this->assertGreaterThan(0,(float)$w['original_pool_length'],basename($file));
   $this->assertGreaterThan(0,(float)$w['original_distance'],basename($file));
   $this->assertGreaterThan(0,(int)$w['elapsed_time_ms'],basename($file));
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
}
