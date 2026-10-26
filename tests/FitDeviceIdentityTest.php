<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\FIT_Importer;

final class FitDeviceIdentityTest extends TestCase {
 private function identify($devices){
  $m=new ReflectionMethod(FIT_Importer::class,'device_info');$m->setAccessible(true);
  return $m->invoke(new FIT_Importer(),$devices);
 }
 public function test_garmin_identity_is_independent_of_workout_type(){
  $this->assertSame(array('Garmin','965'),$this->identify(array(array(0=>0,2=>1,4=>965))));
 }
 public function test_form_identity_is_preserved_from_same_device_record(){
  $this->assertSame(array('Development','FORM Swim Goggles'),$this->identify(array(array(0=>0,2=>255,27=>'FORM Swim Goggles'))));
 }
 public function test_development_manufacturer_is_not_assumed_to_be_apple(){
  $this->assertSame(array('Development','1234'),$this->identify(array(array(0=>0,2=>255,4=>1234))));
 }
 public function test_apple_watch_requires_apple_watch_model_evidence(){
  $this->assertSame(array('Apple','Apple Watch Ultra 2'),$this->identify(array(array(0=>0,2=>255,27=>'Apple Watch Ultra 2'))));
 }
 public function test_apple_watch_descriptor_can_identify_development_manufacturer(){
  $this->assertSame(array('Apple','Watch3,2'),$this->identify(array(array(0=>0,2=>255,19=>"Christopher's Apple Watch (4.3.1)",27=>'Watch3,2'))));
 }
 public function test_recording_device_fields_are_not_mixed_with_accessory(){
  $devices=array(
   array(0=>0,2=>1,27=>'Forerunner 965'),
   array(0=>1,2=>15,27=>'HRM-Pro Plus')
  );
  $this->assertSame(array('Garmin','Forerunner 965'),$this->identify($devices));
 }
}
