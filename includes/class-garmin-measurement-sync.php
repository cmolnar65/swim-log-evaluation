<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Opt-in owner-scoped measurement jobs. No public route or posted owner ID. */
final class Garmin_Measurement_Sync {
    const HOOK = 'swimlog_garmin_measurement_sync';
    const RETRY_HOOK = 'swimlog_garmin_measurement_continue';
    const PREF = 'swimlog_garmin_measurement_categories';
    const GENERATION = 'swimlog_garmin_measurement_generation';
    const STATE = 'swimlog_garmin_measurement_state';

    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( self::RETRY_HOOK, array( __CLASS__, 'run' ) );
        add_action( 'deleted_user', array( __CLASS__, 'clear' ) );
    }

    public static function categories( $owner ) {
        $saved = get_user_meta( $owner, self::PREF, true );
        return is_array( $saved ) ? array_values( array_intersect( array( 'weight', 'body_composition', 'spo2' ), $saved ) ) : array();
    }

    /** Called only for the current owner after the settings form's nonce check. */
    public static function configure( $categories ) {
        $owner = (int) get_current_user_id();
        if ( ! $owner || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return false;
        if ( ! is_array( $categories ) || count( $categories ) > 3 ) return false;
        foreach ( $categories as $category ) {
            if ( ! is_string( $category ) || ! in_array( $category, array( 'weight', 'body_composition', 'spo2' ), true ) ) return false;
        }
        update_user_meta( $owner, self::GENERATION, wp_generate_uuid4() );
        self::clear( $owner );
        $categories = array_values( array_unique( $categories ) );
        if ( ! $categories ) {
            delete_user_meta( $owner, self::PREF );
            return true;
        }
        update_user_meta( $owner, self::PREF, $categories );
        $scheduled = wp_schedule_event( time() + 300, 'hourly', self::HOOK, array( $owner ), true );
        if ( is_wp_error( $scheduled ) || ! $scheduled ) {
            delete_user_meta( $owner, self::PREF );
            return false;
        }
        return true;
    }

    public static function clear( $owner ) {
        wp_clear_scheduled_hook( self::HOOK, array( (int) $owner ) );
        wp_clear_scheduled_hook( self::RETRY_HOOK, array( (int) $owner ) );
        delete_user_meta( (int) $owner, self::STATE );
    }

    public static function deactivate() {
        wp_unschedule_hook( self::HOOK );
        wp_unschedule_hook( self::RETRY_HOOK );
    }

    private static function later( $owner, $seconds ) {
        $args = array( $owner );
        if ( ! wp_next_scheduled( self::RETRY_HOOK, $args ) ) {
            return wp_schedule_single_event( time() + max( 60, min( 86400, $seconds ) ), self::RETRY_HOOK, $args, true );
        }
        return true;
    }

    /** Core cron is the only entry point that may establish an owner's job context. */
    public static function run( $owner ) {
        if ( ! wp_doing_cron() || ! is_int( $owner ) || $owner < 1 ) return;
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
        $categories = self::categories( $owner );
        $generation = get_user_meta( $owner, self::GENERATION, true );
        if ( ! $categories || ! user_can( $owner, 'swimlog_manage_own_workouts' ) || ! Garmin_Personal_API::is_configured( $owner ) ) {
            self::clear( $owner );
            return;
        }
        // Connection-level lock prevents overlapping cron workers from consuming the same cursor.
        global $wpdb;
        $lock = 'swimlog_health_job_' . md5( $wpdb->prefix . ':' . $owner );
        $acquired = (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
        if ( 1 !== $acquired ) return;
        $previous = (int) get_current_user_id();
        try {
            // Store/API ownership checks remain unchanged. This identity is limited to the cron job.
            wp_set_current_user( $owner );
            $today = wp_date( 'Y-m-d', null, wp_timezone() );
            $state = get_user_meta( $owner, self::STATE, true );
            if ( ! is_array( $state ) || ( $state['categories'] ?? array() ) !== $categories || empty( $state['pending'] ) ) {
                $end = new \DateTimeImmutable( $today, new \DateTimeZone( 'UTC' ) );
                $days = array();
                // Latest day first; continuation walks all seven days without starving older dates.
                for ( $i = 0; $i < 7; ++$i ) $days[] = $end->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
                $state = array( 'categories' => $categories, 'pending' => $days, 'results' => array(), 'retries' => 0, 'started_utc' => gmdate( 'Y-m-d H:i:s' ) );
            }
            $day = $state['pending'][0];
            $state['attempt_utc'] = gmdate( 'Y-m-d H:i:s' );
            $rows = Garmin_Measurement_Import::run( $owner, $day, $day, $categories );
            $failed = is_wp_error( $rows );
            if ( $failed ) {
                $rows = array();
                foreach ( $categories as $category ) $rows[] = array( 'date' => $day, 'category' => $category, 'status' => 'failed', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 1, 'reports_added' => 0, 'reports_updated' => 0, 'reports_skipped' => 0 );
            }
            $delay = 300 * ( 1 << min( 2, (int) $state['retries'] ) );
            if ( is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    // Persist only normalized counts/status; never payloads, credentials or values.
                    $safe = array_intersect_key( $row, array_flip( array( 'date', 'category', 'status', 'added', 'updated', 'skipped', 'failed', 'excluded', 'reports_added', 'reports_updated', 'reports_skipped', 'retry_after' ) ) );
                    $state['results'][ $day . ':' . $row['category'] ] = $safe;
                    if ( in_array( $row['status'], array( 'failed', 'not_attempted' ), true ) ) $failed = true;
                    if ( isset( $row['retry_after'] ) ) $delay = max( $delay, (int) $row['retry_after'] );
                }
            }
            if ( $failed ) {
                ++$state['retries'];
                $state['status'] = 'retrying';
            } else {
                $state['retries'] = 0;
                $state['status'] = 'running';
            }
            // Three attempts per day/window; retain the failed result and revisit next hourly cycle.
            if ( ! $failed || $state['retries'] >= 3 ) {
                array_shift( $state['pending'] );
                $state['retries'] = 0;
            }
            if ( ! $state['pending'] ) {
                $state['status'] = 'complete';
                foreach ( $state['results'] as $row ) {
                    if ( in_array( $row['status'], array( 'failed', 'not_attempted' ), true ) ) $state['status'] = 'complete_with_errors';
                }
                if ( ! empty( $state['job_errors'] ) ) $state['status'] = 'complete_with_errors';
                wp_clear_scheduled_hook( self::RETRY_HOOK, array( $owner ) );
                $state['finished_utc'] = gmdate( 'Y-m-d H:i:s' );
            }
            wp_cache_delete( $owner, 'user_meta' );
            // Settings changed during HTTP: do not resurrect a disabled/changed schedule.
            if ( self::categories( $owner ) !== $categories || get_user_meta( $owner, self::GENERATION, true ) !== $generation ) return;
            update_user_meta( $owner, self::STATE, $state );
            if ( $state['pending'] ) {
                $scheduled = self::later( $owner, $failed ? $delay : 60 );
                if ( is_wp_error( $scheduled ) || ! $scheduled ) {
                    $state['status'] = 'schedule_error';
                    update_user_meta( $owner, self::STATE, $state );
                }
            }
        } catch ( \Throwable $error ) {
            // Extensions/transport can throw. Preserve data and expose a safe job error, not its message.
            wp_cache_delete( $owner, 'user_meta' );
            if ( self::categories( $owner ) === $categories && get_user_meta( $owner, self::GENERATION, true ) === $generation && isset( $state ) ) {
                $state['status'] = 'job_error';
                if ( isset( $day ) ) $state['job_errors'][ $day ] = true;
                $state['attempt_utc'] = gmdate( 'Y-m-d H:i:s' );
                $state['retries'] = 1 + (int) ( $state['retries'] ?? 0 );
                if ( $state['retries'] >= 3 ) {
                    array_shift( $state['pending'] );
                    $state['retries'] = 0;
                }
                update_user_meta( $owner, self::STATE, $state );
                if ( $state['pending'] ) self::later( $owner, 300 );
            }
        } finally {
            wp_set_current_user( $previous );
            Database::read( 'get_var', $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
}
