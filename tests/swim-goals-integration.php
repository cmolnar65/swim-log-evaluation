<?php
// Synthetic WordPress integration: goals, normalized swim totals, owner scoping and calendar boundaries.
use SwimLogEvaluation\{Admin_Goals, Database, Swim_Goals};

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-swim-goals.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';
require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-goals.php';

$checks = 0;
$verify = static function ( $condition, $message ) use ( &$checks ) { ++$checks; if ( ! $condition ) throw new RuntimeException( $message ); };
$owner = wp_create_user( 'swimlog_goals_owner_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
$other = wp_create_user( 'swimlog_goals_other_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
if ( is_wp_error( $owner ) || is_wp_error( $other ) ) throw new RuntimeException( 'Create synthetic goal owners.' );
$owner = (int) $owner; $other = (int) $other;
$old_user = get_current_user_id(); $get = $_GET; $created_workouts = array(); $created_goals = array();
try {
	foreach ( array( $owner, $other ) as $user_id ) ( new WP_User( $user_id ) )->add_cap( 'swimlog_manage_own_workouts' );
	$verify( '1.6' === get_option( 'swimlog_db_version' ) && Database::schema_is_complete(), 'Schema 1.6 goal migration is installed and complete.' );
	$today = wp_date( 'Y-m-d', null, wp_timezone() );
	$past_start = wp_date( 'Y-m-d', time() - ( 3 * DAY_IN_SECONDS ), wp_timezone() );
	$past_middle = wp_date( 'Y-m-d', time() - ( 2 * DAY_IN_SECONDS ), wp_timezone() );
	$past_end = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS, wp_timezone() );
	$due = wp_date( 'Y-m-d', time() + ( 7 * DAY_IN_SECONDS ), wp_timezone() );
	$now = current_time( 'mysql' );
	$insert_workout = static function ( $type, $date, $distance_m ) use ( $owner, $now, &$created_workouts ) {
		global $wpdb;
		Database::write( 'insert', Database::table( 'workouts' ), array( 'user_id' => $owner, 'workout_type' => $type, 'workout_start' => $date . ' 08:00:00', 'total_distance_m' => $distance_m, 'created_at' => $now, 'updated_at' => $now ) );
		$id = (int) $wpdb->insert_id; $created_workouts[] = $id; return $id;
	};
	$insert_workout( 'pool', $past_start, 1000 );
	$insert_workout( 'pool', $past_middle, 914.4 ); // 1,000 yd, normalized in canonical meters.
	$insert_workout( 'open_water', $past_end, 500 );
	$insert_workout( 'running', $past_middle, 5000 );
	$insert_workout( 'pool', $today, 900 );
	$period = Swim_Goals::create( $owner, array( 'goal_type' => Swim_Goals::PERIOD, 'course_unit' => 'm', 'target_distance' => '2000', 'start_date' => $past_start, 'end_date' => $past_end, 'title' => 'Synthetic all-swimming period' ) );
	$verify( ! is_wp_error( $period ), 'Create date-range goal.' ); $created_goals[] = $period['id'];
	$verify( abs( $period['progress_meters'] - 2414.4 ) < 0.001 && 'achieved' === $period['status'], 'Date-range distance includes pool and open-water canonical totals and excludes land/out-of-range distance.' );
	$single = Swim_Goals::create( $owner, array( 'goal_type' => Swim_Goals::SINGLE, 'course_unit' => 'm', 'target_distance_value' => 1500, 'target_pace_ms' => 120000, 'start_date' => $today, 'end_date' => $due, 'title' => 'Synthetic pace goal' ) );
	$verify( ! is_wp_error( $single ), 'Create one-time pace goal.' ); $created_goals[] = $single['id'];
	$one_day = Swim_Goals::create( $owner, array( 'goal_type' => Swim_Goals::PERIOD, 'course_unit' => 'yd', 'target_distance' => '500', 'start_date' => $past_middle, 'end_date' => $past_middle, 'title' => 'Synthetic one-day goal' ) );
	$verify( ! is_wp_error( $one_day ), 'Create one-day period goal.' ); $created_goals[] = $one_day['id'];
	$performance_workout = $insert_workout( 'pool', $today, 1500 );
	Database::write( 'insert', Database::table( 'performances' ), array( 'user_id' => $owner, 'workout_id' => $performance_workout, 'distance_value' => 1500, 'course_unit' => 'm', 'stroke' => 'BR', 'duration_ms' => 1700000, 'evaluation_version' => 'synthetic', 'achieved_at' => $now, 'created_at' => $now ) );
	$single = Swim_Goals::get_for_user( $single['id'], $owner );
	$verify( 'achieved' === $single['status'] && '' === $single['stroke'], 'Matching exact-course performance at any stroke achieves the one-time goal.' );
	$verify( array() === Swim_Goals::list_for_user( $other ), 'Another swimmer cannot list this owner’s goals.' );
	$verify( false === Swim_Goals::delete_for_user( $period['id'], $other ), 'Another swimmer cannot delete this owner’s goal.' );
	$markers = Swim_Goals::calendar_markers( $owner, $past_start, $due );
	$marker_pairs = array(); foreach ( $markers as $marker ) $marker_pairs[] = $marker['id'] . ':' . $marker['marker'];
	$verify( in_array( $period['id'] . ':goal_start', $marker_pairs, true ) && in_array( $period['id'] . ':goal_end', $marker_pairs, true ) && in_array( $single['id'] . ':goal_due', $marker_pairs, true ) && in_array( $one_day['id'] . ':goal_start_end', $marker_pairs, true ), 'Private calendar includes date-range first/last days, one-day range and the one-time due date.' );
	wp_set_current_user( $owner ); $_GET = array( 'page' => 'swimlog-goals' ); ob_start(); Admin_Goals::page(); $page = ob_get_clean();
	$verify( false !== strpos( $page, 'Set Goal' ) && false !== strpos( $page, 'List Goals' ) && false !== strpos( $page, 'Synthetic all-swimming period' ) && false !== strpos( $page, 'Synthetic pace goal' ), 'Goals page lists both private goals and their progress.' );
	$verify( Swim_Goals::delete_for_user( $period['id'], $owner ) && Swim_Goals::delete_for_user( $single['id'], $owner ) && Swim_Goals::delete_for_user( $one_day['id'], $owner ), 'Owner can remove their goals.' );
	$created_goals = array();
	WP_CLI::success( 'Synthetic swim goal storage, progress, ownership and calendar checks passed ' . $checks . ' assertions.' );
} finally {
	wp_set_current_user( $owner );
	foreach ( $created_goals as $goal_id ) Swim_Goals::delete_for_user( $goal_id, $owner );
	foreach ( $created_workouts as $workout_id ) {
		Database::write( 'delete', Database::table( 'performances' ), array( 'user_id' => $owner, 'workout_id' => $workout_id ) );
		Database::write( 'delete', Database::table( 'workouts' ), array( 'user_id' => $owner, 'id' => $workout_id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $owner ); wp_delete_user( $other );
	$_GET = $get; wp_set_current_user( $old_user );
}
