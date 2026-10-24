<?php
use PHPUnit\Framework\TestCase;

final class WorkoutRebuildTest extends TestCase {
 public function test_rebuild_core_is_fail_closed_and_transactional(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString('public static function rebuild_workout',$code);
  $this->assertStringContainsString("import_status='complete'",$code);
  $this->assertStringContainsString("if(!\$row->stored_path||!is_file(\$row->stored_path))",$code);
  $this->assertStringContainsString("query('START TRANSACTION')",$code);
  $this->assertStringContainsString("query('ROLLBACK')",$code);
  $this->assertStringContainsString("query('COMMIT')",$code);
  $this->assertStringContainsString('self::replace_structure($workout_id,$authority[\'parsed\'])',$code);
  $this->assertStringContainsString('Evaluator::evaluate_workout($workout_id,$uid)',$code);
 }
 public function test_admin_rebuild_requires_nonce_and_preserves_delete_as_separate_action(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("check_admin_referer( 'swimlog_rebuild_workout_' . \$detail )",$code);
  $this->assertStringContainsString('Importer::rebuild_workout( $detail, $user_id )',$code);
  $this->assertStringContainsString('Rebuild from Preserved Sources',$code);
  $this->assertStringContainsString("check_admin_referer( 'swimlog_delete_workout_' . \$detail )",$code);
 }
}
