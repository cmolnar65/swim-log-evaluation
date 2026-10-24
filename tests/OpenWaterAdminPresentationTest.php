<?php
use PHPUnit\Framework\TestCase;

final class OpenWaterAdminPresentationTest extends TestCase {
 public function test_open_water_detail_has_structured_analytics_sections(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("esc_html_e('Open Water Summary'",$source);
  $this->assertStringContainsString("esc_html_e('Timing and Recording'",$source);
  $this->assertStringContainsString("esc_html_e('1 km Splits'",$source);
  $this->assertStringContainsString("esc_html_e('GPS Route'",$source);
  $this->assertStringContainsString("esc_html_e('Source FIT Laps / Splits'",$source);
  $this->assertStringContainsString("analytics['gps_point_count']",$source);
  $this->assertStringContainsString('count($laps)',$source);
 }
 public function test_open_water_summary_styles_are_present(){
  $css=file_get_contents(dirname(__DIR__).'/assets/css/admin.css');
  $this->assertStringContainsString('.swimlog-admin .swimlog-ows-summary',$css);
 }
}
