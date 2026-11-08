<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Admin_UI;
require_once dirname(__DIR__).'/admin/class-admin-ui.php';
require_once dirname(__DIR__).'/includes/class-structured-workout.php';
final class AdminPrescriptionPreviewTest extends TestCase {
 public function test_preview_explains_structure_without_mutating_document_or_export(){
  $doc=array('version'=>1,'unit'=>'yd','sections'=>array(array('type'=>'section','label'=>'Main','children'=>array(array('type'=>'repeat','count'=>2,'children'=>array(array('type'=>'step','distance'=>100,'stroke'=>'BR','rest'=>'0','equipment'=>array('Fins','Custom buoy'),'description'=>'Synthetic cue')))) )));
  $before=$doc;$text=Admin_UI::prescription_text('Synthetic',$doc);
  foreach(array('Breaststroke','2 x (','Fins','Custom buoy','Synthetic cue','none (continuous)','Total: 200 yd')as$needle)$this->assertStringContainsString($needle,$text);
  $this->assertSame($before,$doc);
  $this->assertStringContainsString('100 yd BR',SwimLogEvaluation\Structured_Workout_Export::text('Synthetic',$doc));
 }
 public function test_invalid_definition_is_explained_without_json_dump(){
  $text=Admin_UI::prescription_text('Invalid',array());$this->assertNotSame('',$text);$this->assertStringNotContainsString('{',$text);
 }
}
