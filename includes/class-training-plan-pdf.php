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
        if ( ! function_exists( 'proc_open' ) ) {
            return new \WP_Error( 'swimlog_pdf_unavailable', 'PDF text extraction is unavailable on this server.' );
        }
        $binary = apply_filters( 'swimlog_pdf_text_extractor', '/usr/bin/pdftotext' );
        if ( ! is_string( $binary ) || ! in_array( $binary, array( '/usr/bin/pdftotext', '/usr/local/bin/pdftotext' ), true ) ||
            ! is_file( $binary ) || ! is_executable( $binary ) ) {
            return new \WP_Error( 'swimlog_pdf_unavailable', 'The server needs the pdftotext utility to import PDFs.' );
        }
        // Command arguments are individually quoted; no uploaded filename reaches the shell.
        $command = escapeshellarg( $binary ) . ' -layout -enc UTF-8 -nopgbrk ' . escapeshellarg( $path ) . ' -';
        $descriptors = array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) );
        $process = proc_open( $command, $descriptors, $pipes );
        if ( ! is_resource( $process ) ) {
            return new \WP_Error( 'swimlog_pdf_extract', 'Could not start PDF extraction.' );
        }
        fclose( $pipes[0] );
        stream_set_blocking( $pipes[1], false );
        stream_set_blocking( $pipes[2], false );
        $text = '';
        $stderr = '';
        $started = microtime( true );
        $timed_out = false;
        $oversized = false;
        while ( true ) {
            $read = array();
            if ( ! feof( $pipes[1] ) ) $read[] = $pipes[1];
            if ( ! feof( $pipes[2] ) ) $read[] = $pipes[2];
            if ( ! $read ) break;
            if ( microtime( true ) - $started > 15 ) { $timed_out = true; break; }
            $write = null;
            $except = null;
            $ready = stream_select( $read, $write, $except, 0, 200000 );
            if ( false === $ready ) break;
            foreach ( $read as $stream ) {
                $chunk = fread( $stream, 8192 );
                if ( $stream === $pipes[1] ) $text .= $chunk;
                else $stderr .= $chunk;
            }
            if ( strlen( $text ) > self::MAX_TEXT_BYTES || strlen( $stderr ) > 8192 ) {
                $oversized = true;
                break;
            }
        }
        if ( $timed_out || $oversized ) proc_terminate( $process );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $status = proc_close( $process );
        if ( $timed_out || $oversized || 0 !== $status ) {
            return new \WP_Error( 'swimlog_pdf_extract', 'Unable to extract PDF text. The document may be encrypted, unsupported, or too large.' );
        }
        $text = trim( str_replace( "\r", '', $text ) );
        if ( '' === $text ) {
            return new \WP_Error( 'swimlog_pdf_scanned', 'No selectable text found. Scanned PDFs require OCR before import.' );
        }
        return array( 'text' => $text, 'bytes' => strlen( $text ), 'sha256' => hash_file( 'sha256', $path ) );
    }
}
