<?php
// Synthetic full-range persistent jobs, overlapping sources, and recovery coverage.
use SwimLogEvaluation\Garmin_History_Jobs as Jobs;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Garmin_Measurement_Import as Importer;
use SwimLogEvaluation\Database;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history-jobs.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
global $wpdb;
$previous = get_current_user_id(); $owner = 0; $checks = 0; $mode = 'year'; $requests = array(); $peer = null; $peer_name = '';
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $pre, $args, $url ) use ( &$mode, &$requests ) {
    if ( 0 !== strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected extended history request.' );
    $requests[] = $url; $code = 200;
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) $body = 'synthetic.extended.token';
    elseif ( false !== strpos( $url, 'search/activities' ) ) {
        parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
        $day = $q['startDate']; $offset = (int) $q['start']; $rows = array();
        if ( 'gap' === $mode && '2018-01-01' === $day && $offset >= 100 ) { $code = 500; }
        elseif ( '2018-01-01' === $day ) {
            if ( 0 === $offset ) {
                for ( $i = 1; $i <= 100; ++$i ) $rows[] = array( 'activityId' => 1000 + $i, 'startTimeLocal' => $day . ' 00:00:00', 'activityType' => array( 'typeKey' => 'running' ) );
            } elseif ( 100 === $offset ) {
                foreach ( array( 2001, 2002 ) as $id ) $rows[] = array( 'activityId' => $id, 'startTimeLocal' => $day . ' 12:00:00', 'activityType' => array( 'typeKey' => 'lap_swimming' ) );
            }
        } elseif ( '2018-12-31' === $day && 0 === $offset ) $rows[] = array( 'activityId' => 2003, 'startTimeLocal' => $day . ' 23:59:59', 'activityType' => array( 'typeKey' => 'lap_swimming' ) );
        $body = wp_json_encode( $rows );
    } else {
        $day = substr( $url, -10 );
        $body = false !== strpos( $url, 'weight/all/' )
            ? wp_json_encode( array( array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T06:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ), array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T07:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ) ) )
            : wp_json_encode( array( 'calendarDate' => $day, 'averageSpO2' => 96.4 ) );
        if ( 'year' === $mode && ! in_array( $day, array( '2018-01-01', '2018-12-31' ), true ) ) $body = false !== strpos( $url, 'weight/all/' ) ? '[]' : '{}';
    }
    return array( 'response' => array( 'code' => $code ), 'headers' => array(), 'body' => $body );
};
$run = static function ( $id ) use ( &$owner ) {
    wp_clear_scheduled_hook( Jobs::HOOK, array( $owner, $id ) ); wp_set_current_user( 0 );
    do_action( Jobs::HOOK, $owner, $id );
    if ( get_current_user_id() ) throw new RuntimeException( 'Extended worker leaked identity.' );
    wp_set_current_user( $owner ); return Jobs::get( $id );
};
$expire = static function ( $id ) use ( &$owner, $wpdb ) {
    $job = Jobs::get( $id ); $job['not_before'] = 0;
    Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s', $wpdb->options, wp_json_encode( $job ), 'swimlog_hj_' . $owner . '_' . $id ) );
};
$drain = static function ( $id, $limit = 800 ) use ( $run ) {
    for ( $i = 0; $i < $limit; ++$i ) {
        $job = Jobs::get( $id );
        if ( in_array( $job['status'], array( 'completed', 'completed_with_errors', 'blocked', 'cancelled', 'paused', 'waiting' ), true ) ) return $job;
        $run( $id );
    }
    throw new RuntimeException( 'Persistent job did not complete bounded full-year fixture.' );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $created = wp_create_user( 'swimlog_extended_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
    $verify( ! is_wp_error( $created ), 'Create extended owner.' ); $owner = (int) $created;
    $user = new WP_User( $owner ); $user->add_cap( 'swimlog_manage_own_workouts' ); $user->add_cap( 'swimlog_upload_workouts' );
    wp_set_current_user( $owner ); API::save_key( $owner, 'synthetic-extended-key' );
    if ( ! defined( 'DOING_CRON' ) ) define( 'DOING_CRON', true );
    foreach ( array( 2001, 2002, 2003 ) as $id ) Database::write( 'insert', Database::table( 'imports' ), array( 'user_id' => $owner, 'source_type' => 'fit', 'source_provider' => 'garmin', 'external_activity_id' => (string) $id, 'original_filename' => 'synthetic.fit', 'file_hash' => hash( 'sha256', 'extended-' . $id ), 'import_status' => 'pending', 'imported_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
    $job = Jobs::start( '2018-01-01', '2018-12-31', array( 'lap_swimming', 'weight', 'body_composition', 'spo2' ) ); $id = $job['id'];
    $job = $drain( $id );
    $verify( 'completed' === $job['status'] && ! $job['ready'] && ! $job['failed'], 'Complete persistent full-year job, not only pure date traversal.' );
    $verify( 365 === $job['counts']['activity_days'] && 365 === $job['counts']['measurement_days'], 'Every inclusive 2018 day covered in both pipelines.' );
    $verify( 3 === $job['counts']['discovered'] && 3 === $job['counts']['activity_review'], 'Busy non-swim page and both year boundaries retain supported swims.' );
    $verify( 8 === $job['counts']['added'] && 1089 === $job['counts']['measurement_no_data'], 'Two/day weight and BMI at both boundaries; 363 empty days times three categories.' );
    $verify( count( array_filter( $requests, static function ( $url ) { return false !== strpos( $url, 'startDate=2018-01-01' ) && false !== strpos( $url, 'start=100' ); } ) ) === 1, 'Raw pagination passes first hundred non-swims.' );
    $verify( Jobs::units( $id, 0 )['total'] > 700 && 20 === count( Jobs::units( $id, 0 )['items'] ) && 20 === count( Jobs::units( $id, 20 )['items'] ), 'Long ledger is retained and paged without truncating completion.' );
    $summary = $job['counts']; $before = count( $requests ); $run( $id );
    $verify( Jobs::get( $id )['counts'] === $summary && $before === count( $requests ), 'Completed job replay cannot recount or refetch.' );
    $rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) );
    $verify( 8 === $rows, 'Full-year records preserve distinct same-value readings without invented zeros.' );

    $mode = 'normal';
    $manual = Importer::run( $owner, '2018-01-01', '2018-01-01', array( 'weight', 'body_composition', 'spo2' ) );
    $verify( 2 === $manual[0]['skipped'] && 2 === $manual[1]['skipped'], 'Manual import overlaps historical readings safely.' );
    require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-sync.php';
    SwimLogEvaluation\Garmin_Measurement_Sync::configure( array( 'weight', 'spo2' ) );
    update_user_meta( $owner, SwimLogEvaluation\Garmin_Measurement_Sync::STATE, array( 'categories' => array( 'weight', 'spo2' ), 'pending' => array( '2018-01-01' ), 'results' => array(), 'retries' => 0 ) );
    wp_set_current_user( 0 ); do_action( SwimLogEvaluation\Garmin_Measurement_Sync::HOOK, $owner ); wp_set_current_user( $owner );
    $verify( $rows === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Database::table( 'health_measurements' ), $owner ) ), 'Regular scheduled sync overlaps manual and historical import without duplicate rows.' );
    SwimLogEvaluation\Garmin_Measurement_Sync::configure( array() );

    $mode = 'gap'; $job = Jobs::start( '2018-01-01', '2018-01-02', array( 'lap_swimming' ) ); $id = $job['id'];
    $run( $id );
    for ( $i = 0; $i < 3; ++$i ) { $expire( $id ); $job = $run( $id ); }
    $job = $drain( $id );
    $verify( 'completed_with_errors' === $job['status'] && 1 === count( $job['failed'] ) && 1 === $job['counts']['activity_days'], 'Failed page leaves explicit incomplete first day while later day finishes.' );
    $mode = 'normal'; Jobs::control( $id, 'retry' ); $job = $drain( $id );
    $verify( 'completed' === $job['status'] && 2 === $job['counts']['activity_days'] && 2 === $job['counts']['discovered'] && 2 === $job['counts']['activity_review'], 'Failed-page retry recovers its remaining page chain and counts each day/activity once.' );

    $job = Jobs::start( '2020-02-28', '2020-03-01', array( 'weight' ) ); $id = $job['id']; $job = $drain( $id );
    $verify( 'completed' === $job['status'] && 3 === $job['counts']['measurement_days'] && 6 === $job['counts']['added'], 'Persistent chain covers leap day inclusively.' );

    $job = Jobs::start( '2018-02-01', '2018-02-01', array( 'weight' ) ); $id = $job['id'];
    $peer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $peer_name = 'swimlog_h_control_' . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner ), 0, 40 );
    $peer->get_var( $peer->prepare( 'SELECT GET_LOCK(%s, 0)', $peer_name ) );
    $verify( is_wp_error( Jobs::control( $id, 'cancel' ) ) && 'queued' === Jobs::get( $id )['status'], 'Competing control mutation cannot alter job.' );
    $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_name ) ); $peer->close(); $peer = null;
    Jobs::control( $id, 'cancel' );

    $job = Jobs::start( '2018-02-02', '2018-02-02', array( 'weight' ) ); $id = $job['id'];
    delete_user_meta( $owner, API::META_KEY ); $before = count( $requests ); $job = $run( $id );
    $verify( 'blocked' === $job['status'] && $before === count( $requests ) && 1 === count( $job['ready'] ), 'Missing key blocks without dropping cursor or making HTTP.' );
    API::save_key( $owner, 'synthetic-extended-key' ); Jobs::control( $id, 'resume' ); $job = $drain( $id );
    $verify( 'completed' === $job['status'], 'Key restoration resumes original work.' );
    WP_CLI::success( 'Extended historical regressions passed ' . $checks . ' assertions: durable full year, pagination gaps, leap day, source overlaps, completed replay, result paging, competing controls and key recovery.' );
} finally {
    remove_filter( 'pre_http_request', $http, 10 );
    if ( $peer ) { $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_name ) ); $peer->close(); }
    if ( $owner ) {
        Jobs::delete_owner( $owner );
        if ( class_exists( 'SwimLogEvaluation\\Garmin_Measurement_Sync' ) ) SwimLogEvaluation\Garmin_Measurement_Sync::clear( $owner );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports', 'imports' ) as $table ) Database::write( 'delete', Database::table( $table ), array( 'user_id' => $owner ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $owner );
    }
    wp_set_current_user( $previous );
}
