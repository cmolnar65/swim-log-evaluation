<?php
use PHPUnit\Framework\TestCase;
final class AdminPersonalBestsExtractionTest extends TestCase {
 public function test_main_admin_preserves_personal_bests_callback_and_menu(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("'swimlog-personal-bests', 'personal_bests'",$admin);
  $this->assertStringContainsString('public function personal_bests()',$admin);
  $this->assertStringContainsString("require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-personal-bests.php';",$admin);
  $this->assertStringContainsString('Admin_Personal_Bests::personal_bests();',$admin);
 }
 public function test_personal_bests_implementation_is_extracted(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin-personal-bests.php');
  $this->assertStringNotContainsString('Records::age_group_personal_bests(',$admin);
  $this->assertStringContainsString('final class Admin_Personal_Bests',$source);
  $this->assertStringContainsString('Records::personal_bests( $user_id, $course )',$source);
  $this->assertStringContainsString('Records::age_group_personal_bests( $user_id, $course, $group_label )',$source);
  $this->assertStringContainsString('Records::progression( $user_id, $history_distance, $course, $history_stroke )',$source);
  $this->assertStringContainsString('Age_Group::for_user( $user_id )',$source);
  $this->assertStringContainsString("'swimlog_view_own_results'",$source);
 }
}
