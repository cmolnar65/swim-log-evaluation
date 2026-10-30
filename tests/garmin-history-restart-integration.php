<?php
// Two distinct wp eval processes prove persistence beyond PHP static/request state.
use SwimLogEvaluation\Garmin_History_Jobs as Jobs;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Garmin_Measurement_Import as Importer;
use SwimLogEvaluation\Database;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history-jobs.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
global $wpdb;
$previous = get_current_user_id(); $owner = 0; $retain = false;
$fixture_key = 'swimlog_h8_restart_fixture';
$http = static function ( $pre, $args, $url ) {
    if ( 0 !== strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected restart HTTP.' );
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => false !== strpos( $url, 'personal-api-key/token' ) ? 'synthetic.restart.token' : '[{"calendarDate":"2018-04-01","timestampGMT":"2018-04-01T06:00:00Z","weight":82.5}]' );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    if ( 'setup' === SWIMLOG_H8_PHASE ) {
        if ( get_option( $fixture_key ) ) throw new RuntimeException( 'Unexpected leftover restart fixture.' );
        $created = wp_create_user( 'swimlog_restart_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        if ( is_wp_error( $created ) ) throw new RuntimeException( 'Create restart owner failed.' );
        $owner = (int) $created; ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
        wp_set_current_user( $owner ); API::save_key( $owner, 'synthetic-restart-key' );
        $job = Jobs::start( '2018-04-01', '2018-04-01', array( 'weight' ) );
        if ( is_wp_error( $job ) ) throw new RuntimeException( 'Create restart job failed.' );
        $imported = Importer::run( $owner, '2018-04-01', '2018-04-01', array( 'weight' ) );
        if ( 1 !== $imported[0]['added'] ) throw new RuntimeException( 'Prepare interrupted committed measurement.' );
        $unit = Jobs::units( $job['id'] )['items'][0];
        $unit['status'] = 'working'; $unit['attempts'] = 1;
        $job['status'] = 'running'; $job['statuses'][$unit['key']] = 'working';
        Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s', $wpdb->options, wp_json_encode( $unit ), 'swimlog_hu_' . $owner . '_' . $job['id'] . '_' . $unit['key'] ) );
        Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s', $wpdb->options, wp_json_encode( $job ), 'swimlog_hj_' . $owner . '_' . $job['id'] ) );
        add_option( $fixture_key, array( 'owner' => $owner, 'job' => $job['id'] ), '', 'no' );
        $retain = true;
        WP_CLI::success( 'Restart fixture persisted a committed import with an unfinished ledger unit for a separate PHP process.' );
    } elseif ( 'verify' === SWIMLOG_H8_PHASE ) {
        $fixture = get_option( $fixture_key ); $owner = (int) ( $fixture['owner'] ?? 0 ); $id = $fixture['job'] ?? '';
        if ( ! $owner ) throw new RuntimeException( 'Restart fixture missing.' );
        wp_set_current_user( $owner ); $before = Jobs::get( $id );
        if ( 'running' !== $before['status'] || 1 !== count( $before['ready'] ) ) throw new RuntimeException( 'Persisted cursor was not recovered.' );
        if ( ! defined( 'DOING_CRON' ) ) define( 'DOING_CRON', true );
        wp_clear_scheduled_hook( Jobs::HOOK, array( $owner, $id ) ); wp_set_current_user( 0 );
        do_action( Jobs::HOOK, $owner, $id );
        if ( get_current_user_id() ) throw new RuntimeException( 'Restart worker identity leaked.' );
        wp_set_current_user( $owner ); $job = Jobs::get( $id );
        $count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) );
        if ( 'completed' !== $job['status'] || $job['ready'] || 1 !== $count || 1 !== $job['counts']['skipped'] || ( $job['counts']['added'] ?? 0 ) || Jobs::next( $id ) ) throw new RuntimeException( 'Fresh-process replay duplicated, recounted, or lost work.' );
        WP_CLI::success( 'Historical restart recovery passed: fresh process, unfinished cursor, already committed source, exactly one observation, recovered duplicate count, identity restoration and task cleanup.' );
    } else throw new RuntimeException( 'Unknown restart phase.' );
} finally {
    remove_filter( 'pre_http_request', $http, 10 );
    if ( ! $retain ) {
        if ( $owner ) {
            Jobs::delete_owner( $owner );
            foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $table ) Database::write( 'delete', Database::table( $table ), array( 'user_id' => $owner ) );
            require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $owner );
        }
        delete_option( $fixture_key );
    }
    wp_set_current_user( $previous );
}
