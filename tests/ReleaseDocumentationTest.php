<?php
use PHPUnit\Framework\TestCase;

final class ReleaseDocumentationTest extends TestCase {
 public function test_release_docs_publish_version_07_consistently(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  $this->assertStringContainsString('v0.8 development status',$readme);$this->assertStringContainsString('current public stable release remains v0.7',$readme);$this->assertStringContainsString('Stable tag: 0.7',$readme);
  $this->assertStringContainsString('= 0.7 =',$readme);
  $this->assertStringNotContainsString('0.7 - In development',$readme);
  $this->assertStringNotContainsString('v0.7 development',$readme);
  $this->assertStringNotContainsString('v0.7 Block Editor support — in development',$github);
  $this->assertStringContainsString("Version: 0.7",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '0.7'",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_DB_VERSION', '0.9'",$plugin);
  $this->assertStringContainsString('## Current release: v0.7',$github);
 }
 public function test_v07_docs_cover_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Latest Workouts','Personal Bests','Upcoming Events','Workout','Training Log','Progress')as$block){$this->assertStringContainsString($block,$readme);$this->assertStringContainsString($block,$github);}
  foreach(array('[swimlog_latest]','[swimlog_bests]','[swimlog_upcoming_events]','[swimlog_workout]','[swimlog_log]','[swimlog_progress]')as$shortcode){$this->assertStringContainsString($shortcode,$readme);$this->assertStringContainsString($shortcode,$github);}
  foreach(array('Create Post/Edit Post','workout numbers','responsive','Wide and Full','REST API permission')as$feature){$this->assertStringContainsString($feature,$readme);}
  $this->assertStringContainsString('dynamic/server-rendered',$readme);
  $this->assertStringContainsString('no logged-in-viewer fallback',$readme);
  $this->assertStringContainsString('Open-Water Routes',$github);
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
 public function test_v08_development_docs_cover_manual_workout_contract_without_advancing_release_metadata(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');$plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');$github=file_get_contents(dirname(__DIR__).'/README.md');$admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  foreach(array('v0.8 development — complete, not yet released','first-class manual pool-workout','coarse workouts','measured repetition times','intervals/send-offs','transactional','REST API remains read-only')as$feature)$this->assertStringContainsString($feature,$github);
  foreach(array('Measured reps require one actual swim time per repetition','not split into personal-best times','Untimed sets still count toward training volume','does not create open-water GPS routes')as$help)$this->assertStringContainsString($help,$admin);
  $this->assertStringContainsString('Stable tag: 0.7',$readme);$this->assertStringContainsString('Version: 0.7',$plugin);$this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '0.7'",$plugin);
 }
}
