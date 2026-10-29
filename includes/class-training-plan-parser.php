<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Phase 04-10A.3: conservative workout boundaries and candidate structures.
 * Read-only conversion: nothing is persisted until later review/import phases.
 */
final class Training_Plan_Parser {
    const MAX_WORKOUTS = 500;
    const MAX_LINES = 20000;

    public static function parse( $text ) {
        if ( ! is_string( $text ) || '' === trim( $text ) || strlen( $text ) > 1048576 ) {
            return new \WP_Error( 'swimlog_plan_text', 'Invalid extracted training-plan text.' );
        }
        $lines = preg_split( '/\\r\\n|\\r|\\n/', $text );
        if ( count( $lines ) > self::MAX_LINES ) return new \WP_Error( 'swimlog_plan_lines', 'Too many PDF text lines.' );
        $blocks = array();
        $current = null;
        $week = 0;
        foreach ( $lines as $line ) {
            $line = trim( $line );
            $week_heading = preg_match( '/^week\\s*(\\d{1,2})\\b/i', $line, $m ) || preg_match( '/^(\\d{1,2})\\s+of\\s+6\\b/i', $line, $m );
            if ( $week_heading ) $week = (int) $m[1];
            $boundary = preg_match( '/^(?:workout|session|practice)\\s*(?:#|no\\.?\\s*)?(\\d{1,3})\\b(?:\\s*[:.\\-–]\\s*(.*))?$/iu', $line, $m );
            // USMS/LaneMate layouts place "Swim #N of 24" inside the WEEK/WORKOUT table row.
            if ( ! $boundary && preg_match( '/\\bSwim\\s*#\\s*(\\d{1,3})\\s+of\\s+24\\b/iu', $line, $swim ) ) {
                $boundary = true;
                $m = array( $line, $swim[1] );
            }
            if ( $boundary ) {
                if ( null !== $current ) $blocks[] = $current;
                if ( count( $blocks ) >= self::MAX_WORKOUTS ) return new \WP_Error( 'swimlog_plan_count', 'Too many workouts.' );
                $current = array( 'source_number' => (int) $m[1], 'week' => $week, 'heading' => $line, 'lines' => array(), 'flags' => array() );
            } elseif ( null !== $current && '' !== $line && ! $week_heading ) {
                $current['lines'][] = $line;
            }
        }
        if ( null !== $current ) $blocks[] = $current;
        if ( ! $blocks ) return array( 'workouts' => array(), 'flags' => array( 'No explicit Workout/Session/Practice numbered headings detected; manual boundary review required.' ) );
        $result = array();
        $previous = 0;
        foreach ( $blocks as $i => $block ) {
            $flags = array();
            if ( $block['source_number'] !== $previous + 1 ) $flags[] = 'Workout numbering is not sequential; verify boundaries.';
            $previous = $block['source_number'];
            $unit = null;
            $nodes = array();
            foreach ( $block['lines'] as $line ) {
                // Explicit summary totals are reconciled separately, not counted as swim steps.
                if ( preg_match( '/^(?:total|workout total|total distance)\\s*[:=\\-]?\\s*[0-9,]+\\s*(?:yards?|yds?|meters?|metres?|m)\\b/i', $line ) ) continue;
                $parsed = self::parse_line( $line );
                if ( null === $parsed ) {
                    $flags[] = 'Unconverted instruction: ' . $line;
                    continue;
                }
                if ( $unit && $unit !== $parsed['unit'] ) {
                    $flags[] = 'Mixed distance units; manual conversion required: ' . $line;
                    continue;
                }
                $unit = $parsed['unit'];
                $nodes[] = $parsed['node'];
            }
            if ( ! $unit ) $flags[] = 'No unambiguous distance/unit/stroke steps found.';
            $document = $unit && $nodes ? array( 'unit' => $unit, 'sections' => array( array( 'type' => 'section', 'label' => 'Imported workout', 'children' => $nodes ) ) ) : null;
            $summary = $document ? Structured_Workout::summarize( $document ) : null;
            if ( is_wp_error( $summary ) ) {
                $flags[] = 'Candidate structure failed validation: ' . $summary->get_error_message();
                $document = null;
                $summary = null;
            }
            $result[] = array(
                'sequence' => $i + 1, 'source_number' => $block['source_number'], 'week' => $block['week'],
                'heading' => $block['heading'], 'source_lines' => $block['lines'],
                'document' => $document, 'calculated_distance' => $summary ? $summary['totals']['distance'] : null,
                'unit' => $unit, 'flags' => $flags, 'review_required' => true,
            );
        }
        return array( 'workouts' => $result, 'flags' => array() );
    }

    private static function parse_line( $line ) {
        // Only accept explicit distance units and strokes; never infer either.
        if ( ! preg_match( '/^(?:(\\d{1,2})\\s*[x×]\\s*)?(\\d{1,5})(?:\\s*)(yards?|yds?|meters?|metres?|m)\\s+(freestyle|free|front crawl|backstroke|back|breaststroke|breast|butterfly|fly|individual medley|im|mixed)\\b(.*)$/iu', $line, $m ) ) return null;
        $unit = preg_match( '/^(?:y|yd)/i', $m[3] ) ? 'yd' : 'm';
        $strokes = array( 'freestyle' => 'FR', 'free' => 'FR', 'front crawl' => 'FR', 'backstroke' => 'BK', 'back' => 'BK', 'breaststroke' => 'BR', 'breast' => 'BR', 'butterfly' => 'FL', 'fly' => 'FL', 'individual medley' => 'MX', 'im' => 'MX', 'mixed' => 'MX' );
        $stroke = $strokes[ strtolower( $m[4] ) ];
        $suffix = trim( $m[5] );
        $step = array( 'type' => 'step', 'distance' => (int) $m[2], 'stroke' => $stroke );
        if ( '' !== $suffix ) $step['description'] = substr( $suffix, 0, 500 );
        $count = isset( $m[1] ) && '' !== $m[1] ? (int) $m[1] : 1;
        if ( $count < 1 || $count > 100 ) return null;
        return array( 'unit' => $unit, 'node' => $count > 1 ? array( 'type' => 'repeat', 'count' => $count, 'children' => array( $step ) ) : $step );
    }
}
