<?php
// Executed by wp eval in the disposable wp-env database after Plugin Check.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Workout;
use SwimLogEvaluation\Shortcodes;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-pdf.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-planned-workouts.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-structured-workouts.php';
$verify = static function( $ok, $message ) {
    if ( ! $ok ) throw new RuntimeException( $message );
};
$verify( Database::schema_is_complete(), 'Activated schema is incomplete.' );
$owner = wp_create_user( 'swimlog_cleanup_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
$verify( ! is_wp_error( $owner ), 'Could not create synthetic owner.' );
$table = Database::table( 'workouts' );
$id = 0;
$post_id = 0;
try {
    global $wpdb;
    $ok = Database::write( 'insert', $table, array(
        'user_id' => $owner, 'workout_start' => '2026-10-01 12:00:00',
        'notes' => 'synthetic private note', 'created_at' => current_time( 'mysql' ),
        'updated_at' => current_time( 'mysql' ),
    ) );
    $verify( 1 === $ok, 'Workout insert failed.' );
    $id = (int) $wpdb->insert_id;
    $first = Workout::get_for_user( $id, $owner );
    $verify( $first && 'synthetic private note' === $first->notes, 'Owner read failed.' );
    $first->notes = 'caller mutation';
    $verify( 'synthetic private note' === Workout::get_for_user( $id, $owner )->notes, 'Cached objects are shared.' );
    $verify( null === Workout::get_for_user( $id, $owner + 10000 ), 'Foreign owner read leaked.' );
    require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-workouts.php';
    $lookup = new ReflectionMethod( SwimLogEvaluation\Admin_Workouts::class, 'linked_post_ids' );
    $lookup->setAccessible( true );
    $verify( array() === $lookup->invoke( null, $id ), 'Unexpected linked post.' );
    $post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $owner, 'post_title' => 'Synthetic cache test' ), true );
    $verify( ! is_wp_error( $post_id ), 'Post creation failed.' );
    update_post_meta( $post_id, '_swimlog_workout_id', $id );
    $verify( array( (string) $post_id ) === array_map( 'strval', $lookup->invoke( null, $id ) ), 'Negative linked-post cache survived creation.' );
    wp_trash_post( $post_id );
    $verify( array() === $lookup->invoke( null, $id ), 'Linked-post cache survived trash.' );
    $verify( true === Workout::update_metadata( $id, $owner, 0, 'updated note' ), 'Metadata update failed.' );
    $verify( 'updated note' === Workout::get_for_user( $id, $owner )->notes, 'Write left stale cache.' );
    $wpdb->update( $table, array( 'notes' => 'external write' ), array( 'id' => $id ) );
    $verify( 'external write' === Workout::get_for_user( $id, $owner )->notes, 'External write left stale cache.' );
    Database::write( 'query', 'START TRANSACTION' );
    Database::write( 'update', $table, array( 'notes' => 'uncommitted' ), array( 'id' => $id ) );
    $verify( 'uncommitted' === Workout::get_for_user( $id, $owner )->notes, 'Transaction read failed.' );
    Database::write( 'query', 'ROLLBACK' );
    $verify( 'external write' === Workout::get_for_user( $id, $owner )->notes, 'Rollback left stale cache.' );
    $verify( Database::install_schema(), 'Idempotent schema install failed.' );
    $verify( 'external write' === Workout::get_for_user( $id, $owner )->notes, 'Schema recheck lost existing data.' );
    update_user_meta( $owner, 'swimlog_public_results', '0' );
    wp_set_current_user( 0 );
    $output = Shortcodes::workout( array( 'id' => $id ) );
    $verify( false !== strpos( $output, 'unavailable' ), 'Private workout is publicly visible.' );
    $verify( false === strpos( $output, 'external write' ), 'Private notes leaked.' );
    $verify( true === Workout::delete( $id, $owner ), 'Workout deletion failed.' );
    $verify( null === Workout::get_for_user( $id, $owner ), 'Deleted workout remained cached.' );
    $id = 0;
    WP_CLI::success( 'Production staging smoke: owner isolation, private output, cache copies, internal/external invalidation, rollback, schema idempotence, and deletion passed.' );
} finally {
    Database::write( 'query', 'ROLLBACK' );
    if ( $post_id && ! is_wp_error( $post_id ) ) wp_delete_post( $post_id, true );
    if ( $id ) Database::write( 'delete', $table, array( 'id' => $id, 'user_id' => $owner ) );
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $owner );
}
