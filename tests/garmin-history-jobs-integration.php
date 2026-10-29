<?php
// Synthetic job/HTTP integration against actual staged WordPress and MariaDB.
use SwimLogEvaluation\Garmin_History_Jobs as Jobs;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Admin_Garmin_History as UI;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history-jobs.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin-history.php';
global $wpdb;
$previous = get_current_user_id(); $post = $_POST; $request = $_REQUEST; $get = $_GET;
$owner = 0; $other = 0; $checks = 0; $calls = 0; $mode = 'normal'; $active_id = ''; $nonce_filter = null; $schedule_filter = null; $peer = null; $peer_name = '';
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $pre, $args, $url ) use ( &$calls, &$mode, &$active_id ) {
    ++$calls;
    if ( 0 !== strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected history job HTTP.' );
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'synthetic.jobs.token' );
    if ( 'pause' === $mode ) { Jobs::control( $active_id, 'pause' ); $mode = 'normal'; }
    if ( 'cancel' === $mode ) { Jobs::control( $active_id, 'cancel' ); $mode = 'normal'; }
    $code = in_array( $mode, array( '401', '429', '500' ), true ) ? (int) $mode : 200;
    if ( false !== strpos( $url, 'search/activities' ) ) {
        parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
        $rows = (int) $q['start'] > 0 ? array() : array( array( 'activityId' => '2018-01-01' === $q['startDate'] ? 12345 : 12346, 'startTimeLocal' => $q['startDate'] . ' 06:00:00', 'activityType' => array( 'typeKey' => 'lap_swimming' ) ) );
        $body = wp_json_encode( $rows );
    } else {
        $day = substr( $url, -10 );
        $body = false !== strpos( $url, 'weight/all/' ) ? wp_json_encode( array( array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T06:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ) ) ) : wp_json_encode( array( 'calendarDate' => $day, 'averageSpO2' => 96.4 ) );
        if ( 'partial' === $mode && false !== strpos( $url, 'spo2acclimation' ) ) $code = 500;
        if ( 'empty' === $mode ) $body = false !== strpos( $url, 'weight/all/' ) ? '[]' : '{}';
    }
    return array( 'response' => array( 'code' => $code ), 'headers' => array( 'retry-after' => '120' ), 'body' => $body );
};
$run = static function ( $id ) use ( &$owner ) {
    wp_clear_scheduled_hook( Jobs::HOOK, array( $owner, $id ) );
    wp_set_current_user( 0 );
    do_action( Jobs::HOOK, $owner, $id );
    if ( 0 !== get_current_user_id() ) throw new RuntimeException( 'Worker leaked owner identity.' );
    wp_set_current_user( $owner );
    return Jobs::get( $id );
};
$expire = static function ( $id ) use ( &$owner, $wpdb ) {
    $job = Jobs::get( $id ); $job['not_before'] = 0;
    Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s', $wpdb->options, wp_json_encode( $job ), 'swimlog_hj_' . $owner . '_' . $id ) );
    delete_transient( 'swimlog_health_retry_' . $owner );
};
$drain = static function ( $id ) use ( $run ) {
    for ( $i = 0; $i < 25; ++$i ) {
        $job = Jobs::get( $id );
        if ( ! in_array( $job['status'], array( 'queued', 'running' ), true ) ) return $job;
        $job = $run( $id );
    }
    throw new RuntimeException( 'Job did not finish bounded synthetic range.' );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $created = wp_create_user( 'swimlog_jobs_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $created ), 'Create history job user.' );
        if ( 'owner' === $name ) $owner = (int) $created; else $other = (int) $created;
        $user = new WP_User( $created ); $user->add_cap( 'swimlog_manage_own_workouts' ); $user->add_cap( 'swimlog_upload_workouts' );
    }
    wp_set_current_user( $owner ); API::save_key( $owner, 'synthetic-jobs-key' );
    $verify( is_wp_error( Jobs::start( '2018-02-30', '2018-03-01', array( 'weight' ) ) ) && is_wp_error( Jobs::start( '2018-01-01', '2018-12-31', array( array( 'weight' ) ) ) ), 'Reject invalid range/nested categories.' );
    $job = Jobs::start( '2018-01-01', '2018-12-31', array( 'weight' ) ); $id = $job['id'];
    $verify( 'queued' === $job['status'] && (bool) Jobs::next( $id ), 'Full-year configuration persisted and scheduled.' );
    $verify( is_wp_error( Jobs::start( '2018-01-01', '2018-01-01', array( 'weight' ) ) ), 'One unfinished job enforced.' );
    $verify( ! get_user_meta( $owner, 'swimlog_garmin_sync_enabled', true ) && ! get_user_meta( $owner, 'swimlog_garmin_measurement_categories', true ), 'Backfill never enables regular sync.' );
    $verify( Jobs::control( $id, 'pause' )['status'] === 'paused' && ! Jobs::next( $id ), 'Pause cancels future tasks.' );
    if ( ! defined( 'DOING_CRON' ) ) define( 'DOING_CRON', true );
    $before = $calls; $paused = $run( $id );
    $verify( $before === $calls && 'paused' === $paused['status'], 'Paused worker makes no requests.' );
    $verify( 'queued' === Jobs::control( $id, 'resume' )['status'], 'Resume restores scheduling.' );
    $verify( 'cancelled' === Jobs::control( $id, 'cancel' )['status'] && is_wp_error( Jobs::control( $id, 'resume' ) ), 'Cancel terminal; cannot resurrect.' );

    foreach ( array( 12345, 12346 ) as $activity ) Database::write( 'insert', Database::table( 'imports' ), array( 'user_id' => $owner, 'source_type' => 'fit', 'source_provider' => 'garmin', 'external_activity_id' => (string) $activity, 'original_filename' => 'synthetic.fit', 'file_hash' => hash( 'sha256', (string) $activity ), 'import_status' => 'pending', 'imported_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
    $job = Jobs::start( '2018-01-01', '2018-01-02', array( 'lap_swimming', 'weight', 'body_composition', 'spo2' ) ); $id = $job['id']; $active_id = $id;
    wp_set_current_user( $other );
    $verify( null === Jobs::get( $id ) && is_wp_error( Jobs::control( $id, 'cancel' ) ) && ! Jobs::units( $id ), 'Foreign owner cannot view/control results.' );
    wp_set_current_user( $owner );
    $job = $drain( $id );
    $verify( 'completed' === $job['status'] && ! $job['ready'] && ! $job['failed'] && ! Jobs::next( $id ), 'Discovery, import queue and measurement chain complete durably.' );
    $verify( 2 === $job['counts']['discovered'] && 2 === $job['counts']['activity_days'] && 2 === $job['counts']['measurement_days'], 'Distinct discovery/day counts.' );
    $verify( 2 === $job['counts']['activity_review'] && 4 === $job['counts']['added'] && 6 === $job['counts']['reports_added'], 'Pending activities stay review; distinct report/reading counts.' );
    $review = Jobs::units( $id, 0, 'review' ); $verify( 2 === $review['total'], 'Review filtering returns both pending activities.' );
    $json = wp_json_encode( array( $job, Jobs::units( $id ) ) );
    $verify( false === strpos( $json, '82.5' ) && false === strpos( $json, 'synthetic-jobs-key' ) && false === strpos( $json, 'synthetic.jobs.token' ), 'Ledger contains no values/payload/credentials.' );
    $autoload = $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM %i WHERE option_name = %s', $wpdb->options, 'swimlog_hj_' . $owner . '_' . $id ) );
    $verify( 'no' === $autoload && '1.3' === get_option( 'swimlog_db_version' ), 'Private ledger non-autoloaded; schema unchanged.' );
    ob_start(); UI::status( $job, 0, 'review' ); $html = ob_get_clean();
    $verify( false !== strpos( $html, 'Review pending import' ) && false !== strpos( $html, 'garmin_activity_id' ) && false === strpos( $html, '82.5' ), 'Authorized review links rendered without raw values.' );

    $mode = 'partial';
    $job = Jobs::start( '2018-01-03', '2018-01-03', array( 'weight', 'body_composition', 'spo2' ) ); $id = $job['id'];
    $job = $run( $id );
    $verify( 'waiting' === $job['status'] && 2 === $job['counts']['added'], 'Partial success retained while failed category waits.' );
    $before = $calls; $same = $run( $id );
    $verify( $before === $calls && $same['counts'] === $job['counts'], 'Early wake-up neither requests nor recounts.' );
    $mode = 'normal'; $expire( $id ); $job = $run( $id );
    $verify( 'completed' === $job['status'] && 2 === $job['counts']['added'] && 0 === ( $job['counts']['skipped'] ?? 0 ) && 3 === $job['counts']['reports_added'], 'Only failed category retried; completed counts preserved.' );

    $mode = '500'; $job = Jobs::start( '2018-01-04', '2018-01-04', array( 'weight' ) ); $id = $job['id'];
    for ( $i = 0; $i < 3; ++$i ) { $expire( $id ); $job = $run( $id ); }
    $verify( 'completed_with_errors' === $job['status'] && 1 === count( $job['failed'] ) && 1 === Jobs::units( $id, 0, 'failed' )['total'], 'Three failed attempts finish explicitly incomplete.' );
    $mode = 'normal'; $verify( 'queued' === Jobs::control( $id, 'retry' )['status'], 'Explicit failed-only retry requeues ledger.' );
    $job = $run( $id );
    $verify( 'completed' === $job['status'] && ! $job['failed'] && 1 === $job['counts']['measurement_days'] && 1 === $job['counts']['added'], 'Failed-only retry resolves once without double day/import counts.' );

    $mode = '401'; $job = Jobs::start( '2018-01-05', '2018-01-05', array( 'weight' ) ); $id = $job['id'];
    $job = $run( $id );
    $verify( 'blocked' === $job['status'] && 1 === count( $job['ready'] ) && ! Jobs::next( $id ), 'Authorization failure blocks without dropping progress.' );
    $mode = 'empty'; Jobs::control( $id, 'resume' ); $job = $run( $id );
    $verify( 'completed' === $job['status'] && 1 === $job['counts']['measurement_no_data'], 'Resume after connection recovery distinguishes empty response.' );

    $mode = '429'; $job = Jobs::start( '2018-01-06', '2018-01-06', array( 'spo2' ) ); $id = $job['id']; $job = $run( $id );
    $verify( 'waiting' === $job['status'] && $job['not_before'] >= time() + 119, 'Rate limit persisted as wait.' );
    $before = $calls; $run( $id ); $verify( $before === $calls, 'Known cooldown makes no HTTP calls.' );
    $mode = 'normal'; $expire( $id ); $job = $run( $id );
    $verify( 'completed' === $job['status'], 'Rate-limited job recovers.' );

    $mode = 'pause'; $job = Jobs::start( '2018-01-07', '2018-01-07', array( 'weight' ) ); $id = $job['id']; $active_id = $id;
    $job = $run( $id );
    $verify( 'paused' === $job['status'] && 1 === $job['counts']['added'] && ! Jobs::next( $id ), 'Pause during HTTP preserves outcome without resurrection.' );
    Jobs::control( $id, 'resume' ); $job = $run( $id );
    $verify( 'completed' === $job['status'], 'Paused final unit can resume to completion.' );

    $mode = 'cancel'; $job = Jobs::start( '2018-01-08', '2018-01-08', array( 'weight' ) ); $id = $job['id']; $active_id = $id;
    $job = $run( $id );
    $verify( 'cancelled' === $job['status'] && 1 === $job['counts']['added'] && ! Jobs::next( $id ), 'Cancel during HTTP records completed import but schedules nothing.' );
    $preserved = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND calendar_date = %s', Database::table( 'health_measurements' ), $owner, '2018-01-08' ) );
    $verify( 1 === $preserved, 'Cancellation retains actual measurements.' );

    $mode = 'normal'; $job = Jobs::start( '2018-01-09', '2018-01-09', array( 'weight' ) ); $id = $job['id'];
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts', false );
    $before = $calls; wp_set_current_user( 0 ); wp_clear_scheduled_hook( Jobs::HOOK, array( $owner, $id ) ); do_action( Jobs::HOOK, $owner, $id );
    ( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' ); wp_set_current_user( $owner );
    $job = Jobs::get( $id ); $verify( 'blocked' === $job['status'] && $before === $calls, 'Revoked owner capability blocks before HTTP.' );
    Jobs::control( $id, 'resume' );
    $peer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $peer_name = 'swimlog_h_worker_' . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner ), 0, 40 );
    $peer->get_var( $peer->prepare( 'SELECT GET_LOCK(%s, 0)', $peer_name ) );
    $before = $calls; $run( $id );
    $verify( $before === $calls && (bool) Jobs::next( $id ), 'Competing worker consumes nothing and retains a wake-up.' );
    $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_name ) ); $peer->close(); $peer = null;
    $job = $run( $id ); $verify( 'completed' === $job['status'], 'Worker lock released and job finishes.' );

    // Real form submission, bad nonce, stale/foreign job references.
    $_POST = array( 'swimlog_history_action' => 'start', '_wpnonce' => wp_create_nonce( 'swimlog_history_control' ), 'history_start' => '2018-01-10', 'history_end' => '2018-01-10', 'history_categories' => array( 'weight' ) ); $_REQUEST = $_POST; $_GET = array();
    ob_start(); UI::render(); $html = ob_get_clean();
    $job = Jobs::get(); $id = $job['id'];
    $verify( 'queued' === $job['status'] && false !== strpos( $html, 'Cancel — keep imported records' ), 'Actual nonce-protected start form creates queued job and controls.' );
    WP_CLI::log( 'SWIMLOG_HISTORY_HTML_BASE64=' . base64_encode( $html ) );
    $nonce_filter = static function () { return static function () { throw new RuntimeException( 'expected nonce rejection' ); }; };
    add_filter( 'wp_die_handler', $nonce_filter ); add_filter( 'wp_die_ajax_handler', $nonce_filter );
    $_POST = array( 'swimlog_history_action' => 'cancel', 'history_job' => $id, '_wpnonce' => 'invalid' ); $_REQUEST = $_POST;
    $denied = false; ob_start(); try { UI::render(); } catch ( RuntimeException $error ) { $denied = 'expected nonce rejection' === $error->getMessage(); } finally { ob_end_clean(); }
    $verify( $denied && 'queued' === Jobs::get( $id )['status'], 'Invalid form nonce cannot cancel job.' );

    if ( ! defined( 'DOING_AJAX' ) ) define( 'DOING_AJAX', true );
    $ajax = static function () {
        ob_start(); try { UI::ajax_status(); } catch ( RuntimeException $error ) { if ( 'expected nonce rejection' !== $error->getMessage() ) throw $error; }
        return ob_get_clean();
    };
    $_POST = array( 'job' => $id, 'nonce' => wp_create_nonce( 'swimlog_history_status' ) ); $_REQUEST = $_POST;
    $answer = json_decode( $ajax(), true );
    $verify( ! empty( $answer['success'] ) && false !== strpos( $answer['data']['html'], $id ) && false === strpos( $answer['data']['html'], '82.5' ), 'Actual AJAX status accepts owner nonce and returns safe HTML.' );
    ( new WP_User( $other ) )->add_cap( 'swimlog_manage_all_users' ); wp_set_current_user( 0 ); wp_set_current_user( $other );
    $_POST['nonce'] = wp_create_nonce( 'swimlog_history_status' ); $_REQUEST = $_POST;
    $answer = json_decode( $ajax(), true );
    $verify( is_array( $answer ) && false === $answer['success'] && empty( $answer['data'] ), 'Actual AJAX denies foreign job even with manage-all capability.' );
    wp_set_current_user( $owner ); $_POST['nonce'] = 'invalid'; $_REQUEST = $_POST;
    $answer = $ajax();
    $verify( false === strpos( $answer, $id ), 'Invalid status nonce discloses no job data.' );
    $_POST = array(); $_REQUEST = array();
    Jobs::control( $id, 'cancel' );

    $schedule_filter = static function ( $pre, $event ) { return Jobs::HOOK === $event->hook ? new WP_Error( 'fixture_schedule', 'synthetic schedule failure' ) : $pre; };
    add_filter( 'pre_schedule_event', $schedule_filter, 10, 2 );
    $job = Jobs::start( '2018-01-11', '2018-01-11', array( 'weight' ) ); $id = $job['id'];
    $verify( 'blocked' === Jobs::get( $id )['status'] && ! Jobs::next( $id ), 'Scheduling failure is persisted and actionable.' );
    remove_filter( 'pre_schedule_event', $schedule_filter, 10 ); $schedule_filter = null;
    Jobs::control( $id, 'resume' ); $job = $run( $id ); $verify( 'completed' === $job['status'], 'Schedule repair resumes durable queue.' );

    $job = Jobs::start( '2018-01-12', '2018-01-12', array( 'weight' ) ); $id = $job['id'];
    $write_count = 0;
    $failure_filter = static function ( $sql ) use ( &$write_count, $owner, $id ) {
        if ( 0 === stripos( ltrim( $sql ), 'INSERT INTO' ) && false !== strpos( $sql, 'swimlog_hj_' . $owner . '_' . $id ) ) {
            ++$write_count;
            if ( 2 === $write_count ) throw new RuntimeException( 'synthetic ledger write failure' );
        }
        return $sql;
    };
    add_filter( 'query', $failure_filter, 20 );
    try { $job = $run( $id ); } finally { remove_filter( 'query', $failure_filter, 20 ); }
    $verify( 'blocked' === $job['status'] && 0 === ( $job['counts']['added'] ?? 0 ) && ! Jobs::units( $id )['items'][0]['outcomes'], 'Failed ledger commit rolls back counts and unit outcome atomically.' );
    $actual = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND calendar_date = %s', Database::table( 'health_measurements' ), $owner, '2018-01-12' ) );
    $verify( 1 === $actual, 'Already committed import survives ledger failure.' );
    Jobs::control( $id, 'resume' ); $job = $run( $id );
    $verify( 'completed' === $job['status'] && 1 === $job['counts']['skipped'] && 0 === ( $job['counts']['added'] ?? 0 ), 'At-least-once recovery replays safely and records recovered duplicate once.' );

    $verify( count( Jobs::recent() ) <= 10 && count( Jobs::recent() ) > 1, 'Recent job history retained and bounded.' );
    WP_CLI::success( 'Historical job workflow passed ' . $checks . ' assertions: persistence, queue/retries/counts, owner isolation, auth/cooldowns, controls during HTTP, review links, scheduling recovery and nonce-protected UI.' );
} finally {
    if ( $nonce_filter ) { remove_filter( 'wp_die_handler', $nonce_filter ); remove_filter( 'wp_die_ajax_handler', $nonce_filter ); }
    if ( $schedule_filter ) remove_filter( 'pre_schedule_event', $schedule_filter, 10 );
    if ( $peer ) { $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_name ) ); $peer->close(); }
    remove_filter( 'pre_http_request', $http, 10 );
    $_POST = $post; $_REQUEST = $request; $_GET = $get;
    foreach ( array( $owner, $other ) as $id ) if ( $id ) {
        Jobs::delete_owner( $id );
        delete_transient( 'swimlog_health_retry_' . $id ); delete_transient( 'swimlog_activity_retry_' . $id );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports', 'imports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
    }
    wp_set_current_user( $previous );
}
