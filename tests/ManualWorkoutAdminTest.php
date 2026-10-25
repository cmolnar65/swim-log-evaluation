<?php
use PHPUnit\Framework\TestCase;

final class ManualWorkoutAdminTest extends TestCase {
 public function test_phase3_admin_uses_manual_service_and_canonical_workout_detail(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("'swimlog-manual-workout', 'manual_workout'",$admin);
  $this->assertStringContainsString("check_admin_referer( 'swimlog_create_manual_workout' )",$admin);
  $this->assertStringContainsString('Manual_Workout::save( $user_id, $document )',$admin);
  $this->assertStringContainsString("'segments' => array()",$admin);
  $this->assertStringContainsString("'page' => 'swimlog-workouts', 'workout_id' => (int) \$result['workout_id']",$admin);
  $this->assertStringContainsString("handle_manual_workout_actions",$plugin);
 }
 public function test_phase3_form_collects_coarse_fields_without_performance_precision(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  foreach(array('workout_date','workout_time','location_id','pool_length','course_unit','total_distance','duration_minutes','notes') as $field)$this->assertStringContainsString('name="'.$field.'"',$admin);
  $this->assertStringContainsString("elapsed_time_ms' => null === \$duration_minutes ? null : (int) round( \$duration_minutes * 60000 )",$admin);
  $this->assertStringNotContainsString("timing_type\"",$admin);
 }
}
