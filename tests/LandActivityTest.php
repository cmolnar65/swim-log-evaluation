<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;
use SwimLogEvaluation\Land_Activity;
use SwimLogEvaluation\Equivalent_Steps;
use SwimLogEvaluation\Tests\Land_Fixture;
require_once dirname(__DIR__).'/includes/class-land-activity.php';
require_once __DIR__.'/land-activity-fixtures.php';
final class LandActivityTest extends TestCase {
    private function parse( ...$args ) {
        $p=Land_Fixture::path(...$args);
        try{return (new FIT_Importer())->parse($p);}finally{unlink($p);}
    }
    public function test_outdoor_run_uses_recorded_moving_time_and_gps_without_swim_fields() {
        $p=$this->parse();$this->assertFalse(is_wp_error($p));$w=$p['workout'];
        $this->assertSame('running',$w['workout_type']);$this->assertSame('outdoor',$w['activity_environment']);
        $this->assertSame(45000,$w['moving_time_ms']);$this->assertNotSame(50000,$w['moving_time_ms']);
        $this->assertSame(60000,$w['elapsed_time_ms']);$this->assertEquals(5000,$w['total_distance_m']);
        $this->assertNull($w['pool_length_m']);$this->assertNull($w['average_cadence']);$this->assertSame(array(),$p['lengths']);
        $this->assertCount(3,$p['trackpoints']);$this->assertEqualsWithDelta(40,$p['trackpoints'][0]['latitude'],0.00001);
        $this->assertNull($p['laps'][0]['stroke']);$this->assertSame(45000,$p['laps'][0]['moving_time_ms']);
        $this->assertNull(Equivalent_Steps::calculate($w));
    }
    public function test_indoor_and_unknown_environments_do_not_depend_on_gps() {
        $p=$this->parse(11,27,false,0,1,4294967295);
        $this->assertSame('walking',$p['workout']['workout_type']);$this->assertSame('indoor',$p['workout']['activity_environment']);
        $this->assertNull($p['workout']['total_distance_m']);$this->assertNull($p['trackpoints'][0]['latitude']);
        $this->assertNull($this->parse(11,0,false)['workout']['activity_environment']);
        $this->assertSame('indoor',$this->parse(1,1,true)['workout']['activity_environment']);
    }
    public function test_cycling_ambiguous_sport_and_multisession_remain_rejected() {
        foreach(array(array(2,7),array(0,0),array(1,18),array(11,17),array(1,2,true,0,2)) as $args)$this->assertTrue(is_wp_error($this->parse(...$args)));
    }
    public function test_land_imports_are_enabled_and_swim_types_are_preserved() {
        $this->assertTrue(Land_Activity::enabled());
        $this->assertSame(array('lap_swimming','open_water_swimming','running','walking'),Land_Activity::garmin_types());
        $this->assertFalse(is_wp_error(Land_Activity::validate($this->parse())));
    }
    public function test_distances_stay_separate_and_missing_is_not_a_zero_reading() {
        $rows=array((object)array('workout_type'=>'pool','total_distance_m'=>1000),(object)array('workout_type'=>'running','total_distance_m'=>5000),(object)array('workout_type'=>'walking','total_distance_m'=>null),(object)array('workout_type'=>'walking','total_distance_m'=>0));
        $totals=Land_Activity::distances($rows);
        $this->assertSame(5000.0,$totals['running']);$this->assertSame(0.0,$totals['walking']);
        $this->assertSame(1,$totals['running_count']);$this->assertSame(2,$totals['walking_count']);
        $this->assertSame(1,$totals['walking_known']);
    }

}
