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
        $week = 0; $after_total = false;
        foreach ( $lines as $line ) {
            $line = trim( $line, " \t\n\r\0\x0B\f" );
            if ( preg_match( '/^(?:Training Plan for|U\.S\. Masters Swimming\/LaneMate Training Plan|WEEK\s+WORKOUT|SWIM SETS|SWIM TRACKER)\b/i', $line ) ) continue;
            $week_heading = preg_match( '/^week\\s*(\\d{1,2})\\b/i', $line, $m ) || preg_match( '/^(\\d{1,2})\\s+of\\s+6\\b/i', $line, $m );
            if ( $week_heading ) $week = (int) $m[1];
            $heading_instruction = '';
            $boundary = preg_match( '/^(?:workout|session|practice)\\s*(?:#|no\\.?\\s*)?(\\d{1,3})\\b(?:\\s*[:.\\-–]\\s*(.*))?$/iu', $line, $m );
            // USMS/LaneMate layouts place "Swim #N of 24" inside the WEEK/WORKOUT table row.
            if ( ! $boundary && preg_match( '/\\bSwim\\s*#\\s*(\\d{1,3})\\s+of\\s+24\\b(.*)$/iu', $line, $swim ) ) {
                $boundary = true;
                $m = array( $line, $swim[1] );
                $heading_instruction = trim( $swim[2] );
            }
            if ( $boundary ) {
                $after_total = false;
                if ( null !== $current ) $blocks[] = $current;
                if ( count( $blocks ) >= self::MAX_WORKOUTS ) return new \WP_Error( 'swimlog_plan_count', 'Too many workouts.' );
                $current = array( 'source_number' => (int) $m[1], 'week' => $week, 'heading' => $line, 'lines' => '' !== $heading_instruction ? array( $heading_instruction ) : array(), 'flags' => array() );
            } elseif ( null !== $current && '' !== $line && ! $week_heading ) {
                if ( $after_total && preg_match( '/^[0-9]{1,4}$/D', $line ) ) continue;
                $current['lines'][] = $line;
                if ( preg_match( '/^(?:total|workout total|total distance)\s*[:=\-]?\s*[0-9,]+\s*(?:yards?|yds?|meters?|metres?|m)\b/i', $line ) ) $after_total = true;
            }
        }
        if ( null !== $current ) $blocks[] = $current;
        if ( ! $blocks ) {
            $totals = array_filter( $lines, static function( $line ) { return (bool) preg_match( '/^\s*(?:total|workout total|total distance)\s*[:=\-]?\s*[0-9,]+\s*(?:yards?|yds?|meters?|metres?|m)\b/i', $line ); } );
            if ( 1 === count( $totals ) && preg_match( '/^\s*(?:\d+\s*[x×]\s*)?\d+\s*(?:m|yds?)?\s*(?:EZ|free|breast|back|fly|kick|swim|choice|ODD)\b/im', $text ) ) {
                $blocks[] = array( 'source_number' => 1, 'week' => $week, 'heading' => 'Single workout', 'lines' => array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) ), 'flags' => array( 'No numbered heading; verify the single-workout boundary before approval.' ) );
            } else return array( 'workouts' => array(), 'flags' => array( 'No explicit Workout/Session/Practice numbered headings detected; manual boundary review required.' ) );
        }
        $result = array();
        $previous = 0;
        foreach ( $blocks as $i => $block ) {
            $flags = $block['flags'];
            if ( $block['source_number'] !== $previous + 1 ) $flags[] = 'Workout numbering is not sequential; verify boundaries.';
            $previous = $block['source_number'];
            $conversion_lines = array();
            foreach ( $block['lines'] as $source_line ) {
                if ( preg_match( '/^((?:(?:Skills|Fitness|Equipment)\s*:|(?:snorkel|kickboard|fins|paddles|pull buoy)\b).*?)(?:\t+| {2,})(?=\d+\s*(?:[x×]|EZ|free|kick|swim|breast|back|choice))/iu', $source_line, $columns ) ) {
                    $conversion_lines[] = trim( $columns[1] );
                    $conversion_lines[] = trim( substr( $source_line, strlen( $columns[0] ) ) );
                } else $conversion_lines[] = $source_line;
            }
            $converted = self::convert( $conversion_lines );
            $flags = array_merge( $flags, $converted['flags'] );
            $unit = $converted['unit'];
            $document = $converted['document'];
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

    /** Use only an explicit workout TOTAL as the default unit for compact rows. */
    private static function convert( $lines ) {
        $declared = array();
        foreach ( $lines as $line ) {
            if ( preg_match( '/^(?:total|workout total|total distance)\s*[:=\-]?\s*[0-9,]+\s*(yards?|yds?|meters?|metres?|m)\b/i', $line, $m ) ) $declared[] = preg_match( '/^y/i', $m[1] ) ? 'yd' : 'm';
        }
        $unit = 1 === count( array_unique( $declared ) ) ? $declared[0] : null;
        $flags = array(); $sections = array(); $metadata = array();
        $group = array( 'label' => 'Imported workout', 'count' => 1, 'rest' => null, 'description' => null, 'subtotal' => null, 'nodes' => array() );
        foreach ( $lines as $line ) {
            if ( preg_match( '/^(?:total|workout total|total distance)\s*[:=\-]?\s*[0-9,]+\s*(?:yards?|yds?|meters?|metres?|m)\b/i', $line ) ) continue;
            if ( preg_match( '/^(?:skills|fitness|equipment)\s*:/i', $line ) || preg_match( '/^(?:snorkel|kickboard|fins|paddles|pull buoy)\b/i', $line ) ) {
                $metadata[] = $line;
                continue;
            }
            if ( preg_match( '/^(?:repeat\s+|do\s+)?(\d{1,3})\s*[x×]\s*(?:\(([^)]*)\))?\s*(?:[:\-]?\s*([0-9,]+))?\s*:?(?:\s*)$/iu', $line, $m ) ) {
                self::finish_group( $group, $sections, $unit, $flags );
                $count = (int) $m[1];
                if ( $count < 1 || $count > 100 ) { $flags[] = 'Invalid section repeat count: ' . $line; $count = 1; }
                $description = trim( $m[2] ?? '' );
                if ( preg_match( '/\/\s*:\d+/', $description ) ) $flags[] = 'Additional round timing requires manual review; retained in section instructions.';
                $group = array( 'label' => 'Set ' . ( count( $sections ) + 1 ), 'count' => $count, 'rest' => self::rest( $description ), 'description' => $description ?: null, 'subtotal' => isset( $m[3] ) && '' !== $m[3] ? (int) str_replace( ',', '', $m[3] ) : null, 'nodes' => array() );
                continue;
            }
            $parsed = self::parse_line( $line, $unit, $group['rest'] );
            if ( null === $parsed ) { $flags[] = 'Unconverted instruction: ' . $line; continue; }
            if ( $unit && $unit !== $parsed['unit'] ) { $flags[] = 'Mixed distance units; manual conversion required: ' . $line; continue; }
            $unit = $parsed['unit'];
            $group['nodes'][] = $parsed['node'];
        }
        self::finish_group( $group, $sections, $unit, $flags );
        if ( ! $unit ) $flags[] = 'No unambiguous distance/unit/stroke steps found.';
        if ( $sections && $metadata ) {
            $description = implode( "\n", $metadata ) . ( empty( $sections[0]['description'] ) ? '' : "\n" . $sections[0]['description'] );
            if ( strlen( $description ) <= 500 ) $sections[0]['description'] = $description;
            else $flags[] = 'Workout metadata exceeds the description limit; retained in original instructions for review.';
        }
        return array( 'unit' => $unit, 'flags' => $flags, 'document' => $unit && $sections ? array( 'unit' => $unit, 'sections' => $sections ) : null );
    }

    private static function finish_group( $group, &$sections, $unit, &$flags ) {
        if ( ! $group['nodes'] ) {
            if ( null !== $group['subtotal'] ) $flags[] = 'Section has a subtotal but no parsed swimming steps.';
            return;
        }
        $children = $group['nodes'];
        if ( $group['count'] > 1 ) $children = array( array( 'type' => 'repeat', 'count' => $group['count'], 'rest' => '0', 'description' => $group['description'], 'children' => $children ) );
        $section = array( 'type' => 'section', 'label' => $group['label'], 'rest' => '0', 'description' => $group['description'], 'children' => $children );
        if ( null !== $group['subtotal'] && $unit ) {
            $summary = Structured_Workout::summarize( array( 'unit' => $unit, 'sections' => array( $section ) ) );
            if ( is_wp_error( $summary ) || abs( $summary['totals']['distance'] - $group['subtotal'] ) > 0.001 ) $flags[] = 'Section subtotal differs from its parsed repeated steps; verify the section boundary and repeat count.';
        }
        $sections[] = $section;
    }

    private static function rest( $text ) {
        return preg_match( '/\brest\s*:?\s*((?:\d+:|:)\d{2}|\d+\s*(?:s|sec|seconds))\b/i', $text, $m ) ? trim( $m[1] ) : null;
    }

    private static function stroke( $text ) {
        if ( ! preg_match( '/\b(individual medley|front crawl|freestyle|free|flutter|FR|breaststroke|breast|BR|backstroke|back|BK|butterfly|fly|FL|BU|im|mixed|choice|MX)\b/i', $text, $m ) ) return null;
        $name = strtolower( $m[1] );
        if ( in_array( $name, array( 'freestyle', 'free', 'front crawl', 'flutter', 'fr' ), true ) ) return 'FR';
        if ( in_array( $name, array( 'breaststroke', 'breast', 'br' ), true ) ) return 'BR';
        if ( in_array( $name, array( 'backstroke', 'back', 'bk' ), true ) ) return 'BK';
        if ( in_array( $name, array( 'butterfly', 'fly', 'fl', 'bu' ), true ) ) return 'FL';
        return 'MX';
    }

    private static function step( $distance, $instruction, $fallback, $rest, $context = '' ) {
        $stroke = self::stroke( $instruction );
        if ( null === $stroke ) $stroke = $fallback;
        if ( null === $stroke || strlen( $instruction ) > 500 ) return null;
        $text = $context . ' ' . $instruction;
        return array( 'type' => 'step', 'distance' => $distance, 'stroke' => $stroke, 'rest' => self::rest( $instruction ) ?? $rest, 'description' => $instruction,
            'drill' => (bool) preg_match( '/\b(kick|drill|up[ -]down|pull)\b/i', $text ),
            'equipment' => preg_match( '/\b(?:on (?:a )?board|kickboard)\b/i', $text ) ? array( 'Kickboard' ) : array() );
    }

    private static function repeated( $count, $children ) {
        return 1 === $count && 1 === count( $children ) ? $children[0] : array( 'type' => 'repeat', 'count' => $count, 'rest' => '0', 'children' => $children );
    }

    private static function parse_line( $line, $default_unit = null, $rest = null ) {
        if ( ! preg_match( '/^(?:(\d{1,3})\s*[x×]\s*)?(\d{1,5})\s*(?:(yards?|yds?|meters?|metres?|m)\b)?\s*:?\s*(.+)$/iu', $line, $m ) ) return null;
        $count = isset( $m[1] ) && '' !== $m[1] ? (int) $m[1] : 1;
        $distance = (int) $m[2]; $instruction = trim( $m[4] );
        $unit = ! empty( $m[3] ) ? ( preg_match( '/^y/i', $m[3] ) ? 'yd' : 'm' ) : $default_unit;
        if ( ! $unit || $count < 1 || $count > 100 || $distance < 1 || strlen( $instruction ) > 500 ) return null;
        // Alternation retains order and odd remainders, rather than assigning one stroke to the set.
        if ( preg_match( '/^(.*?)\bODD\s*[:\-]?\s*(.+?)[;,]?\s+EVEN\s*[:\-]?\s*(.+)$/iu', $instruction, $a ) ) {
            $odd = self::step( $distance, trim( $a[2], " ;," ), null, $rest );
            $even = self::step( $distance, trim( $a[3], " ;," ), null, $rest );
            if ( ! $odd || ! $even ) return null;
            $pairs = intdiv( $count, 2 ); $children = array();
            if ( $pairs ) $children[] = self::repeated( $pairs, array( $odd, $even ) );
            if ( $count % 2 ) $children[] = $odd;
            
            $node = self::repeated( 1, $children ); $node['description'] = $instruction;
            return array( 'unit' => $unit, 'node' => $node );
        }
        // Explicit alternating lengths: the outer distance sets the repeat count; split distances are not added twice.
        if ( preg_match( '/^(.*?)\bas\s+(\d{1,5})\s+(.+?)\s*\+\s*(\d{1,5})\s+(.+)$/iu', $instruction, $a ) ) {
            if ( 1 !== substr_count( $instruction, '+' ) ) return null;
            $cycle = (int) $a[2] + (int) $a[4];
            $cycles = $cycle > 0 ? $distance / $cycle : 0;
            if ( $cycles < 1 || $cycles > 100 || floor( $cycles ) != $cycles ) return null;
            $fallback = self::stroke( $a[1] );
            $first = self::step( (int) $a[2], $a[3], $fallback, '0', $a[1] );
            $second = self::step( (int) $a[4], $a[5], $fallback, '0', $a[1] );
            if ( ! $first || ! $second ) return null;
            $children = $cycles > 1 ? array( self::repeated( (int) $cycles - 1, array( $first, $second ) ) ) : array();
            $second['rest'] = $rest;
            $children[] = $first; $children[] = $second;
            $node = self::repeated( 1, $children ); $node['description'] = $instruction;
            return array( 'unit' => $unit, 'node' => self::repeated( $count, array( $node ) ) );
        }
        $step = self::step( $distance, $instruction, null, $rest );
        if ( ! $step ) return null;
        // A shorthand without units still requires an explicit supported prescription, not arbitrary prose containing a stroke word.
        if ( empty( $m[3] ) && ! preg_match( '/^(?:EZ\s+)?(?:free|freestyle|front crawl|back|backstroke|breast|breaststroke|fly|butterfly|choice|mixed|swim|kick|up[ -]down|FR|BR|BK|FL|BU|MX)\b/i', $instruction ) ) return null;
        return array( 'unit' => $unit, 'node' => self::repeated( $count, array( $step ) ) );
    }

}
