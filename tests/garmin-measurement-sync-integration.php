<?php
// Only synthetic HTTP and isolated test owners. Run against actual production staging.
use SwimLogEvaluation\Garmin_Measurement_Sync as Sync;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Database;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-sync.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin-measurements.php';
global $wpdb;
$previous = (int) get_current_user_id();
$original_post = $_POST;
$original_request = $_REQUEST;
$owner = 0;
$checks = 0;
$calls = array();
$scenario = 'normal';
$nonce_filter = null;
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $preempt, $args, $url ) use ( &$calls, &$scenario ) {
    $calls[] = $url;
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'synthetic.token.fixture' );
    if ( 'exception' === $scenario ) throw new RuntimeException( 'synthetic transport exception' );
    $day = substr( $url, -10 );
    $body = false !== strpos( $url, 'weight/all/' )
        ? wp_json_encode( array( array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T06:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ) ) )
        : wp_json_encode( array( 'calendarDate' => $day, 'averageSpO2' => 'correction' === $scenario ? 97 : 96 ) );
    return array( 'response' => array( 'code' => '429' === $scenario ? 429 : ( '500' === $scenario ? 500 : 200 ) ), 'headers' => array( 'retry-after' => '7200' ), 'body' => $body );
};
$run = static function () use ( &$owner ) {
    wp_clear_scheduled_hook( Sync::RETRY_HOOK, array( $owner ) );
    do_action( Sync::HOOK, $owner );
    return get_user_meta( $owner, Sync::STATE, true );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $created = wp_create_user( 'swimlog_health_schedule_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
    $verify( ! is_wp_error( $created ), 'Create schedule owner.' );
    $owner = (int) $created;
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
    wp_set_current_user( $owner );
    $verify( array() === Sync::categories( $owner ) && ! wp_next_scheduled( Sync::HOOK, array( $owner ) ), 'Default off.' );
    $verify( ! Sync::configure( array( 'blood_pressure' ) ) && ! Sync::configure( array( array( 'weight' ) ) ), 'Reject unsupported/nested categories.' );
    $verify( ! is_wp_error( API::save_key( $owner, 'synthetic-only-schedule-key' ) ), 'Configure synthetic key.' );
    $verify( Sync::configure( array( 'weight', 'body_composition', 'spo2' ) ), 'Owner opts in.' );
    $verify( (bool) wp_next_scheduled( Sync::HOOK, array( $owner ) ), 'Hourly event scheduled.' );
    $verify( ! get_user_meta( $owner, 'swimlog_garmin_sync_enabled', true ), 'Activity sync remains independent.' );
    wp_set_current_user( 0 );
    $before = count( $calls );
    if ( ! wp_doing_cron() ) {
        Sync::run( $owner );
        $verify( $before === count( $calls ), 'Callback denied outside cron.' );
    }
    if ( ! defined( 'DOING_CRON' ) ) define( 'DOING_CRON', true );
    $state = $run();
    $today = wp_date( 'Y-m-d', null, wp_timezone() );
    $verify( 0 === get_current_user_id(), 'Anonymous cron identity restored.' );
    $verify( 6 === count( $state['pending'] ) && 3 === count( $state['results'] ), 'One day all selected categories; six remain.' );
    $verify( $today === reset( $state['results'] )['date'], 'Latest day first.' );
    $verify( 3 === count( $calls ), 'Token plus shared weight/BMI and SpO2 request.' );
    $verify( (bool) wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) ), 'Continuation scheduled.' );
    for ( $i = 0; $i < 6; ++$i ) $state = $run();
    $verify( 'complete' === $state['status'] && 21 === count( $state['results'] ) && ! $state['pending'], 'Entire seven-day window completes.' );
    $verify( ! wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) ), 'Completed window has no orphan continuation.' );
    $count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) );
    $verify( 14 === $count, 'Two distinct measurement categories per day, no fabricated SpO2 samples.' );
    $state = $run();
    $verify( 1 === $state['results'][$today . ':weight']['skipped'] && 0 === $state['results'][$today . ':weight']['added'], 'Hourly overlap skips duplicate weight.' );
    $verify( $count === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) ), 'No duplicate rows on overlap.' );

    // Force a fresh window and an older fetch audit for a legitimate provider summary update.
    Sync::clear( $owner );
    $wpdb->query( $wpdb->prepare( 'UPDATE %i SET fetched_at_utc = %s WHERE user_id = %d', Database::table( 'health_reports' ), '2020-01-01 00:00:00', $owner ) );
    $scenario = 'correction';
    $state = $run();
    $verify( 1 === $state['results'][$today . ':spo2']['reports_updated'], 'Provider report correction updates one existing report.' );

    Sync::clear( $owner );
    $scenario = '500';
    $state = $run();
    $verify( 'retrying' === $state['status'] && 7 === count( $state['pending'] ) && 1 === $state['retries'], 'Failure retains day for bounded retry.' );
    $state = $run(); $state = $run();
    $verify( 6 === count( $state['pending'] ) && 'failed' === $state['results'][$today . ':spo2']['status'], 'After three attempts move onward and retain failure.' );
    Sync::clear( $owner );
    $scenario = '429';
    $state = $run();
    $next = wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) );
    $verify( $next >= time() + 7195, 'Retry respects two-hour Garmin wait.' );
    $before = count( $calls );
    $state = $run();
    $verify( $before === count( $calls ), 'Cooldown performs no HTTP.' );
    delete_transient( 'swimlog_health_retry_' . $owner );

    // A second connection owns the job lock: current worker must not consume the cursor.
    $other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $lock = 'swimlog_health_job_' . md5( $wpdb->prefix . ':' . $owner );
    $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
    $snapshot = get_user_meta( $owner, Sync::STATE, true );
    $before = count( $calls );
    Sync::run( $owner );
    $verify( $snapshot === get_user_meta( $owner, Sync::STATE, true ) && $before === count( $calls ), 'Concurrent worker cannot duplicate job consumption.' );
    $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); $other->close();

    $scenario = 'exception';
    wp_set_current_user( $previous );
    $caught = false;
    try { $run(); } catch ( RuntimeException $error ) { $caught = 'synthetic transport exception' === $error->getMessage(); }
    $verify( $caught && $previous === get_current_user_id(), 'Exceptions restore prior authenticated identity and release lock.' );

    $scenario = 'normal';
    wp_set_current_user( $owner );
    $verify( Sync::configure( array( 'spo2' ) ), 'Change selected categories resets cursor.' );
    wp_set_current_user( 0 );
    $state = $run();
    $verify( 1 === count( $state['results'] ) && array( 'spo2' ) === $state['categories'], 'Only selected category imported.' );
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts', false );
    $before = count( $calls );
    Sync::run( $owner );
    $verify( $before === count( $calls ) && ! wp_next_scheduled( Sync::HOOK, array( $owner ) ) && ! wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) ), 'Capability revocation cancels all tasks before HTTP.' );
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
    wp_set_current_user( $owner );
    Sync::configure( array( 'weight' ) );
    delete_user_meta( $owner, API::META_KEY );
    wp_set_current_user( 0 ); Sync::run( $owner );
    $verify( $before === count( $calls ) && ! wp_next_scheduled( Sync::HOOK, array( $owner ) ), 'Missing key cancels task without HTTP.' );
    API::save_key( $owner, 'synthetic-only-schedule-key' );

    // Actual nonce-protected controls: successful configuration, invalid nonce and disabling.
    wp_set_current_user( $owner );
    $_POST = array( 'swimlog_measurement_schedule' => '1', '_wpnonce' => wp_create_nonce( 'swimlog_measurement_schedule' ), 'scheduled_categories' => array( 'weight' ) );
    $_REQUEST = $_POST;
    ob_start(); SwimLogEvaluation\Admin_Garmin_Measurements::render(); $html = ob_get_clean();
    $verify( array( 'weight' ) === Sync::categories( $owner ) && false !== strpos( $html, 'Measurement schedule saved.' ), 'Actual settings form saves opt-in.' );
    $verify( false === strpos( $html, 'synthetic-only-schedule-key' ) && false === strpos( $html, '82.5' ), 'Schedule UI exposes no payload/key.' );
    $nonce_filter = static function () { return static function () { throw new RuntimeException( 'nonce denied' ); }; };
    add_filter( 'wp_die_handler', $nonce_filter ); add_filter( 'wp_die_ajax_handler', $nonce_filter );
    $_POST['_wpnonce'] = 'bad'; $_POST['scheduled_categories'] = array( 'spo2' ); $_REQUEST = $_POST;
    $denied = false; ob_start();
    try { SwimLogEvaluation\Admin_Garmin_Measurements::render(); } catch ( RuntimeException $error ) { $denied = 'nonce denied' === $error->getMessage(); } finally { ob_end_clean(); }
    $verify( $denied && array( 'weight' ) === Sync::categories( $owner ), 'Invalid nonce cannot alter schedule.' );
    $_POST = array(); $_REQUEST = array();
    $verify( Sync::configure( array() ) && ! Sync::categories( $owner ) && ! wp_next_scheduled( Sync::HOOK, array( $owner ) ) && ! wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) ), 'Opt-out removes both job types.' );
    $verify( $count === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) ), 'Disabling preserves all existing observations.' );
    WP_CLI::success( 'Scheduled Garmin measurements passed ' . $checks . ' assertions: opt-in, owner cron context, seven-day overlap, duplicates/corrections, retries, concurrency, privacy, nonce and cancellation.' );
} finally {
    if ( $nonce_filter ) { remove_filter( 'wp_die_handler', $nonce_filter ); remove_filter( 'wp_die_ajax_handler', $nonce_filter ); }
    remove_filter( 'pre_http_request', $http, 10 );
    $_POST = $original_post; $_REQUEST = $original_request;
    if ( $owner ) {
        Sync::clear( $owner );
        delete_transient( 'swimlog_health_retry_' . $owner );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $owner ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $owner );
    }
    wp_set_current_user( $previous );
}
