<?php
// End-to-end synthetic Garmin -> manual/scheduled persistence -> private calendar.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Garmin_Personal_API as API;
use SwimLogEvaluation\Garmin_Measurement_Import as Importer;
use SwimLogEvaluation\Garmin_Measurement_Sync as Sync;
use SwimLogEvaluation\Calendar_Measurements as Calendar;
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-calendar-measurements.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-sync.php';
global $wpdb;
$previous = (int) get_current_user_id();
$timezone = get_option( 'timezone_string' );
$users = array();
$checks = 0;
$mode = 'normal';
$day = '2025-11-02';
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$http = static function ( $preempt, $args, $url ) use ( &$mode, $day ) {
    if ( 0 !== strpos( $url, API::API_BASE ) ) throw new RuntimeException( 'Unexpected endpoint.' );
    if ( false !== strpos( $url, 'personal-api-key/token' ) ) return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'synthetic.pipeline.token' );
    $weight = false !== strpos( $url, 'weight/all/' );
    $sample = array( 'calendarDate' => $day, 'timestampGMT' => '2025-11-02T07:30:00Z', 'date' => $day . 'T01:30:00', 'weight' => 82.5, 'bmi' => 24.1 );
    $repeat_hour = $sample; $repeat_hour['timestampGMT'] = '2025-11-02T08:30:00Z';
    $midnight = $sample; $midnight['timestampGMT'] = '2025-11-03T06:30:00Z'; $midnight['date'] = $day . 'T23:30:00';
    $body = $weight ? wp_json_encode( array( $sample, $sample, $repeat_hour, $midnight ) ) : wp_json_encode( array(
        'calendarDate' => $day, 'averageSpO2' => 'corrected' === $mode ? 97.2 : 96.4, 'avgSleepSpO2' => 95.8,
        'lastSevenDaysAvgSpO2' => 96.8, 'startTimestampGMT' => '2025-11-02T06:00:00', 'endTimestampGMT' => '2025-11-03T07:00:00',
        'spO2SingleValues' => array( array( 1762068600000, 94 ), array( 1762072200000, 98 ) ),
    ) );
    if ( 'opt_out' === $mode ) Sync::configure( array() );
    return array( 'response' => array( 'code' => ! $weight && 'partial_failure' === $mode ? 500 : 200 ), 'headers' => array(), 'body' => $body );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    foreach ( array( 'owner', 'other' ) as $name ) {
        $id = wp_create_user( 'swimlog_pipeline_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        $verify( ! is_wp_error( $id ), 'Create isolated pipeline user.' );
        $users[$name] = (int) $id;
        $user = new WP_User( $id ); $user->add_cap( 'swimlog_manage_own_workouts' ); $user->add_cap( 'swimlog_view_own_results' );
    }
    $owner = $users['owner'];
    wp_set_current_user( $owner );
    API::save_key( $owner, 'synthetic-pipeline-key' );
    update_option( 'timezone_string', 'America/Denver' );
    $result = Importer::run( $owner, $day, $day, array( 'weight', 'body_composition', 'spo2' ) );
    $verify( 3 === $result[0]['added'] && 1 === $result[0]['skipped'], 'Manual import deduplicates repeated stream item, retaining equal values at distinct instants.' );
    $verify( 3 === $result[1]['added'] && 1 === $result[1]['skipped'], 'BMI retains independent observations with verified units.' );
    $verify( 1 === $result[2]['reports_added'] && 0 === $result[2]['added'] && 2 === $result[2]['excluded'], 'SpO2 provider report only; no invented samples or mean.' );
    $view = Calendar::for_user( $owner, $day, '2025-11-03' );
    $verify( 1 === count( $view['days'] ) && $day === $view['days'][0]['date'], 'Provider date controls placement even when UTC crosses midnight.' );
    $visible = $view['days'][0];
    $verify( 6 === count( $visible['readings'] ) && 1 === count( $visible['reports'] ) && ! $visible['aggregates'], 'Full pipeline preserves role and category separation.' );
    $weights = array_values( array_filter( $visible['readings'], static function ( $row ) { return 'weight' === $row['category']; } ) );
    $verify( $weights[0]['source_local_time'] === $weights[1]['source_local_time'] && $weights[0]['time_utc'] !== $weights[1]['time_utc'], 'Repeated local DST hour remains distinct in final calendar projection.' );
    foreach ( $weights as $row ) $verify( '82.5' === $row['values']['weight']['value'] && 'kg' === $row['values']['weight']['unit'] && ! $row['has_conflict'], 'Equal values at different times are not conflicts or unit conversions.' );
    $values = $visible['reports'][0]['values'];
    $verify( '96.4' === $values['average']['value'] && '95.8' === $values['sleep_average']['value'] && '96.8' === $values['seven_day_average']['value'], 'Provider/day, sleep and baseline summaries are kept separate.' );
    update_option( 'timezone_string', 'Pacific/Auckland' );
    $verify( $view === Calendar::for_user( $owner, $day, '2025-11-03' ), 'Changing site timezone does not move imported observations.' );

    $seed_job = static function () use ( $owner, $day ) {
        wp_set_current_user( $owner );
        Sync::configure( array( 'weight', 'body_composition', 'spo2' ) );
        // Deterministic historical job cursor exercises the real callback on the same imported day.
        update_user_meta( $owner, Sync::STATE, array( 'categories' => Sync::categories( $owner ), 'pending' => array( $day ), 'results' => array(), 'retries' => 0 ) );
        wp_set_current_user( 0 );
    };
    if ( ! defined( 'DOING_CRON' ) ) define( 'DOING_CRON', true );
    $seed_job(); do_action( Sync::HOOK, $owner );
    $state = get_user_meta( $owner, Sync::STATE, true );
    $verify( 0 === get_current_user_id() && 'complete' === $state['status'], 'Actual cron callback completes and restores anonymous context.' );
    $verify( 0 === $state['results'][$day . ':weight']['added'] && 4 === $state['results'][$day . ':weight']['skipped'], 'Scheduled overlap with manual import adds no duplicates.' );
    wp_set_current_user( $owner );
    $verify( $view === Calendar::for_user( $owner, $day, '2025-11-03' ), 'Manual then scheduled import displays each observation only once.' );

    $mode = 'partial_failure';
    $seed_job(); do_action( Sync::HOOK, $owner );
    $state = get_user_meta( $owner, Sync::STATE, true );
    $verify( 'complete' === $state['results'][$day . ':weight']['status'] && 'failed' === $state['results'][$day . ':spo2']['status'], 'Partial job failure reports category-level success and failure.' );
    wp_set_current_user( $owner );
    $verify( $view === Calendar::for_user( $owner, $day, '2025-11-03' ), 'Failure leaves calendar data intact.' );

    $mode = 'corrected';
    Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET fetched_at_utc = %s WHERE user_id = %d', Database::table( 'health_reports' ), '2020-01-01 00:00:00', $owner ) );
    $seed_job(); do_action( Sync::HOOK, $owner );
    wp_set_current_user( $owner );
    $corrected = Calendar::for_user( $owner, $day, $day );
    $verify( 1 === count( $corrected['days'][0]['reports'] ) && '97.2' === $corrected['days'][0]['reports'][0]['values']['average']['value'], 'Scheduled provider correction replaces report without an extra calendar entry.' );
    $verify( 6 === count( $corrected['days'][0]['readings'] ), 'Report correction preserves all distinct readings.' );
    $verify( false === strpos( wp_json_encode( $corrected ), 'synthetic-pipeline-key' ) && false === strpos( wp_json_encode( $corrected ), 'synthetic.pipeline.token' ), 'Calendar projection excludes credentials.' );
    wp_set_current_user( $users['other'] );
    $verify( is_wp_error( Calendar::for_user( $owner, $day, $day ) ), 'Foreign owner cannot read imported measurements.' );
    $verify( array() === Calendar::for_user( $users['other'], $day, $day )['days'], 'Other owner has independent empty calendar.' );

    $mode = 'opt_out';
    $seed_job(); do_action( Sync::HOOK, $owner );
    $verify( ! Sync::categories( $owner ) && ! get_user_meta( $owner, Sync::STATE, true ) && ! wp_next_scheduled( Sync::RETRY_HOOK, array( $owner ) ) && ! wp_next_scheduled( Sync::HOOK, array( $owner ) ), 'Opt-out during HTTP cannot resurrect state or tasks.' );
    wp_set_current_user( $owner );
    $verify( 6 === count( Calendar::for_user( $owner, $day, $day )['days'][0]['readings'] ), 'In-flight opt-out preserves imported measurements.' );
    WP_CLI::success( 'Measurement pipeline passed ' . $checks . ' assertions: manual/scheduled overlap, DST, UTC day boundary, units, SpO2 roles, correction, partial failures, privacy and in-flight opt-out.' );
} finally {
    remove_filter( 'pre_http_request', $http, 10 );
    update_option( 'timezone_string', $timezone );
    foreach ( $users as $id ) {
        Sync::clear( $id ); delete_transient( 'swimlog_health_retry_' . $id );
        foreach ( array( 'health_report_members', 'health_measurements', 'health_reports' ) as $name ) Database::write( 'delete', Database::table( $name ), array( 'user_id' => $id ) );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
    }
    wp_set_current_user( $previous );
}
