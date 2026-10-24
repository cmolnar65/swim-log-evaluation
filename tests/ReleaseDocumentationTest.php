<?php
use PHPUnit\Framework\TestCase;

final class ReleaseDocumentationTest extends TestCase {
 public function test_release_docs_publish_version_06_consistently(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $this->assertStringContainsString('Stable tag: 0.6',$readme);
  $this->assertStringContainsString('= 0.6 =',$readme);
  $this->assertStringNotContainsString('0.6 - In development',$readme);
  $this->assertStringContainsString("Version: 0.6",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '0.6'",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '0.8'",$plugin);
 }
 public function test_v06_changelog_covers_open_water_release_work(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  foreach(array(
   'Garmin Personal API import-state presentation',
   'optimized trackpoint processing',
   'deterministic open-water/import regression coverage',
   'transactional workout rebuilds',
   'GPX and TCX open-water import',
   'scheduled Garmin synchronization',
   'first-GPS-fix matching within 500 meters',
   'swimlog/v1 REST API',
   'all registered public shortcodes',
   'Garmin scheduled-sync operational telemetry',
   'persist the authoritative import',
   'analytics metric unit/provenance metadata'
  )as$feature)$this->assertStringContainsString($feature,$readme);
 }
}
