<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Phase 04-10A.2: bounded, transient extraction of user-supplied PDF text. */
final class Training_Plan_PDF {
    const MAX_BYTES = 10485760;
    const MAX_TEXT_BYTES = 1048576;

    public static function extract( $upload ) {
        if ( ! is_array( $upload ) || ! isset( $upload['error'], $upload['size'], $upload['tmp_name'], $upload['name'] ) ||
            UPLOAD_ERR_OK !== (int) $upload['error'] ) {
            return new \WP_Error( 'swimlog_pdf_upload', 'PDF upload failed.' );
        }
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) {
            return new \WP_Error( 'swimlog_pdf_permission', 'Permission denied.' );
        }
        $path = $upload['tmp_name'];
        if ( ! is_string( $path ) || ! is_uploaded_file( $path ) ||
            ! is_file( $path ) || ! is_readable( $path ) ||
            (int) $upload['size'] < 8 || (int) $upload['size'] > self::MAX_BYTES ||
            filesize( $path ) > self::MAX_BYTES ) {
            return new \WP_Error( 'swimlog_pdf_size', 'Invalid PDF upload or file exceeds 10 MB.' );
        }
        if ( 'pdf' !== strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) ) ) {
            return new \WP_Error( 'swimlog_pdf_extension', 'Only PDF files are accepted.' );
        }
        $header = file_get_contents( $path, false, null, 0, 8 );
        if ( false === $header || 0 !== strpos( $header, '%PDF-' ) ) {
            return new \WP_Error( 'swimlog_pdf_signature', 'File does not have a PDF signature.' );
        }
        // No shell/process execution: use a bounded, local PDF stream reader.
        // Supports selectable text in uncompressed and FlateDecode content streams.
        $pdf = file_get_contents( $path );
        if ( false === $pdf || strlen( $pdf ) > self::MAX_BYTES ) {
            return new \WP_Error( 'swimlog_pdf_read', 'Could not read the uploaded PDF.' );
        }
        $text = self::extract_text_streams( $pdf );
        if ( is_wp_error( $text ) ) return $text;
        $text = trim( str_replace( "\r", '', $text ) );
        if ( '' === $text ) {
            return new \WP_Error( 'swimlog_pdf_scanned', 'No selectable text found. Scanned PDFs require OCR before import.' );
        }
        return array( 'text' => $text, 'bytes' => strlen( $text ), 'sha256' => hash_file( 'sha256', $path ) );
    }
    /**
     * Extract basic PDF text operators without invoking external programs.
     * Complex font encodings and scanned PDFs are intentionally unsupported.
     */
    private static function extract_text_streams( $pdf ) {
        $text = '';
        $offset = 0;
        $count = 0;
        while ( false !== ( $stream_pos = strpos( $pdf, 'stream', $offset ) ) ) {
            if ( ++$count > 2000 ) return new \WP_Error( 'swimlog_pdf_complex', 'PDF contains too many streams.' );
            $begin = $stream_pos + 6;
            if ( isset( $pdf[$begin] ) && "\r" === $pdf[$begin] ) ++$begin;
            if ( isset( $pdf[$begin] ) && "\n" === $pdf[$begin] ) ++$begin;
            $finish = strpos( $pdf, 'endstream', $begin );
            if ( false === $finish ) break;
            $raw = substr( $pdf, $begin, $finish - $begin );
            $offset = $finish + 9;
            $dictionary = substr( $pdf, max( 0, $stream_pos - 1024 ), min( 1024, $stream_pos ) );
            if ( false !== strpos( $dictionary, '/FlateDecode' ) ) {
                if ( strlen( $raw ) > self::MAX_BYTES ) continue;
                $decoded = @gzuncompress( rtrim( $raw, "\r\n" ), self::MAX_TEXT_BYTES );
                if ( false === $decoded ) continue;
                $raw = $decoded;
            } elseif ( false !== strpos( $dictionary, '/Filter' ) ) {
                continue;
            }
            if ( ! preg_match( '/\\b(?:BT|Tj|TJ)\\b/', $raw ) ) continue;
            if ( preg_match_all( '/\\((?:\\\\\\\\.|\\\\.|[^\\\\)])*\\)\\s*(?:Tj|\\x27|\\x22)|\\[(?:[^\\]]{0,16384})\\]\\s*TJ/s', $raw, $matches ) ) {
                foreach ( $matches[0] as $operator ) {
                    if ( preg_match_all( '/\\((?:\\\\.|[^\\\\)])*\\)/s', $operator, $pieces ) ) {
                        $line = '';
                        foreach ( $pieces[0] as $piece ) {
                            $part = substr( $piece, 1, -1 );
                            $part = preg_replace_callback( '/\\\\([0-7]{1,3})/', function ( $m ) { return chr( octdec( $m[1] ) ); }, $part );
                            $part = str_replace( array( '\\\\n', '\\\\r', '\\\\t', '\\\\(', '\\\\)', '\\\\\\\\' ), array( "\n", "\r", "\t", '(', ')', '\\\\' ), $part );
                            $line .= $part;
                        }
                        $text .= $line . "\n";
                        if ( strlen( $text ) > self::MAX_TEXT_BYTES ) {
                            return new \WP_Error( 'swimlog_pdf_text_limit', 'Extracted PDF text exceeds the size limit.' );
                        }
                    }
                }
            }
        }
        return $text;
    }

}
