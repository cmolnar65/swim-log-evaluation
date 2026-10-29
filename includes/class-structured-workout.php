<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Structured swimming prescription model (v1.2).
 *
 * A definition is an ordered tree. It is deliberately separate from recorded
 * FIT laps/lengths and from v1.1 free-form planned-workout instructions.
 */
final class Structured_Workout {
    const VERSION = 1;
    const MAX_DEPTH = 8;
    const MAX_NODES = 500;
    const MAX_EXPANDED_STEPS = 10000;
    const STROKES = array( 'FR', 'BR', 'BK', 'FL', 'MX' );

    /**
     * Validate and normalize an editable definition without mutating records.
     *
     * Node types:
     * step: distance, stroke, optional pace/rest/description/drill/equipment.
     * repeat: count, children, optional rest/pace/description.
     * ladder: ordered rungs (repeat or step), optional rest/pace/description.
     * section: label, children, optional description.
     *
     * Distance is in the document's declared unit, never silently converted.
     */
    public static function normalize( $document ) {
        if ( ! is_array( $document ) || ! isset( $document['unit'], $document['sections'] ) ) {
            return new \WP_Error( 'swimlog_structure_document', 'A unit and sections are required.' );
        }
        if ( ! in_array( $document['unit'], array( 'm', 'yd' ), true ) || ! is_array( $document['sections'] ) || ! $document['sections'] ) {
            return new \WP_Error( 'swimlog_structure_document', 'Use meters or yards and at least one section.' );
        }
        $count = 0;
        $normalized = array();
        foreach ( $document['sections'] as $section ) {
            $node = self::node( $section, 0, $count, false );
            if ( is_wp_error( $node ) ) return $node;
            if ( 'section' !== $node['type'] ) return new \WP_Error( 'swimlog_structure_section', 'Top-level nodes must be sections.' );
            $normalized[] = $node;
        }
        return array( 'version' => self::VERSION, 'unit' => $document['unit'], 'sections' => $normalized );
    }

    private static function node( $input, $depth, &$count, $in_ladder ) {
        if ( ! is_array( $input ) || ++$count > self::MAX_NODES || $depth > self::MAX_DEPTH ) {
            return new \WP_Error( 'swimlog_structure_limit', 'Workout structure exceeds its complexity limit.' );
        }
        $type = isset( $input['type'] ) ? $input['type'] : '';
        if ( ! in_array( $type, array( 'section', 'ladder', 'repeat', 'step' ), true ) ) {
            return new \WP_Error( 'swimlog_structure_type', 'Unknown workout node type.' );
        }
        $node = array( 'type' => $type );
        foreach ( array( 'description', 'pace', 'rest' ) as $field ) {
            $value = isset( $input[ $field ] ) ? $input[ $field ] : null;
            if ( null !== $value && ( ! is_string( $value ) || strlen( $value ) > 500 ) ) {
                return new \WP_Error( 'swimlog_structure_text', 'Descriptions, pace, and rest must be short text.' );
            }
            // Null means unspecified; the literal "0" means explicitly no rest.
            $node[ $field ] = $value;
        }
        if ( 'step' === $type ) {
            $distance = isset( $input['distance'] ) ? $input['distance'] : null;
            if ( ! is_numeric( $distance ) || ! is_finite( (float) $distance ) || (float) $distance <= 0 || (float) $distance > 100000 ) {
                return new \WP_Error( 'swimlog_structure_distance', 'Step distance must be positive and finite.' );
            }
            $stroke = isset( $input['stroke'] ) ? strtoupper( (string) $input['stroke'] ) : '';
            if ( 'BU' === $stroke ) $stroke = 'FL';
            if ( ! in_array( $stroke, self::STROKES, true ) || ( $in_ladder && 'MX' === $stroke ) ) {
                return new \WP_Error( 'swimlog_structure_stroke', 'Invalid stroke for this step.' );
            }
            $node['distance'] = (float) $distance;
            $node['stroke'] = $stroke;
            $node['drill'] = ! empty( $input['drill'] );
            $node['equipment'] = array();
            if ( isset( $input['equipment'] ) ) {
                if ( ! is_array( $input['equipment'] ) || count( $input['equipment'] ) > 20 ) {
                    return new \WP_Error( 'swimlog_structure_equipment', 'Invalid equipment list.' );
                }
                foreach ( $input['equipment'] as $item ) {
                    if ( ! is_string( $item ) || '' === trim( $item ) || strlen( $item ) > 60 ) {
                        return new \WP_Error( 'swimlog_structure_equipment', 'Invalid equipment item.' );
                    }
                    $node['equipment'][] = trim( $item );
                }
            }
            return $node;
        }
        if ( 'repeat' === $type ) {
            $repeats = isset( $input['count'] ) ? $input['count'] : null;
            if ( ! is_numeric( $repeats ) || (int) $repeats != $repeats || (int) $repeats < 1 || (int) $repeats > 100 ) {
                return new \WP_Error( 'swimlog_structure_repeat', 'Repeat count must be 1–100.' );
            }
            $node['count'] = (int) $repeats;
        }
        if ( 'section' === $type ) {
            $label = isset( $input['label'] ) ? $input['label'] : '';
            if ( ! is_string( $label ) || '' === trim( $label ) || strlen( $label ) > 100 ) {
                return new \WP_Error( 'swimlog_structure_label', 'A short section label is required.' );
            }
            $node['label'] = trim( $label );
        }
        $key = 'ladder' === $type ? 'rungs' : 'children';
        if ( ! isset( $input[ $key ] ) || ! is_array( $input[ $key ] ) || ! $input[ $key ] ) {
            return new \WP_Error( 'swimlog_structure_children', 'A group must contain steps.' );
        }
        $node[ $key ] = array();
        foreach ( $input[ $key ] as $child ) {
            $nested = self::node( $child, $depth + 1, $count, $in_ladder || 'ladder' === $type );
            if ( is_wp_error( $nested ) ) return $nested;
            if ( 'section' === $nested['type'] || ( 'ladder' === $type && ! in_array( $nested['type'], array( 'repeat', 'step' ), true ) ) ) {
                return new \WP_Error( 'swimlog_structure_nesting', 'Invalid workout nesting.' );
            }
            $node[ $key ][] = $nested;
        }
        return $node;
    }

    /** Expand only for totals, previews, and later export; keep saved tree intact. */
    public static function summarize( $document ) {
        $normalized = self::normalize( $document );
        if ( is_wp_error( $normalized ) ) return $normalized;
        $totals = array( 'distance' => 0.0, 'by_stroke' => array_fill_keys( self::STROKES, 0.0 ), 'expanded_steps' => 0 );
        foreach ( $normalized['sections'] as $section ) {
            $result = self::accumulate( $section, 1, $totals );
            if ( is_wp_error( $result ) ) return $result;
        }
        return array( 'unit' => $normalized['unit'], 'totals' => $totals, 'document' => $normalized );
    }

    private static function accumulate( $node, $multiplier, &$totals ) {
        if ( 'step' === $node['type'] ) {
            $totals['expanded_steps'] += $multiplier;
            if ( $totals['expanded_steps'] > self::MAX_EXPANDED_STEPS ) {
                return new \WP_Error( 'swimlog_structure_expansion', 'Too many expanded steps.' );
            }
            $amount = $node['distance'] * $multiplier;
            $totals['distance'] += $amount;
            $totals['by_stroke'][ $node['stroke'] ] += $amount;
            return true;
        }
        $multiplier *= 'repeat' === $node['type'] ? $node['count'] : 1;
        foreach ( ( 'ladder' === $node['type'] ? $node['rungs'] : $node['children'] ) as $child ) {
            $result = self::accumulate( $child, $multiplier, $totals );
            if ( is_wp_error( $result ) ) return $result;
        }
        return true;
    }
}
