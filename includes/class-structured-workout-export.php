<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Prescription-only exports: never include source files, private notes or recorded history. */
final class Structured_Workout_Export {
    public static function text( $title, $document ) {
        $summary = Structured_Workout::summarize( $document );
        if ( is_wp_error( $summary ) ) return $summary;
        $lines = array( 'Workout: ' . $title, 'Distance unit: ' . $summary['unit'], '' );
        foreach ( $summary['document']['sections'] as $section ) self::lines( $section, $summary['unit'], 0, $lines );
        $lines[] = '';
        $lines[] = 'Total: ' . self::number( $summary['totals']['distance'] ) . ' ' . $summary['unit'];
        foreach ( $summary['totals']['by_stroke'] as $stroke => $distance ) if ( $distance > 0 ) $lines[] = $stroke . ': ' . self::number( $distance ) . ' ' . $summary['unit'];
        return implode( "\n", $lines ) . "\n";
    }

    private static function number( $value ) { return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' ); }

    private static function lines( $node, $unit, $depth, &$lines ) {
        $indent = str_repeat( '  ', $depth );
        if ( 'step' === $node['type'] ) {
            $line = self::number( $node['distance'] ) . ' ' . $unit . ' ' . $node['stroke'];
            if ( $node['drill'] ) $line .= ' - drill';
            if ( $node['equipment'] ) $line .= ' - equipment: ' . implode( ', ', $node['equipment'] );
        } elseif ( 'repeat' === $node['type'] ) $line = $node['count'] . ' x (';
        elseif ( 'ladder' === $node['type'] ) $line = 'Ladder (';
        else $line = $node['label'];
        $lines[] = $indent . $line;
        if ( null !== $node['pace'] && '' !== $node['pace'] ) $lines[] = $indent . '  Pace / send-off: ' . $node['pace'];
        $lines[] = $indent . '  Rest: ' . ( null === $node['rest'] || '' === $node['rest'] ? 'unspecified' : ( '0' === $node['rest'] ? 'none (continuous)' : $node['rest'] ) );
        if ( null !== $node['description'] && '' !== $node['description'] ) {
            foreach ( preg_split( '/\r\n|\r|\n/', $node['description'] ) as $line ) $lines[] = $indent . '  ' . $line;
        }
        if ( 'step' !== $node['type'] ) {
            foreach ( 'ladder' === $node['type'] ? $node['rungs'] : $node['children'] as $child ) self::lines( $child, $unit, $depth + 1, $lines );
            if ( in_array( $node['type'], array( 'repeat', 'ladder' ), true ) ) $lines[] = $indent . ')';
        }
    }

    public static function pdf( $title, $document ) {
        $text = self::text( $title, $document );
        if ( is_wp_error( $text ) ) return $text;
        $autoload = SWIMLOG_EVALUATION_DIR . 'vendor/autoload.php';
        if ( ! is_readable( $autoload ) ) return new \WP_Error( 'swimlog_pdf_runtime', 'Bundled PDF renderer is unavailable.' );
        require_once $autoload;
        if ( ! class_exists( '\Dompdf\Dompdf' ) ) return new \WP_Error( 'swimlog_pdf_runtime', 'Bundled PDF renderer is unavailable.' );
        try {
            $options = new \Dompdf\Options();
            $options->set( 'isRemoteEnabled', false );
            $options->set( 'isPhpEnabled', false );
            $options->set( 'isJavascriptEnabled', false );
            $options->set( 'chroot', SWIMLOG_EVALUATION_DIR . 'vendor/dompdf/dompdf' );
            $options->set( 'defaultFont', 'DejaVu Sans' );
            $pdf = new \Dompdf\Dompdf( $options );
            $pdf->setPaper( 'letter', 'portrait' );
            $summary = Structured_Workout::summarize( $document );
            $body = '<p>Distance unit: ' . esc_html( $summary['unit'] ) . '</p>';
            foreach ( $summary['document']['sections'] as $section ) {
                $lines = array(); self::lines( $section, $summary['unit'], 0, $lines );
                $body .= '<div class="section"><pre>' . esc_html( implode( "\n", $lines ) ) . '</pre></div>';
            }
            $total = substr( $text, strrpos( $text, "\nTotal:" ) + 1 );
            $body .= '<div class="section totals"><pre>' . esc_html( $total ) . '</pre></div>';
            $html = '<!doctype html><html><head><meta charset="UTF-8"><style>@page{margin:44pt 42pt 48pt}body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;color:#172d40}h1{font-size:19pt;margin:0 0 8pt}.subtitle{font-size:9pt;color:#536475;border-bottom:2pt solid #286d86;padding-bottom:12pt;margin-bottom:18pt}.section{page-break-inside:avoid;margin-bottom:14pt}.totals{border-top:1pt solid #286d86;padding-top:8pt}pre{margin:0;font-family:"DejaVu Sans Mono",monospace;font-size:9pt;line-height:1.5;white-space:pre-wrap;word-wrap:break-word}</style></head><body><h1>' . esc_html( $title ) . '</h1><div class="subtitle">Swim workout prescription | ' . esc_html__( 'Private workout export', 'chriss-swim-training-progress-evaluation' ) . '</div>' . $body . '</body></html>';
            $pdf->loadHtml( $html, 'UTF-8' );
            $pdf->render();
            $pdf->getCanvas()->page_text( 42, 756, 'Swim workout  |  Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont( 'DejaVu Sans' ), 8, array( 0.33, 0.39, 0.45 ) );
            return $pdf->output();
        } catch ( \Throwable $error ) {
            return new \WP_Error( 'swimlog_pdf_render', 'The workout PDF could not be rendered.' );
        }
    }
}
