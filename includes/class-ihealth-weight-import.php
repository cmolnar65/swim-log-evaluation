<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-personal-api.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';

/** Bounded, manually initiated import of iHealth Personal API weight records. */
final class IHealth_Weight_Import {
    const MAX_DAYS = 90;
    const MAX_PAGE = 20;

    public static function request( $start, $end, $page ) {
        if ( ! is_string( $start ) || ! is_string( $end ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $end ) || ! is_int( $page ) || $page < 1 || $page > self::MAX_PAGE ) return self::error();
        $timezone = wp_timezone();
        $first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $timezone );
        $last = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $timezone );
        if ( ! $first || ! $last || $first->format( 'Y-m-d' ) !== $start || $last->format( 'Y-m-d' ) !== $end || $last < $first || $first->diff( $last )->days >= self::MAX_DAYS || $last > new \DateTimeImmutable( 'today', $timezone ) ) return self::error();
        return array( 'start_time' => $first->getTimestamp(), 'end_time' => $last->modify( '+1 day' )->getTimestamp() - 1, 'page_index' => $page, 'locale' => 'default' );
    }

    private static function error() {
        return new \WP_Error( 'swimlog_ihealth_weight_import', __( 'Choose valid dates within the last 90 days, ending no later than today, and a page from 1 to 20.', 'chriss-swim-training-progress-evaluation' ) );
    }

    private static function fail() {
        return new \WP_Error( 'swimlog_ihealth_weight_response', __( 'iHealth weight data could not be imported. Check the connection and try again.', 'chriss-swim-training-progress-evaluation' ) );
    }

    private static function weight_unit( $value ) {
        $units = array( '0' => array( 'kg', 1.0 ), '1' => array( 'lbs', 0.45359237 ), '2' => array( 'stone', 6.35029318 ) );
        if ( ! is_scalar( $value ) ) return null;
        $key = strtolower( trim( (string) $value ) );
        if ( isset( $units[ $key ] ) ) return $units[ $key ];
        $names = array( 'kg' => array( 'kg', 1.0 ), 'kgs' => array( 'kg', 1.0 ), 'lbs' => array( 'lbs', 0.45359237 ), 'lb' => array( 'lbs', 0.45359237 ), 'stone' => array( 'stone', 6.35029318 ), 'st' => array( 'stone', 6.35029318 ) );
        return $names[ $key ] ?? null;
    }

    /** Pure synthetic-fixture adapter; never returns source identifiers or raw values. */
    public static function adapt_record( $record ) {
        if ( ! is_array( $record ) || ! is_scalar( $record['DataID'] ?? null ) || ! isset( $record['MDate'] ) || ! is_numeric( (string) $record['MDate'] ) ) return null;
        $external = (string) $record['DataID'];
        if ( '' === $external || strlen( $external ) > 191 || preg_match( '/[^A-Za-z0-9._:-]/', $external ) ) return null;
        $timestamp = filter_var( $record['MDate'], FILTER_VALIDATE_INT );
        if ( false === $timestamp || $timestamp < 0 || $timestamp > time() + 86400 ) return null;
        $weight = $record['WeightValue'] ?? null;
        $unit = self::weight_unit( $record['WeightUnit'] ?? null );
        $raw_fields = array();
        $names = array( 'WeightValue', 'BMI', 'BoneValue', 'FatValue', 'WaterValue', 'MuscaleValue', 'VFR', 'DCI' );
        foreach ( $names as $name ) {
            if ( ! array_key_exists( $name, $record ) || ! is_scalar( $record[ $name ] ) || ! is_numeric( (string) $record[ $name ] ) || strlen( (string) $record[ $name ] ) > 64 ) continue;
            $raw_fields[ $name ] = array( 'value' => $record[ $name ], 'unit' => 'WeightValue' === $name && $unit ? $unit[0] : 'unknown' );
        }
        if ( ! $raw_fields ) return null;
        $utc = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
        $calendar_date = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
        $values = array();
        if ( is_scalar( $weight ) && is_numeric( (string) $weight ) && $unit && (float) $weight > 0 && (float) $weight < 1000 ) {
            $kg = (float) $weight * $unit[1];
            if ( $kg > 0 && $kg < 1000 ) {
                $canonical = rtrim( rtrim( number_format( $kg, 6, '.', '' ), '0' ), '.' );
                $values['weight'] = array( 'value' => $canonical, 'unit' => 'kg' );
            }
        }
        $has_composition = count( array_diff( array_keys( $raw_fields ), array( 'WeightValue' ) ) ) > 0;
        $category = $has_composition || ! isset( $values['weight'] ) ? 'body_composition' : 'weight';
        if ( 'body_composition' === $category && ! isset( $values['weight'] ) && ! $raw_fields ) return null;
        return array( 'category' => $category, 'day' => $calendar_date, 'observation' => array(
            'role' => 'sample', 'external_id' => $external, 'observed_at_utc' => $utc,
            'calendar_date' => $calendar_date, 'date_basis' => 'site_timezone', 'values' => $values,
            'provenance' => array( 'normalization_version' => 'ihealth-weight-v1', 'source_format' => 'ihealth-personal-weight', 'source_fields' => $raw_fields ),
        ) );
    }

    /** Import one manually selected provider page; no payload is returned to the caller. */
    public static function import_page( $owner, $start, $end, $page ) {
        $params = self::request( $start, $end, $page );
        if ( is_wp_error( $params ) ) return $params;
        $payload = IHealth_Personal_API::get_resource( $owner, 'OpenApiWeight', 'weight.json', $params );
        if ( is_wp_error( $payload ) || ! is_array( $payload['WeightDataList'] ?? null ) || count( $payload['WeightDataList'] ) > 200 ) return self::fail();
        $groups = array();
        $invalid = 0;
        foreach ( $payload['WeightDataList'] as $record ) {
            $adapted = self::adapt_record( $record );
            if ( ! $adapted ) { ++$invalid; continue; }
            $key = $adapted['category'] . '|' . $adapted['day'];
            if ( ! isset( $groups[ $key ] ) ) $groups[ $key ] = array( 'category' => $adapted['category'], 'day' => $adapted['day'], 'observations' => array() );
            $groups[ $key ]['observations'][] = $adapted['observation'];
        }
        $page_length = filter_var( $payload['PageLength'] ?? null, FILTER_VALIDATE_INT );
        $has_more = ! empty( $payload['NextPageUrl'] ) || ( false !== $page_length && $page_length > 0 && is_numeric( $payload['RecordCount'] ?? null ) && (int) $payload['RecordCount'] > $page * $page_length );
        $totals = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'duplicates' => 0, 'possible_duplicates' => 0, 'conflicts' => 0, 'invalid' => $invalid, 'page' => $page, 'has_more' => $has_more );
        foreach ( $groups as $group ) {
            $result = Health_Measurement_Store::save_report( $owner, array( 'provider' => 'ihealth', 'category' => $group['category'], 'calendar_date' => $group['day'], 'fetched_at_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'summary' => array(), 'context' => array( 'normalization_version' => 'ihealth-weight-v1', 'source_format' => 'ihealth-personal-weight' ) ), $group['observations'] );
            if ( is_wp_error( $result ) ) return self::fail();
            foreach ( array( 'added', 'updated', 'skipped', 'duplicates', 'possible_duplicates', 'conflicts' ) as $counter ) $totals[ $counter ] += (int) $result['counts'][ $counter ];
        }
        return $totals;
    }
}
