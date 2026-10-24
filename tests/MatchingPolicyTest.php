<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Importer;

final class MatchingPolicyTest extends TestCase {
 public function test_explicit_offset_start_is_normalized_to_wordpress_timezone(){
  $this->assertSame('2026-10-23 12:00:00',Importer::normalize_match_start('2026-10-23T18:00:00Z'));
  $this->assertSame('2026-10-23 12:00:00',Importer::normalize_match_start('2026-10-23T12:00:00-06:00'));
 }

 public function test_naive_local_start_is_preserved_as_local_wall_time(){
  $this->assertSame('2026-10-23 12:00:00',Importer::normalize_match_start('2026-10-23 12:00:00'));
 }

 public function test_pool_exact_match_is_scoped_to_pool_workouts_and_uses_normalized_start(){
  global $wpdb;
  $wpdb=new class {
   public $prefix='wp_';
   public $prepared;
   public function prepare($sql,...$args){$this->prepared=array('sql'=>$sql,'args'=>$args);return $this->prepared;}
   public function get_row($prepared){$this->prepared=$prepared;return(object)array('id'=>77,'workout_type'=>'pool');}
   public function get_results($prepared){return array();}
  };
  $match=Importer::classify_match(42,array(
   'workout_type'=>'pool',
   'workout_start'=>'2026-10-23T18:00:00Z',
   'total_distance_m'=>1500.0,
   'elapsed_time_ms'=>1800000,
  ));
  $this->assertSame('exact',$match['status']);
  $this->assertSame(77,(int)$match['workout']->id);
  $this->assertStringContainsString("workout_type='pool'",$wpdb->prepared['sql']);
  $this->assertSame('2026-10-23 12:00:00',$wpdb->prepared['args'][1]);
 }

 public function test_multiple_exact_open_water_candidates_require_review(){
  global $wpdb;
  $candidates=array(
   (object)array('id'=>101,'workout_type'=>'open_water','elapsed_time_ms'=>3600000,'moving_time_ms'=>3500000),
   (object)array('id'=>102,'workout_type'=>'open_water','elapsed_time_ms'=>3605000,'moving_time_ms'=>3505000),
  );
  $wpdb=new class($candidates) {
   public $prefix='wp_';
   private $candidates;
   public function __construct($candidates){$this->candidates=$candidates;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_results($prepared){return $this->candidates;}
   public function get_row($prepared){return null;}
  };
  $match=Importer::classify_match(42,array(
   'workout_type'=>'open_water',
   'workout_start'=>'2026-10-23 08:00:00',
   'total_distance_m'=>3000.0,
   'elapsed_time_ms'=>3600000,
   'moving_time_ms'=>3500000,
  ));
  $this->assertSame('probable',$match['status']);
  $this->assertSame(101,(int)$match['workout']->id);
 }

 public function test_single_exact_open_water_candidate_can_auto_attach(){
  global $wpdb;
  $candidate=(object)array('id'=>103,'workout_type'=>'open_water','elapsed_time_ms'=>3600000,'moving_time_ms'=>3500000);
  $wpdb=new class($candidate) {
   public $prefix='wp_';
   private $candidate;
   public function __construct($candidate){$this->candidate=$candidate;}
   public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
   public function get_results($prepared){return array($this->candidate);}
   public function get_row($prepared){return null;}
  };
  $match=Importer::classify_match(42,array(
   'workout_type'=>'open_water',
   'workout_start'=>'2026-10-23 08:00:00',
   'total_distance_m'=>3000.0,
   'elapsed_time_ms'=>3600000,
   'moving_time_ms'=>3500000,
  ));
  $this->assertSame('exact',$match['status']);
  $this->assertSame(103,(int)$match['workout']->id);
 }
}
