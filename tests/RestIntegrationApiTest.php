<?php
use PHPUnit\Framework\TestCase;
final class RestIntegrationApiTest extends TestCase {
 public function test_plugin_registers_rest_api(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("add_action( 'rest_api_init'",$code);
  $this->assertStringContainsString("REST_API::register();",$code);
 }
 public function test_api_is_read_only_authenticated_and_owner_scoped(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("const NS='swimlog/v1'",$code);
  $this->assertStringContainsString("'methods'=>\\WP_REST_Server::READABLE",$code);
  $this->assertStringContainsString("is_user_logged_in()&&current_user_can('swimlog_view_own_results')",$code);
  $this->assertStringContainsString('get_current_user_id()',$code);
  $this->assertStringContainsString('Workout::get_for_user($id,$uid)',$code);
  $this->assertStringNotContainsString('CREATABLE',$code);
  $this->assertStringNotContainsString('EDITABLE',$code);
  $this->assertStringNotContainsString('DELETABLE',$code);
 }
 public function test_api_does_not_expose_private_ows_or_source_fields(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringNotContainsString('Workout::trackpoints',$code);
  $this->assertStringNotContainsString('Workout::imports',$code);
  $this->assertStringNotContainsString("'latitude'",$code);
  $this->assertStringNotContainsString("'longitude'",$code);
  $this->assertStringNotContainsString("'stored_path'",$code);
  $this->assertStringNotContainsString("'original_filename'",$code);
  $this->assertStringNotContainsString("'notes'",$code);
  $this->assertStringContainsString("'splits'=>\$a['splits']??array()",$code);
 }
 public function test_api_caps_pagination(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString('max(1,min(100',$code);
 }
}
