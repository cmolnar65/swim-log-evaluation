<?php
use PHPUnit\Framework\TestCase;

final class StrokeTrainingVolumeTest extends TestCase {
 public function test_query_uses_active_pool_lengths_and_keeps_courses_separate(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString('public static function stroke_volume_for_user',$code);
  $this->assertStringContainsString("w.workout_type='pool'",$code);
  $this->assertStringContainsString("n.length_type='active'",$code);
  $this->assertStringContainsString("w.pool_length_unit course_unit",$code);
  $this->assertStringContainsString("GROUP BY w.pool_length_unit,COALESCE(NULLIF(n.stroke,''),'UNKNOWN')",$code);
  $this->assertStringContainsString("n.distance_m/0.9144",$code);
 }
 public function test_admin_history_shows_training_volume_summary(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Pool Training Volume by Stroke',$code);
  $this->assertStringContainsString('Training volume is calculated from normalized active pool lengths, not personal-best records. Meter and yard courses are kept separate.',$code);
  $this->assertStringContainsString('Workout::stroke_volume_for_user($user_id,$filters)',$code);
  $this->assertStringContainsString('Active swim time',$code);
 }
}
