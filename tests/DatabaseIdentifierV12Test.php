<?php
use PHPUnit\Framework\TestCase;

final class DatabaseIdentifierV12Test extends TestCase {
    public function test_location_queries_prepare_identifiers_with_legacy_fallback() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-location.php' );
        $this->assertStringContainsString( "has_cap( 'identifier_placeholders' )", $source );
        $this->assertStringContainsString( 'SELECT * FROM %i WHERE user_id = %d', $source );
        $this->assertStringContainsString( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', $source );
        $this->assertStringContainsString( 'esc_sql( $table )', $source );
    }
}
