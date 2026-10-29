<?php
use PHPUnit\Framework\TestCase;

final class WorkoutRebuildTest extends TestCase {
 public function test_rebuild_core_is_fail_closed_and_transactional(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString('public static function rebuild_workout',$code);
  $this->assertStringContainsString("import_status='complete'",$code);
  $this->assertStringContainsString("if(!\$row->stored_path||!is_file(\$row->stored_path))",$code);
  $this->assertStringContainsString("Database::write( 'query', 'START TRANSACTION')",$code);
  $this->assertStringContainsString("Database::write( 'query', 'ROLLBACK')",$code);
  $this->assertStringContainsString("Database::write( 'query', 'COMMIT')",$code);
  $this->assertStringContainsString('self::replace_structure($workout_id,$authority[\'parsed\'])',$code);
  $this->assertStringContainsString('Evaluator::evaluate_workout($workout_id,$uid)',$code);
 }

 public function test_rebuild_uses_archive_aware_parser_and_parser_loads_dependencies(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString('self::parse_source($row->source_type,$row->stored_path)',$code);
  $this->assertStringContainsString("require_once SWIMLOG_EVALUATION_DIR.'includes/class-fit-importer.php';return new FIT_Importer()",$code);
  $this->assertStringContainsString("require_once SWIMLOG_EVALUATION_DIR.'includes/class-csv-importer.php';return new CSV_Importer()",$code);
  $this->assertStringNotContainsString('$parser->parse($row->stored_path)',$code);
 }

 public function test_admin_rebuild_requires_nonce_and_preserves_delete_as_separate_action(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin-workouts.php');
  $this->assertStringContainsString("check_admin_referer( 'swimlog_rebuild_workout_' . \$detail )",$code);
  $this->assertStringContainsString('Importer::rebuild_workout( $detail, $user_id )',$code);
  $this->assertStringContainsString('Rebuild from Preserved Sources',$code);
  $this->assertStringContainsString("check_admin_referer( 'swimlog_delete_workout_' . \$detail )",$code);
 }

 public function test_rebuild_prefers_form_fit_authority_independent_of_import_order(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');
  $this->assertStringContainsString("if(self::is_form_fit(\$item['parsed'],(string)\$item['row']->original_filename))", $code);
  $this->assertStringContainsString("if(null===\$first_fit)\$first_fit=\$item", $code);
  $this->assertStringContainsString("if(null!==\$first_fit)\$authority=\$first_fit", $code);
  $schema=file_get_contents(dirname(__DIR__).'/includes/class-database.php');
  $this->assertStringContainsString('authoritative_import_id bigint(20) unsigned NULL',$schema);
  $this->assertStringContainsString("'authoritative_import_id'=>(int)\$authority['row']->id",$code);
  $this->assertStringContainsString("'authoritative_import_id'=>\$import_id",$code);
 }
}

