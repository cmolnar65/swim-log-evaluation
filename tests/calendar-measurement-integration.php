<?php
// Synthetic owner data only; real REST permissions and indexed calendar projection.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Health_Measurement_Store as Store;
use SwimLogEvaluation\Calendar_Measurements as Measurements;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-calendar-measurements.php';
global $wpdb;
$original_user = get_current_user_id();
$original_cookies = $_COOKIE;
$original_timezone = get_option( 'timezone_string' );
$users = array();
$checks = 0;
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$login = static function ( $id ) {
    wp_set_current_user( $id );
    $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $id, time() + 3600, 'logged_in' );
};
$call = static function ( $path, $params = array(), $nonce = true ) {
    $request = new WP_REST_Request( 'GET', '/swimlog/v1/' . $path );
    foreach ( $params as $key => $value ) $request->set_param( $key, $value );
    if ( $nonce ) $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
    return rest_do_request( $request );
};
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_health_calendar_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create calendar measurement owner.' );
        $users[$name] = (int) $id;
        $user = new WP_User( $id );
        $user->add_cap( 'swimlog_manage_own_workouts' );
        $user->add_cap( 'swimlog_view_own_results' );
    }
    $owner = $users['owner'];
    $login( $owner );
    $report = array( 'provider' => 'garmin', 'category' => 'spo2', 'calendar_date' => '2026-11-01', 'fetched_at_utc' => '2026-11-02T12:00:00Z',
        'summary' => array( 'average' => array( 'value' => 96.4, 'unit' => '%' ), 'sleep_average' => array( 'value' => 95.8, 'unit' => '%' ), 'seven_day_average' => array( 'value' => 96.8, 'unit' => '%' ) ),
        'context' => array( 'device' => 'PRIVATE_HEALTH_PROVENANCE_SENTINEL', 'window_start_utc' => '2026-10-31T23:00:00Z', 'window_end_utc' => '2026-11-01T23:00:00Z' ) );
    $sample = array( 'role' => 'sample', 'observed_at_utc' => '2026-10-31T23:30:00Z', 'calendar_date' => '2026-11-01', 'date_basis' => 'provider_calendar',
        'values' => array( 'spo2' => array( 'value' => 97, 'unit' => '%' ) ), 'provenance' => array( 'device' => 'PRIVATE_HEALTH_PROVENANCE_SENTINEL' ) );
    $second = $sample; $second['observed_at_utc'] = '2026-11-01T00:30:00Z';
    $conflict = $sample; $conflict['values']['spo2']['value'] = 94;
    $hourly = $sample; $hourly['role'] = 'hourly_average'; $hourly['observed_at_utc'] = '2026-11-01T02:00:00Z';
    $unknown = $sample; $unknown['external_id'] = 'private-source-id'; $unknown['observed_at_utc'] = null; $unknown['calendar_date'] = null; $unknown['date_basis'] = 'unresolved';
    $verify( ! is_wp_error( Store::save_report( $owner, $report, array( $sample, $second, $conflict, $hourly, $unknown ) ) ), 'Seed private report and sample roles.' );
    $adjacent = $report; $adjacent['calendar_date'] = '2026-11-02'; $adjacent['summary'] = array();
    $verify( ! is_wp_error( Store::save_report( $owner, $adjacent, array( $sample ) ) ), 'Overlap membership does not duplicate source sample.' );
    $weight = $report; $weight['category'] = 'weight'; $weight['summary'] = array();
    $weight_sample = $sample; $weight_sample['values'] = array( 'weight' => array( 'value' => 82.5, 'unit' => 'kg' ) );
    $verify( ! is_wp_error( Store::save_report( $owner, $weight, array( $weight_sample ) ) ), 'Seed weight.' );
    $bp = $report; $bp['category'] = 'blood_pressure'; $bp['summary'] = array();
    $bp_sample = $sample; $bp_sample['values'] = array( 'systolic' => array( 'value' => 120, 'unit' => 'mmHg' ), 'diastolic' => array( 'value' => 80, 'unit' => 'mmHg' ) );
    $verify( ! is_wp_error( Store::save_report( $owner, $bp, array( $bp_sample ) ) ), 'Seed grouped BP without claiming Garmin transport support.' );

    $data = Measurements::for_user( $owner, '2026-11-01', '2026-11-01' );
    $verify( ! is_wp_error( $data ) && 1 === count( $data['days'] ) && '2026-11-01' === $data['days'][0]['date'], 'Source date controls day despite different UTC date.' );
    $day = $data['days'][0];
    $verify( 5 === count( $day['readings'] ) && 1 === count( $day['reports'] ) && 1 === count( $day['aggregates'] ), 'Samples, provider summaries and aggregates separate; unknown day excluded.' );
    $verify( '96.4' === $day['reports'][0]['values']['average']['value'] && '96.8' === $day['reports'][0]['values']['seven_day_average']['value'], 'Provider and baseline values remain distinct, no recomputed mean.' );
    $conflicts = array_filter( $day['readings'], static function ( $row ) { return $row['has_conflict']; } );
    $verify( 2 === count( $conflicts ), 'Both conflicting same-time observations marked.' );
    $bp_rows = array_values( array_filter( $day['readings'], static function ( $row ) { return 'blood_pressure' === $row['category']; } ) );
    $verify( isset( $bp_rows[0]['values']['systolic'], $bp_rows[0]['values']['diastolic'] ), 'BP pair remains one grouped reading.' );
    $verify( false === strpos( wp_json_encode( $data ), 'PRIVATE_HEALTH' ) && false === strpos( wp_json_encode( $data ), 'private-source-id' ), 'Projection excludes device/provenance/external identifiers.' );
    $empty = Measurements::for_user( $owner, '2026-10-31', '2026-10-31' );
    $verify( array() === $empty['days'], 'No inference from UTC date into wrong day.' );
    update_option( 'timezone_string', 'Pacific/Honolulu' );
    $verify( $data === Measurements::for_user( $owner, '2026-11-01', '2026-11-01' ), 'Site timezone change does not reassign provider dates.' );
    update_option( 'timezone_string', $original_timezone );
    $verify( is_wp_error( Measurements::for_user( $owner, '2026-02-30', '2026-03-01' ) ), 'Impossible range rejected.' );
    $verify( is_wp_error( Measurements::for_user( $owner, '2026-10-01', '2026-12-02' ) ), 'Oversized range rejected.' );
    rest_get_server();
    $params = array( 'start' => '2026-11-01', 'end' => '2026-11-01' );
    $response = $call( 'calendar/measurements', $params );
    $verify( 200 === $response->get_status(), 'Owner cookie and nonce accepted.' );
    $headers = $response->get_headers();
    $verify( isset( $headers['Cache-Control'] ) && 'private, no-store' === $headers['Cache-Control'], 'Private no-store response.' );
    $verify( $response->get_data() === $data, 'REST returns same safe day projection.' );
    $verify( $call( 'calendar/measurements', $params, false )->get_status() >= 400, 'Missing REST nonce denied.' );
    $request = new WP_REST_Request( 'GET', '/swimlog/v1/calendar/measurements' );
    foreach ( $params as $key => $value ) $request->set_param( $key, $value );
    $request->set_header( 'X-WP-Nonce', 'invalid' );
    $verify( rest_do_request( $request )->get_status() >= 400, 'Invalid REST nonce denied.' );
    unset( $_COOKIE[LOGGED_IN_COOKIE] );
    $verify( $call( 'calendar/measurements', $params )->get_status() >= 400, 'Noncookie/Application Password style authentication cannot disclose measurements.' );
    $login( $owner );
    $legacy = $call( 'calendar', $params );
    $verify( 200 === $legacy->get_status() && ! isset( $legacy->get_data()['measurements'] ) && false === strpos( wp_json_encode( $legacy->get_data() ), '96.4' ), 'Existing calendar response unchanged.' );
    update_user_meta( $owner, 'swimlog_public_results', '1' );
    update_user_meta( $owner, 'swimlog_public_ows_routes', '1' );
    update_user_meta( $owner, 'swimlog_rest_api_access', '1' );
    $portability = $call( 'workouts' );
    $verify( 200 === $portability->get_status() && false === strpos( wp_json_encode( $portability->get_data() ), '96.4' ), 'Existing portability response excludes health data.' );
    $login( $users['other'] );
    ( new WP_User( $users['other'] ) )->add_cap( 'swimlog_manage_all_users' );
    wp_set_current_user( 0 ); wp_set_current_user( $users['other'] );
    $verify( is_wp_error( Measurements::for_user( $owner, '2026-11-01', '2026-11-01' ) ), 'Foreign owner denied at service layer.' );
    $verify( $call( 'calendar/measurements', array_merge( $params, array( 'user' => $owner ) ) )->get_status() >= 400, 'Manage-all capability does not grant foreign health access.' );
    $own_empty = $call( 'calendar/measurements', $params );
    $verify( 200 === $own_empty->get_status() && array() === $own_empty->get_data()['days'], 'Other owner receives only own empty result.' );
    wp_set_current_user( 0 ); unset( $_COOKIE[LOGGED_IN_COOKIE] );
    $verify( $call( 'calendar/measurements', $params, false )->get_status() >= 400, 'Anonymous route denied.' );

    // Malformed storage is excluded rather than leaking an arbitrary JSON object.
    $login( $owner );
    $wpdb->insert( Database::table( 'health_reports' ), array( 'user_id' => $owner, 'provider' => 'garmin', 'category' => 'spo2', 'provider_date' => '2026-11-01', 'identity_hash' => hash( 'sha256', 'invalid-calendar-report' ), 'content_hash' => hash( 'sha256', 'invalid' ), 'summary_json' => '{"version":1,"values":{"secret":{"value":12,"unit":"unknown"}}}', 'context_json' => '{}', 'fetched_at_utc' => '2026-11-02 12:00:00', 'created_at_utc' => '2026-11-02 12:00:00', 'updated_at_utc' => '2026-11-02 12:00:00' ) );
    $filtered = Measurements::for_user( $owner, '2026-11-01', '2026-11-01' );
    $verify( 1 === $filtered['invalid_records'] && false === strpos( wp_json_encode( $filtered ), 'secret' ), 'Malformed numeric envelope excluded with count.' );
    for ( $i = 0; $i <= 500; ++$i ) {
        $wpdb->insert( Database::table( 'health_reports' ), array( 'user_id' => $owner, 'provider' => 'garmin', 'category' => 'spo2', 'provider_date' => '2026-11-01', 'identity_hash' => hash( 'sha256', 'density-' . $i ), 'content_hash' => hash( 'sha256', 'density-content' ), 'summary_json' => '{"version":1,"values":{"average":{"value":"96","unit":"%"}}}', 'context_json' => '{}', 'fetched_at_utc' => '2026-11-02 12:00:00', 'created_at_utc' => '2026-11-02 12:00:00', 'updated_at_utc' => '2026-11-02 12:00:00' ) );
    }
    $dense = Measurements::for_user( $owner, '2026-11-01', '2026-11-01' );
    $verify( is_wp_error( $dense ) && 413 === $dense->get_error_data()['status'], 'Dense range fails explicitly instead of silently truncating.' );
    WP_CLI::success( 'Private calendar measurements passed ' . $checks . ' assertions: source dates/DST, grouped values, report/sample separation, overlap/conflicts, owner cookie/nonce boundary, portability exclusion and malformed storage.' );
} finally {
    $_COOKIE = $original_cookies;
    update_option( 'timezone_string', $original_timezone );
    foreach ( $users as $id ) {
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $id );
    }
    wp_set_current_user( $original_user );
}
