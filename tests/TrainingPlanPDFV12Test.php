<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-training-plan-pdf.php';
final class TrainingPlanPDFV12Test extends TestCase {
    public function test_invalid_upload_is_rejected_without_reading_a_pdf() {
        $result = SwimLogEvaluation\Training_Plan_PDF::extract( array() );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'swimlog_pdf_upload', $result->get_error_code() );
    }

    public function test_bundled_parser_classes_construct_with_the_memory_limit() {
        $config = new Smalot\PdfParser\Config();
        $config->setDecodeMemoryLimit( 32 * 1024 * 1024 );
        $parser = new Smalot\PdfParser\Parser( array(), $config );
        $this->assertInstanceOf( Smalot\PdfParser\Parser::class, $parser );
    }

    public function test_pdf_extraction_has_private_upload_guards() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-pdf.php' );
        foreach ( array( 'is_uploaded_file(', 'current_user_can(', 'MAX_BYTES', 'MAX_TEXT_BYTES', "'%PDF-'", 'vendor/autoload.php', 'Smalot\\PdfParser\\Parser', 'setDecodeMemoryLimit(', 'swimlog_pdf_scanned' ) as $guard ) {
            $this->assertStringContainsString( $guard, $source );
        }
    }
    public function test_import_screen_requires_nonce_acknowledgement_and_owner_scoped_preview() {
        $source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        foreach ( array( "check_admin_referer( 'swimlog_pdf_extract' )", 'swimlog_pdf_acknowledge', "set_transient( 'swimlog_pdf_' . \$owner", "get_transient( 'swimlog_pdf_' . \$owner", 'esc_textarea(', 'No workouts have been created.' ) as $guard ) {
            $this->assertStringContainsString( $guard, $source );
        }
    }


}

