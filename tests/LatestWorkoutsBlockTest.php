<?php
use PHPUnit\Framework\TestCase;

final class LatestWorkoutsBlockTest extends TestCase {
	public function test_latest_workouts_block_is_native_dynamic_block() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/latest-workouts/block.json' ), true );
		$this->assertSame( 'swimlog/latest-workouts', $metadata['name'] );
		$this->assertSame( 'swimlog-block-latest-workouts', $metadata['editorScript'] );
		$this->assertFalse( $metadata['supports']['html'] );
	}

	public function test_block_maps_to_shared_latest_renderer_not_shortcode_text() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/latest-workouts/index.js' );

		$this->assertStringContainsString( 'Shortcodes::render_latest(', $blocks );
		$this->assertStringContainsString( 'public static function latest($atts){return self::render_latest($atts);}', $shortcodes );
		$this->assertStringNotContainsString( '[swimlog_latest', $editor );
		$this->assertStringContainsString( "block: 'swimlog/latest-workouts'", $editor );
	}

	public function test_block_preserves_latest_shortcode_attribute_semantics() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/latest-workouts/block.json' ), true );
		$this->assertSame( 1, $metadata['attributes']['count']['default'] );
		$this->assertSame( 0, $metadata['attributes']['user']['default'] );
		$this->assertSame( 'default', $metadata['attributes']['titleMode']['default'] );

		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/latest-workouts/index.js' );
		$this->assertStringContainsString( 'max: 20', $editor );
		$this->assertStringContainsString( 'use the page or post author', $editor );
	}
}
