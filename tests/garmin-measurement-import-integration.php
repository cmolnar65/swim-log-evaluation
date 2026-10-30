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
$original_request = $_REQUEST;
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
        : wp_json_encode( array( 'calendarDate' => '2026-10-01', 'averageSpO2' => 96.33333333333333, 'lowestSpO2' => 91, 'avgSleepSpO2' => 95.12345678912345, 'startTimestampGMT' => '2026-10-01T06:00:00', 'endTimestampGMT' => '2026-10-02T06:00:00', 'spO2SingleValues' => array( array( 1790834400000, 94 ) ) ) );
    if ( 'milliseconds' === $scenario && false !== strpos( $url, 'weight/all' ) ) {
        $entries = json_decode( $body, true );
        foreach ( $entries as $index => &$entry ) {
            $entry['weight'] *= 1000;
            $entry['timestampGMT'] = 0 === $index ? 1790834400000 : 1790838000000;
            $entry['date'] = 0 === $index ? 1790812800000 : 1790816400000;
        }
        unset( $entry );
        $body = wp_json_encode( $entries );
    }
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
    $scenario = 'milliseconds';
    $millisecond_repeat = Importer::run( $owner, '2026-10-01', '2026-10-01', array( 'weight', 'body_composition' ) );
    $verify( 'complete' === $millisecond_repeat[0]['status'] && 2 === $millisecond_repeat[0]['skipped'] && 0 === $millisecond_repeat[0]['added'], 'Live integer GMT timestamps identify existing string-format weight readings.' );
    $verify( 'complete' === $millisecond_repeat[1]['status'] && 2 === $millisecond_repeat[1]['skipped'] && 0 === $millisecond_repeat[1]['added'], 'Live integer GMT timestamps identify existing BMI readings.' );
    $verify( 2 === $millisecond_repeat[0]['excluded'], 'Numeric local date is excluded without blocking valid UTC readings.' );
    $scenario = 'normal';
    // Reproduce the prior development build: source grams accidentally labeled kg.
    $raw = array( array( 'calendarDate' => '2026-10-02', 'timestampGMT' => 1790920800000, 'weight' => 83500.0 ) );
    $fixed = Importer::normalize( 'weight', '2026-10-02', $raw, '2026-10-03T12:00:00Z' );
    $legacy = $fixed['observations'][0];
    $legacy['values']['weight']['value'] = '83500';
    $legacy['provenance'] = array( 'normalization_version' => 'garmin-measurements-v1' );
    $seed = \SwimLogEvaluation\Health_Measurement_Store::save_report( $owner, $fixed['report'], array( $legacy ) );
    $verify( ! is_wp_error( $seed ) && 1 === $seed['counts']['added'], 'Seed exact gram-as-kg development defect.' );
    $measurements = Database::table( 'health_measurements' );
    $members = Database::table( 'health_report_members' );
    $before_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND calendar_date = %s', $measurements, $owner, '2026-10-02' ) );
    $fixed['report']['fetched_at_utc'] = '2026-10-03T12:00:01Z';
    $fault = static function ( $query ) use ( $measurements ) {
        return 0 === strpos( $query, 'UPDATE ' . chr( 96 ) . $measurements . chr( 96 ) ) ? 'SELECT 1 WHERE 1 = 0' : $query;
    };
    add_filter( 'query', $fault );
    try {
        $failed_repair = \SwimLogEvaluation\Health_Measurement_Store::save_report( $owner, $fixed['report'], $fixed['observations'] );
    } finally {
        remove_filter( 'query', $fault );
    }
    $verify( is_wp_error( $failed_repair ), 'Refuse unsuccessful repair CAS and roll back.' );
    $still_legacy = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND id = %d', $measurements, $owner, (int) $before_row->id ) );
    $verify( $before_row->content_hash === $still_legacy->content_hash && $before_row->values_json === $still_legacy->values_json, 'Failed repair preserves exact original row.' );
    $repaired = \SwimLogEvaluation\Health_Measurement_Store::save_report( $owner, $fixed['report'], $fixed['observations'] );
    $verify( ! is_wp_error( $repaired ) && 1 === $repaired['counts']['updated'] && 0 === $repaired['counts']['added'], 'Repair exact legacy row without adding a reading.' );
    $after_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND calendar_date = %s', $measurements, $owner, '2026-10-02' ) );
    $verify( $before_row->id === $after_row->id && '83.5' === json_decode( $after_row->values_json, true )['values']['weight']['value'], 'Retain row ID and correct stored kilograms.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND measurement_id = %d', $members, $owner, (int) $after_row->id ) ), 'Preserve existing report membership.' );
    $again = \SwimLogEvaluation\Health_Measurement_Store::save_report( $owner, $fixed['report'], $fixed['observations'] );
    $verify( ! is_wp_error( $again ) && 1 === $again['counts']['skipped'], 'Corrected row reimport remains idempotent.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND calendar_date = %s', $measurements, $owner, '2026-10-02' ) ), 'No old incorrect duplicate remains.' );

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
    $_REQUEST = $_POST;
    ob_start();
    $verify( true === API::test_connection( $owner ), 'Test current synthetic credential before showing import controls.' );
    UI::render();
    $html = ob_get_clean();
    $verify( false !== strpos( $html, 'Measurement import results' ) && false !== strpos( $html, 'Readings: added / updated / skipped' ), 'Actual form submits and renders import results.' );
    $verify( false === strpos( $html, 'synthetic-only-not-a-real-key' ) && false === strpos( $html, 'synthetic.token.fixture' ) && false === strpos( $html, '82.5' ), 'Results do not expose credentials or raw measurements.' );
    $verify( false !== strpos( $html, 'BMI only' ) && false !== strpos( $html, 'not imported' ), 'Limitations visible.' );
    $_POST['_wpnonce'] = 'invalid';
    $_REQUEST = $_POST;
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
    $_REQUEST = $original_request;
    if ( $owner ) {
        delete_transient( 'swimlog_health_retry_' . $owner );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $owner ) );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $owner );
    }
    wp_set_current_user( $original_user );
}

