<?php
// Synthetic HTTP and synthetic FIT session fixtures only; no real Garmin data.
use SwimLogEvaluation\Garmin_History as History;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Importer;
use SwimLogEvaluation\Database;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-importer.php';
global $wpdb;
$previous = get_current_user_id(); $owner = 0; $checks = 0; $calls = array(); $mode = 'pages'; $archive = '';
$storage = get_option( 'swimlog_private_storage_dir' );
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$directory = sys_get_temp_dir() . '/swimlog-history-' . wp_generate_uuid4();
$peer = null; $peer_lock = '';
$verify = static function ( $ok, $why ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $why ); };
$reply = static function ( $body, $code = 200 ) { return array( 'response' => array( 'code' => $code ), 'headers' => array( 'retry-after' => '120' ), 'body' => $body ); };
$http = static function ( $pre, $args, $url ) use ( &$mode, &$calls, &$archive, $reply ) {
    $calls[] = $url;
    if ( 0 !== strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected history HTTP.' );
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return $reply( 'synthetic.history.token' );
    if ( false !== strpos( $url, 'download-service/' ) ) return $reply( $archive );
    if ( 'limited' === $mode ) return $reply( '{}', 429 );
    if ( 'failure' === $mode ) return $reply( '{}', 500 );
    if ( false !== strpos( $url, 'search/activities' ) ) {
        parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
        $day = $query['startDate']; $offset = (int) $query['start'];
        if ( 'empty' === $mode ) return $reply( '[]' );
        if ( 'malformed' === $mode ) return $reply( '{}' );
        $rows = array();
        if ( $offset < 100 || 'stalled' === $mode ) {
            for ( $i = 1; $i <= 100; ++$i ) $rows[] = array( 'activityId' => $i, 'startTimeLocal' => $day . ' 06:00:00', 'activityType' => array( 'typeKey' => 'running' ) );
        } elseif ( 100 === $offset ) {
            foreach ( array( 101, 102 ) as $id ) $rows[] = array( 'activityId' => $id, 'startTimeLocal' => $day . ' 23:59:59', 'activityType' => array( 'typeKey' => 'lap_swimming' ) );
        }
        if ( 'boundary' === $mode ) $rows[0]['startTimeLocal'] = '2017-12-31 23:59:59';
        return $reply( wp_json_encode( $rows ) );
    }
    $day = substr( $url, -10 );
    if ( 'empty' === $mode ) return $reply( false !== strpos( $url, 'weight/all/' ) ? '[]' : '{}' );
    return $reply( false !== strpos( $url, 'weight/all/' )
        ? wp_json_encode( array( array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T06:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ), array( 'calendarDate' => $day, 'timestampGMT' => $day . 'T07:00:00Z', 'weight' => 82.5, 'bmi' => 24.1 ) ) )
        : wp_json_encode( array( 'calendarDate' => $day, 'averageSpO2' => 96.4 ) ) );
};
$make_fit = static function ( $elapsed ) {
    $data = pack( 'CCCvC', 0x40, 0, 0, 18, 5 ) . pack( 'CCC', 2, 4, 0x86 ) . pack( 'CCC', 5, 1, 0 ) . pack( 'CCC', 6, 1, 0 ) . pack( 'CCC', 7, 4, 0x86 ) . pack( 'CCC', 9, 4, 0x86 );
    $data .= chr( 0 ) . pack( 'VCCVV', strtotime( '2018-01-01T12:00:00Z' ) - 631065600, 5, 18, $elapsed, 100000 );
    $fit = pack( 'CCvV', 12, 0x20, 2180, strlen( $data ) ) . '.FIT' . $data;
    $crc = 0;
    for ( $i = 0; $i < strlen( $fit ); ++$i ) { $byte = ord( $fit[$i] ); for ( $b = 0; $b < 8; ++$b ) { $bit = ( $crc & 1 ) ^ ( ( $byte >> $b ) & 1 ); $crc >>= 1; if ( $bit ) $crc ^= 0xA001; } }
    return $fit . pack( 'v', $crc );
};
$zip_fit = static function ( $fit ) use ( $directory ) {
    $path = $directory . '/synthetic.zip'; $zip = new ZipArchive();
    $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ); $zip->addFromString( 'history.fit', $fit ); $zip->close();
    return file_get_contents( $path );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    wp_mkdir_p( $directory ); $_SERVER['DOCUMENT_ROOT'] = ABSPATH;
    update_option( 'swimlog_private_storage_dir', $directory );
    $created = wp_create_user( 'swimlog_history_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
    $verify( ! is_wp_error( $created ), 'Create history owner.' ); $owner = (int) $created;
    $user = new WP_User( $owner ); $user->add_cap( 'swimlog_manage_own_workouts' ); $user->add_cap( 'swimlog_upload_workouts' );
    wp_set_current_user( $owner ); API::save_key( $owner, 'synthetic-history-key' );
    $cursor = History::cursor( '2018-01-01', '2018-01-01' );
    $first = History::activity_step( $owner, $cursor, array( 'lap_swimming' ) );
    $verify( ! is_wp_error( $first ) && 100 === $first['cursor']['offset'] && ! $first['activities'] && ! $first['cursor']['done'], 'Non-swim full page must not stop discovery.' );
    $second = History::activity_step( $owner, $first['cursor'], array( 'lap_swimming' ) );
    $verify( 2 === count( $second['activities'] ) && 102 === $second['cursor']['offset'] && ! $second['cursor']['done'], 'More than 100 activities and partial page continue.' );
    $last = History::activity_step( $owner, $second['cursor'], array( 'lap_swimming' ) );
    $verify( $last['cursor']['done'], 'Only explicit empty page exhausts day.' );
    $mode = 'stalled';
    $verify( is_wp_error( History::activity_step( $owner, $first['cursor'], array( 'lap_swimming' ) ) ), 'Repeating page fails instead of false completion.' );
    foreach ( array( 'boundary', 'malformed', 'failure' ) as $mode ) $verify( is_wp_error( History::activity_step( $owner, $cursor, array( 'lap_swimming' ) ) ), 'Failed/malformed/out-of-range page does not advance.' );
    $mode = 'limited';
    $limited = History::activity_step( $owner, $cursor, array( 'lap_swimming' ) );
    $verify( is_wp_error( $limited ) && 120 === $limited->get_error_data()['retry_after'], 'Activity Retry-After returned.' );
    $before = count( $calls ); History::activity_step( $owner, $cursor, array( 'lap_swimming' ) );
    $verify( $before === count( $calls ), 'Activity cooldown makes no HTTP requests.' );
    delete_transient( 'swimlog_activity_retry_' . $owner );

    $mode = 'empty'; $scan = History::cursor( '2018-01-01', '2018-12-31' ); $days = 0;
    while ( ! $scan['done'] && $days < 366 ) { $step = History::activity_step( $owner, $scan, array( 'lap_swimming' ) ); if ( is_wp_error( $step ) ) throw new RuntimeException( 'Year activity traversal failed.' ); $scan = $step['cursor']; ++$days; }
    $verify( 365 === $days && $scan['done'] && '2018-12-31' === $scan['day'], 'Inclusive full-year activity traversal.' );
    $scan = History::cursor( '2018-01-01', '2018-12-31' ); $days = 0;
    while ( ! $scan['done'] && $days < 366 ) { $step = History::measurement_step( $owner, $scan, array( 'weight', 'body_composition', 'spo2' ) ); if ( is_wp_error( $step ) ) throw new RuntimeException( 'Year measurement traversal failed.' ); $scan = $step['cursor']; ++$days; }
    $verify( 365 === $days && $scan['done'] && '2018-12-31' === $scan['day'], 'Inclusive full-year measurement traversal; empty days distinguished.' );
    $mode = 'normal';
    $m = History::measurement_step( $owner, $cursor, array( 'weight', 'body_composition', 'spo2' ) );
    $verify( 2 === $m['results'][0]['added'] && 2 === $m['results'][1]['added'] && 1 === $m['results'][2]['reports_added'], 'History imports verified readings/reports and multiple same-day readings.' );
    $again = History::measurement_step( $owner, $cursor, array( 'weight', 'body_composition', 'spo2' ) );
    $verify( 2 === $again['results'][0]['skipped'] && 0 === $again['results'][0]['added'], 'Historical overlap skips duplicates.' );
    $mode = 'failure'; $failed = History::measurement_step( $owner, $cursor, array( 'spo2' ) );
    $verify( $failed['retry'] && $cursor === $failed['cursor'], 'Failed measurement keeps exact cursor for retry.' );

    $fit = $make_fit( 600000 ); $archive = $zip_fit( $fit ); $mode = 'normal';
    $source = $directory . '/preserved.fit'; file_put_contents( $source, $fit );
    $table = Database::table( 'imports' );
    $seed = static function ( $id, $status, $hash, $path, $workout = null ) use ( $owner, $table, $wpdb ) {
        Database::write( 'insert', $table, array( 'user_id' => $owner, 'workout_id' => $workout, 'source_type' => 'fit', 'source_provider' => 'garmin', 'external_activity_id' => (string) $id, 'original_filename' => 'history.fit', 'stored_filename' => basename( $path ), 'stored_path' => $path, 'file_hash' => $hash, 'file_size' => 64, 'parser_version' => 'synthetic', 'import_status' => $status, 'imported_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
        return (int) $wpdb->insert_id;
    };
    $id = $seed( 7001, 'failed', hash( 'sha256', $fit ), $source );
    $retry = API::import_activity( $owner, 7001 );
    $verify( ! is_wp_error( $retry ) && 'imported' === $retry['status'] && $id === $retry['import_id'], 'Failed Garmin ID retries same persisted source/import row successfully.' );
    $before = count( $calls ); $known = API::import_activity( $owner, 7001 );
    $verify( 'already_associated' === $known['status'] && $before === count( $calls ), 'Valid complete ID skips download.' );
    $seed( 7003, 'pending', hash( 'sha256', 'pending-fixture' ), $source, $retry['workout_id'] );
    $before = count( $calls ); $pending = API::import_activity( $owner, 7003 );
    $verify( 'pending_review' === $pending['status'] && $before === count( $calls ), 'Pending ID remains review without duplicate import or download.' );
    $seed( 7004, 'complete', hash( 'sha256', 'orphan' ), $source );
    $verify( is_wp_error( API::import_activity( $owner, 7004 ) ), 'Orphan complete import is not counted as imported.' );
    $seed( 7005, 'processing', hash( 'sha256', 'processing' ), $source );
    $verify( is_wp_error( API::import_activity( $owner, 7005 ) ), 'Incomplete processing record requires repair.' );
    $fit2 = $make_fit( 650000 ); $archive = $zip_fit( $fit2 ); $source2 = $directory . '/preserved2.fit'; file_put_contents( $source2, $fit2 );
    $id2 = $seed( 7002, 'failed', hash( 'sha256', $fit2 ), $source2 );
    $probable = API::import_activity( $owner, 7002 );
    $verify( ! is_wp_error( $probable ) && 'pending_review' === $probable['status'] && $id2 === $probable['import_id'], 'Failed-to-probable retry updates existing row instead of duplicate pending insert.' );
    $seed( 7006, 'failed', hash( 'sha256', 'different-source' ), $source );
    $verify( is_wp_error( API::import_activity( $owner, 7006 ) ), 'Changed failed source is not overwritten.' );
    $archive = $zip_fit( $make_fit( 700000 ) );
    $verify( is_wp_error( History::import_activity( $owner, 7010, 'open_water_swimming' ) ), 'Historical open-water import still requires location review without GPS match.' );

    $peer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $peer_lock = 'swimlog_import_' . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner ), 0, 48 );
    $peer->get_var( $peer->prepare( 'SELECT GET_LOCK(%s, 0)', $peer_lock ) );
    $before = count( $calls );
    $verify( is_wp_error( API::import_activity( $owner, 7001 ) ) && $before === count( $calls ), 'Competing connection blocks import before preflight/download.' );
    $verify( is_wp_error( Importer::import_trusted_fit( $owner, $source, 'history.fit' ) ), 'Uploaded/trusted incoming sources share owner serialization.' );
    $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_lock ) ); $peer->close(); $peer = null;
    wp_set_current_user( 0 ); $before = count( $calls );
    $verify( is_wp_error( History::activity_step( $owner, $cursor, array( 'lap_swimming' ) ) ) && is_wp_error( History::measurement_step( $owner, $cursor, array( 'weight' ) ) ) && is_wp_error( History::import_activity( $owner, 7001, 'lap_swimming' ) ) && $before === count( $calls ), 'Anonymous history calls denied before HTTP.' );
    WP_CLI::success( 'Historical Garmin primitives passed ' . $checks . ' assertions: full year, raw pagination, boundaries/stalls, cooldown, measurement overlap, activity status/retry, preserved sources, location review and competing connection.' );
} finally {
    remove_filter( 'pre_http_request', $http, 10 );
    if ( $peer ) { $peer->get_var( $peer->prepare( 'SELECT RELEASE_LOCK(%s)', $peer_lock ) ); $peer->close(); }
    if ( $owner ) {
        delete_transient( 'swimlog_activity_retry_' . $owner ); delete_transient( 'swimlog_health_retry_' . $owner );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports', 'imports', 'workouts' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $owner ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $owner );
    }
    foreach ( (array) glob( $directory . '/*' ) as $path ) if ( is_file( $path ) ) unlink( $path );
    if ( is_dir( $directory ) ) rmdir( $directory );
    update_option( 'swimlog_private_storage_dir', $storage );
    if ( null === $docroot ) unset( $_SERVER['DOCUMENT_ROOT'] ); else $_SERVER['DOCUMENT_ROOT'] = $docroot;
    wp_set_current_user( $previous );
}
