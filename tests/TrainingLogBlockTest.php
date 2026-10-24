<?php
use PHPUnit\Framework\TestCase;
final class TrainingLogBlockTest extends TestCase {
 public function test_training_log_block_is_native_dynamic_block(){
  $m=json_decode(file_get_contents(dirname(__DIR__).'/blocks/training-log/block.json'),true);
  $this->assertSame('swimlog/training-log',$m['name']);$this->assertSame('swimlog-block-training-log',$m['editorScript']);$this->assertFalse($m['supports']['html']);
  $this->assertSame('monthly',$m['attributes']['mode']['default']);$this->assertSame(0,$m['attributes']['month']['default']);$this->assertSame(0,$m['attributes']['year']['default']);
 }
 public function test_shortcode_and_block_share_renderer(){
  $s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$b=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$j=file_get_contents(dirname(__DIR__).'/blocks/training-log/index.js');
  $this->assertStringContainsString('public static function log($atts){return self::render_log($atts);}',$s);$this->assertStringContainsString('Shortcodes::render_log( $atts )',$b);
  $this->assertStringNotContainsString('[swimlog_log',$j);$this->assertStringContainsString("block: 'swimlog/training-log'",$j);
 }
 public function test_annual_mode_preserves_year_without_month_presence_semantics(){
  $b=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');$s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString("if ( 'annual' === \$mode )",$b);$this->assertStringContainsString("\$atts['year']",$b);
  $this->assertStringContainsString("\$annual=\$has_year&&!\$has_month",$s);
  $annual=substr($b,strpos($b,"if ( 'annual' === \$mode )"),strpos($b,'} else {',strpos($b,"if ( 'annual' === \$mode )"))-strpos($b,"if ( 'annual' === \$mode )"));
  $this->assertStringNotContainsString("\$atts['month']",$annual);
 }
 public function test_editor_exposes_monthly_and_annual_controls(){
  $j=file_get_contents(dirname(__DIR__).'/blocks/training-log/index.js');
  $this->assertStringContainsString('Monthly workout log',$j);$this->assertStringContainsString('Annual monthly totals',$j);$this->assertStringContainsString('Current month',$j);$this->assertStringContainsString('blank = current year',$j);$this->assertStringContainsString('use the page or post author',$j);
 }
}
