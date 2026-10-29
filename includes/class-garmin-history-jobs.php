<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Private non-autoloaded job ledger in existing WP options; never registered with REST. */
final class Garmin_History_Jobs {
    const HOOK = 'swimlog_garmin_history_job';
    private static function error( $code ) {
        $messages = array(
            'input' => __( 'Choose valid inclusive dates ending no later than today and at least one supported category.', 'chriss-swim-training-progress-evaluation' ),
            'active' => __( 'You already have an unfinished historical job. Resume or cancel it before starting another.', 'chriss-swim-training-progress-evaluation' ),
            'connection' => __( 'Save your Garmin Personal API key before starting a historical import.', 'chriss-swim-training-progress-evaluation' ),
            'forbidden' => __( 'You do not have permission for this historical import.', 'chriss-swim-training-progress-evaluation' ),
            'state' => __( 'That control is not available for this job status. Refresh the job and choose an available control.', 'chriss-swim-training-progress-evaluation' ),
            'busy' => __( 'Another job control is being saved. Retry shortly.', 'chriss-swim-training-progress-evaluation' )
        );
        return new \WP_Error( 'swimlog_history_job_' . $code, $messages[$code] ?? __( 'Job progress could not be saved. Existing imports were retained. Check the job status and resume to retry safely.', 'chriss-swim-training-progress-evaluation' ) );
    }
    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 2 );
        add_action( 'deleted_user', array( __CLASS__, 'delete_owner' ) );
    }
    private static function owner() {
        $id = (int) get_current_user_id();
        return $id && is_user_logged_in() && current_user_can( 'swimlog_manage_own_workouts' ) ? $id : 0;
    }
    private static function id_valid( $id ) { return is_string( $id ) && (bool) preg_match( '/^[a-f0-9]{32}$/D', $id ); }
    private static function job_key( $owner, $id ) { return 'swimlog_hj_' . $owner . '_' . $id; }
    private static function unit_key( $owner, $id, $key ) { return 'swimlog_hu_' . $owner . '_' . $id . '_' . $key; }
    private static function pointer( $owner ) { return 'swimlog_ha_' . $owner; }
    private static function read( $key ) {
        global $wpdb;
        $value = Database::read( 'get_var', $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s FOR UPDATE', $wpdb->options, $key ) );
        return is_string( $value ) ? json_decode( $value, true, 64 ) : null;
    }
    private static function write( $key, $value ) {
        global $wpdb;
        $json = wp_json_encode( $value );
        if ( ! is_string( $json ) || strlen( $json ) > 2097152 ) throw new \RuntimeException( 'capacity' );
        $ok = Database::write( 'query', $wpdb->prepare( 'INSERT INTO %i (option_name,option_value,autoload) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)', $wpdb->options, $key, $json, 'no' ) );
        if ( false === $ok ) throw new \RuntimeException( 'storage' );
        wp_cache_delete( $key, 'options' );
    }
    private static function lock( $owner, $worker = false, $release = false ) {
        global $wpdb;
        $name = 'swimlog_h_' . ( $worker ? 'worker_' : 'control_' ) . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner ), 0, 40 );
        if ( $release ) return 1 === (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
        return 1 === (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
    }
    /** Only short metadata mutations run in this transaction; HTTP/imports run outside it. */
    private static function mutate( $owner, $callback ) {
        if ( ! self::lock( $owner ) ) return self::error( 'busy' );
        $transaction = false;
        try {
            if ( (int) Database::read( 'get_var', 'SELECT @@session.in_transaction' ) ) return self::error( 'transaction' );
            global $wpdb;
            $engine = Database::read( 'get_row', $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->options ) ) );
            if ( ! $engine || 'InnoDB' !== $engine->Engine ) return self::error( 'engine' );
            if ( false === Database::write( 'query', 'START TRANSACTION' ) ) return self::error( 'storage' );
            $transaction = true;
            $result = $callback();
            if ( is_wp_error( $result ) ) { Database::write( 'query', 'ROLLBACK' ); $transaction = false; return $result; }
            if ( false === Database::write( 'query', 'COMMIT' ) ) throw new \RuntimeException( 'storage' );
            $transaction = false;
            return $result;
        } catch ( \Throwable $error ) {
            if ( $transaction ) Database::write( 'query', 'ROLLBACK' );
            return self::error( 'capacity' === $error->getMessage() ? 'capacity' : 'storage' );
        } finally { self::lock( $owner, false, true ); }
    }
    private static function unfinished( $job ) {
        return is_array( $job ) && ! in_array( $job['status'], array( 'completed', 'completed_with_errors', 'cancelled' ), true );
    }
    private static function runnable( $job ) { return in_array( $job['status'], array( 'queued', 'running', 'waiting' ), true ); }
    private static function allowed( $job, $owner ) {
        return is_array( $job ) && (int) $job['owner'] === $owner && self::id_valid( $job['id'] );
    }
    private static function enqueue( &$job, $kind, $data ) {
        $identity = 'activity' === $kind ? 'activity:' . $data['activity_id'] : ( 'page' === $kind ? 'page:' . $data['cursor']['day'] . ':' . $data['cursor']['offset'] : 'measurement:' . $data['date'] );
        $key = hash( 'sha256', $identity );
        $name = self::unit_key( $job['owner'], $job['id'], $key );
        if ( self::read( $name ) ) return;
        if ( count( $job['units'] ) >= 20000 ) throw new \RuntimeException( 'capacity' );
        self::write( $name, array( 'key' => $key, 'kind' => $kind, 'data' => $data, 'status' => 'queued', 'attempts' => 0, 'outcomes' => array(), 'error' => '' ) );
        $job['units'][] = $key; $job['statuses'][$key] = 'queued'; $job['ready'][] = $key;
        if ( 'activity' === $kind ) ++$job['counts']['discovered'];
    }
    private static function categories( $selected ) {
        if ( ! is_array( $selected ) || ! $selected || count( $selected ) > 5 ) return false;
        foreach ( $selected as $category ) if ( ! is_string( $category ) || ! in_array( $category, array( 'lap_swimming', 'open_water_swimming', 'weight', 'body_composition', 'spo2' ), true ) ) return false;
        return array_values( array_unique( $selected ) );
    }
    public static function start( $start, $end, $selected ) {
        $owner = self::owner();
        if ( ! $owner ) return self::error( 'forbidden' );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
        $cursor = Garmin_History::cursor( $start, $end ); $selected = self::categories( $selected );
        if ( is_wp_error( $cursor ) || ! $selected ) return self::error( 'input' );
        $types = array_values( array_intersect( $selected, array( 'lap_swimming', 'open_water_swimming' ) ) );
        if ( $types && ! current_user_can( 'swimlog_upload_workouts' ) ) return self::error( 'forbidden' );
        if ( ! Garmin_Personal_API::is_configured( $owner ) ) return self::error( 'connection' );
        $result = self::mutate( $owner, static function () use ( $owner, $start, $end, $selected, $cursor, $types ) {
            $active = self::read( self::pointer( $owner ) );
            if ( is_string( $active ) && self::unfinished( self::read( self::job_key( $owner, $active ) ) ) ) return self::error( 'active' );
            $id = str_replace( '-', '', wp_generate_uuid4() );
            $job = array( 'id' => $id, 'owner' => $owner, 'start' => $start, 'end' => $end, 'selected' => $selected, 'types' => $types, 'measurements' => array_values( array_diff( $selected, $types ) ),
                'status' => 'queued', 'generation' => 1, 'created_utc' => gmdate( 'Y-m-d H:i:s' ), 'updated_utc' => gmdate( 'Y-m-d H:i:s' ), 'not_before' => 0, 'error' => '',
                'units' => array(), 'statuses' => array(), 'ready' => array(), 'failed' => array(), 'counts' => array( 'discovered' => 0, 'activity_days' => 0, 'measurement_days' => 0 ) );
            if ( $types ) self::enqueue( $job, 'page', array( 'cursor' => $cursor ) );
            if ( $job['measurements'] ) self::enqueue( $job, 'measurement', array( 'date' => $start, 'pending' => $job['measurements'] ) );
            self::write( self::job_key( $owner, $id ), $job ); self::write( self::pointer( $owner ), $id );
            return $job;
        } );
        if ( ! is_wp_error( $result ) ) { self::arm( $owner, $result['id'], 60 ); return self::get( $result['id'] ); }
        return $result;
    }
    public static function get( $id = null ) {
        $owner = self::owner(); if ( ! $owner ) return self::error( 'forbidden' );
        if ( null === $id ) $id = self::read( self::pointer( $owner ) );
        if ( ! self::id_valid( $id ) ) return null;
        $job = self::read( self::job_key( $owner, $id ) );
        return self::allowed( $job, $owner ) ? $job : null;
    }
    public static function recent() {
        $owner = self::owner(); if ( ! $owner ) return array();
        global $wpdb;
        $rows = Database::read( 'get_col', $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 10', $wpdb->options, $wpdb->esc_like( 'swimlog_hj_' . $owner . '_' ) . '%' ) );
        $out = array();
        foreach ( (array) $rows as $row ) { $job = json_decode( $row, true ); if ( self::allowed( $job, $owner ) ) $out[] = $job; }
        return $out;
    }
    public static function units( $id, $offset = 0, $filter = 'all' ) {
        $job = self::get( $id ); if ( ! is_array( $job ) || ! is_int( $offset ) || $offset < 0 ) return array();
        $keys = array();
        foreach ( $job['units'] as $key ) {
            $status = $job['statuses'][$key] ?? '';
            if ( 'failed' === $filter && 'failed' !== $status ) continue;
            if ( 'review' === $filter && 'review' !== $status ) continue;
            $keys[] = $key;
        }
        $items = array();
        foreach ( array_slice( $keys, $offset, 20 ) as $key ) {
            $unit = self::read( self::unit_key( $job['owner'], $id, $key ) );
            if ( is_array( $unit ) ) $items[] = $unit;
        }
        return array( 'total' => count( $keys ), 'items' => $items );
    }

    public static function control( $id, $action ) {
        $owner = self::owner();
        if ( ! $owner || ! self::id_valid( $id ) || ! in_array( $action, array( 'pause', 'resume', 'cancel', 'retry' ), true ) ) return self::error( 'forbidden' );
        $result = self::mutate( $owner, static function () use ( $owner, $id, $action ) {
            $job = self::read( self::job_key( $owner, $id ) );
            if ( ! self::allowed( $job, $owner ) || 'cancelled' === $job['status'] ) return self::error( 'state' );
            if ( 'pause' === $action ) {
                if ( ! self::runnable( $job ) ) return self::error( 'state' );
                $job['status'] = 'paused';
            } elseif ( 'cancel' === $action ) {
                if ( ! self::unfinished( $job ) ) return self::error( 'state' );
                $job['status'] = 'cancelled';
            } else {
                $active = self::read( self::pointer( $owner ) );
                if ( $active !== $id && is_string( $active ) && self::unfinished( self::read( self::job_key( $owner, $active ) ) ) ) return self::error( 'active' );
                if ( 'retry' === $action ) {
                    if ( 'completed_with_errors' !== $job['status'] || ! $job['failed'] ) return self::error( 'state' );
                    foreach ( $job['failed'] as $key ) {
                        $unit = self::read( self::unit_key( $owner, $id, $key ) );
                        $unit['status'] = 'queued'; $job['statuses'][$key] = 'queued'; $unit['attempts'] = 0; $unit['error'] = '';
                        self::write( self::unit_key( $owner, $id, $key ), $unit ); $job['ready'][] = $key;
                    }
                    $job['failed'] = array();
                } else {
                    if ( ! self::unfinished( $job ) ) return self::error( 'state' );
                    if ( $job['ready'] ) {
                        $key = $job['ready'][0]; $unit = self::read( self::unit_key( $owner, $id, $key ) );
                        $unit['attempts'] = 0; self::write( self::unit_key( $owner, $id, $key ), $unit );
                    }
                }
                $job['status'] = $job['ready'] ? 'queued' : ( $job['failed'] ? 'completed_with_errors' : 'completed' ); $job['not_before'] = 0; $job['error'] = ''; self::write( self::pointer( $owner ), $id );
            }
            ++$job['generation']; $job['updated_utc'] = gmdate( 'Y-m-d H:i:s' );
            self::write( self::job_key( $owner, $id ), $job ); return $job;
        } );
        if ( ! is_wp_error( $result ) ) {
            wp_clear_scheduled_hook( self::HOOK, array( $owner, $id ) );
            if ( self::runnable( $result ) ) self::arm( $owner, $id, 60 );
        }
        return is_array( $result ) ? self::get( $id ) : $result;
    }
    public static function next( $id ) {
        $job = self::get( $id );
        return is_array( $job ) ? wp_next_scheduled( self::HOOK, array( $job['owner'], $id ) ) : false;
    }
    private static function arm( $owner, $id, $seconds ) {
        $args = array( $owner, $id );
        $current = self::read( self::job_key( $owner, $id ) );
        if ( ! self::allowed( $current, $owner ) || ! self::runnable( $current ) ) return;
        if ( wp_next_scheduled( self::HOOK, $args ) ) return;
        $ok = wp_schedule_single_event( time() + max( 60, min( 86400, $seconds ) ), self::HOOK, $args, true );
        $current = self::read( self::job_key( $owner, $id ) );
        if ( ! self::allowed( $current, $owner ) || ! self::runnable( $current ) ) { wp_clear_scheduled_hook( self::HOOK, $args ); return; }
        if ( is_wp_error( $ok ) || ! $ok ) self::mutate( $owner, static function () use ( $owner, $id ) {
            $job = self::read( self::job_key( $owner, $id ) );
            if ( self::allowed( $job, $owner ) && self::runnable( $job ) ) { $job['status'] = 'blocked'; $job['error'] = 'schedule'; self::write( self::job_key( $owner, $id ), $job ); }
            return true;
        } );
    }
    private static function next_day( $date, $end ) { return $date < $end ? ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->modify( '+1 day' )->format( 'Y-m-d' ) : null; }
    private static function counts( $unit ) {
        $out = array();
        if ( 'activity' === $unit['kind'] ) {
            if ( isset( $unit['outcomes']['activity'] ) ) $out['activity_' . $unit['outcomes']['activity']['status']] = 1;
        } elseif ( 'measurement' === $unit['kind'] ) {
            foreach ( $unit['outcomes'] as $row ) {
                $out['measurement_' . $row['status']] = 1 + ( $out['measurement_' . $row['status']] ?? 0 );
                foreach ( array( 'added', 'updated', 'skipped', 'reports_added', 'reports_updated', 'reports_skipped', 'excluded' ) as $key ) $out[$key] = ( $out[$key] ?? 0 ) + (int) ( $row[$key] ?? 0 );
            }
        }
        return $out;
    }
    private static function safe_rows( $rows ) {
        $out = array();
        foreach ( $rows as $row ) $out[$row['category']] = array_intersect_key( $row, array_flip( array( 'date', 'category', 'status', 'added', 'updated', 'skipped', 'reports_added', 'reports_updated', 'reports_skipped', 'excluded', 'retry_after', 'error_code', 'http_status' ) ) );
        return $out;
    }
    private static function execute( $owner, $job, $unit ) {
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history.php';
        if ( 'page' === $unit['kind'] ) return Garmin_History::activity_step( $owner, $unit['data']['cursor'], $job['types'] );
        if ( 'activity' === $unit['kind'] ) return Garmin_History::import_activity( $owner, $unit['data']['activity_id'], $unit['data']['type'] );
        $cursor = Garmin_History::cursor( $unit['data']['date'], $unit['data']['date'] );
        return Garmin_History::measurement_step( $owner, $cursor, $unit['data']['pending'] );
    }
    /** One bounded work unit per callback. A pre-armed watchdog retries process termination. */
    public static function run( $owner, $id ) {
        if ( ! wp_doing_cron() || ! is_int( $owner ) || $owner < 1 || ! self::id_valid( $id ) ) return;
        if ( ! self::lock( $owner, true ) ) {
            $waiting = self::read( self::job_key( $owner, $id ) );
            if ( self::allowed( $waiting, $owner ) && self::runnable( $waiting ) ) self::arm( $owner, $id, 60 );
            return;
        }
        $previous = (int) get_current_user_id();
        try {
            $claim = self::mutate( $owner, static function () use ( $owner, $id ) {
                $job = self::read( self::job_key( $owner, $id ) );
                if ( ! self::allowed( $job, $owner ) || ! self::runnable( $job ) || ! $job['ready'] ) return null;
                if ( $job['not_before'] > time() ) return array( 'wait' => $job['not_before'] - time() );
                require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
                if ( ! user_can( $owner, 'swimlog_manage_own_workouts' ) || ( $job['types'] && ! user_can( $owner, 'swimlog_upload_workouts' ) ) || ! Garmin_Personal_API::is_configured( $owner ) ) {
                    $job['status'] = 'blocked'; $job['error'] = 'authorization_or_connection'; self::write( self::job_key( $owner, $id ), $job ); return null;
                }
                $unit = self::read( self::unit_key( $owner, $id, $job['ready'][0] ) );
                if ( ! is_array( $unit ) ) return self::error( 'ledger' );
                ++$unit['attempts']; $unit['status'] = 'working'; $job['statuses'][$unit['key']] = 'working'; $job['status'] = 'running'; $job['updated_utc'] = gmdate( 'Y-m-d H:i:s' );
                self::write( self::unit_key( $owner, $id, $unit['key'] ), $unit ); self::write( self::job_key( $owner, $id ), $job );
                return array( 'job' => $job, 'unit' => $unit );
            } );
            if ( ! is_array( $claim ) ) {
                if ( is_wp_error( $claim ) ) {
                    $waiting = self::read( self::job_key( $owner, $id ) );
                    if ( self::allowed( $waiting, $owner ) && self::runnable( $waiting ) ) self::arm( $owner, $id, 60 );
                }
                return;
            }
            if ( isset( $claim['wait'] ) ) { self::arm( $owner, $id, $claim['wait'] ); return; }
            self::arm( $owner, $id, 300 );
            wp_set_current_user( $owner );
            try {
                $result = $claim['unit']['attempts'] > 3 ? self::error( 'interrupted' ) : self::execute( $owner, $claim['job'], $claim['unit'] );
            } catch ( \Throwable $error ) { $result = self::error( 'interrupted' ); }
            $saved = self::finish( $owner, $id, $claim['unit'], $result );
            wp_clear_scheduled_hook( self::HOOK, array( $owner, $id ) );
            $current = self::read( self::job_key( $owner, $id ) );
            if ( is_array( $saved ) && self::allowed( $current, $owner ) && self::runnable( $current ) ) self::arm( $owner, $id, max( 60, $current['not_before'] - time() ) );
            elseif ( is_wp_error( $saved ) ) {
                self::mutate( $owner, static function () use ( $owner, $id, $saved ) {
                    $job = self::read( self::job_key( $owner, $id ) );
                    if ( self::allowed( $job, $owner ) && self::runnable( $job ) ) { $job['status'] = 'blocked'; $job['error'] = $saved->get_error_code(); self::write( self::job_key( $owner, $id ), $job ); }
                    return true;
                } );
            }
        } finally { wp_set_current_user( $previous ); self::lock( $owner, true, true ); }
    }
    private static function finish( $owner, $id, $claimed, $result ) {
        return self::mutate( $owner, static function () use ( $owner, $id, $claimed, $result ) {
            $job = self::read( self::job_key( $owner, $id ) );
            if ( ! self::allowed( $job, $owner ) ) return self::error( 'ledger' );
            $unit = self::read( self::unit_key( $owner, $id, $claimed['key'] ) );
            $before = self::counts( $unit ); $failed = is_wp_error( $result ); $delay = 300 * ( 1 << min( 2, max( 0, $unit['attempts'] - 1 ) ) );
            $code = $failed ? $result->get_error_code() : ''; $info = $failed ? $result->get_error_data() : null;
            if ( is_array( $info ) && isset( $info['retry_after'] ) ) $delay = max( $delay, (int) $info['retry_after'] );
            $auth = in_array( $code, array( 'swimlog_history_forbidden', 'swimlog_garmin_auth_failed', 'swimlog_garmin_not_configured', 'swimlog_garmin_decrypt_failed', 'swimlog_garmin_crypto_unavailable' ), true ) || ( is_array( $info ) && in_array( $info['status'] ?? 0, array( 401, 403 ), true ) );
            if ( 'activity' === $unit['kind'] ) {
                if ( in_array( $code, array( 'swimlog_ows_location_review', 'swimlog_source_disagreement' ), true ) ) {
                    $failed = false; $unit['outcomes']['activity'] = array( 'status' => 'review' );
                } elseif ( ! $failed ) {
                    $status = in_array( $result['status'] ?? '', array( 'imported', 'already_associated', 'associated', 'pending_review' ), true ) ? $result['status'] : '';
                    if ( ! $status ) { $failed = true; $code = 'unexpected_activity_result'; }
                    else $unit['outcomes']['activity'] = array( 'status' => 'imported' === $status ? 'imported' : ( 'pending_review' === $status ? 'review' : 'already' ), 'import_id' => (int) ( $result['import_id'] ?? 0 ) );
                }
            } elseif ( 'measurement' === $unit['kind'] && ! $failed ) {
                $rows = self::safe_rows( $result['results'] ); $pending = array();
                foreach ( $rows as $category => $row ) {
                    if ( in_array( $row['status'], array( 'failed', 'not_attempted' ), true ) ) {
                        $pending[] = $category;
                        if ( in_array( $row['http_status'] ?? 0, array( 401, 403 ), true ) || in_array( $row['error_code'] ?? '', array( 'swimlog_garmin_auth_failed', 'swimlog_garmin_not_configured', 'swimlog_garmin_decrypt_failed', 'swimlog_garmin_crypto_unavailable', 'swimlog_health_forbidden' ), true ) ) $auth = true;
                        $delay = max( $delay, (int) ( $row['retry_after'] ?? 0 ) );
                    }
                    // Completed category results survive retries of other categories in this day.
                    $unit['outcomes'][$category] = $row;
                }
                $unit['data']['pending'] = $pending; $failed = (bool) $pending;
                if ( $failed ) $code = 'measurement_request';
            }
            $unit['error'] = sanitize_key( $code );
            $terminal = ! $failed || ( $unit['attempts'] >= 3 && ! $auth );
            if ( $auth ) { if ( self::runnable( $job ) ) { $job['status'] = 'blocked'; $job['error'] = 'authorization_or_connection'; } $unit['status'] = 'queued'; }
            elseif ( ! $terminal ) { $unit['status'] = 'queued'; if ( self::runnable( $job ) ) { $job['status'] = 'waiting'; $job['not_before'] = time() + min( 86400, $delay ); } }
            else {
                $unit['status'] = $failed ? 'failed' : ( 'activity' === $unit['kind'] && 'review' === ( $unit['outcomes']['activity']['status'] ?? '' ) ? 'review' : 'complete' );
                $job['ready'] = array_values( array_diff( $job['ready'], array( $unit['key'] ) ) );
                if ( $failed ) $job['failed'][$unit['key']] = $unit['key'];
                else unset( $job['failed'][$unit['key']] );
                // Cancelled discovery must not enqueue a new chain; completed imports still get counted.
                if ( 'cancelled' !== $job['status'] ) {
                    if ( 'page' === $unit['kind'] ) {
                        if ( ! $failed ) {
                            foreach ( $result['activities'] as $activity ) self::enqueue( $job, 'activity', $activity );
                            if ( 0 === $result['raw_count'] ) ++$job['counts']['activity_days'];
                            if ( ! $result['cursor']['done'] ) self::enqueue( $job, 'page', array( 'cursor' => $result['cursor'] ) );
                        } else {
                            $next = self::next_day( $unit['data']['cursor']['day'], $job['end'] );
                            if ( $next ) self::enqueue( $job, 'page', array( 'cursor' => Garmin_History::cursor( $next, $job['end'] ) ) );
                        }
                    } elseif ( 'measurement' === $unit['kind'] ) {
                        if ( empty( $unit['followed'] ) ) ++$job['counts']['measurement_days'];
                        $next = self::next_day( $unit['data']['date'], $job['end'] );
                        if ( $next ) self::enqueue( $job, 'measurement', array( 'date' => $next, 'pending' => $job['measurements'] ) );
                    }
                }
                if ( self::runnable( $job ) ) {
                    $job['not_before'] = $failed && ( ( is_array( $info ) && isset( $info['retry_after'] ) ) || $delay > 1200 ) ? time() + min( 86400, $delay ) : 0;
                    $job['status'] = $job['ready'] ? 'queued' : ( $job['failed'] ? 'completed_with_errors' : 'completed' );
                }
            }
            if ( $terminal && ! $auth ) $unit['followed'] = true;
            $job['statuses'][$unit['key']] = $unit['status'];
            $after = self::counts( $unit );
            foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) $job['counts'][$key] = ( $job['counts'][$key] ?? 0 ) - ( $before[$key] ?? 0 ) + ( $after[$key] ?? 0 );
            $job['updated_utc'] = gmdate( 'Y-m-d H:i:s' );
            self::write( self::unit_key( $owner, $id, $unit['key'] ), $unit ); self::write( self::job_key( $owner, $id ), $job ); return $job;
        } );
    }
    public static function deactivate() { wp_unschedule_hook( self::HOOK ); }
    public static function delete_owner( $owner ) {
        global $wpdb; $owner = (int) $owner;
        $jobs = Database::read( 'get_col', $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'swimlog_hj_' . $owner . '_' ) . '%' ) );
        foreach ( (array) $jobs as $json ) { $job = json_decode( $json, true ); if ( self::allowed( $job, $owner ) ) wp_clear_scheduled_hook( self::HOOK, array( $owner, $job['id'] ) ); }
        Database::write( 'query', $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name = %s', $wpdb->options, $wpdb->esc_like( 'swimlog_hj_' . $owner . '_' ) . '%', $wpdb->esc_like( 'swimlog_hu_' . $owner . '_' ) . '%', self::pointer( $owner ) ) );
    }
}
