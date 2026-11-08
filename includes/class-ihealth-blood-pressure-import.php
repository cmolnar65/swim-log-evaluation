<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';

/** Bounded, manually initiated import of private iHealth blood-pressure records. */
final class IHealth_Blood_Pressure_Import {
    const MAX_DAYS = 90;
    const MAX_PAGE = 20;
    const MAX_RECORDS = 200;

    public static function request( $start, $end, $page ) {
        if ( ! is_string( $start ) || ! is_string( $end ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $end ) || ! is_int( $page ) || $page < 1 || $page > self::MAX_PAGE ) return self::error( 'request' );
        $timezone = wp_timezone();
        $first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $timezone );
        $last = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $timezone );
        if ( ! $first || ! $last || $first->format( 'Y-m-d' ) !== $start || $last->format( 'Y-m-d' ) !== $end || $last < $first || $first->diff( $last )->days >= self::MAX_DAYS || $last > new \DateTimeImmutable( 'today', $timezone ) ) return self::error( 'request' );
        return array( 'start_time' => $first->getTimestamp(), 'end_time' => $last->modify( '+1 day' )->getTimestamp() - 1, 'page_index' => $page, 'locale' => 'default' );
    }

    private static function error( $kind ) {
        if ( 'request' === $kind ) return new \WP_Error( 'swimlog_ihealth_bp_import', __( 'Choose valid dates within the last 90 days, ending no later than today, and a page from 1 to 20.', 'chriss-swim-training-progress-evaluation' ) );
        return new \WP_Error( 'swimlog_ihealth_bp_response', __( 'iHealth blood-pressure data could not be imported. Check the connection and try again.', 'chriss-swim-training-progress-evaluation' ) );
    }

    private static function unit( $value ) {
        if ( ! is_string( $value ) ) return null;
        $unit = strtolower( trim( $value ) );
        return 'mmhg' === $unit || 'mm hg' === $unit ? 'mmHg' : null;
    }

    private static function decimal( $value ) {
        if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) return null;
        if ( is_float( $value ) && ! is_finite( $value ) ) return null;
        $text = (string) $value;
        if ( strlen( $text ) > 20 || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,9})?$/D', $text ) || (float) $text <= 0 ) return null;
        return $text;
    }

    /** Adapt only verified numeric pressure, pulse, time, and private record identity fields. */
    public static function adapt_record( $record, $bp_unit ) {
        if ( ! is_array( $record ) || 'mmHg' !== self::unit( $bp_unit ) || ! is_scalar( $record['DataID'] ?? null ) || ! isset( $record['MDate'] ) || ! is_numeric( (string) $record['MDate'] ) ) return null;
        $external = (string) $record['DataID'];
        if ( '' === $external || strlen( $external ) > 191 || preg_match( '/[^A-Za-z0-9._:-]/', $external ) ) return null;
        $timestamp = filter_var( $record['MDate'], FILTER_VALIDATE_INT );
        if ( false === $timestamp || $timestamp < 0 || $timestamp > time() + 86400 ) return null;
        $systolic = self::decimal( $record['HP'] ?? null );
        $diastolic = self::decimal( $record['LP'] ?? null );
        if ( null === $systolic || null === $diastolic ) return null;

        $values = array(
            'systolic' => array( 'value' => $systolic, 'unit' => 'mmHg' ),
            'diastolic' => array( 'value' => $diastolic, 'unit' => 'mmHg' ),
        );
        $pulse = self::decimal( $record['HR'] ?? null );
        if ( null !== $pulse ) $values['pulse'] = array( 'value' => $pulse, 'unit' => 'bpm' );

        $utc = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
        $calendar_date = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
        return array(
            'day' => $calendar_date,
            'observation' => array(
                'role' => 'sample',
                'external_id' => $external,
                'observed_at_utc' => $utc,
                'calendar_date' => $calendar_date,
                'date_basis' => 'site_timezone',
                'values' => $values,
                'provenance' => array( 'normalization_version' => 'ihealth-bp-v1', 'source_format' => 'ihealth-personal-bp' ),
            ),
        );
    }

    /** Import one manually selected provider page; the payload is never returned to the caller. */
    public static function import_page( $owner, $start, $end, $page ) {
        $params = self::request( $start, $end, $page );
        if ( is_wp_error( $params ) ) return $params;
        $payload = IHealth_Personal_API::get_resource( $owner, 'OpenApiBP', 'bp.json', $params );
        if ( is_wp_error( $payload ) || ! is_array( $payload['BPDataList'] ?? null ) || count( $payload['BPDataList'] ) > self::MAX_RECORDS ) return self::error( 'response' );
        $unit = self::unit( $payload['BPUnit'] ?? null );
        if ( null === $unit ) return self::error( 'response' );

        $groups = array();
        $invalid = 0;
        foreach ( $payload['BPDataList'] as $record ) {
            $adapted = self::adapt_record( $record, $unit );
            if ( ! $adapted ) { ++$invalid; continue; }
            if ( ! isset( $groups[ $adapted['day'] ] ) ) $groups[ $adapted['day'] ] = array( 'day' => $adapted['day'], 'observations' => array() );
            $groups[ $adapted['day'] ]['observations'][] = $adapted['observation'];
        }

        $page_length = filter_var( $payload['PageLength'] ?? null, FILTER_VALIDATE_INT );
        $has_more = ! empty( $payload['NextPageUrl'] ) || ( false !== $page_length && $page_length > 0 && is_numeric( $payload['RecordCount'] ?? null ) && (int) $payload['RecordCount'] > $page * $page_length );
        $totals = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'duplicates' => 0, 'possible_duplicates' => 0, 'conflicts' => 0, 'invalid' => $invalid, 'page' => $page, 'has_more' => $has_more );
        foreach ( $groups as $group ) {
            $result = Health_Measurement_Store::save_report( $owner, array(
                'provider' => 'ihealth',
                'category' => 'blood_pressure',
                'calendar_date' => $group['day'],
                'fetched_at_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ),
                'summary' => array(),
                'context' => array( 'normalization_version' => 'ihealth-bp-v1', 'source_format' => 'ihealth-personal-bp' ),
            ), $group['observations'] );
            if ( is_wp_error( $result ) ) return self::error( 'response' );
            foreach ( array( 'added', 'updated', 'skipped', 'duplicates', 'possible_duplicates', 'conflicts' ) as $counter ) $totals[ $counter ] += (int) $result['counts'][ $counter ];
        }
        return $totals;
    }
}
