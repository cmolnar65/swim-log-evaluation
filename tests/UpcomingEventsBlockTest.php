<?php
use PHPUnit\Framework\TestCase;

final class UpcomingEventsBlockTest extends TestCase {
	public function test_upcoming_events_block_is_native_dynamic_block() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/upcoming-events/block.json' ), true );
		$this->assertSame( 'swimlog/upcoming-events', $metadata['name'] );
		$this->assertSame( 'swimlog-block-upcoming-events', $metadata['editorScript'] );
		$this->assertFalse( $metadata['supports']['html'] );
	}

	public function test_block_and_shortcode_share_upcoming_events_renderer() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/upcoming-events/index.js' );
		$this->assertStringContainsString( 'Shortcodes::render_events(', $blocks );
		$this->assertStringContainsString( 'public static function events($atts){return self::render_events($atts);}', $shortcodes );
		$this->assertStringNotContainsString( '[swimlog_upcoming_events', $editor );
		$this->assertStringContainsString( "block: 'swimlog/upcoming-events'", $editor );
	}

	public function test_omitted_count_preserves_site_default_semantics() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/upcoming-events/block.json' ), true );
		$this->assertSame( 0, $metadata['attributes']['count']['default'] );
		$this->assertSame( 0, $metadata['attributes']['user']['default'] );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/upcoming-events/index.js' );
		$this->assertStringContainsString( 'Use site default event count', $editor );
		$this->assertStringContainsString( 'min: 1, max: 50', $editor );
		$this->assertStringContainsString( 'use the page or post author', $editor );
	}

	public function test_existing_shortcode_count_clamping_remains_authoritative() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$this->assertStringContainsString( "get_option('swimlog_default_event_count',5)", $shortcodes );
		$this->assertStringContainsString( '$count=max(1,min(50,$count))', $shortcodes );
	}
}
