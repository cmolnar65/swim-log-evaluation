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
 public function test_garmin_export_csv_is_cleanly_rejected_in_v02(){
  $path=getenv('SWIMLOG_GARMIN_CSV_FIXTURE');
  if(!$path||!is_file($path))$this->markTestSkipped('Set SWIMLOG_GARMIN_CSV_FIXTURE to the unchanged private Garmin export CSV activity_22038489308.csv.');
  $result=(new CSV_Importer)->parse($path);
  $this->assertTrue(is_wp_error($result),'Garmin export CSV must not be interpreted as a FORM CSV in v0.2.');
  $this->assertSame('swimlog_csv_format',$result->get_error_code());
 }
 public function test_paired_form_sources_meet_match_fingerprint(){
  $fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));
  $csv=(new CSV_Importer)->parse($this->fixture('form-2026-09-19.csv'));
  $this->assertFalse(is_wp_error($fit));$this->assertFalse(is_wp_error($csv));
  $fw=$fit['workout'];$cw=$csv['workout'];
  $this->assertSame($fw['workout_start'],$cw['workout_start']);
  $this->assertSame($fw['original_distance_unit'],$cw['original_distance_unit']);
  $this->assertSame($fw['pool_length_unit'],$cw['pool_length_unit']);
  $this->assertEqualsWithDelta((float)$fw['pool_length_m'],(float)$cw['pool_length_m'],0.001);
  $this->assertEqualsWithDelta((float)$fw['total_distance_m'],(float)$cw['total_distance_m'],0.5);
  $this->assertEqualsWithDelta((int)$fw['elapsed_time_ms'],(int)$cw['elapsed_time_ms'],2000);
  $this->assertTrue(Importer::validate($fit));
  $this->assertTrue(Importer::validate($csv));
 }
 public function test_fit_first_then_csv_attaches_to_same_workout(){
  global $wpdb;
  $fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));
  $csv=(new CSV_Importer)->parse($this->fixture('form-2026-09-19.csv'));
  $this->assertFalse(is_wp_error($fit));$this->assertFalse(is_wp_error($csv));
  $fw=$fit['workout'];$cw=$csv['workout'];

  // Represent the authoritative workout created by importing FIT first.
  $wpdb=new class($fw){
   public $prefix='wp_';private $fit;
   public function __construct($fit){$this->fit=$fit;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($prepared){
    $a=$prepared['args'];$uid=(int)$a[0];$start=$a[1];$dist=(float)$a[2];$elapsed=(int)$a[3];
    if($uid!==42)return null;
    if(abs(strtotime($this->fit['workout_start'])-strtotime($start))>2)return null;
    if(abs((float)$this->fit['total_distance_m']-$dist)>0.5)return null;
    if(abs((int)$this->fit['elapsed_time_ms']-$elapsed)>2000)return null;
    return (object)array('id'=>9001,'user_id'=>42);
   }
  };

  $m=new ReflectionMethod(Importer::class,'find_match');$m->setAccessible(true);
  $match=$m->invoke(null,42,$cw);
  $this->assertNotNull($match,'The supported FORM CSV should attach to the FIT-created workout.');
  $this->assertSame(9001,(int)$match->id);
 }
 public function test_csv_first_then_fit_attaches_to_same_workout_and_fit_is_structural_authority(){
  global $wpdb;
  $csv=(new CSV_Importer)->parse($this->fixture('form-2026-09-19.csv'));
  $fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));
  $this->assertFalse(is_wp_error($csv));$this->assertFalse(is_wp_error($fit));
  $cw=$csv['workout'];$fw=$fit['workout'];

  // Represent the workout already created from CSV, then match the later FIT.
  $wpdb=new class($cw){
   public $prefix='wp_';private $csv;
   public function __construct($csv){$this->csv=$csv;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($prepared){
    $a=$prepared['args'];$uid=(int)$a[0];$start=$a[1];$dist=(float)$a[2];$elapsed=(int)$a[3];
    if($uid!==42)return null;
    if(abs(strtotime($this->csv['workout_start'])-strtotime($start))>2)return null;
    if(abs((float)$this->csv['total_distance_m']-$dist)>0.5)return null;
    if(abs((int)$this->csv['elapsed_time_ms']-$elapsed)>2000)return null;
    return (object)array('id'=>9002,'user_id'=>42);
   }
  };

  $m=new ReflectionMethod(Importer::class,'find_match');$m->setAccessible(true);
  $match=$m->invoke(null,42,$fw);
  $this->assertNotNull($match,'The later FIT should attach to the CSV-created workout.');
  $this->assertSame(9002,(int)$match->id);

  // FIT import path always replaces normalized structure and updates the
  // workout, making FIT authoritative after attachment.
  $this->assertCount(65,$fit['laps']);
  $this->assertCount(152,$fit['lengths']);
  $active=array_values(array_filter($fit['lengths'],fn($x)=>$x['length_type']==='active'));
  $rest=array_values(array_filter($fit['lengths'],fn($x)=>$x['length_type']==='rest'));
  $this->assertCount(120,$active);$this->assertCount(32,$rest);
  $this->assertEqualsWithDelta(3000.0,(float)$fw['total_distance_m'],0.01);
  $this->assertEqualsWithDelta(3945671,(int)$fw['elapsed_time_ms'],10);
 }
 public function test_duplicate_source_hash_detection_rule(){
  $path=$this->fixture('form-2026-09-19.fit');
  $first=hash_file('sha256',$path);$second=hash_file('sha256',$path);
  $this->assertSame($first,$second);
  $this->assertSame(64,strlen($first));
  // Importer duplicate protection is scoped to user_id + SHA-256 before parsing.
  $source=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString("SELECT id FROM \\$it WHERE user_id=%d AND file_hash=%s",$source);
  $this->assertStringContainsString("swimlog_duplicate",$source);
 }

 public function test_september_18_fit_is_clearly_different_from_september_19(){
  $other=getenv('SWIMLOG_DIFFERENT_FIT_FIXTURE');
  if(!$other||!is_file($other))$this->markTestSkipped('Set SWIMLOG_DIFFERENT_FIT_FIXTURE to the unchanged private FORM Sep 18 FIT.');
  $sep19=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));
  $sep18=(new FIT_Importer)->parse($other);
  $this->assertFalse(is_wp_error($sep19));$this->assertFalse(is_wp_error($sep18));
  $a=$sep19['workout'];$b=$sep18['workout'];
  $this->assertGreaterThan(2,abs(strtotime($a['workout_start'])-strtotime($b['workout_start'])));
  $sameFingerprint=abs(strtotime($a['workout_start'])-strtotime($b['workout_start']))<=2
   && abs((float)$a['total_distance_m']-(float)$b['total_distance_m'])<=0.5
   && abs((int)$a['elapsed_time_ms']-(int)$b['elapsed_time_ms'])<=2000;
  $this->assertFalse($sameFingerprint,'A clearly different real workout must not satisfy the automatic attachment fingerprint.');
 }
 public function test_probable_uncertain_match_is_not_silently_attached(){
  global $wpdb;
  $fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));
  $this->assertFalse(is_wp_error($fit));
  $existing=$fit['workout'];
  $uncertain=$fit['workout'];

  // Preserve the strong identifying characteristics but move elapsed time
  // outside the frozen high-confidence auto-attach tolerance. This is an
  // intentionally ambiguous candidate, not permission to create a new
  // probable-match threshold.
  $uncertain['elapsed_time_ms']=(int)$existing['elapsed_time_ms']+5000;

  $wpdb=new class($existing){
   public $prefix='wp_';private $existing;
   public function __construct($existing){$this->existing=$existing;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_row($prepared){
    $a=$prepared['args'];
    $auto=((int)$a[0]===42)
      && abs(strtotime($this->existing['workout_start'])-strtotime($a[1]))<=2
      && abs((float)$this->existing['total_distance_m']-(float)$a[2])<=0.5
      && abs((int)$this->existing['elapsed_time_ms']-(int)$a[3])<=2000;
    return $auto?(object)array('id'=>9003,'user_id'=>42):null;
   }
   public function get_results($prepared){return array();}
  };

  $m=new ReflectionMethod(Importer::class,'find_match');$m->setAccessible(true);
  $match=$m->invoke(null,42,$uncertain);
  $this->assertNull($match,'An uncertain candidate outside high-confidence tolerances must never be silently attached.');

  // The confirmation/review path is deliberately not asserted here because
  // probable-match tolerances are not yet frozen. This regression protects
  // the safety boundary until that workflow is specified and implemented.
 }
 public function test_probable_match_classifier_requires_review(){
  global $wpdb;$fit=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));$this->assertFalse(is_wp_error($fit));$w=$fit['workout'];
  $candidate=(object)array('id'=>9010,'user_id'=>42,'workout_start'=>$w['workout_start'],'total_distance_m'=>$w['total_distance_m'],'elapsed_time_ms'=>$w['elapsed_time_ms'],'pool_length_m'=>$w['pool_length_m'],'pool_length_unit'=>$w['pool_length_unit']);
  $wpdb=new class($candidate){public $prefix='wp_';private $c;public function __construct($c){$this->c=$c;}public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}public function get_row($p){return null;}public function get_results($p){return array($this->c);}};
  $w['elapsed_time_ms']=(int)$w['elapsed_time_ms']+5000;
  $m=Importer::classify_match(42,$w);$this->assertSame('probable',$m['status']);$this->assertSame(9010,(int)$m['workout']->id);
 }
 public function test_fit_crc_rejects_corruption(){
  $src=$this->fixture('form-2026-09-19.fit');$bin=file_get_contents($src);$this->assertNotFalse($bin);
  $hs=ord($bin[0]);$size=unpack('V',substr($bin,4,4))[1];$at=$hs+min(20,max(1,$size-1));$bin[$at]=chr(ord($bin[$at])^0x01);
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-fit-');file_put_contents($tmp,$bin);try{$r=(new FIT_Importer)->parse($tmp);$this->assertTrue(is_wp_error($r));$this->assertSame('swimlog_fit_crc',$r->get_error_code());}finally{@unlink($tmp);}
 }

 public function test_disagreement_is_checked_before_source_preservation(){
  $source=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $match=strpos($source,'$match_info=self::classify_match');
  $disagreement=strpos($source,"\$match_info['status']==='disagreement'");
  $preserve=strpos($source,'$upload=self::preserve_source');
  $this->assertNotFalse($match);$this->assertNotFalse($disagreement);$this->assertNotFalse($preserve);
  $this->assertLessThan($preserve,$disagreement,'A disagreement must stop the import before a permanent source copy is created.');
 }
 public function test_new_sources_use_dedicated_protected_storage(){
  $source=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString("'swim-log-evaluation/private'",$source);
  $this->assertStringContainsString("Require all denied",$source);
  $this->assertStringContainsString("Deny from all",$source);
  $this->assertStringContainsString("web.config",$source);
  $this->assertStringContainsString("index.php",$source);
 }
 public function test_untracked_source_cleanup_is_present_for_database_failures(){
  $source=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString("cleanup_unowned_source",$source);
  $this->assertStringContainsString('if(!$failure_saved)self::cleanup_unowned_source',$source);
  $this->assertStringContainsString('if(!$ok){self::cleanup_unowned_source',$source);
 }

 public function test_fit_device_metadata_is_not_generic_placeholder(){
  $r=(new FIT_Importer)->parse($this->fixture('form-2026-09-19.fit'));$this->assertFalse(is_wp_error($r));
  $manufacturer=$r['workout']['device_manufacturer'];$this->assertNotSame('FIT',$manufacturer);
  $this->assertTrue($manufacturer===null||is_string($manufacturer));
 }
}
