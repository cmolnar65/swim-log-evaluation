<?php
use PHPUnit\Framework\TestCase;

final class ReleaseDocumentationTest extends TestCase {
 public function test_release_docs_identify_version_11_release_consistently(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  $this->assertStringContainsString('Stable tag: 1.1',$readme);
  $this->assertStringContainsString('= 1.1 =',$readme);
  $this->assertStringContainsString("Version: 1.1",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '1.1'",$plugin);
  $this->assertStringContainsString('## v1.1 release candidate status',$github);
  $release_workflow=file_get_contents(dirname(__DIR__).'/.github/workflows/release-package.yml');
  $plugin_check_workflow=file_get_contents(dirname(__DIR__).'/.github/workflows/plugin-check.yml');
  $this->assertStringContainsString('chriss-swim-training-progress-evaluation-1.1.zip',$release_workflow);
  $this->assertStringNotContainsString('chriss-swim-training-progress-evaluation-1.0.zip',$release_workflow);
  $this->assertStringContainsString('chriss-swim-training-progress-evaluation-1.1.zip',$plugin_check_workflow);
  foreach(array('## Release candidate: v1.0','The `develop` branch is the v1.0 release candidate.','Stable tag: 0.7','current public stable release remains v0.7','v0.8 development status','v0.8 development — complete, not yet released')as$stale)$this->assertStringNotContainsString($stale,$readme.$github);
 }
 public function test_v11_docs_cover_planned_workout_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Planned Workouts','Duplicate Week','planned-versus-actual','wp-admin Calendar','read-only','database schema to 1.2')as$feature)$this->assertStringContainsString($feature,$readme);
  foreach(array('Planned Workouts','duplicate a week','Matching is never automatic','administration **Calendar**','REST remains read-only','database schema to **1.2**')as$feature)$this->assertStringContainsString($feature,$github);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '1.1'",file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php'));
 }
 public function test_v10_docs_cover_calendar_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Swim Training Calendar','Month and Week','Saturday','Sunday','Monday','weekly','mobile','GPS','database schema remains 0.9')as$feature)$this->assertStringContainsString($feature,$readme);
  foreach(array('Swim Training Calendar','Month and Week','Saturday','Sunday','Monday','weekly summaries','date-grouped agenda','Escape-to-close','database schema remains version 0.9')as$feature)$this->assertStringContainsString($feature,$github);
  $this->assertStringContainsString('## v1.0',$github);
  $this->assertStringContainsString('= v1.0 =',$readme);
 }
 public function test_v09_docs_cover_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Pool Analytics','Training Trends','Race Readiness 2.0','REST-API.md','per-length stroke counts','admin','PHP 7.4')as$feature)$this->assertStringContainsString($feature,$readme);
  foreach(array('Pool Analytics','Training Trends','Race Readiness 2.0','REST API and data-portability surface','REST-API.md','administration architecture','no new database schema migration')as$feature)$this->assertStringContainsString($feature,$github);
 }
 public function test_v08_docs_cover_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('manual pool-workout','measured repetition times','intervals/send-offs','transactional','per-length FIT stroke counts','Total Strokes','heart-rate and cadence','Apple Watch','FIT Activity local timestamps','gzip','ZIP','three workout photos','database schema to 0.9')as$feature)$this->assertStringContainsString($feature,$readme);
  foreach(array('manual pool-workout','per-length stroke counts','total-strokes','heart-rate/cadence','Apple Watch','FIT Activity local timestamps','FIT gzip','FORM ZIP','three photos','schema is version 0.9')as$feature)$this->assertStringContainsString($feature,$github);
 }
 public function test_v07_docs_cover_block_release_features(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $github=file_get_contents(dirname(__DIR__).'/README.md');
  foreach(array('Latest Workouts','Personal Bests','Upcoming Events','Workout','Training Log','Progress')as$block){$this->assertStringContainsString($block,$readme);$this->assertStringContainsString($block,$github);}
  foreach(array('[swimlog_latest]','[swimlog_bests]','[swimlog_upcoming_events]','[swimlog_workout]','[swimlog_log]','[swimlog_progress]')as$shortcode){$this->assertStringContainsString($shortcode,$readme);$this->assertStringContainsString($shortcode,$github);}
  foreach(array('Create Post/Edit Post','workout numbers','responsive','Wide and Full','REST API permission')as$feature)$this->assertStringContainsString($feature,$readme);
  $this->assertStringContainsString('dynamic/server-rendered',$readme);
  $this->assertStringContainsString('no logged-in-viewer fallback',$readme);
  $this->assertStringContainsString('Open-Water Routes',$github);
 }
 public function test_v06_changelog_covers_open_water_release_work(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  foreach(array('Garmin Personal API import-state presentation','optimized trackpoint processing','deterministic open-water/import regression coverage','transactional workout rebuilds','GPX and TCX open-water import','scheduled Garmin synchronization','first-GPS-fix matching within 500 meters','swimlog/v1 REST API','all registered public shortcodes','Garmin scheduled-sync operational telemetry','persist the authoritative import','analytics metric unit/provenance metadata')as$feature)$this->assertStringContainsString($feature,$readme);
 }
 public function test_manual_workout_help_preserves_v08_timing_contract(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin-manual-workout.php');
  foreach(array('Measured reps require one actual swim time per repetition','not split into personal-best times','Untimed sets still count toward training volume','does not create open-water GPS routes')as$help)$this->assertStringContainsString($help,$admin);
 }
}
