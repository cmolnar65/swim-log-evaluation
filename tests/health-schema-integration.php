<?php
// Synthetic migration regression in disposable wp-env only; never shipped.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Plugin;

global $wpdb;
$verify = static function ( $ok, $message ) {
    if ( ! $ok ) throw new RuntimeException( $message );
};
$original_prefix = $wpdb->prefix;
$original_version = get_option( 'swimlog_db_version', false );
$original_user = get_current_user_id();
$prefix = $original_prefix . 'healthtest_' . strtolower( wp_generate_password( 8, false ) ) . '_';
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$verify( ! empty( $admin ), 'No disposable administrator.' );
$health = array( 'health_reports', 'health_measurements', 'health_report_members' );
$failure_filter = null;
try {
    // Only plugin table resolution changes; WordPress core users/options stay on the original tables.
    $wpdb->prefix = $prefix;
    $verify( Database::install_schema(), 'Fresh health schema failed.' );
    $legacy = array_diff( Database::required_tables(), $health );
    $legacy_schema = array();
    foreach ( $legacy as $name ) {
        $table = Database::table( $name );
        $row = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );
        $legacy_schema[$name] = $row[1];
    }
    $workouts = Database::table( 'workouts' );
    $wpdb->insert( $workouts, array( 'user_id' => 777, 'workout_start' => '2026-10-01 12:00:00', 'notes' => 'legacy sentinel', 'created_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-01 12:00:00' ) );
    $verify( 1 === $wpdb->rows_affected, 'Legacy sentinel insert failed.' );
    $legacy_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i LIMIT 1', $workouts ), ARRAY_A );
    foreach ( $health as $name ) $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', Database::table( $name ) ) );
    update_option( 'swimlog_db_version', '1.2' );
    wp_set_current_user( (int) $admin[0] );
    $verify( ! Database::schema_is_complete(), 'Legacy-only schema should require migration.' );

    // Inject a failed CREATE; version must stay at 1.2 even if other new tables succeeded.
    $target = Database::table( 'health_measurements' );
    $failure_filter = static function ( $sql ) use ( $target ) {
        return false !== strpos( $sql, 'CREATE TABLE ' . $target . ' (' ) ? 'INVALID SYNTHETIC MIGRATION STATEMENT' : $sql;
    };
    add_filter( 'query', $failure_filter, 1 );
    $old_suppress = $wpdb->suppress_errors( true );
    ( new Plugin() )->maybe_upgrade_schema();
    $wpdb->suppress_errors( $old_suppress );
    remove_filter( 'query', $failure_filter, 1 );
    $failure_filter = null;
    $verify( '1.2' === get_option( 'swimlog_db_version' ), 'Failed migration advanced version.' );
    ( new Plugin() )->maybe_upgrade_schema();
    $verify( '1.3' === get_option( 'swimlog_db_version' ), 'Retry did not advance schema.' );
    $verify( Database::schema_is_complete(), 'Upgraded schema incomplete.' );
    foreach ( $legacy_schema as $name => $sql ) {
        $row = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', Database::table( $name ) ), ARRAY_N );
        // AUTO_INCREMENT changes from the sentinel insert are data state, not a schema mutation.
        $strip = static function ( $value ) { return preg_replace( '/ AUTO_INCREMENT=\\d+/', '', $value ); };
        $verify( $strip( $sql ) === $strip( $row[1] ), 'Legacy table definition changed: ' . $name );
    }
    $verify( $legacy_row === $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i LIMIT 1', $workouts ), ARRAY_A ), 'Legacy data changed.' );

    $reports = Database::table( 'health_reports' );
    $measurements = Database::table( 'health_measurements' );
    $members = Database::table( 'health_report_members' );
    $report = array( 'user_id' => 777, 'provider' => 'garmin', 'category' => 'spo2', 'provider_date' => '2026-11-01', 'identity_hash' => hash( 'sha256', 'report' ), 'content_hash' => hash( 'sha256', 'summary' ), 'summary_json' => '{"averageSpO2":96.4}', 'context_json' => '{"windowStartUTC":"2026-11-01T06:00:00Z"}', 'fetched_at_utc' => '2026-11-02 01:00:00', 'created_at_utc' => '2026-11-02 01:00:00', 'updated_at_utc' => '2026-11-02 01:00:00' );
    $verify( 1 === $wpdb->insert( $reports, $report ), 'Report insert failed.' );
    $report_id = (int) $wpdb->insert_id;
    $measurement = array( 'user_id' => 777, 'provider' => 'garmin', 'category' => 'blood_pressure', 'record_role' => 'sample', 'identity_hash' => hash( 'sha256', 'observation' ), 'content_hash' => hash( 'sha256', 'values' ), 'observed_at_utc' => '2026-11-01T07:30:00.123456Z', 'source_local_time' => '2026-11-01T01:30:00.123456', 'source_offset_seconds' => -21600, 'calendar_date' => '2026-11-01', 'date_basis' => 'source_local', 'values_json' => '{"systolic":{"value":"120","unit":"mmHg"},"diastolic":{"value":"80","unit":"mmHg"}}', 'provenance_json' => '{"format":1,"synthetic":true}', 'created_at_utc' => '2026-11-02 01:00:00', 'updated_at_utc' => '2026-11-02 01:00:00' );
    $verify( 1 === $wpdb->insert( $measurements, $measurement ), 'Grouped observation insert failed.' );
    $measurement_id = (int) $wpdb->insert_id;
    $member = array( 'user_id' => 777, 'report_id' => $report_id, 'measurement_id' => $measurement_id, 'created_at_utc' => '2026-11-02 01:00:00' );
    $verify( 1 === $wpdb->insert( $members, $member ), 'Membership failed.' );
    $old_suppress = $wpdb->suppress_errors( true );
    $verify( false === $wpdb->insert( $reports, $report ), 'Duplicate report allowed.' );
    $verify( false === $wpdb->insert( $measurements, $measurement ), 'Duplicate observation allowed.' );
    $verify( false === $wpdb->insert( $members, $member ), 'Duplicate membership allowed.' );
    $wpdb->suppress_errors( $old_suppress );
    $measurement['user_id'] = 778;
    $measurement['calendar_date'] = null;
    $measurement['observed_at_utc'] = null;
    $measurement['source_offset_seconds'] = null;
    $measurement['date_basis'] = 'unresolved';
    $verify( 1 === $wpdb->insert( $measurements, $measurement ), 'Different owner or unknown time unsupported.' );
    $before = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id', $measurements ), ARRAY_A );
    $verify( Database::install_schema() && Database::install_schema(), 'Repeated migration failed.' );
    $verify( $before === $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id', $measurements ), ARRAY_A ), 'Reinstall rewrote measurement precision or grouped values.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $reports ) ), 'Report lost on reinstall.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $members ) ), 'Membership lost on reinstall.' );
    $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX owner_identity', $measurements ) );
    $verify( ! Database::schema_is_complete(), 'Missing unique index went undetected.' );
    $verify( Database::install_schema(), 'Missing index not repaired.' );
    $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN provenance_json', $measurements ) );
    $verify( ! Database::schema_is_complete(), 'Missing column went undetected.' );
    $verify( Database::install_schema(), 'Missing column not repaired.' );
    WP_CLI::success( 'Health schema migration: fresh install, failed-upgrade retry, 1.2 upgrade, legacy preservation, grouped values/time precision, owner uniqueness, idempotence and partial-schema repair passed.' );
} finally {
    if ( $failure_filter ) remove_filter( 'query', $failure_filter, 1 );
    foreach ( Database::required_tables() as $name ) $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', Database::table( $name ) ) );
    $wpdb->prefix = $original_prefix;
    if ( false === $original_version ) delete_option( 'swimlog_db_version' );
    else update_option( 'swimlog_db_version', $original_version );
    wp_set_current_user( $original_user );
}
