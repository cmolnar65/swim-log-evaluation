<?php
use PHPUnit\Framework\TestCase;

final class DatabaseIdentifierV12Test extends TestCase {
    public function test_location_queries_use_wordpress_62_identifiers() {
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-location.php' );
        $this->assertStringContainsString( 'SELECT * FROM %i WHERE user_id = %d', $source );
        $this->assertStringContainsString( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', $source );
        $this->assertStringNotContainsString( "has_cap( 'identifier_placeholders' )", $source );
    }
}
