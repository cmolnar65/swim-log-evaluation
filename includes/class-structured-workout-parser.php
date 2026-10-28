<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Conservative, side-effect-free conversion of supported written swim notation.
 * Does not save, link, overwrite, or infer ambiguous workout instructions.
 */
final class Structured_Workout_Parser {
    public static function parse( $instructions, $unit = '' ) {
        if ( ! is_string( $instructions ) || '' === trim( $instructions ) || strlen( $instructions ) > 65535 ) {
            return new \WP_Error( 'swimlog_parse_input', 'Workout instructions must contain text.' );
        }
        $lines = preg_split( '/\r\n|\n|\r/', $instructions );
        $sections = array();
        $section = null;
        $ladder = null;
        $rung = null;
        $expected = array();
        $declared_total = null;
        $explicit_unit = null;
        $summary_mode = false;
        foreach ( $lines as $index => $raw ) {
            $line = trim( preg_replace( '/\*\*/', '', $raw ) );
            $line = preg_replace( '/^[-*]\s+/', '', $line );
            if ( '' === $line ) continue;
            if ( preg_match( '/^Workout:\s*(.+)$/i', $line ) ) continue;
            if ( preg_match( '/^Workout Summary\s*$/i', $line ) ) {
                self::flush( $sections, $section, $ladder, $rung );
                $summary_mode = true; continue;
            }
            if ( preg_match( '/^(?:Main Set|Warm[- ]?up|Cool[- ]?down|Custom)(?:\s*[—–-]\s*([\d,.]+)\s*(meters?|metres?|yards?|yd|m))?\s*$/i', $line, $m ) ) {
                self::flush( $sections, $section, $ladder, $rung );
                $section = array( 'type' => 'section', 'label' => preg_replace( '/\s*[—–-].*$/u', '', $line ), 'children' => array() );
                if ( isset( $m[2] ) && $m[2] ) {
                    $u = self::unit( $m[2] );
                    if ( $explicit_unit && $explicit_unit !== $u ) return self::error( $index, 'Conflicting distance units.' );
                    $explicit_unit = $u;
                    $expected[] = (float) str_replace( ',', '', $m[1] );
                }
                $summary_mode = false; continue;
            }
            if ( preg_match( '/^Ladder\s*\d*\s*$/i', $line ) ) {
                if ( $summary_mode ) return self::error( $index, 'Unexpected ladder after summary.' );
                if ( null === $section ) $section = array( 'type' => 'section', 'label' => 'Main Set', 'children' => array() );
                self::flush_ladder( $section, $ladder, $rung );
                $ladder = array( 'type' => 'ladder', 'rungs' => array() );
                continue;
            }
            if ( preg_match( '/^(\d+)\s*[×xX]\s*\(\s*(\d+(?:\.\d+)?)\s*(FR|BR|BK|FL|BU|MX)\s*\+\s*(\d+(?:\.\d+)?)\s*(FR|BR|BK|FL|BU|MX)\s*\)$/i', $line, $m ) ) {
                if ( $summary_mode ) return self::error( $index, 'Unexpected step after summary.' );
                $steps = array(
                    array( 'type' => 'step', 'distance' => (float) $m[2], 'stroke' => strtoupper( $m[3] ) ),
                    array( 'type' => 'step', 'distance' => (float) $m[4], 'stroke' => strtoupper( $m[5] ) ),
                );
                $rung = array( 'type' => 'repeat', 'count' => (int) $m[1], 'children' => $steps );
                if ( null !== $ladder ) {
                    self::flush_rung( $ladder, $rung );
                } else {
                    if ( null === $section ) $section = array( 'type' => 'section', 'label' => 'Main Set', 'children' => array() );
                    $section['children'][] = $rung; $rung = null;
                }
                continue;
            }
            if ( preg_match( '/^(?:Pace|Rest):\s*(.+)$/i', $line, $m ) ) {
                if ( $summary_mode ) return self::error( $index, 'Pace or rest in summary.' );
                $field = stripos( $line, 'Pace:' ) === 0 ? 'pace' : 'rest';
                $value = trim( $m[1] );
                if ( null !== $ladder && count( $ladder['rungs'] ) ) {
                    $last = count( $ladder['rungs'] ) - 1;
                    $ladder['rungs'][ $last ][ $field ] = $value;
                } elseif ( null !== $section && count( $section['children'] ) ) {
                    $last = count( $section['children'] ) - 1;
                    $section['children'][ $last ][ $field ] = $value;
                } else return self::error( $index, 'Pace or rest has no preceding set.' );
                continue;
            }
            if ( preg_match( '/^Total:\s*([\d,.]+)\s*(meters?|metres?|yards?|yd|m)?\s*$/i', $line, $m ) ) {
                if ( ! empty( $m[2] ) ) {
                    $u = self::unit( $m[2] );
                    if ( $explicit_unit && $explicit_unit !== $u ) return self::error( $index, 'Conflicting distance units.' );
                    $explicit_unit = $u;
                }
                if ( $summary_mode ) $declared_total = (float) str_replace( ',', '', $m[1] );
                else {
                    if ( null === $ladder || ! $ladder['rungs'] ) return self::error( $index, 'Total has no preceding ladder.' );
                    $sum = Structured_Workout::summarize( array( 'unit' => 'm', 'sections' => array( array( 'type' => 'section', 'label' => 'Check', 'children' => array( $ladder ) ) ) ) );
                    if ( is_wp_error( $sum ) || abs( $sum['totals']['distance'] - (float) str_replace( ',', '', $m[1] ) ) > 0.001 ) return self::error( $index, 'Ladder total does not match its steps.' );
                }
                continue;
            }
            if ( $summary_mode && preg_match( '/^(?:Freestyle|Breaststroke|Backstroke|Butterfly|Mixed):\s*([\d,.]+)\s*(?:meters?|metres?|yards?|yd|m)?$/i', $line, $m ) ) {
                $names = array( 'freestyle' => 'FR', 'breaststroke' => 'BR', 'backstroke' => 'BK', 'butterfly' => 'FL', 'mixed' => 'MX' );
                $name = strtolower( substr( $line, 0, strpos( $line, ':' ) ) );
                $expected[ $names[ $name ] ] = (float) str_replace( ',', '', $m[1] ); continue;
            }
            if ( $summary_mode && preg_match( '/^(?:Equipment|Focus):\s*.+$/i', $line ) ) continue;
            return self::error( $index, 'Unsupported or ambiguous instruction: ' . $line );
        }
        self::flush( $sections, $section, $ladder, $rung );
        if ( ! $sections ) return new \WP_Error( 'swimlog_parse_empty', 'No supported workout steps were found.' );
        if ( $unit && ! in_array( $unit, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_parse_unit', 'Invalid requested distance unit.' );
        if ( $unit && $explicit_unit && $unit !== $explicit_unit ) return new \WP_Error( 'swimlog_parse_unit', 'Written distance unit differs from selected course.' );
        if ( ! $unit && ! $explicit_unit ) return new \WP_Error( 'swimlog_parse_unit', 'Specify meters or yards before conversion.' );
        $document = array( 'unit' => $explicit_unit ? $explicit_unit : $unit, 'sections' => $sections );
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        if ( null !== $declared_total && abs( $declared_total - $summary['totals']['distance'] ) > 0.001 ) return new \WP_Error( 'swimlog_parse_total', 'Declared workout total differs from calculated distance.' );
        foreach ( $expected as $key => $value ) {
            if ( is_int( $key ) ) {
                if ( abs( $value - $summary['totals']['distance'] ) > 0.001 ) return new \WP_Error( 'swimlog_parse_total', 'Section total differs from calculated distance.' );
            } elseif ( abs( $value - $summary['totals']['by_stroke'][ $key ] ) > 0.001 ) {
                return new \WP_Error( 'swimlog_parse_stroke_total', 'Declared stroke total differs from calculated distance.' );
            }
        }
        return $summary;
    }
    private static function unit( $text ) { return preg_match( '/^(?:yards?|yd)$/i', $text ) ? 'yd' : 'm'; }
    private static function error( $index, $message ) {
        return new \WP_Error( 'swimlog_parse_ambiguous', 'Line ' . ( $index + 1 ) . ': ' . $message );
    }
    private static function flush_rung( &$ladder, &$rung ) {
        if ( null !== $rung ) { $ladder['rungs'][] = $rung; $rung = null; }
    }
    private static function flush_ladder( &$section, &$ladder, &$rung ) {
        if ( null !== $ladder ) {
            self::flush_rung( $ladder, $rung );
            if ( $ladder['rungs'] ) $section['children'][] = $ladder;
            $ladder = null;
        }
    }
    private static function flush( &$sections, &$section, &$ladder, &$rung ) {
        if ( null !== $section ) {
            self::flush_ladder( $section, $ladder, $rung );
            if ( $section['children'] ) $sections[] = $section;
            $section = null;
        }
    }
}
