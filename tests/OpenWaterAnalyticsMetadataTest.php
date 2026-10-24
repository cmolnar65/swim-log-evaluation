<?php
use PHPUnit\Framework\TestCase;

final class OpenWaterAnalyticsMetadataTest extends TestCase {
 public function test_ows_analytics_describe_units_and_provenance(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString("'metric_metadata' => array(",$code);
  $this->assertStringContainsString("'unit' => 'ms/100m', 'provenance' => 'derived'",$code);
  $this->assertStringContainsString("'provenance' => 'measured', 'source' => 'imported FIT cadence samples'",$code);
  $this->assertStringContainsString("'provenance' => 'interpolated'",$code);
  $this->assertStringContainsString('moving split values are estimated when marked',$code);
 }
}
