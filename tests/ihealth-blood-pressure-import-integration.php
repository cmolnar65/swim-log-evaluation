<?php
// Synthetic-only iHealth Personal API and private measurement persistence integration.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Calendar_Measurements as Calendar;
use SwimLogEvaluation\Health_Measurement_Store as Store;
use SwimLogEvaluation\IHealth_OAuth as OAuth;
use SwimLogEvaluation\IHealth_Blood_Pressure_Import as Importer;

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-blood-pressure-import.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-calendar-measurements.php';

global $wpdb;
$previous_user = get_current_user_id();
$old_application = get_option( OAuth::APP_OPTION, null );
$users = array();
$payload_unit = 'mmHg';
$timestamp = time() - 3600;
$checks = 0;
$verify = static function ( $condition, $message ) use ( &$checks ) { ++$checks; if ( ! $condition ) throw new RuntimeException( $message ); };
$mock = static function ( $pre, $args, $url ) use ( &$payload_unit, $timestamp ) {
    if ( false !== strpos( $url, '/bp.json/' ) ) {
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array(
            'BPUnit' => $payload_unit,
            'BPDataList' => array( array(
                'DataID' => 'synthetic-ihealth-bp-001', 'MDate' => (string) $timestamp, 'HP' => 121, 'LP' => 79, 'HR' => 64,
                'BPL' => 1, 'IsArr' => 1, 'Lat' => 123, 'Lon' => 456, 'Note' => 'synthetic-private-note',
                'DataSource' => 'synthetic-device', 'TimeZone' => 'synthetic-zone', 'measurement_time' => 'synthetic-time',
            ) ),
            'PageLength' => 25, 'RecordCount' => 1, 'NextPageUrl' => '',
        ) ) );
    }
    if ( false !== strpos( $url, 'userauthorization' ) ) {
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array(
            'APIName' => 'OpenApiBP', 'AccessToken' => 'synthetic-bp-access', 'RefreshToken' => 'synthetic-bp-refresh', 'UserID' => 'synthetic-bp-account', 'Expires' => 3600,
        ) ) );
    }
    return $pre;
};
add_filter( 'pre_http_request', $mock, 10, 3 );

try {
    foreach ( array( 'owner', 'other' ) as $key ) {
        $id = wp_create_user( 'swimlog_ihealth_bp_' . $key . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create synthetic iHealth BP test user.' );
        $users[ $key ] = (int) $id;
        $user = new WP_User( $id );
        $user->add_cap( 'manage_options' );
        $user->add_cap( 'swimlog_manage_own_workouts' );
        $user->add_cap( 'swimlog_view_own_results' );
    }

    $owner = $users['owner'];
    wp_set_current_user( $owner );
    $view = OAuth::application_view();
    $verify( true === OAuth::save_application( $owner, 'synthetic-bp-app', 'synthetic-bp-secret', array( 'OpenApiBP' ), $view['revision'], array( 'sc' => 'synthetic-bp-sc', 'blood_pressure_sv' => 'synthetic-bp-sv' ) ), 'Save encrypted synthetic BP signing configuration.' );
    $authorization = OAuth::begin_authorization( $owner );
    $verify( is_string( $authorization ), 'Start BP-scoped OAuth authorization.' );
    parse_str( (string) parse_url( $authorization, PHP_URL_QUERY ), $query );
    parse_str( (string) parse_url( $query['redirect_uri'] ?? '', PHP_URL_QUERY ), $redirect );
    $verify( OAuth::complete_authorization( $owner, array( 'state' => $redirect['state'] ?? '', 'code' => 'synthetic-bp-code' ) ), 'Store encrypted BP-only grant.' );

    $today = wp_date( 'Y-m-d', null, wp_timezone() );
    $start = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify( '-5 days' )->format( 'Y-m-d' );
    $first = Importer::import_page( $owner, $start, $today, 1 );
    $verify( ! is_wp_error( $first ) && 1 === $first['added'] && 0 === $first['invalid'], 'Import the one bounded synthetic blood-pressure record.' );
    $repeat = Importer::import_page( $owner, $start, $today, 1 );
    $verify( ! is_wp_error( $repeat ) && 1 === $repeat['skipped'] && 0 === $repeat['added'], 'Repeated page is idempotent by private iHealth source identity.' );

    $table = Database::table( 'health_measurements' );
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND provider = %s AND category = %s AND external_id = %s', $table, $owner, 'ihealth', 'blood_pressure', 'synthetic-ihealth-bp-001' ) );
    $verify( $row && false === strpos( $row->provenance_json, 'synthetic-private-note' ) && false === strpos( $row->provenance_json, 'synthetic-device' ) && false === strpos( $row->provenance_json, 'synthetic-zone' ), 'Do not retain notes, device source or timezone metadata.' );
    $verify( false === strpos( $row->values_json, 'synthetic' ) && false === strpos( $row->provenance_json, 'synthetic-account' ), 'Do not include account or provider payload values in normalized storage.' );

    $day = wp_date( 'Y-m-d', $timestamp, wp_timezone() );
    $calendar = Calendar::for_user( $owner, $day, $day );
    $readings = array_values( array_filter( $calendar['days'][0]['readings'], static function ( $reading ) { return 'blood_pressure' === $reading['category'] && 'iHealth' === $reading['source']; } ) );
    $verify( 1 === count( $readings ) && '121' === $readings[0]['values']['systolic']['value'] && '79' === $readings[0]['values']['diastolic']['value'] && '64' === $readings[0]['values']['pulse']['value'], 'Calendar exposes only normalized private BP/pulse values to the owner.' );

    $payload_unit = 'kPa';
    $unsupported = Importer::import_page( $owner, $start, $today, 1 );
    $verify( is_wp_error( $unsupported ), 'Unsupported BP unit fails closed.' );
    $verify( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND provider = %s AND category = %s', $table, $owner, 'ihealth', 'blood_pressure' ) ), 'Unsupported-unit response does not add or alter a stored reading.' );

    wp_set_current_user( $users['other'] );
    $verify( null === Store::get_for_user( (int) $row->id, $users['other'] ), 'A different signed-in user cannot read the private BP record.' );
    WP_CLI::success( 'Synthetic iHealth BP import passed ' . $checks . ' assertions: OAuth scope/signing, bounded import, idempotence, mmHg/pulse mapping, source-field exclusion, owner-only calendar access and unknown-unit rejection.' );
} finally {
    remove_filter( 'pre_http_request', $mock, 10 );
    foreach ( $users as $id ) {
        Database::write( 'delete', Database::table( 'health_measurements' ), array( 'user_id' => $id ) );
        Database::write( 'delete', Database::table( 'health_report_members' ), array( 'user_id' => $id ) );
        Database::write( 'delete', Database::table( 'health_reports' ), array( 'user_id' => $id ) );
        delete_user_meta( $id, OAuth::CONNECTION_META );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $id );
    }
    if ( null === $old_application ) delete_option( OAuth::APP_OPTION );
    else update_option( OAuth::APP_OPTION, $old_application, false );
    wp_set_current_user( $previous_user );
}
