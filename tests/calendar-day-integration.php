<?php
// Synthetic data only: actual activated staging renderer and privacy boundaries.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Calendar;
use SwimLogEvaluation\Blocks;
use SwimLogEvaluation\Planned_Workout as Plan;
use SwimLogEvaluation\Event;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-calendar.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';
$old_user = get_current_user_id(); $users = array(); $checks = 0;
$verify = static function( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
try {
    global $wpdb;
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_day_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create day-view fixture owner.' ); $users[$name] = $id;
        $user = new WP_User( $id ); $user->add_cap( 'swimlog_manage_own_workouts' ); $user->add_cap( 'swimlog_view_own_results' ); $user->add_cap( 'swimlog_manage_own_events' );
    }
    $owner = $users['owner']; wp_set_current_user( $owner );
    update_user_meta( $owner, 'swimlog_calendar_first_day', 'monday' );
    $focus = ( new DateTimeImmutable( 'first day of next month', wp_timezone() ) )->modify( '+10 days' ); $key = $focus->format( 'Y-m-d' );
    foreach ( array( 'pool', 'open_water' ) as $index => $type ) {
        $ok = Database::write( 'insert', Database::table( 'workouts' ), array( 'user_id' => $owner, 'workout_start' => $key . ( $index ? ' 14:00:00' : ' 08:00:00' ), 'workout_type' => $type, 'total_distance_m' => $index ? 1200 : 600, 'elapsed_time_ms' => 1800000, 'moving_time_ms' => 1700000, 'pool_length_m' => $index ? null : 25, 'pool_length_unit' => 'm', 'primary_stroke' => 'FR', 'notes' => 'PRIVATE_DAY_SOURCE_SENTINEL', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
        $verify( false !== $ok, 'Create recorded swim.' );
    }
    foreach ( array( 'running' => 5000, 'walking' => 300 ) as $type => $meters ) {
        $verify( false !== Database::write( 'insert', Database::table( 'workouts' ), array( 'user_id' => $owner, 'workout_start' => $key . ( 'running' === $type ? ' 15:00:00' : ' 16:00:00' ), 'workout_type' => $type, 'activity_environment' => 'outdoor', 'total_distance_m' => $meters, 'elapsed_time_ms' => 900000, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) ), 'Create private land activity.' );
    }
    for ( $i = 1; $i <= 12; ++$i ) {
        $id = Plan::save( $owner, array( 'title' => 'Synthetic plan ' . $i . ' <script>not executable</script>', 'planned_date' => $key, 'workout_type' => 'pool', 'planned_distance_m' => 500, 'pool_course' => 'm', 'pool_length_m' => 25, 'primary_stroke' => 'FR', 'workout_instructions' => str_repeat( 'Synthetic technique instructions. ', 25 ), 'notes' => 'PRIVATE_DAY_PLAN_SENTINEL' ) );
        $verify( ! is_wp_error( $id ), 'Create scrollable day plan fixture.' );
    }
    $event = Event::save( $owner, array( 'event_name' => 'Synthetic swim meet', 'event_date' => $key, 'event_time' => '16:00', 'distance_value' => 100, 'course_unit' => 'm', 'stroke' => 'FR' ) );
    $verify( ! is_wp_error( $event ), 'Create day event.' );
    $start = $focus->modify( 'first day of this month' ); $end = $focus->modify( 'last day of this month' );
    $data = Calendar::for_user( $owner, $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), true );
    $verify( ! is_wp_error( $data ) && 4 === count( $data['workouts'] ) && 12 === count( $data['planned_workouts'] ) && 1 === count( $data['events'] ), 'All permitted day items in existing calendar payload.' );
    $verify( 25.0 === $data['workouts'][0]['pool_length_m'] && null === $data['workouts'][1]['pool_length_m'], 'Calendar retains measured pool length and unknown open-water length.' );
    set_error_handler( static function ( $severity, $message, $file, $line ) {
        if ( error_reporting() & $severity ) throw new ErrorException( $message, 0, $severity, $file, $line );
        return false;
    } );
    try {
        $html = Blocks::render_calendar_for_user( $owner, array( 'year' => (int) $focus->format( 'Y' ), 'month' => (int) $focus->format( 'n' ) ) );
    } finally {
        restore_error_handler();
    }
    preg_match_all( '/data-calendar-workout="([^"]*)"/', $html, $cards );
    $details = array_map( static function ( $encoded ) { return json_decode( html_entity_decode( $encoded, ENT_QUOTES, 'UTF-8' ), true ); }, $cards[1] );
    $verify( 4 === count( $details ) && 25.0 === (float) $details[0]['pool_length_m'] && array_key_exists( 'pool_length_m', $details[1] ) && null === $details[1]['pool_length_m'], 'Rendered recorded cards preserve pool-length detail without warnings.' );
    $verify( false !== strpos( $html, 'data-calendar-day="' . $key . '"' ) && false !== strpos( $html, 'aria-haspopup="dialog"' ), 'Server dates are keyboard/touch dialog triggers.' );
    $verify( false === strpos( $html . wp_json_encode( $data ), 'PRIVATE_DAY_' ), 'Private notes leaked into calendar/day payload.' );
    wp_set_current_user( $users['other'] );
    $verify( false === strpos( Blocks::render_calendar_for_user( $owner ), 'data-calendar-day=' ), 'Foreign calendar exposed day triggers.' );
    wp_set_current_user( 0 );
    $verify( false === strpos( Blocks::render_calendar_for_user( $owner ), 'data-calendar-day=' ), 'Anonymous calendar exposed day triggers.' );
    WP_CLI::log( 'SWIMLOG_DAY_CALENDAR_HTML_BASE64=' . base64_encode( $html ) );
    WP_CLI::log( 'SWIMLOG_DAY_CALENDAR_DATA_BASE64=' . base64_encode( wp_json_encode( array( 'calendar' => $data, 'date' => $key, 'empty_date' => $focus->modify( '+1 day' )->format( 'Y-m-d' ) ) ) ) );
    WP_CLI::success( 'Calendar day rendering and privacy passed ' . $checks . ' assertions.' );
} finally {
    foreach ( $users as $id ) {
        foreach ( array( 'planned_workouts', 'workouts', 'events' ) as $table ) Database::write( 'delete', Database::table( $table ), array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
    }
    wp_set_current_user( $old_user );
}
