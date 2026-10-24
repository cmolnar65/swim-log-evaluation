<?php
use PHPUnit\Framework\TestCase;

final class ReleaseDocumentationTest extends TestCase {
 public function test_development_docs_do_not_prematurely_publish_version_06(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $this->assertStringContainsString('Stable tag: 0.5',$readme);
  $this->assertStringContainsString('= 0.6 - In development =',$readme);
  $this->assertStringContainsString("Version: 0.5",$plugin);
  $this->assertStringContainsString("SWIMLOG_EVALUATION_VERSION', '0.5'",$plugin);
 }
 public function test_v06_changelog_covers_open_water_release_work(){
  $readme=file_get_contents(dirname(__DIR__).'/readme.txt');
  $this->assertStringContainsString('Garmin Personal API import-state presentation',$readme);
  $this->assertStringContainsString('Optimize open-water trackpoint analytics processing.',$readme);
  $this->assertStringContainsString('Expand deterministic open-water FIT regression coverage',$readme);
 }
}
