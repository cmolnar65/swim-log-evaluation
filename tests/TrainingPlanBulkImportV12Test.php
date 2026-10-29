<?php
use PHPUnit\Framework\TestCase;
final class TrainingPlanBulkImportV12Test extends TestCase {
    public function test_bulk_import_guards() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-bulk-import.php' );
        $this->assertStringContainsString( 'Training_Plan_Review::assess', $source );
        $this->assertStringContainsString( 'swimlog_bulk_duplicate', $source );
        $this->assertStringContainsString( 'swimlog_bulk_rollback', $source );
    }
    public function test_admin_bulk_import_nonce() {
        $source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString( 'swimlog_pdf_bulk_import', $source );
        $this->assertStringContainsString( 'Training_Plan_Bulk_Import::run', $source );
    }
}
