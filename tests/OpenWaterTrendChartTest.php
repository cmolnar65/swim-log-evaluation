<?php
use PHPUnit\Framework\TestCase;

final class OpenWaterTrendChartTest extends TestCase {
 public function test_admin_open_water_trend_charts_use_recorded_trackpoint_fields(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('private function open_water_metric_svg',$code);
  $this->assertStringContainsString("open_water_metric_svg(\$trackpoints,'heart_rate'",$code);
  $this->assertStringContainsString("open_water_metric_svg(\$trackpoints,'cadence'",$code);
  $this->assertStringContainsString("count( \$samples ) < 2",$code);
  $this->assertStringContainsString('Missing samples are not estimated or filled in.',$code);
  $this->assertStringContainsString('Heart rate over elapsed recording time',$code);
  $this->assertStringContainsString('Cadence over elapsed recording time',$code);
 }
 public function test_trend_charts_remain_admin_only(){
  $public=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringNotContainsString('swimlog-ows-metric',$public);
  $this->assertStringNotContainsString('open_water_metric_svg',$public);
 }
}
