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
        // Pure-PHP parser decodes embedded ToUnicode font mappings; no shell calls.
        $autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
        if ( ! is_readable( $autoload ) ) {
            return new \WP_Error( 'swimlog_pdf_dependency', 'Bundled PDF parser is missing; rebuild the plugin with production Composer dependencies.' );
        }
        require_once $autoload;
        try {
            $config = new \Smalot\\PdfParser\\Config();
            $config->setDecodeMemoryLimit( 32 * 1024 * 1024 );
            $parser = new \Smalot\\PdfParser\\Parser( array(), $config );
            $document = $parser->parseFile( $path );
            $text = trim( str_replace( "\\r", '', $document->getText() ) );
        } catch ( \Throwable $error ) {
            return new \WP_Error( 'swimlog_pdf_decode', 'Could not decode PDF text safely.' );
        }
        if ( strlen( $text ) > self::MAX_TEXT_BYTES ) {
            return new \WP_Error( 'swimlog_pdf_text_limit', 'Extracted PDF text exceeds the size limit.' );
        }
        if ( '' === $text ) {
            return new \WP_Error( 'swimlog_pdf_scanned', 'No selectable text found. Scanned PDFs require OCR before import.' );
        }
        if ( ! preg_match( '/\\b(?:swim|workout|session|practice|week)\\b/i', $text ) ) {
            return new \WP_Error( 'swimlog_pdf_encoding', 'PDF text has no recognizable workout headings; review the document or font encoding.' );
        }
        return array( 'text' => $text, 'bytes' => strlen( $text ), 'sha256' => hash_file( 'sha256', $path ) );
    }
}
