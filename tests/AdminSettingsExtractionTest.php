<?php
use PHPUnit\Framework\TestCase;
final class AdminSettingsExtractionTest extends TestCase {
 public function test_main_admin_preserves_settings_callbacks_and_menu_slugs(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  foreach(array('privacy','api_help','settings') as $method){$this->assertStringContainsString('public function '.$method.'()',$admin);$this->assertStringContainsString('Admin_Settings::'.$method.'();',$admin);}
  $this->assertStringContainsString("'swimlog-privacy', 'privacy'",$admin);
  $this->assertStringContainsString("'swimlog-api-help', array( \$this, 'api_help' )",$admin);
  $this->assertStringContainsString("'swimlog-settings', 'settings'",$admin);
 }
 public function test_settings_implementation_is_extracted(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin-settings.php');
  $this->assertStringNotContainsString('swimlog_save_privacy',$admin);
  $this->assertStringNotContainsString('swimlog_save_settings',$admin);
  $this->assertStringNotContainsString('swimlog_rebuild_performances',$admin);
  $this->assertStringContainsString('final class Admin_Settings',$source);
  $this->assertStringContainsString("check_admin_referer( 'swimlog_save_privacy' )",$source);
  $this->assertStringContainsString("check_admin_referer('swimlog_save_settings')",$source);
  $this->assertStringContainsString("check_admin_referer('swimlog_rebuild_performances')",$source);
  $this->assertStringContainsString("rest_url( 'swimlog/v1' )",$source);
  $this->assertStringContainsString("update_user_meta( \$uid, 'swimlog_calendar_first_day', \$calendar_first_day );",$source);
  $this->assertStringContainsString('name="calendar_first_day"',$source);
  foreach(array('saturday','sunday','monday') as $day){$this->assertStringContainsString('value="'.$day.'"',$source);}
  $this->assertStringContainsString('Importer::validate_storage_directory($storage_dir,true)',$source);
  $this->assertStringContainsString('Evaluator::rebuild_all_users()',$source);
  $this->assertLessThan(25000,strlen($admin));
 }
}
