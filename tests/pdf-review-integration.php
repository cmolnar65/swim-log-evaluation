<?php
// Real staged WordPress/MariaDB and rendered admin HTML; synthetic text only.
use SwimLogEvaluation\Training_Plan_Parser as Parser;
use SwimLogEvaluation\Training_Plan_Bulk_Import as Import;
use SwimLogEvaluation\Training_Plan_Collection as Collection;
use SwimLogEvaluation\Structured_Workout_Store as Store;
use SwimLogEvaluation\Admin_Structured_Workouts as Admin;
foreach ( array( 'training-plan-parser', 'training-plan-review', 'training-plan-bulk-import' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'includes/class-' . $file . '.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-structured-workouts.php';
if ( ! defined( 'WP_ADMIN' ) ) define( 'WP_ADMIN', true );
$checks = 0;
$verify = static function( $condition, $message ) use ( &$checks ) { ++$checks; if ( ! $condition ) throw new RuntimeException( $message ); };
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_request = $_REQUEST;
$users = array(); $token = wp_generate_password( 32, false );
$text = "Workout 1\n4 x 100 yards free\nTotal: 400 yards\nWorkout 2\n100 meters free\nCoach cue: \"left\\right\"\nTotal: 200 meters\nWorkout 3\nSwim easy until tired";
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_review_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create synthetic review user.' ); $users[$name] = $id;
        ( new WP_User( $id ) )->add_cap( 'swimlog_manage_own_workouts' );
    }
    $owner = $users['owner']; wp_set_current_user( $owner );
    $parsed = Parser::parse( $text );
    $verify( ! is_wp_error( $parsed ) && 3 === count( $parsed['workouts'] ), 'Three synthetic candidates.' );
    set_transient( 'swimlog_pdf_' . $owner . '_' . $token, array( 'text' => $text, 'bytes' => strlen( $text ) ), Admin::PDF_PREVIEW_TTL );
    // Check the persisted expiration without sleeping or changing the host clock.
    if ( ! wp_using_ext_object_cache() ) {
        $expires = (int) get_option( '_transient_timeout_swimlog_pdf_' . $owner . '_' . $token );
        $verify( $expires >= time() + 86390 && $expires <= time() + 86410, 'PDF review did not retain a full 24-hour window.' );
    }
    $_GET = array( 'pdf_preview' => $token );
    ob_start(); Admin::page(); $html = ob_get_clean();
    $verify( false !== strpos( $html, 'available for 24 hours after upload' ), 'PDF review notice advertises the wrong lifetime.' );
    $dom = new DOMDocument(); $previous_errors = libxml_use_internal_errors( true ); $dom->loadHTML( $html ); libxml_clear_errors(); libxml_use_internal_errors( $previous_errors );
    $xpath = new DOMXPath( $dom );
    $verify( 3 === $xpath->query( '//form[@data-swimlog-pdf-import]//input[@name="swimlog_pdf_selected[]"]' )->length, 'Every candidate needs a selection checkbox, including flagged/null structures.' );
    $verify( 3 === $xpath->query( '//form[@data-swimlog-pdf-import]//div[@data-swimlog-pdf-editor]' )->length, 'Each candidate needs an independent editable structure.' );
    $verify( 3 === $xpath->query( '//form[@data-swimlog-pdf-import]//input[contains(@name,"[reviewed]")]' )->length, 'Each candidate needs explicit approval.' );
    WP_CLI::log( 'SWIMLOG_PDF_REVIEW_HTML_BASE64=' . base64_encode( $html ) );
    $doc = $parsed['workouts'][1]['document']; $doc['sections'][0]['children'][0]['distance'] = 200;
    $review = array( 'document' => wp_json_encode( $doc ), 'total' => '200', 'unit' => 'm', 'reviewed' => '1' );
    $fingerprint = hash( 'sha256', $text );
    $count_posts = static function() use ( $owner ) { return count( get_posts( array( 'post_type' => array_merge( array( Store::POST_TYPE ), Collection::post_types() ), 'author' => $owner, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) ) ); };
    $before = $count_posts();
    $verify( is_wp_error( Import::run( $owner, 'Missing review', $parsed, array( 2 ), $fingerprint, array() ) ), 'Selected flagged workout imported without review.' );
    $wrong = $review; $wrong['total'] = '100';
    $verify( is_wp_error( Import::run( $owner, 'Wrong distance', $parsed, array( 2 ), $fingerprint, array( 2 => $wrong ) ) ), 'Review checkbox bypassed distance validation.' );
    $verify( $before === $count_posts(), 'Failed review created records.' );
    // The real POST handler must preserve corrections on validation failure.
    $_POST = array( 'swimlog_pdf_bulk_import' => '1', 'swimlog_pdf_review_payload_complete' => '1', 'swimlog_pdf_token' => $token, 'swimlog_pdf_plan_name' => 'Keep my corrections', 'swimlog_pdf_selected' => array( '2' ), 'swimlog_pdf_reviews' => array( 2 => $wrong ), '_wpnonce' => wp_create_nonce( 'swimlog_pdf_bulk_import' ) );
    $_REQUEST = $_POST;
    $valid_nonce = $_POST['_wpnonce'];
    $die = static function() { return static function() { throw new RuntimeException( 'nonce_denied' ); }; };
    add_filter( 'wp_die_handler', $die ); $denied = false;
    try {
        $_POST['_wpnonce'] = 'invalid'; $_REQUEST = $_POST;
        try { Admin::handle_actions(); } catch ( RuntimeException $error ) { $denied = 'nonce_denied' === $error->getMessage(); }
    } finally { remove_filter( 'wp_die_handler', $die ); }
    $verify( $denied, 'Review import accepted an invalid nonce.' );
    $_POST['_wpnonce'] = $valid_nonce; $_REQUEST = $_POST;
    unset( $_POST['swimlog_pdf_review_payload_complete'] ); $_REQUEST = $_POST;
    Admin::handle_actions();
    $verify( false !== strpos( $GLOBALS['swimlog_structure_error'] ?? '', 'truncated' ), 'Truncated form was accepted.' );
    $_POST['swimlog_pdf_review_payload_complete'] = '1'; $_REQUEST = $_POST;
    Admin::handle_actions();
    $verify( isset( $GLOBALS['swimlog_structure_error'] ), 'POST handler did not report review failure.' );
    $verify( 'Keep my corrections' === $GLOBALS['swimlog_pdf_review_input']['name'] && '100' === $GLOBALS['swimlog_pdf_review_input']['reviews'][2]['total'], 'Validation failure discarded review inputs.' );
    $verify( $before === $count_posts(), 'Failed POST created records.' );
    $block_review_meta = static function( $check, $id, $key ) { return '_swimlog_pdf_review' === $key ? false : $check; };
    add_filter( 'add_post_metadata', $block_review_meta, 10, 3 );
    try { $failed = Import::run( $owner, 'Injected review write failure', $parsed, array( 2 ), hash( 'sha256', 'review failure' ), array( 2 => $review ) ); }
    finally { remove_filter( 'add_post_metadata', $block_review_meta, 10 ); }
    $verify( is_wp_error( $failed ) && $before === $count_posts(), 'Failed review evidence write left partial records.' );
    $result = Import::run( $owner, 'Manually reviewed selection', $parsed, array( 2 ), $fingerprint, array( 2 => $review, 3 => array( 'document' => 'invalid unselected input' ) ) );
    $verify( ! is_wp_error( $result ) && 1 === $result['count'], 'Corrected selected workout failed import: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
    wp_cache_flush(); $workouts = Collection::workouts( $owner, $result['plan_id'] );
    $verify( 1 === count( $workouts ), 'Unselected workouts were imported.' );
    $item = Store::get( $workouts[0]->ID, $owner );
    $verify( 200.0 === (float) $item['document']['sections'][0]['children'][0]['distance'], 'Reviewed edits did not persist.' );
    $evidence = get_post_meta( $workouts[0]->ID, '_swimlog_pdf_review', true );
    $verify( $parsed['workouts'][1]['source_lines'] === $evidence['source_lines'] && $owner === $evidence['reviewed_by'], 'Original instructions/review provenance were lost.' );
    $verify( is_wp_error( Import::run( $owner, 'Duplicate', $parsed, array( 2 ), $fingerprint, array( 2 => $review ) ) ), 'Manual review bypassed duplicate detection.' );
    // A previously unparsed candidate can be authored explicitly instead of being permanently blocked.
    $created = Import::run( $owner, 'Authored after review', $parsed, array( 3 ), hash( 'sha256', 'separate synthetic source' ), array( 3 => $review ) );
    $verify( ! is_wp_error( $created ), 'Null candidate could not be corrected into a valid structure.' );
    $compact = Parser::parse( "WEEK 1 of 6 Swim #1 of 24 Repeat 2x (rest :15 btw reps)\t300\n50 EZ choice\n100 swim as 25 breast + 25 back\nTOTAL 300 yds" );
    $compact_review = array( 'document' => wp_json_encode( $compact['workouts'][0]['document'] ), 'total' => '300', 'unit' => 'yd', 'reviewed' => '1' );
    $compact_result = Import::run( $owner, 'Synthetic compact table', $compact, array( 1 ), hash( 'sha256', 'synthetic compact source' ), array( 1 => $compact_review ) );
    $verify( ! is_wp_error( $compact_result ), 'Compact table notation did not import after review.' );
    wp_cache_flush();
    $compact_items = Collection::workouts( $owner, $compact_result['plan_id'] );
    $compact_item = Store::get( $compact_items[0]->ID, $owner );
    $compact_summary = SwimLogEvaluation\Structured_Workout::summarize( $compact_item['document'] );
    $verify( 300.0 === $compact_summary['totals']['distance'] && 2 === $compact_item['document']['sections'][0]['children'][0]['count'], 'Header repeat or implicit units lost on persistence.' );
    wp_set_current_user( $users['other'] );
    $verify( is_wp_error( Store::get( $workouts[0]->ID, $users['other'] ) ), 'Private review exposed another owner workout.' );
    $verify( is_wp_error( Import::run( $owner, 'Foreign', $parsed, array( 2 ), hash( 'sha256', 'foreign' ), array( 2 => $review ) ) ), 'Foreign owner review import accepted.' );
    $_POST['_wpnonce'] = wp_create_nonce( 'swimlog_pdf_bulk_import' ); $_REQUEST = $_POST; unset( $GLOBALS['swimlog_structure_error'] );
    Admin::handle_actions();
    $verify( false !== strpos( $GLOBALS['swimlog_structure_error'] ?? '', 'expired' ), 'Preview token was not owner scoped.' );
    WP_CLI::success( 'PDF review UI and selected-import integration passed ' . $checks . ' assertions.' );
} finally {
    foreach ( $users as $id ) {
        foreach ( get_posts( array( 'post_type' => array_merge( array( Store::POST_TYPE ), Collection::post_types() ), 'author' => $id, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post ) wp_delete_post( $post, true );
        delete_transient( 'swimlog_pdf_' . $id . '_' . $token );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
    }
    $_GET = $old_get; $_POST = $old_post; $_REQUEST = $old_request; wp_set_current_user( $old_user );
    unset( $GLOBALS['swimlog_pdf_review_input'], $GLOBALS['swimlog_structure_error'] );
}
