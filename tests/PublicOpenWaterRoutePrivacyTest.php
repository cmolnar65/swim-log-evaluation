<?php
use PHPUnit\Framework\TestCase;

final class PublicOpenWaterRoutePrivacyTest extends TestCase {
 public function test_public_route_requires_separate_explicit_opt_in(){
  $shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString("get_user_meta(\$uid,'swimlog_public_ows_routes',true)==='1'",$shortcodes);
  $this->assertStringContainsString("self::public_routes_allowed(\$uid)",$shortcodes);
  $this->assertStringContainsString("Workout::trackpoints(\$id,\$uid)",$shortcodes);
  $this->assertStringContainsString('Open-water GPS route',$shortcodes);
 }
 public function test_privacy_ui_warns_that_routes_reveal_locations(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin-settings.php');
  $this->assertStringContainsString("update_user_meta( \$uid, 'swimlog_public_ows_routes', \$public_routes )",$admin);
  $this->assertStringContainsString('Public Open-Water Routes',$admin);
  $this->assertStringContainsString('GPS routes can reveal where you swim, including start and finish locations.',$admin);
  $this->assertStringContainsString('This setting is separate from Public Results and must be enabled explicitly.',$admin);
 }
}
