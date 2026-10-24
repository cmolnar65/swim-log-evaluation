<?php
use PHPUnit\Framework\TestCase;
final class OpenWaterGeographicMapTest extends TestCase {
 public function test_public_map_has_geographic_context_and_keeps_privacy_gate(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString("self::public_routes_allowed(\$uid)",$code);
  $this->assertStringContainsString('swimlog-ows-map-grid',$code);
  $this->assertStringContainsString("esc_html__('Start'",$code);
  $this->assertStringContainsString("esc_html__('Finish'",$code);
  $this->assertStringContainsString('Open-water GPS route map',$code);
  $this->assertStringNotContainsString('leaflet',$code);
  $this->assertStringNotContainsString('tile.openstreetmap',$code);
 }
 public function test_admin_map_has_start_finish_and_grid(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Open-water GPS route map',$code);
  $this->assertStringContainsString("esc_html__('Start'",$code);
  $this->assertStringContainsString("esc_html__('Finish'",$code);
  $this->assertStringContainsString("'circle'=>array('cx'=>true",$code);
 }
}
