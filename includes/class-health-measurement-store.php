<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Private, owner-only persistence for normalized measurement report bundles.
 * Callers supply verified source semantics; no Garmin transport or public routes.
 */
final class Health_Measurement_Store {
    private static function error( $code ) {
        return new \WP_Error( 'swimlog_health_' . $code, sprintf( /* translators: %s: Internal validation or storage error code; contains no measurement values. */ __( 'The private measurement operation could not be completed (%s).', 'chriss-swim-training-progress-evaluation' ), $code ) );
    }

    private static function authorized( $owner ) {
        return is_int( $owner ) && $owner > 0 && is_user_logged_in()
            && $owner === (int) get_current_user_id() && current_user_can( 'swimlog_manage_own_workouts' );
    }

    private static function date( $value ) {
        if ( ! is_string( $value ) ) return false;
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
        return $date && $date->format( 'Y-m-d' ) === $value;
    }

    /** Canonicalize explicit UTC/local timestamps without inferring offsets. */
    public static function timestamp( $value, $utc = true ) {
        if ( ! is_string( $value ) || ! preg_match( '/^(\\d{4}-\\d{2}-\\d{2})T(\\d{2}:\\d{2}:\\d{2})(?:\\.(\\d{1,6}))?' . ( $utc ? 'Z' : '' ) . '$/D', $value, $match ) ) return self::error( 'timestamp' );
        $base = $match[1] . 'T' . $match[2];
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d\\TH:i:s', $base, new \DateTimeZone( 'UTC' ) );
        if ( ! $date || $date->format( 'Y-m-d\\TH:i:s' ) !== $base ) return self::error( 'timestamp' );
        $fraction = isset( $match[3] ) ? rtrim( $match[3], '0' ) : '';
        return $base . ( '' !== $fraction ? '.' . $fraction : '' ) . ( $utc ? 'Z' : '' );
    }

    private static function json( $value ) {
        $json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json || strlen( $json ) > 16384 ) return self::error( 'size' );
        return $json;
    }

    private static function decimal( $value ) {
        if ( is_float( $value ) ) {
            if ( ! is_finite( $value ) ) return self::error( 'value' );
            $value = wp_json_encode( $value );
        }
        if ( ! is_string( $value ) && ! is_int( $value ) ) return self::error( 'value' );
        $value = (string) $value;
        if ( ! preg_match( '/^([0-9]{1,9})(?:\\.([0-9]{1,9}))?$/D', $value, $match ) ) return self::error( 'value' );
        $integer = ltrim( $match[1], '0' );
        $integer = '' === $integer ? '0' : $integer;
        $fraction = isset( $match[2] ) ? rtrim( $match[2], '0' ) : '';
        return $integer . ( '' !== $fraction ? '.' . $fraction : '' );
    }

    private static function units( $category, $summary ) {
        if ( 'spo2' === $category ) {
            return array_fill_keys( $summary ? array( 'average', 'lowest', 'latest', 'sleep_average', 'next_sleep_average', 'seven_day_average' ) : array( 'spo2' ), '%' );
        }
        if ( $summary ) return array();
        if ( 'weight' === $category ) return array( 'weight' => 'kg' );
        if ( 'body_composition' === $category ) return array( 'weight' => 'kg', 'body_fat' => '%', 'body_water' => '%', 'bone_mass' => 'kg', 'muscle_mass' => 'kg', 'bmi' => 'kg/m2' );
        if ( 'blood_pressure' === $category ) return array( 'systolic' => 'mmHg', 'diastolic' => 'mmHg', 'pulse' => 'bpm' );
        return array();
    }

    public static function normalize_values( $category, $values, $summary = false ) {
        if ( ! in_array( $category, array( 'weight', 'body_composition', 'blood_pressure', 'spo2' ), true ) ) return self::error( 'category' );
        if ( ! is_array( $values ) || ( ! $summary && ! $values ) ) return self::error( 'values' );
        $units = self::units( $category, $summary );
        $out = array();
        foreach ( $values as $name => $entry ) {
            if ( ! isset( $units[ $name ] ) || ! is_array( $entry ) || array_diff( array_keys( $entry ), array( 'value', 'unit' ) ) || ! isset( $entry['value'], $entry['unit'] ) || $units[ $name ] !== $entry['unit'] ) return self::error( 'unit' );
            $value = self::decimal( $entry['value'] );
            if ( is_wp_error( $value ) ) return $value;
            if ( ( '%' === $entry['unit'] && (float) $value > 100 ) || ( ( 'spo2' === $category || in_array( $name, array( 'weight', 'systolic', 'diastolic', 'pulse' ), true ) ) && (float) $value <= 0 ) ) return self::error( 'value' );
            $out[ $name ] = array( 'value' => $value, 'unit' => $entry['unit'] );
        }
        if ( 'blood_pressure' === $category && ! $summary && ( ! isset( $out['systolic'] ) || ! isset( $out['diastolic'] ) ) ) return self::error( 'pair' );
        ksort( $out );
        return $out;
    }

    private static function context( $input ) {
        if ( ! is_array( $input ) ) return self::error( 'context' );
        $allowed = array( 'normalization_version', 'device', 'source_format', 'window_start_utc', 'window_end_utc', 'window_start_local', 'window_end_local', 'method', 'coverage', 'source_revision', 'sleep_start_utc', 'sleep_end_utc', 'next_sleep_start_utc', 'next_sleep_end_utc', 'latest_time_utc', 'sleep_start_local', 'sleep_end_local', 'next_sleep_start_local', 'next_sleep_end_local', 'latest_time_local' );
        $out = array();
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) || ! is_string( $value ) || strlen( $value ) > 191 || preg_match( '/[\\x00-\\x1f]/', $value ) ) return self::error( 'context' );
            if ( preg_match( '/_(utc|local)$/D', $key ) ) {
                $value = self::timestamp( $value, false !== strpos( $key, '_utc' ) );
                if ( is_wp_error( $value ) ) return $value;
            }
            $out[ $key ] = $value;
        }
        foreach ( array( 'utc', 'local' ) as $kind ) {
            $start = 'window_start_' . $kind;
            $end = 'window_end_' . $kind;
            if ( isset( $out[ $start ], $out[ $end ] ) && new \DateTimeImmutable( $out[ $end ], new \DateTimeZone( 'UTC' ) ) < new \DateTimeImmutable( $out[ $start ], new \DateTimeZone( 'UTC' ) ) ) return self::error( 'window' );
        }
        ksort( $out );
        return $out;
    }

    /** Pure validation/canonicalization, also exercised on every supported PHP. */
    public static function normalize_observation( $category, $input ) {
        if ( ! in_array( $category, array( 'weight', 'body_composition', 'blood_pressure', 'spo2' ), true ) || ! is_array( $input ) ) return self::error( 'category' );
        $allowed = array( 'role', 'external_id', 'confirmed_correction', 'expected_content_hash', 'observed_at_utc', 'source_local_time', 'source_offset_seconds', 'calendar_date', 'date_basis', 'values', 'provenance' );
        if ( array_diff( array_keys( $input ), $allowed ) ) return self::error( 'fields' );
        $role = $input['role'] ?? '';
        if ( ! in_array( $role, array( 'sample', 'hourly_average', 'sleep_average', 'calculated_mean' ), true ) || ( 'spo2' !== $category && 'sample' !== $role ) ) return self::error( 'role' );
        $values = self::normalize_values( $category, $input['values'] ?? null );
        $provenance = self::context( $input['provenance'] ?? array() );
        if ( is_wp_error( $values ) ) return $values;
        if ( is_wp_error( $provenance ) ) return $provenance;
        $external = $input['external_id'] ?? null;
        if ( null !== $external && ( ! is_string( $external ) || '' === $external || strlen( $external ) > 191 || preg_match( '/[\\x00-\\x1f]/', $external ) ) ) return self::error( 'identity' );
        $utc = $input['observed_at_utc'] ?? null;
        $local = $input['source_local_time'] ?? null;
        if ( null !== $utc ) $utc = self::timestamp( $utc );
        if ( null !== $local ) $local = self::timestamp( $local, false );
        if ( is_wp_error( $utc ) || is_wp_error( $local ) ) return self::error( 'timestamp' );
        if ( null === $external && null === $utc ) return self::error( 'identity' );
        $offset = $input['source_offset_seconds'] ?? null;
        if ( null !== $offset && ( ! is_int( $offset ) || abs( $offset ) > 50400 || null === $utc || null === $local ) ) return self::error( 'offset' );
        if ( null !== $offset ) {
            $instant = new \DateTimeImmutable( $utc );
            $local_in_utc = new \DateTimeImmutable( $local, new \DateTimeZone( 'UTC' ) );
            if ( $instant->modify( ( $offset >= 0 ? '+' : '' ) . $offset . ' seconds' )->format( 'Y-m-d H:i:s.u' ) !== $local_in_utc->format( 'Y-m-d H:i:s.u' ) ) return self::error( 'offset' );
        }
        $day = $input['calendar_date'] ?? null;
        $basis = $input['date_basis'] ?? 'unresolved';
        if ( ! in_array( $basis, array( 'provider_calendar', 'source_local', 'site_timezone', 'unresolved' ), true ) || ( null !== $day && ! self::date( $day ) ) || ( 'unresolved' === $basis ? null !== $day : null === $day ) ) return self::error( 'date' );
        if ( 'source_local' === $basis && ( null === $local || substr( $local, 0, 10 ) !== $day ) ) return self::error( 'date' );
        if ( 'site_timezone' === $basis && ( null === $utc || ( new \DateTimeImmutable( $utc ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' ) !== $day ) ) return self::error( 'date' );
        $correction = $input['confirmed_correction'] ?? false;
        if ( ! is_bool( $correction ) || ( $correction && null === $external ) ) return self::error( 'correction' );
        $expected = $input['expected_content_hash'] ?? null;
        if ( ( null !== $expected && ( ! is_string( $expected ) || ! preg_match( '/^[a-f0-9]{64}$/D', $expected ) ) ) || ( $correction && null === $expected ) ) return self::error( 'correction' );
        return array( 'expected_content_hash' => $expected, 'record_role' => $role, 'external_id' => $external, 'confirmed_correction' => $correction, 'observed_at_utc' => $utc, 'source_local_time' => $local, 'source_offset_seconds' => $offset, 'calendar_date' => $day, 'date_basis' => $basis, 'values' => $values, 'provenance' => $provenance );
    }

    private static function hash( $value ) {
        return hash( 'sha256', self::json( $value ) );
    }

    private static function read_identity( $table, $owner, $identity ) {
        global $wpdb;
        return Database::read( 'get_row', $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND identity_hash = %s FOR UPDATE', $table, $owner, $identity ) );
    }

    /** Save one bounded, prevalidated report and its observations atomically. */
    public static function save_report( $owner, $report, $observations ) {
        global $wpdb;
        if ( ! self::authorized( $owner ) ) return self::error( 'forbidden' );
        if ( ! is_array( $report ) || ! is_array( $observations ) || count( $observations ) > 1000 || array_diff( array_keys( $report ), array( 'provider', 'category', 'calendar_date', 'fetched_at_utc', 'summary', 'context' ) ) ) return self::error( 'report' );
        $provider = $report['provider'] ?? '';
        $category = $report['category'] ?? '';
        $day = $report['calendar_date'] ?? '';
        if ( 'garmin' !== $provider || ! in_array( $category, array( 'weight', 'body_composition', 'blood_pressure', 'spo2' ), true ) || ! self::date( $day ) ) return self::error( 'report' );
        $fetched = self::timestamp( $report['fetched_at_utc'] ?? '' );
        $summary = self::normalize_values( $category, $report['summary'] ?? array(), true );
        $context = self::context( $report['context'] ?? array() );
        if ( is_wp_error( $fetched ) || is_wp_error( $summary ) || is_wp_error( $context ) || strlen( $fetched ) !== 20 ) return self::error( 'report' );
        // This service never authorizes an undocumented blood-pressure fetch.
        $rows = array();
        foreach ( $observations as $observation ) {
            $row = self::normalize_observation( $category, $observation );
            if ( is_wp_error( $row ) ) return $row;
            $rows[] = $row;
        }
        $summary_json = self::json( array( 'version' => 1, 'values' => $summary ) );
        $context_json = self::json( array( 'version' => 1, 'context' => $context ) );
        if ( is_wp_error( $summary_json ) || is_wp_error( $context_json ) ) return self::error( 'size' );
        $reports = Database::table( 'health_reports' );
        $measurements = Database::table( 'health_measurements' );
        $members = Database::table( 'health_report_members' );
        // Refuse unsafe nested transactions and nontransactional installations.
        if ( (int) Database::read( 'get_var', 'SELECT @@session.in_transaction' ) ) return self::error( 'transaction' );
        foreach ( array( $reports, $measurements, $members ) as $table ) {
            $status = Database::read( 'get_row', $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ) );
            if ( ! $status || 'InnoDB' !== $status->Engine ) return self::error( 'engine' );
        }
        $lock = 'swimlog_health_' . substr( hash( 'sha256', $wpdb->prefix . ':' . $owner . ':' . $provider . ':' . $category ), 0, 48 );
        if ( 1 !== (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) return self::error( 'busy' );
        $transaction = false;
        try {
            if ( false === Database::write( 'query', 'START TRANSACTION' ) ) throw new \RuntimeException( 'transaction' );
            $transaction = true;
            $identity = self::hash( array( $provider, $category, 'report', $day ) );
            $existing = self::read_identity( $reports, $owner, $identity );
            $content = hash( 'sha256', $summary_json . ':' . $context_json );
            $fetched_sql = str_replace( array( 'T', 'Z' ), array( ' ', '' ), $fetched );
            if ( $existing && $fetched_sql <= $existing->fetched_at_utc && $content !== $existing->content_hash ) throw new \RuntimeException( 'stale_report' );
            if ( $existing && $fetched_sql < $existing->fetched_at_utc ) throw new \RuntimeException( 'stale_report' );
            $now = gmdate( 'Y-m-d H:i:s' );
            $report_data = array( 'provider' => $provider, 'category' => $category, 'provider_date' => $day, 'identity_hash' => $identity, 'content_hash' => $content, 'summary_json' => $summary_json, 'context_json' => $context_json, 'fetched_at_utc' => $fetched_sql, 'updated_at_utc' => $now );
            $counts = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'failed' => 0 );
            $report_status = $existing ? ( $content === $existing->content_hash ? 'skipped' : 'updated' ) : 'added';
            if ( $existing ) {
                $report_id = (int) $existing->id;
                if ( false === Database::write( 'update', $reports, $report_data, array( 'id' => $report_id, 'user_id' => $owner ) ) ) throw new \RuntimeException( 'write' );
            } else {
                $report_data['user_id'] = $owner;
                $report_data['created_at_utc'] = $now;
                if ( false === Database::write( 'insert', $reports, $report_data ) ) throw new \RuntimeException( 'write' );
                $report_id = (int) $wpdb->insert_id;
            }
            foreach ( $rows as $row ) {
                $values_json = self::json( array( 'version' => 1, 'values' => $row['values'] ) );
                $provenance_json = self::json( array( 'version' => 1, 'source' => $row['provenance'] ) );
                $identity = self::hash( array( $provider, $category, $row['record_role'], null !== $row['external_id'] ? array( 'external_id', $row['external_id'] ) : array( 'fingerprint', $row['observed_at_utc'], $row['values'] ) ) );
                $old = self::read_identity( $measurements, $owner, $identity );
                $data = array( 'provider' => $provider, 'category' => $category, 'record_role' => $row['record_role'], 'external_id' => $row['external_id'], 'identity_hash' => $identity, 'observed_at_utc' => $row['observed_at_utc'], 'source_local_time' => $row['source_local_time'], 'source_offset_seconds' => $row['source_offset_seconds'], 'calendar_date' => $row['calendar_date'], 'date_basis' => $row['date_basis'], 'values_json' => $values_json );
                // A provider encoding change can omit optional local time on an identical reading.
                // Preserve previously verified metadata; absence is not a correction or deletion.
                if ( $old && null === $data['source_local_time'] ) $data['source_local_time'] = $old->source_local_time;
                // Provenance and containing report do not create a second source measurement.
                $data['content_hash'] = self::hash( $data );
                if ( $old && $old->content_hash !== $data['content_hash'] && ( ! $row['confirmed_correction'] || null === $row['external_id'] || $row['expected_content_hash'] !== $old->content_hash ) ) throw new \RuntimeException( 'ambiguous_correction' );
                $data['provenance_json'] = $provenance_json;
                $data['updated_at_utc'] = $now;
                if ( $old ) {
                    $id = (int) $old->id;
                    $state = $old->content_hash === $data['content_hash'] ? 'skipped' : 'updated';
                    if ( 'updated' === $state && false === Database::write( 'update', $measurements, $data, array( 'id' => $id, 'user_id' => $owner ) ) ) throw new \RuntimeException( 'write' );
                } else {
                    $data['user_id'] = $owner;
                    $data['created_at_utc'] = $now;
                    if ( false === Database::write( 'insert', $measurements, $data ) ) throw new \RuntimeException( 'write' );
                    $id = (int) $wpdb->insert_id;
                    $state = 'added';
                }
                ++$counts[ $state ];
                if ( null !== $row['observed_at_utc'] ) {
                    $peers = Database::read( 'get_var', $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND provider = %s AND category = %s AND record_role = %s AND observed_at_utc = %s AND id != %d', $measurements, $owner, $provider, $category, $row['record_role'], $row['observed_at_utc'], $id ) );
                    if ( null === $peers ) throw new \RuntimeException( 'read' );
                    if ( (int) $peers > 0 ) ++$counts['conflicts'];
                }
                // Both rows were resolved/created by owner identity within this transaction.
                $member_id = Database::read( 'get_var', $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND report_id = %d AND measurement_id = %d', $members, $owner, $report_id, $id ) );
                if ( ! empty( $wpdb->last_error ) ) throw new \RuntimeException( 'read' );
                if ( ! $member_id && false === Database::write( 'insert', $members, array( 'user_id' => $owner, 'report_id' => $report_id, 'measurement_id' => $id, 'created_at_utc' => $now ) ) ) throw new \RuntimeException( 'write' );
            }
            if ( false === Database::write( 'query', 'COMMIT' ) ) throw new \RuntimeException( 'write' );
            $transaction = false;
            return array( 'report_id' => $report_id, 'report_status' => $report_status, 'counts' => $counts );
        } catch ( \RuntimeException $exception ) {
            return self::error( $exception->getMessage() );
        } finally {
            if ( $transaction ) Database::write( 'query', 'ROLLBACK' );
            Database::read( 'get_var', $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    /** Owner-only lookup; same-instant ambiguity is derived, never arrival-order resolution. */
    public static function get_for_user( $id, $owner ) {
        global $wpdb;
        if ( ! self::authorized( $owner ) || ! is_int( $id ) || $id <= 0 ) return self::error( 'forbidden' );
        $table = Database::table( 'health_measurements' );
        $row = Database::read( 'get_row', $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', $table, $id, $owner ) );
        if ( ! $row ) return null;
        $row->has_conflict = false;
        if ( null !== $row->observed_at_utc ) {
            $row->has_conflict = (int) Database::read( 'get_var', $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND provider = %s AND category = %s AND record_role = %s AND observed_at_utc = %s AND id != %d', $table, $owner, $row->provider, $row->category, $row->record_role, $row->observed_at_utc, $id ) ) > 0;
        }
        return $row;
    }
}
