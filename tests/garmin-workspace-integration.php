<?php
// Disposable synthetic users and intercepted HTTP only. No live credentials or payloads.
use SwimLogEvaluation\{Admin_Garmin,Garmin_Personal_API as API,Garmin_Measurement_Sync as Sync,Plugin};
foreach ( array( 'garmin-personal-api', 'garmin-measurement-sync', 'garmin-history-jobs', 'plugin' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'includes/class-' . $file . '.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';
$previous = get_current_user_id(); $post = $_POST; $get = $_GET; $request = $_REQUEST;
$users = array(); $checks = 0; $calls = 0; $mode = 'ok'; $die = null;
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $pre, $args, $url ) use ( &$calls, &$mode ) {
    ++$calls;
    if ( false === strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected synthetic request.' );
    if ( 'replace' === $mode ) { API::save_key( get_current_user_id(), 'synthetic-replacement-during-test' ); $mode = 'ok'; }
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return array( 'response' => array( 'code' => 'fail' === $mode ? 401 : 200 ), 'headers' => array(), 'body' => 'synthetic.workspace.token' );
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '[]' );
};
$render = static function ( $fields = array(), $query = array() ) {
    $_POST = $fields; $_GET = $query; $_REQUEST = array_merge( $query, $fields );
    ob_start(); try { Admin_Garmin::garmin(); return ob_get_contents(); } finally { ob_end_clean(); }
};
$locked = static function ( $html ) use ( $verify ) {
    foreach ( array( 'swimlog-garmin-activities', 'swimlog-garmin-measurements', 'swimlog-garmin-sync', 'swimlog-garmin-history' ) as $id ) $verify( false === strpos( $html, 'id="' . $id . '"' ), 'Untested workspace hides ' . $id );
    $verify( 1 === preg_match( '/<details[^>]+id="swimlog-garmin-settings"[^>]*\sopen/', $html ), 'Connection settings are expanded until tested.' );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_workspace_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        if ( is_wp_error( $id ) ) throw new RuntimeException( 'Create workspace fixture.' );
        $users[$name] = (int) $id;
        $user = new WP_User( $id ); foreach ( array( 'swimlog_view_own_results', 'swimlog_manage_own_workouts' ) as $cap ) $user->add_cap( $cap );
    }
    $owner = $users['owner']; wp_set_current_user( $owner );
    $empty = $render(); $locked( $empty );
    $verify( 0 === $calls, 'Opening setup makes no Garmin request.' );
    $verify( ! API::connection_ready( $owner ), 'No credential is not ready.' );
    $fields = array( 'swimlog_garmin_action' => 'save', 'garmin_api_key' => 'synthetic-workspace-key', '_wpnonce' => wp_create_nonce( 'swimlog_save_garmin_key' ) );
    $saved = $render( $fields ); $locked( $saved );
    $verify( API::is_configured( $owner ) && ! API::connection_ready( $owner ), 'Saving a credential does not count as testing.' );
    $verify( 0 === $calls, 'Saving performs no automatic test.' );
    Plugin::set_garmin_sync_schedule( $owner, true ); Sync::configure( array( 'weight' ) );
    $schedule = wp_next_scheduled( 'swimlog_garmin_scheduled_sync', array( $owner ) );
    $before = $calls;
    $render( array( 'swimlog_garmin_action' => 'save_sync', 'garmin_sync_types_present' => '1', 'garmin_sync_types' => array( 'walking' ), '_wpnonce' => wp_create_nonce( 'swimlog_save_garmin_sync' ) ) );
    $verify( array( 'lap_swimming', 'open_water_swimming' ) === API::scheduled_types( $owner ), 'Forged untested activity settings cannot change preferences.' );
    $render( array( 'swimlog_measurement_schedule' => '1', 'scheduled_categories' => array( 'spo2' ), '_wpnonce' => wp_create_nonce( 'swimlog_measurement_schedule' ) ) );
    $verify( array( 'weight' ) === Sync::categories( $owner ), 'Untested measurement settings cannot change preferences.' );
    foreach ( array( 'activities', 'import_selected', 'import_preflight' ) as $action ) $locked( $render( array( 'swimlog_garmin_action' => $action ) ) );
    $render( array( 'swimlog_measurement_import' => '1' ) );
    $render( array( 'swimlog_history_action' => 'start' ) );
    $render( array(), array( 'garmin_activity_id' => '123' ) );
    $verify( $before === $calls, 'Untested direct posts/detail URLs do not call Garmin.' );
    $verify( ! SwimLogEvaluation\Garmin_History_Jobs::get(), 'Untested history post creates no job.' );
    $mode = 'fail'; $failed = $render( array( 'swimlog_garmin_action' => 'test', '_wpnonce' => wp_create_nonce( 'swimlog_test_garmin_connection' ) ) ); $locked( $failed );
    $verify( ! API::connection_ready( $owner ) && false !== strpos( $failed, 'HTTP 401' ), 'A failed test keeps sections locked and explains failure.' );
    $mode = 'ok'; $tested = $render( array( 'swimlog_garmin_action' => 'test', '_wpnonce' => wp_create_nonce( 'swimlog_test_garmin_connection' ) ) );
    $verify( API::connection_ready( $owner ), 'Only an explicit successful test unlocks the workspace.' );
    $ids = array( 'swimlog-garmin-activities', 'swimlog-garmin-measurements', 'swimlog-garmin-sync', 'swimlog-garmin-history', 'swimlog-garmin-settings' );
    $last = -1; foreach ( $ids as $id ) { $position = strpos( $tested, 'id="' . $id . '"' ); $verify( false !== $position && $position > $last, 'User-requested section order: ' . $id ); $last = $position; }
    $verify( ! preg_match( '/<details[^>]+id="swimlog-garmin-settings"[^>]*\sopen/', $tested ), 'Successful setup collapses connection settings.' );
    foreach ( array( 'lap_swimming', 'open_water_swimming', 'running', 'walking' ) as $type ) $verify( false !== strpos( $tested, 'name="garmin_sync_types[]" value="' . $type . '"' ), 'Explicit sync choice ' . $type );
    $verify( false !== strpos( $tested, 'Save measurement schedule' ), 'Measurement sync remains a separate control inside Automatic Sync.' );
    $verify( $schedule === wp_next_scheduled( 'swimlog_garmin_scheduled_sync', array( $owner ) ) && array( 'weight' ) === Sync::categories( $owner ), 'Testing preserves existing schedules and selections.' );
    $verify( false === strpos( $tested, 'synthetic-workspace-key' ) && false === strpos( $tested, 'synthetic.workspace.token' ), 'Workspace exposes no secret or token.' );
    $verify( false === strpos( $tested, 'Last error:' ), 'Empty error metadata is not displayed.' );
    $retrieved = $render( array( 'swimlog_garmin_action' => 'activities', 'garmin_start_date' => wp_date( 'Y-m-d' ), 'garmin_end_date' => wp_date( 'Y-m-d' ), 'garmin_pool_swim' => '1', '_wpnonce' => wp_create_nonce( 'swimlog_garmin_recent_activities' ) ) );
    $verify( false !== strpos( $retrieved, 'Garmin returned no activities.' ), 'Tested owner can retrieve activities.' );
    $verify( false !== strpos( $retrieved, 'id="swimlog-garmin-measurements"' ), 'Activity results do not hide other workspace sections.' );
    $die = static function () { return static function () { throw new RuntimeException( 'expected nonce denial' ); }; }; add_filter( 'wp_die_handler', $die );
    $denied = false; try { $render( array( 'swimlog_garmin_action' => 'test', '_wpnonce' => 'invalid' ) ); } catch ( RuntimeException $e ) { $denied = 'expected nonce denial' === $e->getMessage(); }
    remove_filter( 'wp_die_handler', $die ); $die = null;
    $verify( $denied && API::connection_ready( $owner ), 'Invalid test nonce cannot change readiness.' );
    API::save_key( $users['other'], 'synthetic-other-key' );
    update_user_meta( $users['other'], API::TESTED_KEY, get_user_meta( $owner, API::TESTED_KEY, true ) );
    $verify( ! API::connection_ready( $users['other'] ), 'Test marker cannot be transferred between owners.' );
    wp_set_current_user( $users['other'] ); $locked( $render( array(), array( 'swimmer_id' => $owner ) ) ); wp_set_current_user( $owner );
    $mode = 'fail'; $verify( is_wp_error( API::test_connection( $owner ) ) && ! API::connection_ready( $owner ), 'A failed retest revokes the workspace test marker.' );
    $mode = 'ok'; API::test_connection( $owner );
    $replacement = $render( array( 'swimlog_garmin_action' => 'save', 'garmin_api_key' => 'synthetic-new-workspace-key', '_wpnonce' => wp_create_nonce( 'swimlog_save_garmin_key' ) ) ); $locked( $replacement );
    $verify( ! API::connection_ready( $owner ), 'Replacing a key requires a new test.' );
    $verify( $schedule === wp_next_scheduled( 'swimlog_garmin_scheduled_sync', array( $owner ) ) && array( 'weight' ) === Sync::categories( $owner ), 'Replacement preserves schedules and selections.' );
    $mode = 'replace'; $verify( is_wp_error( API::test_connection( $owner ) ) && ! API::connection_ready( $owner ), 'A key replacement during testing cannot unlock the new key.' );
    $mode = 'ok'; API::test_connection( $owner );
    $removed = $render( array( 'swimlog_garmin_action' => 'remove', '_wpnonce' => wp_create_nonce( 'swimlog_remove_garmin_key' ) ) ); $locked( $removed );
    $verify( ! API::is_configured( $owner ) && '' === get_user_meta( $owner, API::TESTED_KEY, true ), 'Removal clears key and readiness.' );
    // Only synthetic markup is emitted for responsive browser regression.
    foreach ( array( 'SETUP' => $empty, 'SAVED' => $saved, 'READY' => $tested ) as $name => $html ) echo 'SWIMLOG_GARMIN_' . $name . '_HTML_BASE64=' . base64_encode( $html ) . "\n";
    WP_CLI::success( 'Garmin workspace order, setup gate, owner/nonce boundaries and preference preservation passed ' . $checks . ' assertions.' );
} finally {
    if ( $die ) remove_filter( 'wp_die_handler', $die ); remove_filter( 'pre_http_request', $http, 10 );
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $users as $id ) { wp_set_current_user( $id ); Plugin::set_garmin_sync_schedule( $id, false ); Sync::configure( array() ); wp_delete_user( $id ); }
    $_POST = $post; $_GET = $get; $_REQUEST = $request; wp_set_current_user( $previous );
}
