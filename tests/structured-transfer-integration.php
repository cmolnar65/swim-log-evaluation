<?php
// Synthetic data only; runs against activated production staging, not a source-only mock.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Structured_Workout_Store as Store;
use SwimLogEvaluation\Structured_Workout_Export as Export;
use SwimLogEvaluation\Structured_Workout_FIT as Fit;
use SwimLogEvaluation\Recorded_Workout_Structure as Conversion;
use SwimLogEvaluation\Admin_Structured_Transfers as Transfers;
use SwimLogEvaluation\Planned_Workout as Plan;
use SwimLogEvaluation\Planned_Structure_Link as Link;
Transfers::load();
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
$checks = 0;
$verify = static function( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$old_user = get_current_user_id(); $users = array(); $recorded = 0; $structures = array(); $plans = array();
try {
    global $wpdb;
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_export_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create synthetic owner.' ); $users[$name] = $id;
        ( new WP_User( $id ) )->add_cap( 'swimlog_manage_own_workouts' );
    }
    wp_set_current_user( $users['owner'] ); $owner = $users['owner'];
    Database::write( 'insert', Database::table( 'workouts' ), array( 'user_id' => $owner, 'workout_start' => current_time( 'mysql' ), 'workout_type' => 'pool', 'total_distance_m' => 100, 'pool_length_unit' => 'm', 'notes' => 'PRIVATE SOURCE NOTE', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
    $recorded = (int) $wpdb->insert_id;
    for ( $n = 1; $n <= 4; ++$n ) Database::write( 'insert', Database::table( 'lengths' ), array( 'workout_id' => $recorded, 'sequence_no' => $n, 'distance_m' => 25, 'stroke' => 'FR', 'length_type' => 'active', 'elapsed_time_ms' => 20000, 'created_at' => current_time( 'mysql' ) ) );
    $before = SwimLogEvaluation\Workout::get_for_user( $recorded, $owner );
    $preview = Conversion::preview( $recorded, $owner );
    $verify( ! is_wp_error( $preview ), 'Preview conversion: ' . ( is_wp_error( $preview ) ? $preview->get_error_message() : '' ) );
    $verify( ! get_posts( array( 'post_type' => Store::POST_TYPE, 'author' => $owner, 'post_status' => 'private' ) ), 'Preview wrote a prescription.' );
    $verify( is_wp_error( Conversion::confirm( $recorded, $owner, 'stale', 'Stale' ) ), 'Stale preview accepted.' );
    $result = Conversion::confirm( $recorded, $owner, $preview['hash'], 'Future practice', wp_date( 'Y-m-d', time() + 3 * DAY_IN_SECONDS, wp_timezone() ) );
    $verify( ! is_wp_error( $result ), 'Confirm conversion: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
    $structures[] = $result['structure_id']; $plans[] = $result['planned_id'];
    $verify( Store::POST_TYPE === get_post_type( $result['structure_id'] ) && 'private' === get_post_status( $result['structure_id'] ), 'New structure is not private.' );
    $verify( $result['structure_id'] === Link::for_plan( $result['planned_id'], $owner )['id'], 'Optional schedule did not link.' );
    $verify( wp_json_encode( $before ) === wp_json_encode( SwimLogEvaluation\Workout::get_for_user( $recorded, $owner ) ), 'Conversion changed recorded source.' );
    $verify( 0 === (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE workout_id=%d', Database::table( 'performances' ), $recorded ) ), 'Conversion fabricated PB evidence.' );
    $text = Transfers::download_data( $result['structure_id'], 'txt' );
    $verify( ! is_wp_error( $text ) && false === strpos( $text['bytes'], 'PRIVATE SOURCE NOTE' ), 'Private source notes leaked.' );
    $verify( false !== strpos( $text['bytes'], 'Recorded length time: 20 seconds' ), 'Measured observations lost.' );
    $verify( is_wp_error( Transfers::download_data( $result['structure_id'], 'html' ) ), 'Invalid download type accepted.' );
    wp_set_current_user( $users['other'] );
    $verify( is_wp_error( Transfers::download_data( $result['structure_id'], 'txt' ) ), 'Foreign download allowed.' );
    $verify( is_wp_error( Conversion::preview( $recorded, $users['other'] ) ), 'Foreign source preview allowed.' );
    wp_set_current_user( 0 ); $verify( is_wp_error( Transfers::download_data( $result['structure_id'], 'fit' ) ), 'Anonymous download allowed.' );
    wp_set_current_user( $owner );
    $doc = array( 'unit' => 'yd', 'sections' => array( array( 'type' => 'section', 'label' => 'Main Set', 'rest' => '0', 'children' => array(
        array( 'type' => 'repeat', 'count' => 3, 'rest' => '0', 'children' => array(
            array( 'type' => 'step', 'distance' => 100, 'stroke' => 'FR', 'rest' => '0', 'pace' => 'on 2:00' ),
            array( 'type' => 'step', 'distance' => 50, 'stroke' => 'BR', 'rest' => '15', 'pace' => '1:30/100yd' ),
        ) ),
        array( 'type' => 'step', 'distance' => 25, 'stroke' => 'FL', 'drill' => true, 'equipment' => array( 'Fins' ) ),
    ) ) ) );
    $fit_id = Store::save( $owner, 'Synthetic FIT practice', $doc );
    $verify( ! is_wp_error( $fit_id ), 'Create export fixture.' ); $structures[] = $fit_id;
    $fit = Transfers::download_data( $fit_id, 'fit' );
    $verify( ! is_wp_error( $fit ) && '.FIT' === substr( $fit['bytes'], 8, 4 ), 'FIT export failed.' );
    WP_CLI::log( 'SWIMLOG_FIT_QA_BASE64=' . base64_encode( $fit['bytes'] ) );
    $pdf_doc = $doc;
    for ( $i = 0; $i < 12; ++$i ) $pdf_doc['sections'][] = array( 'type' => 'section', 'label' => 'Technique block ' . ( $i + 1 ), 'description' => 'Relax the shoulders, maintain a long body line, and review stroke technique. No private notes are exported.', 'children' => array( array( 'type' => 'repeat', 'count' => 4, 'rest' => '20', 'children' => array( array( 'type' => 'step', 'distance' => 50, 'stroke' => 'BR', 'rest' => '0', 'equipment' => array( 'Pull buoy' ) ) ) ) ) );
    $pdf = Export::pdf( 'Pool practice - préparation & endurance', $pdf_doc );
    $verify( ! is_wp_error( $pdf ) && '%PDF-' === substr( $pdf, 0, 5 ), 'PDF export failed: ' . ( is_wp_error( $pdf ) ? $pdf->get_error_message() : '' ) );
    WP_CLI::log( 'SWIMLOG_PDF_QA_BASE64=' . base64_encode( $pdf ) );
    $die = static function() { return static function() { throw new RuntimeException( 'nonce rejected' ); }; };
    add_filter( 'wp_die_handler', $die ); $old_get = $_GET; $old_request = $_REQUEST;
    try {
        $_GET = array( 'structure_id' => $fit_id, 'format' => 'fit', '_wpnonce' => 'invalid' ); $_REQUEST = $_GET; $denied = false;
        try { Transfers::download(); } catch ( RuntimeException $e ) { $denied = true; }
        $verify( $denied, 'Download nonce was not required.' );
    } finally { remove_filter( 'wp_die_handler', $die ); $_GET = $old_get; $_REQUEST = $old_request; }
    WP_CLI::success( 'Recorded conversion and exports passed ' . $checks . ' WordPress integration assertions.' );
} finally {
    foreach ( $plans as $id ) Plan::delete( $id, $users['owner'] );
    foreach ( $structures as $id ) wp_delete_post( $id, true );
    if ( $recorded ) { Database::write( 'delete', Database::table( 'lengths' ), array( 'workout_id' => $recorded ) ); Database::write( 'delete', Database::table( 'workouts' ), array( 'id' => $recorded ) ); }
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $id ) wp_delete_user( $id );
    wp_set_current_user( $old_user );
}
