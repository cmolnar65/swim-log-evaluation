<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only review and distance reconciliation for imported workout candidates. */
final class Training_Plan_Review {
    public static function assess( $candidate ) {
        $flags = isset( $candidate['flags'] ) && is_array( $candidate['flags'] ) ? $candidate['flags'] : array();
        $stated = array();
        foreach ( $candidate['source_lines'] as $line ) {
            if ( preg_match( '/^(?:total|workout total|total distance)\\s*[:=\\-]?\\s*([0-9,]+)\\s*(yards?|yds?|meters?|metres?|m)\\b/i', $line, $m ) ) {
                $stated[] = array( 'distance' => (int) str_replace( ',', '', $m[1] ), 'unit' => preg_match( '/^y/i', $m[2] ) ? 'yd' : 'm' );
            }
        }
        $calculated = isset( $candidate['calculated_distance'] ) ? $candidate['calculated_distance'] : null;
        $unit = isset( $candidate['unit'] ) ? $candidate['unit'] : null;
        $status = 'unverified';
        $difference = null;
        if ( count( $stated ) > 1 ) {
            $flags[] = 'Multiple stated totals detected; manual review required.';
        } elseif ( 1 === count( $stated ) && null !== $calculated ) {
            if ( $stated[0]['unit'] !== $unit ) {
                $flags[] = 'Stated and calculated units differ; no implicit conversion.';
                $status = 'unit_mismatch';
            } else {
                $difference = (float) $calculated - $stated[0]['distance'];
                $status = abs( $difference ) < 0.001 ? 'match' : 'mismatch';
                if ( 'mismatch' === $status ) $flags[] = 'Stated and calculated distances differ.';
            }
        } elseif ( ! $stated ) {
            $flags[] = 'No explicit stated total detected.';
        }
        if ( null === $calculated ) $flags[] = 'No valid structured distance available.';
        return array(
            'stated' => 1 === count( $stated ) ? $stated[0] : null,
            'calculated' => $calculated, 'unit' => $unit,
            'difference' => $difference, 'status' => $status,
            'flags' => $flags,
            'ready' => 'match' === $status && ! $flags && ! empty( $candidate['document'] ),
        );
    }
}
