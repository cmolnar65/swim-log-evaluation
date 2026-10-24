<?php
use PHPUnit\Framework\TestCase;
final class ShortcodeAdminDocumentationTest extends TestCase {
 public function test_admin_index_matches_registered_public_shortcodes(){
  $shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  preg_match_all("/add_shortcode\('([^']+)'/",$shortcodes,$m);$registered=$m[1];sort($registered);
  preg_match_all("/array\('(\[swimlog_[^']+\])'/",$admin,$i);$documented=array_map(function($v){return preg_replace('/[\s\]].*$/',']',$v);},$i[1]);
  $documented=array_values(array_unique(array_map(function($v){return trim($v,'[]');},$documented)));sort($documented);
  $this->assertSame($registered,$documented);
 }
 public function test_every_shortcode_has_a_drill_down_page(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  foreach(array('latest','upcoming_events','workout','log','progress')as$name)$this->assertStringContainsString('function shortcode_'.$name.'()',$admin);
  $this->assertStringContainsString('function shortcode_bests()',$admin);
  foreach(array('latest','upcoming-events','workout','log','progress')as$slug)$this->assertStringContainsString("'swimlog-shortcode-'.\$slug",$admin);
  $this->assertStringContainsString("'swimlog-shortcode-bests'",$admin);
 }
 public function test_documented_attributes_follow_handlers(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("array('count'", $admin);
  $this->assertStringContainsString("array('month'", $admin);
  $this->assertStringContainsString("array('year'", $admin);
  $this->assertStringContainsString("array('id'", $admin);
  $this->assertStringContainsString("array('course'", $admin);
  $this->assertStringContainsString("array('stroke'", $admin);
  $this->assertStringContainsString("array('distance'", $admin);
 }
}
