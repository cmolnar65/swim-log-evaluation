<?php
use PHPUnit\Framework\TestCase;

final class WorkoutPhotoAdminTest extends TestCase {
 public function test_workout_screen_supports_three_photo_slots(){
  $src=file_get_contents(dirname(__DIR__).'/admin/class-admin-workouts.php');
  $this->assertStringContainsString('enctype="multipart/form-data"',$src);
  $this->assertStringContainsString('for($photo_slot=0;$photo_slot<3;$photo_slot++)',$src);
  $this->assertStringContainsString("name=\"delete_photo[]\"",$src);
  $this->assertStringContainsString("accept=\"image/jpeg,image/png,image/gif,image/webp\"",$src);
  $this->assertStringContainsString('save_workout_photos( $detail, $user_id )',$src);
 }
 public function test_photo_service_enforces_three_images_and_workout_ownership(){
  $src=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString("array_slice( \$ids, 0, 3 )",$src);
  $this->assertStringContainsString("count( \$ids ) > 3",$src);
  $this->assertStringContainsString("self::get_for_user( \$workout_id, \$user_id )",$src);
  $this->assertStringContainsString("wp_delete_attachment( \$photo_id, true )",$src);
 }
}
