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
 public function test_v07_docs_cover_blocks_without_marking_v07_stable(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Latest Workouts','Personal Bests','Upcoming Events','Workout','Training Log','Progress')as$block){$this->assertStringContainsString($block,$readme);$this->assertStringContainsString($block,$github);}
  foreach(array('[swimlog_latest]','[swimlog_bests]','[swimlog_upcoming_events]','[swimlog_workout]','[swimlog_log]','[swimlog_progress]')as$shortcode){$this->assertStringContainsString($shortcode,$readme);$this->assertStringContainsString($shortcode,$github);}
  $this->assertStringContainsString('dynamic/server-rendered',$readme);
  $this->assertStringContainsString('no logged-in-viewer fallback',$readme);
  $this->assertStringContainsString('Open-Water Routes',$github);
  $this->assertStringContainsString('Stable tag: 0.6',$readme);
  $this->assertStringNotContainsString('Stable tag: 0.7',$readme);
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
