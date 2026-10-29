<?php
// Synthetic private persistence checks against real WordPress/MariaDB.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Health_Measurement_Store as Store;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
global $wpdb;
$original_user = get_current_user_id();
$users = array();
$filter = null;
$checks = 0;
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$report = array( 'provider' => 'garmin', 'category' => 'spo2', 'calendar_date' => '2026-11-01', 'fetched_at_utc' => '2026-11-02T12:00:00Z', 'summary' => array( 'average' => array( 'value' => '96.4', 'unit' => '%' ) ), 'context' => array( 'normalization_version' => 'synthetic-v1' ) );
$sample = array( 'role' => 'sample', 'observed_at_utc' => '2026-11-01T07:30:00Z', 'calendar_date' => '2026-11-01', 'date_basis' => 'provider_calendar', 'values' => array( 'spo2' => array( 'value' => 97, 'unit' => '%' ) ) );
$reports = Database::table( 'health_reports' );
$measurements = Database::table( 'health_measurements' );
$members = Database::table( 'health_report_members' );
$peer = null;
$lock = null;
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_health_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create health test user.' );
        $users[$name] = (int) $id;
        ( new WP_User( $id ) )->add_cap( 'swimlog_manage_own_workouts' );
    }
    $owner = $users['owner'];
    wp_set_current_user( $owner );
    $first = Store::save_report( $owner, $report, array( $sample ) );
    $verify( ! is_wp_error( $first ) && 1 === $first['counts']['added'] && 'added' === $first['report_status'], 'First bundle insert.' );
    $id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d', $measurements, $owner ) );
    $row = Store::get_for_user( $id, $owner );
    $verify( $row && ! $row->has_conflict, 'Private measurement read.' );
    $repeat = Store::save_report( $owner, $report, array( $sample, $sample ) );
    $verify( ! is_wp_error( $repeat ) && 2 === $repeat['counts']['skipped'] && 0 === $repeat['counts']['added'], 'Repeated stream skips.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $members, $owner ) ), 'Repeated membership deduplicated.' );
    $adjacent = $report;
    $adjacent['calendar_date'] = '2026-11-02';
    $adjacent['fetched_at_utc'] = '2026-11-03T12:00:00Z';
    $overlap = Store::save_report( $owner, $adjacent, array( $sample ) );
    $verify( ! is_wp_error( $overlap ) && 1 === $overlap['counts']['skipped'], 'Adjacent report shares existing sample.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $measurements, $owner ) ), 'Overlap did not duplicate measurement.' );
    $second = $sample;
    $second['observed_at_utc'] = '2026-11-01T08:30:00Z';
    $report['fetched_at_utc'] = '2026-11-02T12:01:00Z';
    $result = Store::save_report( $owner, $report, array( $second ) );
    $verify( ! is_wp_error( $result ) && 1 === $result['counts']['added'], 'Equal values at distinct times retained.' );
    $conflict = $sample;
    $conflict['values']['spo2']['value'] = 94;
    $result = Store::save_report( $owner, $report, array( $conflict ) );
    $verify( ! is_wp_error( $result ) && 1 === $result['counts']['added'] && 1 === $result['counts']['conflicts'], 'Same-time differing samples retained as ambiguity.' );
    $verify( Store::get_for_user( $id, $owner )->has_conflict, 'Existing peer also reports ambiguity.' );

    $stable = $sample;
    $stable['external_id'] = 'synthetic-stable-id';
    $stable['observed_at_utc'] = '2026-11-01T09:30:00Z';
    $verify( ! is_wp_error( Store::save_report( $owner, $report, array( $stable ) ) ), 'Stable identity insert.' );
    $stable_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND external_id = %s', $measurements, $owner, 'synthetic-stable-id' ) );
    $before = Store::get_for_user( $stable_id, $owner );
    $stable['values']['spo2']['value'] = 96;
    $verify( is_wp_error( Store::save_report( $owner, $report, array( $stable ) ) ), 'Unconfirmed overwrite rejected.' );
    $stable['confirmed_correction'] = true;
    $stable['expected_content_hash'] = $before->content_hash;
    $result = Store::save_report( $owner, $report, array( $stable ) );
    $verify( ! is_wp_error( $result ) && 1 === $result['counts']['updated'], 'Confirmed compare-and-swap correction.' );
    $stable['values']['spo2']['value'] = 95;
    $verify( is_wp_error( Store::save_report( $owner, $report, array( $stable ) ) ), 'Stale correction hash rejected.' );
    $verify( false !== strpos( Store::get_for_user( $stable_id, $owner )->values_json, '"96"' ), 'Stale correction preserved current value.' );

    $updated = $report;
    $updated['fetched_at_utc'] = '2026-11-02T12:02:00Z';
    $updated['summary']['average']['value'] = 96.7;
    $result = Store::save_report( $owner, $updated, array() );
    $verify( ! is_wp_error( $result ) && 'updated' === $result['report_status'] && $result['report_id'] === $first['report_id'], 'Provider summary refresh updates same report.' );
    $verify( is_wp_error( Store::save_report( $owner, $report, array() ) ), 'Stale summary rejected.' );
    $verify( ! is_wp_error( Store::save_report( $owner, $updated, array() ) ), 'Empty stream does not erase samples.' );

    wp_set_current_user( $users['other'] );
    $verify( is_wp_error( Store::save_report( $owner, $updated, array( $sample ) ) ), 'Foreign write denied.' );
    $verify( null === Store::get_for_user( $id, $users['other'] ), 'Foreign record unavailable to authenticated other owner.' );
    $verify( ! is_wp_error( Store::save_report( $users['other'], $updated, array( $sample ) ) ), 'Same source identity for separate owner allowed.' );
    wp_set_current_user( 0 );
    $verify( is_wp_error( Store::get_for_user( $id, $owner ) ), 'Anonymous read denied.' );
    wp_set_current_user( $owner );
    ( new WP_User( $owner ) )->remove_cap( 'swimlog_manage_own_workouts' );
    wp_set_current_user( 0 ); wp_set_current_user( $owner );
    $verify( is_wp_error( Store::save_report( $owner, $updated, array() ) ), 'Missing capability denied.' );
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
    wp_set_current_user( 0 ); wp_set_current_user( $owner );

    // A second DB connection holding the identical category lock blocks all overlapping writes.
    $peer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $lock = 'swimlog_health_' . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner . ':garmin:spo2' ), 0, 48 );
    $verify( 1 === (int) $peer->get_var( $peer->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ), 'Acquire competing connection lock.' );
    $verify( is_wp_error( Store::save_report( $owner, $updated, array( $sample ) ) ), 'Concurrent category writer rejected for retry.' );
    $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    $lock = null;
    $verify( ! is_wp_error( Store::save_report( $owner, $updated, array( $sample ) ) ), 'Retry after lock release succeeds without duplicate.' );

    Database::write( 'query', 'START TRANSACTION' );
    $verify( is_wp_error( Store::save_report( $owner, $updated, array() ) ), 'Nested transaction refused.' );
    $verify( 1 === (int) $wpdb->get_var( 'SELECT @@session.in_transaction' ), 'Caller transaction not committed/rolled back.' );
    Database::write( 'query', 'ROLLBACK' );

    $snapshot = array();
    foreach ( array( $reports, $measurements, $members ) as $table ) $snapshot[$table] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id', $table, $owner ), ARRAY_A );
    $filter = static function ( $sql ) use ( $members ) { return false !== strpos( $sql, 'INSERT INTO `' . $members . '`' ) ? 'INVALID SYNTHETIC MEMBERSHIP WRITE' : $sql; };
    add_filter( 'query', $filter, 1 );
    $failed_report = $updated;
    $failed_report['calendar_date'] = '2026-11-03';
    $third = $sample;
    $third['observed_at_utc'] = '2026-11-01T10:30:00Z';
    $old_suppress = $wpdb->suppress_errors( true );
    $failure = Store::save_report( $owner, $failed_report, array( $third ) );
    $wpdb->suppress_errors( $old_suppress );
    remove_filter( 'query', $filter, 1 ); $filter = null;
    $verify( is_wp_error( $failure ), 'Injected partial failure returned failure.' );
    foreach ( $snapshot as $table => $rows ) $verify( $rows === $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id', $table, $owner ), ARRAY_A ), 'Partial failure left committed changes.' );
    $verify( ! is_wp_error( Store::save_report( $owner, $failed_report, array( $third ) ) ), 'Retry after rollback released lock.' );
    WP_CLI::success( 'Private measurement persistence passed ' . $checks . ' assertions: duplicate streams, overlap, same-day readings, ambiguity, corrections, stale snapshots, owner privacy, competing connection, nested transaction protection and rollback.' );
} finally {
    if ( $filter ) remove_filter( 'query', $filter, 1 );
    if ( $peer && $lock ) $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    if ( $peer ) $peer->close();
    Database::write( 'query', 'ROLLBACK' );
    foreach ( $users as $id ) {
        foreach ( array( $members, $measurements, $reports ) as $table ) Database::write( 'delete', $table, array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $id );
    }
    wp_set_current_user( $original_user );
}
