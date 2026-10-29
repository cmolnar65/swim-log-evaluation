<?php
use PHPUnit\Framework\TestCase;
final class TrainingPlanPDFV12Test extends TestCase {
    public function test_pdf_extraction_has_private_upload_guards() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-pdf.php' );
        foreach ( array( 'is_uploaded_file(', 'current_user_can(', 'MAX_BYTES', 'MAX_TEXT_BYTES', "'%PDF-'", 'vendor/autoload.php', 'Smalot\\\\PdfParser\\\\Parser', 'setDecodeMemoryLimit(', 'swimlog_pdf_scanned' ) as $guard ) {
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
