<?php
use PHPUnit\Framework\TestCase;

final class DevicePresentationTest extends TestCase {
 public function test_device_label_normalizes_form_without_rewriting_fit_identity(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('private function recording_device_label( $workout )',$source);
  $this->assertStringContainsString("stripos( " . '$combined' . ", 'FORM' )",$source);
  $this->assertStringContainsString("'FORM Swim Goggles'",$source);
  $this->assertStringContainsString('$this->recording_device_label($w)',$source);
 }
 public function test_device_label_preserves_general_manufacturer_and_model_presentation(){
  $source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("stripos( " . '$model, $manufacturer' . " )",$source);
  $this->assertStringContainsString("return " . '$manufacturer' . " . ' ' . " . '$model' . ";",$source);
 }
}
