<?php
use PHPUnit\Framework\TestCase;

final class WorkoutStrokeCountPresentationTest extends TestCase {
 public function test_workout_detail_shows_length_and_total_strokes_when_available(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("esc_html_e('Total strokes'",$admin);
  $this->assertStringContainsString("esc_html_e('Strokes'",$admin);
  $this->assertStringContainsString('$total_strokes=$stroke_count_values?array_sum($stroke_count_values):null',$admin);
  $this->assertStringContainsString("null!==\$l->stroke_count?(int)\$l->stroke_count:'—'",$admin);
 }
 public function test_total_strokes_is_hidden_when_no_length_counts_exist(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("if(null!==\$total_strokes)",$admin);
  $this->assertStringContainsString("return isset(\$length->stroke_count)&&null!==\$length->stroke_count?(int)\$length->stroke_count:null",$admin);
 }
}
