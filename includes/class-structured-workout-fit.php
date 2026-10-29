<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Garmin FIT Workout (type 5), not an Activity file. Profile references are in docs. */
final class Structured_Workout_FIT {
    const MAX_STEPS = 1000;
    public static function compile( $document ) {
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        $steps = array(); $warnings = array();
        foreach ( $summary['document']['sections'] as $section ) {
            $result = self::node( $section, $summary['unit'], null, array(), $steps, $warnings );
            if ( is_wp_error( $result ) ) return $result;
        }
        return array( 'steps' => $steps, 'warnings' => array_values( array_unique( $warnings ) ) );
    }

    private static function seconds( $text ) {
        if ( preg_match( '/^(\d+(?:\.\d+)?)\s*(?:s|sec|seconds)?$/i', trim( $text ), $m ) ) return (float) $m[1];
        if ( preg_match( '/^(?:(\d+))?:(\d{1,2})(?:\.(\d{1,3}))?$/', trim( $text ), $m ) && (int) $m[2] < 60 ) return (int) ( $m[1] ?? 0 ) * 60 + (float) ( $m[2] . ( isset( $m[3] ) ? '.' . $m[3] : '' ) );
        return null;
    }

    private static function rest( $text, &$steps, &$warnings, $unspecified = true, $duration_type = 0 ) {
        if ( null === $text || '' === $text ) {
            if ( ! $unspecified ) return true;
            $warnings[] = 'Unspecified step rests become open, lap-button rests in FIT; use 0 for continuous swimming.';
            $steps[] = array( 'name' => 'Rest: lap button', 'duration_type' => 5, 'duration_value' => 0, 'target_type' => 2, 'target_value' => 0, 'intensity' => 1, 'notes' => 'Rest duration unspecified; press lap to continue.' );
            return true;
        }
        $seconds = self::seconds( $text );
        if ( null === $seconds || $seconds < 0 || $seconds > 86400 ) return new \WP_Error( 'swimlog_fit_rest', 'FIT rest/send-off must be seconds or M:SS, no more than 24 hours.' );
        if ( $seconds > 0 ) $steps[] = array( 'name' => 28 === $duration_type ? 'Send-off' : 'Rest', 'duration_type' => $duration_type, 'duration_value' => (int) round( $seconds * 1000 ), 'target_type' => 2, 'target_value' => 0, 'intensity' => 1, 'notes' => $text );
        return true;
    }

    private static function node( $node, $unit, $inherited_pace, $context, &$steps, &$warnings ) {
        $pace = null !== $node['pace'] && '' !== $node['pace'] ? $node['pace'] : $inherited_pace;
        if ( null !== $node['description'] && '' !== $node['description'] ) $context[] = $node['description'];
        if ( 'section' === $node['type'] ) $context[] = $node['label'];
        if ( 'step' === $node['type'] ) {
            $notes = array_merge( $context, array( 'Stroke: ' . $node['stroke'] . ( $node['drill'] ? ' (drill)' : '' ) ) );
            if ( $node['equipment'] ) $notes[] = 'Equipment: ' . implode( ', ', $node['equipment'] );
            $stroke = array( 'FR' => 0, 'BK' => 1, 'BR' => 2, 'FL' => 3, 'MX' => 5 );
            $step = array( 'name' => $node['distance'] . ' ' . $unit . ' ' . $node['stroke'], 'duration_type' => 1, 'duration_value' => (int) round( $node['distance'] * ( 'yd' === $unit ? 0.9144 : 1 ) * 100 ), 'target_type' => 11, 'target_value' => $node['drill'] ? 4 : $stroke[$node['stroke']], 'intensity' => 0 );
            if ( $step['duration_value'] < 1 ) return new \WP_Error( 'swimlog_fit_distance', 'FIT distances must be at least one centimeter.' );
            $equipment = array( 'fins' => 1, 'kickboard' => 2, 'paddles' => 3, 'pull buoy' => 4, 'snorkel' => 5 );
            if ( 1 === count( $node['equipment'] ) && isset( $equipment[strtolower( $node['equipment'][0] )] ) ) $step['equipment'] = $equipment[strtolower( $node['equipment'][0] )];
            elseif ( $node['equipment'] ) $warnings[] = 'Multiple or custom equipment items are preserved in FIT step notes, not equipment flags.';
            $sendoff = null;
            if ( null !== $pace ) {
                $notes[] = 'Pace / send-off: ' . $pace;
                if ( preg_match( '/^(?:on|@|send-off)\s*(.+)$/i', $pace, $m ) ) {
                    $sendoff = $m[1];
                    if ( null !== $node['rest'] && '' !== $node['rest'] && '0' !== $node['rest'] ) return new \WP_Error( 'swimlog_fit_timing', 'A FIT send-off step cannot also prescribe additional rest. Use rest 0 or unspecified.' );
                    if ( null === self::seconds( $sendoff ) || self::seconds( $sendoff ) <= 0 ) return new \WP_Error( 'swimlog_fit_timing', 'Enter a positive send-off such as on 2:00.' );
                } elseif ( preg_match( '/^(\d+:\d{2}(?:\.\d+)?)\s*\/\s*100\s*(m|yd)$/i', $pace, $m ) && self::seconds( $m[1] ) > 0 ) {
                    $speed = (int) round( ( 'yd' === strtolower( $m[2] ) ? 91.44 : 100 ) / self::seconds( $m[1] ) * 1000 );
                    if ( $speed < 1 || $speed > 4294967294 ) return new \WP_Error( 'swimlog_fit_pace', 'Pace is outside the FIT speed range.' );
                    $step['speed'] = $speed;
                } else return new \WP_Error( 'swimlog_fit_pace', 'FIT pace must use M:SS/100m, M:SS/100yd or on M:SS. Other text remains available in PDF/text.' );
            }
            $step['notes'] = implode( '; ', $notes );
            if ( strlen( $step['notes'] ) > 253 ) return new \WP_Error( 'swimlog_fit_notes', 'FIT step notes exceed 253 UTF-8 bytes. Shorten descriptions or use PDF/text; nothing was truncated.' );
            $steps[] = $step;
            $result = self::rest( null !== $sendoff ? $sendoff : $node['rest'], $steps, $warnings, true, null !== $sendoff ? 28 : 0 );
            if ( is_wp_error( $result ) ) return $result;
        } else {
            if ( null !== $node['pace'] && preg_match( '/^(?:on|@|send-off)\s*/i', $node['pace'] ) ) return new \WP_Error( 'swimlog_fit_group_timing', 'Put FIT send-offs on individual swim steps, not groups.' );
            $start = count( $steps );
            foreach ( 'ladder' === $node['type'] ? $node['rungs'] : $node['children'] as $child ) {
                $result = self::node( $child, $unit, $pace, $context, $steps, $warnings );
                if ( is_wp_error( $result ) ) return $result;
            }
            $result = self::rest( $node['rest'], $steps, $warnings, false );
            if ( is_wp_error( $result ) ) return $result;
            if ( 'repeat' === $node['type'] && $node['count'] > 1 ) $steps[] = array( 'name' => 'Repeat ' . $node['count'] . ' times', 'duration_type' => 6, 'duration_value' => $start, 'target_type' => 2, 'target_value' => $node['count'], 'intensity' => 0, 'notes' => '' );
        }
        return count( $steps ) > self::MAX_STEPS ? new \WP_Error( 'swimlog_fit_limit', 'FIT exports support at most 1000 encoded steps including rests and repeats.' ) : true;
    }

    public static function encode( $title, $document, $pool_length_m = null ) {
        $compiled = self::compile( $document );
        if ( is_wp_error( $compiled ) ) return $compiled;
        $data = self::message( 0, 0, array( array( 0, 0, chr( 5 ) ), array( 1, 132, pack( 'v', 255 ) ), array( 2, 132, pack( 'v', 0 ) ), array( 3, 140, pack( 'V', random_int( 1, 2147483646 ) ) ), array( 4, 134, pack( 'V', max( 0, time() - 631065600 ) ) ) ) );
        $workout = array( array( 4, 0, chr( 5 ) ), array( 6, 132, pack( 'v', count( $compiled['steps'] ) ) ), array( 8, 7, $title . "\0" ), array( 11, 0, chr( 17 ) ) );
        if ( null !== $pool_length_m ) {
            if ( ! is_numeric( $pool_length_m ) || $pool_length_m <= 0 || $pool_length_m > 655.34 ) return new \WP_Error( 'swimlog_fit_pool', 'Pool length is outside the FIT range.' );
            $workout[] = array( 14, 132, pack( 'v', (int) round( $pool_length_m * 100 ) ) );
        }
        $workout[] = array( 15, 0, chr( 'yd' === $document['unit'] ? 1 : 0 ) );
        $message = self::message( 1, 26, $workout );
        if ( is_wp_error( $message ) ) return $message;
        $data .= $message;
        foreach ( $compiled['steps'] as $index => $step ) {
            $fields = array( array( 254, 132, pack( 'v', $index ) ), array( 0, 7, $step['name'] . "\0" ), array( 1, 0, chr( $step['duration_type'] ) ), array( 2, 134, pack( 'V', $step['duration_value'] ) ), array( 3, 0, chr( $step['target_type'] ) ), array( 4, 134, pack( 'V', $step['target_value'] ) ), array( 7, 0, chr( $step['intensity'] ) ), array( 8, 7, $step['notes'] . "\0" ) );
            if ( isset( $step['equipment'] ) ) $fields[] = array( 9, 0, chr( $step['equipment'] ) );
            if ( isset( $step['speed'] ) ) {
                $fields[] = array( 19, 0, chr( 0 ) ); $fields[] = array( 20, 134, pack( 'V', 0 ) );
                $fields[] = array( 21, 134, pack( 'V', $step['speed'] ) ); $fields[] = array( 22, 134, pack( 'V', $step['speed'] ) );
            }
            $message = self::message( 2, 27, $fields );
            if ( is_wp_error( $message ) ) return $message;
            $data .= $message;
        }
        $header = chr( 14 ) . chr( 32 ) . pack( 'vV', 21218, strlen( $data ) ) . '.FIT';
        $header .= pack( 'v', self::crc( $header ) );
        return $header . $data . pack( 'v', self::crc( $header . $data ) );
    }

    private static function message( $local, $global, $fields ) {
        $definition = chr( 64 | $local ) . "\0\0" . pack( 'v', $global ) . chr( count( $fields ) ); $values = chr( $local );
        foreach ( $fields as $field ) {
            if ( strlen( $field[2] ) > 255 || ( 7 === $field[1] && false !== strpos( substr( $field[2], 0, -1 ), "\0" ) ) ) return new \WP_Error( 'swimlog_fit_string', 'FIT strings must fit within 254 UTF-8 bytes and cannot contain NUL characters.' );
            $definition .= chr( $field[0] ) . chr( strlen( $field[2] ) ) . chr( $field[1] ); $values .= $field[2];
        }
        return $definition . $values;
    }

    private static function crc( $bytes ) {
        $crc = 0;
        for ( $i = 0; $i < strlen( $bytes ); ++$i ) {
            $crc ^= ord( $bytes[$i] );
            for ( $bit = 0; $bit < 8; ++$bit ) $crc = ( $crc & 1 ) ? ( ( $crc >> 1 ) ^ 40961 ) : ( $crc >> 1 );
        }
        return $crc;
    }
}
