<?php
use PHPUnit\Framework\TestCase;

final class PublicOpenWaterRoutePrivacyTest extends TestCase {
 public function test_public_route_requires_separate_explicit_opt_in(){
  $visibility=file_get_contents(dirname(__DIR__).'/includes/class-workout-visibility.php');
  $this->assertStringContainsString("'swimlog_public_ows_routes'",$visibility);
  $this->assertStringContainsString('if(self::route_allowed($w,$w))',$visibility);
  $this->assertStringContainsString("Database::table('trackpoints')",$visibility);
  $this->assertStringContainsString('const ROUTES_META',$visibility);
 }
 public function test_privacy_ui_warns_that_routes_reveal_locations(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin-settings.php');
  $this->assertStringContainsString("update_user_meta( \$uid, 'swimlog_public_ows_routes', \$public_routes )",$admin);
  $this->assertStringContainsString('Public Swimming Routes',$admin);
  $this->assertStringContainsString('GPS routes can reveal where you swim, including start and finish locations.',$admin);
  $this->assertStringContainsString('This setting is separate from Public Results and must be enabled explicitly.',$admin);
 }
}
