<?php
// Synthetic HTTP responses only; no real Garmin requests or personal data.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Garmin_Measurement_Import as Importer;
use SwimLogEvaluation\Admin_Garmin_Measurements as UI;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin-measurements.php';
global $wpdb;
$original_user = get_current_user_id();
$original_post = $_POST;
$owner = 0;
$checks = 0;
$calls = array();
$scenario = 'normal';
$nonce_filter = null;
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $preempt, $args, $url ) use ( &$calls, &$scenario ) {
    if ( 0 !== strpos( $url, 'https://personal.connectapi.garmin.com/' ) ) return new WP_Error( 'unexpected_http', 'Unexpected HTTP in measurement test.' );
    $calls[] = array( 'url' => $url, 'timeout' => $args['timeout'] );
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'synthetic.token.fixture' );
    if ( 'timeout' === $scenario ) return new WP_Error( 'http_request_failed', 'synthetic timeout' );
    $body = false !== strpos( $url, 'weight/all' )
        ? wp_json_encode( array( array( 'calendarDate' => '2026-10-01', 'timestampGMT' => '2026-10-01T06:00:00Z', 'date' => '2026-10-01T00:00:00', 'weight' => 82.5, 'bmi' => 24.1, 'bodyFat' => 19.2, 'boneMass' => 4000 ), array( 'calendarDate' => '2026-10-01', 'timestampGMT' => '2026-10-01T07:00:00Z', 'weight' => 82.6, 'bmi' => 24.2 ) ) )
        : wp_json_encode( array( 'calendarDate' => '2026-10-01', 'averageSpO2' => 96.4, 'lowestSpO2' => 91, 'avgSleepSpO2' => 95.8, 'startTimestampGMT' => '2026-10-01T06:00:00', 'endTimestampGMT' => '2026-10-02T06:00:00', 'spO2SingleValues' => array( array( 1790834400000, 94 ) ) ) );
    $status = 200;
    if ( in_array( $scenario, array( '401', '403', '429', '500' ), true ) ) $status = (int) $scenario;
    if ( 'invalid_json' === $scenario ) $body = '{not-json';
    if ( 'oversized' === $scenario ) $body = str_repeat( 'x', 1048577 );
    if ( 'empty' === $scenario ) $body = false !== strpos( $url, 'weight/all' ) ? '[]' : '{}';
    if ( 'wrong_shape' === $scenario ) $body = false !== strpos( $url, 'weight/all' ) ? '{}' : '[]';
    if ( 'wrong_day' === $scenario ) $body = '{"calendarDate":"2026-10-02","averageSpO2":97}';
    return array( 'response' => array( 'code' => $status ), 'headers' => array( 'retry-after' => '120' ), 'body' => $body );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $created = wp_create_user( 'swimlog_measurement_import_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
    $verify( ! is_wp_error( $created ), 'Create import owner.' );
    $owner = (int) $created;
    wp_set_current_user( $owner );
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
    wp_set_current_user( 0 ); wp_set_current_user( $owner );
    $verify( ! is_wp_error( API::save_key( $owner, 'synthetic-only-not-a-real-key' ) ), 'Save encrypted synthetic key.' );
    $result = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'weight', 'body_composition', 'spo2' ) );
    $verify( is_array( $result ) && 3 === count( $result ), 'Return all selected category results.' );
    $verify( 'complete' === $result[0]['status'] && 2 === $result[0]['added'], 'Import individual weight readings.' );
    $verify( 'complete' === $result[1]['status'] && 2 === $result[1]['added'] && 2 === $result[1]['excluded'], 'Import BMI; exclude unknown units.' );
    $verify( 'complete' === $result[2]['status'] && 1 === $result[2]['reports_added'] && 0 === $result[2]['added'] && 1 === $result[2]['excluded'], 'Import provider SpO2 report without guessed samples.' );
    $verify( 3 === count( $calls ), 'One token and two bounded data requests; shared weight response.' );
    $verify( 8 === $calls[1]['timeout'] && 8 === $calls[2]['timeout'], 'Measurement timeout bounded.' );
    $repeat = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'weight', 'body_composition', 'spo2' ) );
    $verify( 2 === $repeat[0]['skipped'] && 0 === $repeat[0]['added'] && 1 === $repeat[2]['reports_skipped'], 'Reimport is duplicate safe.' );
    $snapshot = array();
    foreach ( array( 'health_reports', 'health_measurements', 'health_report_members' ) as $name ) {
        $snapshot[$name] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( $name ), $owner ) );
    }
    foreach ( array( 'timeout', '401', '403', '500', 'invalid_json', 'oversized', 'wrong_shape', 'wrong_day' ) as $scenario ) {
        $bad = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'spo2' ) );
        $verify( 'failed' === $bad[0]['status'] && 1 === $bad[0]['failed'], 'Failed response reported: ' . $scenario );
    }
    $scenario = 'empty';
    $empty = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'weight', 'spo2' ) );
    $verify( 'no_data' === $empty[0]['status'] && 'no_data' === $empty[1]['status'], 'Valid empty response distinguished from failure.' );
    foreach ( $snapshot as $name => $count ) $verify( $count === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( $name ), $owner ) ), 'Failed/empty responses preserved existing records.' );
    $before_calls = count( $calls );
    wp_set_current_user( 0 );
    $verify( is_wp_error( Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'weight' ) ) ), 'Anonymous import denied.' );
    wp_set_current_user( $owner );
    $verify( is_wp_error( Importer::run( $owner + 999, '2026-10-01', '2026-10-01', array( 'weight' ) ) ), 'Foreign import denied.' );
    $verify( is_wp_error( Importer::run( $owner, '2026-10-01', '2026-10-10', array( 'weight' ) ) ), 'Oversized date range denied.' );
    $verify( is_wp_error( Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'blood_pressure' ) ) ), 'Undocumented BP denied.' );
    $verify( $before_calls === count( $calls ), 'Denied imports did not request data.' );

    $scenario = '429';
    $limited = Importer::run( $owner, '2026-10-01', '2026-10-02', array( 'spo2' ) );
    $verify( 'failed' === $limited[0]['status'] && 120 === $limited[0]['retry_after'] && 'not_attempted' === $limited[1]['status'], 'Rate limit stops subsequent requests.' );
    $before_calls = count( $calls );
    $limited_again = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'spo2' ) );
    $verify( isset( $limited_again[0]['retry_after'] ) && $before_calls === count( $calls ), 'Retry-after cooldown honored without another HTTP call.' );
    delete_transient( 'swimlog_health_retry_' . $owner );
    $scenario = 'normal';

    // Render and submit the actual owner form, including nonce rejection.
    $_POST = array( 'swimlog_measurement_import' => '1', '_wpnonce' => wp_create_nonce( 'swimlog_garmin_measurements' ), 'measurement_start' => '2026-10-01', 'measurement_end' => '2026-10-01', 'measurement_categories' => array( 'weight', 'spo2' ) );
    ob_start();
    UI::render();
    $html = ob_get_clean();
    $verify( false !== strpos( $html, 'Measurement import results' ) && false !== strpos( $html, 'Readings: added / updated / skipped' ), 'Actual form submits and renders import results.' );
    $verify( false === strpos( $html, 'synthetic-only-not-a-real-key' ) && false === strpos( $html, 'synthetic.token.fixture' ) && false === strpos( $html, '82.5' ), 'Results do not expose credentials or raw measurements.' );
    $verify( false !== strpos( $html, 'BMI only' ) && false !== strpos( $html, 'not imported' ), 'Limitations visible.' );
    $_POST['_wpnonce'] = 'invalid';
    $nonce_filter = static function () { return static function () { throw new RuntimeException( 'expected nonce denial' ); }; };
    add_filter( 'wp_die_handler', $nonce_filter );
    add_filter( 'wp_die_ajax_handler', $nonce_filter );
    $before_calls = count( $calls );
    $denied = false;
    ob_start();
    try { UI::render(); } catch ( RuntimeException $error ) { $denied = 'expected nonce denial' === $error->getMessage(); } finally { ob_end_clean(); }
    $verify( $denied && $before_calls === count( $calls ), 'Invalid nonce prevents import before HTTP.' );
    WP_CLI::success( 'Manual Garmin measurement import passed ' . $checks . ' assertions: synthetic HTTP, verified normalization, duplicate persistence, bounds/errors, rate limits, privacy and actual nonce-protected form.' );
} finally {
    if ( $nonce_filter ) {
        remove_filter( 'wp_die_handler', $nonce_filter );
        remove_filter( 'wp_die_ajax_handler', $nonce_filter );
    }
    remove_filter( 'pre_http_request', $http, 10 );
    $_POST = $original_post;
    if ( $owner ) {
        delete_transient( 'swimlog_health_retry_' . $owner );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $owner ) );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $owner );
    }
    wp_set_current_user( $original_user );
}
