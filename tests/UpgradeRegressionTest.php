<?php
use PHPUnit\Framework\TestCase;

final class UpgradeRegressionTest extends TestCase {
 public function test_v05_schema_upgrade_preserves_existing_tables_and_adds_location_coordinates(){
  $db=file_get_contents(dirname(__DIR__).'/includes/class-database.php');
  $plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString('dbDelta( $statement )',$db);
  $this->assertStringContainsString('latitude decimal(10,7) NULL',$db);
  $this->assertStringContainsString('longitude decimal(10,7) NULL',$db);
  $this->assertStringContainsString("get_option( 'swimlog_db_version', '0.0.0' )",$plugin);
  $this->assertStringContainsString('Database::install_schema()',$plugin);
  $this->assertStringContainsString('self::migrate_upgrade_settings( $installed )',$plugin);
  $this->assertStringContainsString("update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION )",$plugin);
  $schema=strpos($plugin,'Database::install_schema()');
  $migration=strpos($plugin,'self::migrate_upgrade_settings( $installed )',$schema);
  $version=strpos($plugin,"update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION )",$migration);
  $this->assertLessThan($migration,$schema);
  $this->assertLessThan($version,$migration);
 }
 public function test_v08_schema_adds_manual_workout_provenance_without_reclassifying_legacy_workouts(){
  $db=file_get_contents(dirname(__DIR__).'/includes/class-database.php');
  $this->assertStringContainsString("'manual_workouts'",$db);
  $this->assertStringContainsString("source_type varchar(20) NOT NULL DEFAULT 'import'",$db);
  $this->assertStringContainsString('KEY user_source_type (user_id,source_type)',$db);
  $this->assertStringContainsString("self::table( 'manual_workouts' )",$db);
  $this->assertStringContainsString('workout_id bigint(20) unsigned NOT NULL',$db);
  $this->assertStringContainsString('UNIQUE KEY workout_id (workout_id)',$db);
  $this->assertStringContainsString('schema_version varchar(32) NOT NULL',$db);
  $this->assertStringContainsString('source_document longtext NOT NULL',$db);
  $this->assertStringContainsString('user_id bigint(20) unsigned NOT NULL',$db);
 }
 public function test_legacy_default_upload_types_gain_gpx_and_tcx_only_during_pre_07_upgrade(){
  $plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("version_compare( (string) \$installed_version, '0.7', '<' )",$plugin);
  $this->assertStringContainsString("array( 'fit', 'csv' ) === \$allowed",$plugin);
  $this->assertStringContainsString("update_option( 'swimlog_allowed_upload_types', array( 'fit', 'csv', 'gpx', 'tcx' ) )",$plugin);
 }
}
