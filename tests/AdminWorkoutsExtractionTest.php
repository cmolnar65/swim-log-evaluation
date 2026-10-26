<?php
use PHPUnit\Framework\TestCase;
final class AdminWorkoutsExtractionTest extends TestCase {
 public function test_main_admin_preserves_workout_callbacks_and_menu_slugs(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  foreach(array('workouts','upload','handle_workout_actions') as $method){$this->assertStringContainsString('public function '.$method.'()',$admin);$this->assertStringContainsString('Admin_Workouts::'.$method.'();',$admin);}
  $this->assertStringContainsString("'swimlog-workouts', 'workouts'",$admin);
  $this->assertStringContainsString("'swimlog-upload', 'upload'",$admin);
  $this->assertStringContainsString("require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-workouts.php';",$admin);
 }
 public function test_workout_implementation_is_extracted_from_main_admin(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringNotContainsString('Workout::open_water_analytics(',$admin);
  $this->assertStringNotContainsString('Importer::resolve_pending(',$admin);
  $this->assertStringNotContainsString('save_workout_photos(',$admin);
  $this->assertLessThan(50000,strlen($admin));
 }
 public function test_extracted_component_owns_actions_uploads_photos_and_open_water_presentation(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin-workouts.php');
  $this->assertStringContainsString('final class Admin_Workouts',$source);
  $this->assertStringContainsString("current_user_can( 'swimlog_manage_own_workouts' )",$source);
  $this->assertStringContainsString('Importer::rebuild_workout( $detail, $user_id )',$source);
  $this->assertStringContainsString('Importer::resolve_pending(',$source);
  $this->assertStringContainsString('private static function save_workout_photos(',$source);
  $this->assertStringContainsString('Workout::open_water_analytics(',$source);
  $this->assertStringContainsString('private static function open_water_route_svg(',$source);
  $this->assertStringContainsString('private static function open_water_metric_svg(',$source);
 }
}
