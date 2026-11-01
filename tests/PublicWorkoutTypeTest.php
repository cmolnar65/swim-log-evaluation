<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Shortcodes;
require_once __DIR__.'/public-visibility-fixture.php';
final class PublicWorkoutTypeTest extends TestCase {
 private $meta;
 protected function setUp():void{global $swimlog_test_user_meta;$this->meta=$swimlog_test_user_meta;$swimlog_test_user_meta[42]['swimlog_public_results']='1';}
 protected function tearDown():void{global $swimlog_test_user_meta;$swimlog_test_user_meta=$this->meta;}
 public function test_open_water_workout_uses_open_water_fields_not_pool_placeholder(){
  global $wpdb;$wpdb=new PublicVisibilityFixture(array(PublicVisibilityFixture::row('open_water')));
  $html=Shortcodes::workout(array('id'=>501,'title'=>'no'));
  foreach(array('Open Water','Moving time','Average pace / 100 m')as$text)$this->assertStringContainsString($text,$html);
  $this->assertStringNotContainsString('25 m / Freestyle',$html);
 }
 public function test_public_open_water_workout_exposes_whitelist_without_private_sensors_or_route(){
  global $wpdb;$row=PublicVisibilityFixture::row('open_water');$row->average_heart_rate=132;$row->average_cadence=31.5;$row->notes='PRIVATE_NOTE';$wpdb=new PublicVisibilityFixture(array($row));
  $dto=SwimLogEvaluation\Workout_Visibility::get_public(501);foreach(array('notes','average_heart_rate','average_cadence','workout_start')as$key)$this->assertArrayNotHasKey($key,$dto);
  $this->assertNull($dto['route']);foreach($wpdb->queries as$query)$this->assertStringNotContainsString('swimlog_trackpoints',$query);
 }
 public function test_pool_workout_keeps_pool_field(){
  global $wpdb;$wpdb=new PublicVisibilityFixture(array(PublicVisibilityFixture::row('pool')));$html=Shortcodes::workout(array('id'=>501,'title'=>'no'));
  $this->assertStringContainsString('Pool',$html);$this->assertStringContainsString('25 m / Freestyle',$html);$this->assertStringNotContainsString('Moving time',$html);
 }
 public function test_public_land_distance_uses_owner_presentation_preferences(){
  global $wpdb,$swimlog_test_user_meta;$swimlog_test_user_meta[42]['swimlog_unit_preferences']=array('swim_course'=>'m','running'=>'mi','walking'=>'mi','cycling'=>'mi','weight'=>'lb','show_secondary'=>true);
  $wpdb=new PublicVisibilityFixture(array(PublicVisibilityFixture::row('running',5000,'m')));$html=Shortcodes::latest(array('user'=>42,'title'=>'no'));
  $this->assertStringContainsString('3.11 mi (5.00 km)',$html);
  $this->assertStringNotContainsString('5000 m',$html);
 }
}
