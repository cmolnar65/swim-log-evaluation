<?php
// Synthetic fixtures only. Executed against disposable WordPress and MariaDB.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Training_Plan_Parser as Parser;
use SwimLogEvaluation\Training_Plan_Bulk_Import as Import;
use SwimLogEvaluation\Training_Plan_Collection as Collection;
use SwimLogEvaluation\Training_Plan_Scheduler as Scheduler;
use SwimLogEvaluation\Structured_Workout_Store as Store;
use SwimLogEvaluation\Planned_Structure_Link as Link;
use SwimLogEvaluation\Planned_Workout as Plan;
use SwimLogEvaluation\Calendar;
foreach ( array( 'training-plan-parser', 'training-plan-review', 'training-plan-bulk-import', 'training-plan-scheduler', 'workout', 'event', 'calendar' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'includes/class-' . $file . '.php';
$checks = 0;
$verify = static function( $ok, $message ) use ( &$checks ) {
    ++$checks;
    if ( ! $ok ) throw new RuntimeException( $message );
};
$failures = array();
$run = static function( $name, $test ) use ( &$failures ) {
    try { $test(); WP_CLI::log( 'PASS: ' . $name ); }
    catch ( Throwable $error ) { $failures[] = $name . ': ' . $error->getMessage(); WP_CLI::log( 'FAIL: ' . end( $failures ) ); }
};
$previous_user = get_current_user_id();
$previous_timezone = get_option( 'timezone_string' );
$users = array();
$original_plans = array();
$imported = null;
$structures = array();
try {
    update_option( 'timezone_string', 'America/Denver' );
    foreach ( array( 'owner', 'other', 'admin' ) as $label ) {
        $id = wp_create_user( 'swimlog_integration_' . $label . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create test user.' );
        $users[$label] = (int) $id;
        $user = new WP_User( $id );
        $user->add_cap( 'swimlog_manage_own_workouts' );
        $user->add_cap( 'swimlog_view_own_results' );
        if ( 'admin' === $label ) { $user->set_role( 'administrator' ); $user->add_cap( 'swimlog_manage_all_users' ); }
    }
    $owner = $users['owner'];
    wp_set_current_user( $owner );
    $parsed = Parser::parse( "Workout 1\n4 x 100 yards free\nTotal: 400 yards\nWorkout 2\n2 x 200 meters breaststroke\nTotal: 400 meters" );
    $verify( ! is_wp_error( $parsed ) && 2 === count( $parsed['workouts'] ), 'Synthetic parser fixture.' );
    $fingerprint = hash( 'sha256', 'synthetic integration plan' );
    $post_ids = static function( $owner ) {
        return get_posts( array( 'post_type' => array( Store::POST_TYPE, Collection::POST_TYPE ), 'author' => $owner, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) );
    };
    $run( 'import persistence, selection, duplicates and ownership', function() use ( $verify, $owner, $users, $parsed, $fingerprint, &$imported, &$structures ) {
        $verify( is_wp_error( Import::run( $owner, 'Invalid selection', $parsed, array( 99 ), $fingerprint ) ), 'Missing selection must fail.' );
        $bad = Parser::parse( "Workout 1\n100 yards free\nTotal: 200 yards" );
        $verify( is_wp_error( Import::run( $owner, 'Mismatched', $bad, array( 1 ), $fingerprint ) ), 'Unverified total must fail.' );
        $imported = Import::run( $owner, 'Synthetic private collection', $parsed, array( 1, 2 ), $fingerprint );
        $verify( ! is_wp_error( $imported ) && 2 === $imported['count'], 'Import two verified candidates.' );
        $structures = Collection::workouts( $owner, $imported['plan_id'] );
        $verify( 2 === count( $structures ), 'Persist both structures.' );
        $verify( 'private' === get_post_status( $imported['plan_id'] ), 'Collection must be private.' );
        foreach ( $structures as $index => $post ) {
            $verify( 'private' === $post->post_status && $owner === (int) $post->post_author, 'Private owner-scoped structure.' );
            $verify( $index + 1 === (int) get_post_meta( $post->ID, Collection::META_SEQUENCE, true ), 'Sequence ordering.' );
        }
        $verify( is_wp_error( Import::run( $owner, 'Duplicate', $parsed, array( 1, 2 ), $fingerprint ) ), 'Duplicate fingerprint must fail.' );
        wp_set_current_user( $users['other'] );
        $verify( is_wp_error( Import::run( $owner, 'Wrong owner', $parsed, array( 1 ), hash( 'sha256', 'other' ) ) ), 'Cannot import as another owner.' );
        $verify( is_wp_error( Store::get( $structures[0]->ID, $users['other'] ) ), 'Foreign structure must be inaccessible.' );
        $verify( is_wp_error( Collection::get( $imported['plan_id'], $users['other'] ) ), 'Foreign collection must be inaccessible.' );
        $own = Import::run( $users['other'], 'Independent owner', $parsed, array( 1 ), $fingerprint );
        $verify( ! is_wp_error( $own ), 'Duplicate fingerprint is owner scoped.' );
        wp_set_current_user( $owner );
    } );
    $run( 'partial import failure rolls back all new posts', function() use ( $verify, $owner, $parsed, $post_ids ) {
        wp_set_current_user( $owner ); $before = count( $post_ids( $owner ) ); $attempt = 0;
        $inject = static function( $empty, $post ) use ( &$attempt ) { return Store::POST_TYPE === $post['post_type'] && 2 === ++$attempt ? true : $empty; };
        add_filter( 'wp_insert_post_empty_content', $inject, 10, 2 );
        try { $result = Import::run( $owner, 'Injected failure', $parsed, array( 1, 2 ), hash( 'sha256', 'insert failure' ) ); }
        finally { remove_filter( 'wp_insert_post_empty_content', $inject, 10 ); }
        $verify( is_wp_error( $result ), 'An inserted-post failure must report failure.' );
        $verify( $before === count( $post_ids( $owner ) ), 'Failed import left partial posts.' );
    } );
    $run( 'metadata failures cannot report a successful import', function() use ( $verify, $owner, $parsed, $post_ids ) {
        foreach ( array( Collection::META_PLAN, Import::META_FINGERPRINT ) as $key ) {
            $before = count( $post_ids( $owner ) );
            $inject = static function( $check, $id, $name ) use ( $key ) { return $key === $name ? false : $check; };
            add_filter( 'update_post_metadata', $inject, 10, 3 );
            try { $result = Import::run( $owner, 'Metadata failure', $parsed, array( 1, 2 ), hash( 'sha256', $key ) ); }
            finally { remove_filter( 'update_post_metadata', $inject, 10 ); }
            $verify( is_wp_error( $result ), 'Failed metadata write was reported as success: ' . $key );
            $verify( $before === count( $post_ids( $owner ) ), 'Metadata failure left imported posts.' );
        }
    } );
    $monday = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+14 days' )->modify( 'monday this week' );
    $run( 'scheduling validation, units, calendar and full seven-day duplication', function() use ( $verify, $owner, &$imported, &$structures, &$original_plans, $monday ) {
        $verify( is_array( $imported ) && 2 === count( $structures ), 'Import prerequisite.' );
        $ids = array_map( static function( $p ) { return (int) $p->ID; }, $structures );
        $before = count( Plan::all_for_user( $owner ) );
        $verify( is_wp_error( Scheduler::schedule( $owner, $imported['plan_id'], array( $ids[0] => '2026-02-30' ) ) ), 'Invalid date accepted.' );
        $yesterday = ( new DateTimeImmutable( 'yesterday', wp_timezone() ) )->format( 'Y-m-d' );
        $verify( is_wp_error( Scheduler::schedule( $owner, $imported['plan_id'], array( $ids[0] => $monday->format( 'Y-m-d' ), $ids[1] => $yesterday ) ) ), 'Past date accepted.' );
        $verify( $before === count( Plan::all_for_user( $owner ) ), 'Validation failure partially scheduled workouts.' );
        $original_plans = Scheduler::schedule( $owner, $imported['plan_id'], array( $ids[0] => $monday->format( 'Y-m-d' ), $ids[1] => $monday->modify( '+6 days' )->format( 'Y-m-d' ) ) );
        $verify( ! is_wp_error( $original_plans ) && 2 === count( $original_plans ), 'Flexible scheduling failed.' );
        $a = Plan::get_for_user( $original_plans[0], $owner );
        $b = Plan::get_for_user( $original_plans[1], $owner );
        $verify( abs( 365.76 - (float) $a->planned_distance_m ) < 0.001 && 'yd' === $a->pool_course, 'Yard conversion failed.' );
        $verify( 400.0 === (float) $b->planned_distance_m && 'm' === $b->pool_course, 'Meter distance changed.' );
        $verify( is_wp_error( Scheduler::schedule( $owner, $imported['plan_id'], array( $ids[0] => $monday->format( 'Y-m-d' ) ) ) ), 'Already-scheduled structure accepted.' );
        update_user_meta( $owner, 'swimlog_calendar_first_day', 'monday' );
        $calendar = Calendar::for_user( $owner, $monday->format( 'Y-m-d' ), $monday->modify( '+6 days' )->format( 'Y-m-d' ) );
        $verify( ! is_wp_error( $calendar ) && false !== strpos( wp_json_encode( $calendar ), $a->title ), 'Calendar did not contain scheduled workouts.' );
        $copies = Plan::duplicate_week( $owner, $monday->format( 'Y-m-d' ), $monday->modify( '+7 days' )->format( 'Y-m-d' ) );
        $verify( ! is_wp_error( $copies ) && 2 === count( $copies ), 'Duplicate Week omitted the seventh day.' );
    } );
    $run( 'schedule link failure removes only new rows and links', function() use ( $verify, $owner, $parsed ) {
        $batch = Import::run( $owner, 'Rollback schedule', $parsed, array( 1, 2 ), hash( 'sha256', 'schedule rollback' ) );
        $verify( ! is_wp_error( $batch ), 'Prepare failure fixture.' );
        $items = Collection::workouts( $owner, $batch['plan_id'] );
        $before = count( Plan::all_for_user( $owner ) );
        $second = (int) $items[1]->ID;
        $inject = static function( $check, $id, $key ) use ( $second ) { return $second === (int) $id && Link::META_KEY === $key ? false : $check; };
        add_filter( 'update_post_metadata', $inject, 10, 3 );
        try { $result = Scheduler::schedule( $owner, $batch['plan_id'], array( $items[0]->ID => wp_date( 'Y-m-d' ), $second => wp_date( 'Y-m-d' ) ) ); }
        finally { remove_filter( 'update_post_metadata', $inject, 10 ); }
        $verify( is_wp_error( $result ), 'Link failure must fail scheduling.' );
        $verify( $before === count( Plan::all_for_user( $owner ) ), 'Schedule rollback left rows.' );
        foreach ( $items as $post ) {
            $verify( ! get_post_meta( $post->ID, Link::META_KEY, true ), 'Schedule rollback left a link.' );
            $verify( ! is_wp_error( Store::get( $post->ID, $owner ) ), 'Rollback removed source structure.' );
        }
    } );
    $run( 'recorded locks survive clearing and deleting plans; copies stay independent', function() use ( $verify, $owner, &$original_plans, &$structures, $monday ) {
        $verify( is_array( $original_plans ) && count( $original_plans ) === 2, 'Schedule prerequisite.' );
        global $wpdb;
        Database::write( 'insert', Database::table( 'workouts' ), array( 'user_id' => $owner, 'workout_start' => $monday->format( 'Y-m-d' ) . ' 12:00:00', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
        $recorded = (int) $wpdb->insert_id;
        $plan = $original_plans[0]; $id = (int) $structures[0]->ID;
        $source = Store::get( $id, $owner );
        $verify( true === Plan::associate_completed_workout( $plan, $owner, $recorded ), 'Match recorded swim.' );
        $verify( true === Store::is_locked( $id, $owner ), 'Recorded prescription not locked.' );
        $verify( is_wp_error( Store::save( $owner, 'Forbidden edit', $source['document'], $id ) ), 'Recorded prescription edited.' );
        $verify( is_wp_error( Link::detach( $plan, $owner ) ), 'Recorded prescription unlinked.' );
        $copy = Plan::duplicate( $plan, $owner, $monday->modify( '+15 days' )->format( 'Y-m-d' ) );
        $verify( ! is_wp_error( $copy ), 'Copy locked prescription to a new plan.' );
        $copy_structure = Link::for_plan( $copy, $owner );
        $verify( ! is_wp_error( $copy_structure ) && $id !== $copy_structure['id'], 'Copy shares original structure.' );
        $verify( ! is_wp_error( Store::save( $owner, 'Editable copy', $copy_structure['document'], $copy_structure['id'] ) ), 'Copy not independently editable.' );
        $verify( $source === Store::get( $id, $owner ), 'Copy modified original.' );
        $verify( true === Plan::clear_completed_workout( $plan, $owner ), 'Clear mistaken completion.' );
        $verify( true === Store::is_locked( $id, $owner ), 'Clearing completion unlocked historical prescription.' );
        $verify( true === Plan::delete( $plan, $owner ), 'Delete scheduled row.' );
        $verify( true === Store::is_locked( $id, $owner ), 'Deleting plan unlocked historical prescription.' );
    } );
    $run( 'REST owner/admin authorization, opt-in, public privacy and read-only routes', function() use ( $verify, $owner, $users, &$original_plans, &$imported ) {
        global $wp_rest_server; $wp_rest_server = null; rest_get_server();
        $request = static function( $user, $method, $route, $params = array() ) {
            wp_set_current_user( $user ); $request = new WP_REST_Request( $method, $route );
            foreach ( $params as $key => $value ) $request->set_param( $key, $value );
            return rest_do_request( $request );
        };
        $params = array( 'user' => $owner, 'start' => wp_date( 'Y-m-d' ), 'end' => wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS ) );
        $verify( 200 === $request( $owner, 'GET', '/swimlog/v1/calendar', $params )->get_status(), 'Owner calendar denied.' );
        $verify( $request( $users['other'], 'GET', '/swimlog/v1/calendar', $params )->get_status() >= 400, 'Foreign calendar exposed.' );
        $verify( 200 === $request( $users['admin'], 'GET', '/swimlog/v1/calendar', $params )->get_status(), 'Privileged admin calendar denied.' );
        $verify( $request( 0, 'GET', '/swimlog/v1/calendar', $params )->get_status() >= 400, 'Anonymous calendar exposed.' );
        $verify( $request( $owner, 'POST', '/swimlog/v1/calendar', $params )->get_status() >= 400, 'Calendar write endpoint exposed.' );
        $verify( $request( $owner, 'GET', '/swimlog/v1/workouts' )->get_status() >= 400, 'External REST opt-in bypassed.' );
        update_user_meta( $owner, 'swimlog_rest_api_access', '1' );
        $verify( 200 === $request( $owner, 'GET', '/swimlog/v1/workouts' )->get_status(), 'Opted-in owner REST denied.' );
        $verify( $request( 0, 'GET', '/wp/v2/posts/' . $imported['plan_id'] )->get_status() >= 400, 'Private plan exposed through core REST.' );
        $verify( ! get_post_type_object( Store::POST_TYPE )->public && ! get_post_type_object( Store::POST_TYPE )->show_in_rest, 'Structures are publicly registered.' );
        $verify( ! get_post_type_object( Collection::POST_TYPE )->public && ! get_post_type_object( Collection::POST_TYPE )->show_in_rest, 'Collections are publicly registered.' );
        wp_set_current_user( $owner );
    } );
} finally {
    global $wpdb;
    foreach ( $users as $id ) {
        foreach ( get_posts( array( 'post_type' => array( Store::POST_TYPE, Collection::POST_TYPE ), 'author' => $id, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post ) wp_delete_post( $post, true );
        foreach ( array( 'planned_workouts', 'workouts' ) as $table ) Database::write( 'delete', Database::table( $table ), array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
    }
    update_option( 'timezone_string', $previous_timezone ); wp_set_current_user( $previous_user );
}
WP_CLI::log( 'Training-plan integration assertions: ' . $checks );
if ( $failures ) WP_CLI::error( implode( "\n", $failures ) );
WP_CLI::success( 'Import, structured-copy/history, scheduling and authorization integration passed.' );
