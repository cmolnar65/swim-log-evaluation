<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only review and distance reconciliation for imported workout candidates. */
final class Training_Plan_Review {
    /** Validate explicit owner review without treating parser guesses as approval. */
    public static function approve( $candidate, $input ) {
        if ( ! is_array( $input ) || ! isset( $input['reviewed'] ) || '1' !== $input['reviewed'] ) return new \WP_Error( 'swimlog_pdf_review_required', 'Complete the review checkbox for every selected workout.' );
        if ( ! isset( $input['document'] ) || ! is_string( $input['document'] ) || strlen( $input['document'] ) > 1048576 ) return new \WP_Error( 'swimlog_pdf_review_document', 'A reviewed workout definition is required.' );
        $document = json_decode( $input['document'], true );
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        if ( ! isset( $input['total'], $input['unit'] ) || ! is_string( $input['total'] ) || ! preg_match( '/^[0-9]+(?:\.[0-9]{1,3})?$/D', $input['total'] ) || ! in_array( $input['unit'], array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_pdf_review_total', 'Enter the reviewed total distance and its unit for every selected workout.' );
        $total = (float) $input['total'];
        if ( $total <= 0 || ! is_finite( $total ) || $summary['unit'] !== $input['unit'] || abs( $summary['totals']['distance'] - $total ) > 0.001 ) return new \WP_Error( 'swimlog_pdf_review_mismatch', 'The reviewed distance and unit must match the edited workout total. Correct the workout or confirmed total before importing.' );
        $original = self::assess( $candidate );
        $candidate['document'] = $summary['document'];
        $candidate['calculated_distance'] = $summary['totals']['distance'];
        $candidate['unit'] = $summary['unit'];
        $candidate['manual_review'] = array( 'source_lines' => $candidate['source_lines'], 'original_flags' => $original['flags'], 'original_stated_total' => $original['stated'], 'confirmed_total' => $total, 'unit' => $input['unit'], 'reviewed_by' => get_current_user_id() );
        return $candidate;
    }

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
