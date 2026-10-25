<?php
use PHPUnit\Framework\TestCase;
final class ProgressBlockTest extends TestCase {
 public function test_progress_block_is_native_dynamic_block(){
  $m=json_decode(file_get_contents(dirname(__DIR__).'/blocks/progress/block.json'),true);
  $this->assertSame('swimlog/progress',$m['name']);$this->assertSame('swimlog-block-progress',$m['editorScript']);$this->assertFalse($m['supports']['html']);
 }
 public function test_shortcode_and_block_share_progress_renderer(){
  $s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$b=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$j=file_get_contents(dirname(__DIR__).'/blocks/progress/index.js');
  $this->assertStringContainsString('public static function progress($atts){return self::render_progress($atts);}',$s);$this->assertStringContainsString('Shortcodes::render_progress(',$b);$this->assertStringNotContainsString('[swimlog_progress',$j);
 }
 public function test_block_controls_match_progress_contract(){
  $j=file_get_contents(dirname(__DIR__).'/blocks/progress/index.js');
  foreach(array('Configured default','Meters','Yards','Freestyle','Breaststroke','Backstroke','Butterfly','Mixed','5000') as $label)$this->assertStringContainsString($label,$j);
  $this->assertStringContainsString('Select a stroke and distance to preview progress.',$j);
 }
 public function test_php_renderer_remains_authoritative_for_progression_and_chart(){
  $s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$j=file_get_contents(dirname(__DIR__).'/blocks/progress/index.js');
  $this->assertStringContainsString('Records::progression(',$s);$this->assertStringContainsString("wp_enqueue_script('swimlog-progress'",$s);$this->assertStringContainsString('count($points)>1',$s);
  $this->assertStringNotContainsString('Records::progression',$j);$this->assertStringNotContainsString('duration_ms',$j);$this->assertStringNotContainsString('data-swimlog-progress',$j);
 }
 public function test_single_pb_still_renders_without_requiring_chart(){
  $s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString("if(count(\$points)>1)",$s);$this->assertStringContainsString('swimlog-progress-data',$s);
 }
}
