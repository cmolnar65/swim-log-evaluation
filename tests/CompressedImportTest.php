<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Importer;

final class CompressedImportTest extends TestCase {
 private function sourceType($name,$path){
  $m=new ReflectionMethod(Importer::class,'uploaded_source_type');$m->setAccessible(true);
  return $m->invoke(null,$name,$path);
 }

 private function prepare($source,$path){
  if(!defined('FS_CHMOD_FILE'))define('FS_CHMOD_FILE',0644);
  if(!function_exists('wp_tempnam')){function wp_tempnam($name=''){return tempnam(sys_get_temp_dir(),'swimlog-prep-');}}
  if(!function_exists('wp_delete_file')){function wp_delete_file($path){return @unlink($path);}}
  if(!function_exists('WP_Filesystem')){function WP_Filesystem(){global $wp_filesystem;if(!$wp_filesystem)$wp_filesystem=new class{
   public function get_contents($path){return file_get_contents($path);}
   public function put_contents($path,$data,$mode=false){return false!==file_put_contents($path,$data);}
  };return true;}}
  $m=new ReflectionMethod(Importer::class,'prepare_source_file');$m->setAccessible(true);
  return $m->invoke(null,$source,$path);
 }
 public function test_fit_gzip_is_actually_expanded_for_parser(){
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-gz-');$payload="FIT parser payload";file_put_contents($tmp,gzencode($payload));
  $prepared=null;
  try{$prepared=$this->prepare('fit',$tmp);$this->assertFalse(is_wp_error($prepared));$this->assertTrue($prepared['temporary']);$this->assertSame($payload,file_get_contents($prepared['path']));}
  finally{if($prepared&&!is_wp_error($prepared)&&!empty($prepared['path']))@unlink($prepared['path']);@unlink($tmp);}
 }
 public function test_form_zip_is_actually_expanded_for_parser(){
  if(!class_exists('ZipArchive'))$this->markTestSkipped('ZipArchive unavailable.');
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-zip-');$payload="Date,Distance\n2026-01-01,100\n";$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);$zip->addFromString('FORM-workout.csv',$payload);$zip->close();$prepared=null;
  try{$prepared=$this->prepare('csv',$tmp);$this->assertFalse(is_wp_error($prepared));$this->assertTrue($prepared['temporary']);$this->assertSame($payload,file_get_contents($prepared['path']));}
  finally{if($prepared&&!is_wp_error($prepared)&&!empty($prepared['path']))@unlink($prepared['path']);@unlink($tmp);}
 }
 public function test_trainingpeaks_fit_gzip_is_typed_as_fit(){
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-gz-');file_put_contents($tmp,gzencode('FIT test payload'));
  try{$this->assertSame('fit',$this->sourceType('workout.fit.gz',$tmp));}finally{@unlink($tmp);}
 }
 public function test_gpx_and_tcx_gzip_keep_underlying_type(){
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-gz-');file_put_contents($tmp,gzencode('<xml/>'));
  try{$this->assertSame('gpx',$this->sourceType('route.gpx.gz',$tmp));$this->assertSame('tcx',$this->sourceType('route.tcx.gz',$tmp));}finally{@unlink($tmp);}
 }
 public function test_zip_with_one_supported_form_csv_is_typed_as_csv(){
  if(!class_exists('ZipArchive'))$this->markTestSkipped('ZipArchive unavailable.');
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-zip-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);$zip->addFromString('FORM-workout.csv',"Date,Distance\n2026-01-01,100\n");$zip->addFromString('README.txt','metadata');$zip->close();
  try{$this->assertSame('csv',$this->sourceType('FORM-workout.zip',$tmp));}finally{@unlink($tmp);}
 }
 public function test_zip_rejects_multiple_supported_workout_files(){
  if(!class_exists('ZipArchive'))$this->markTestSkipped('ZipArchive unavailable.');
  $tmp=tempnam(sys_get_temp_dir(),'swimlog-zip-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);$zip->addFromString('one.csv','x');$zip->addFromString('two.fit','x');$zip->close();
  try{$r=$this->sourceType('bundle.zip',$tmp);$this->assertTrue(is_wp_error($r));$this->assertSame('swimlog_zip_contents',$r->get_error_code());}finally{@unlink($tmp);}
 }
}
