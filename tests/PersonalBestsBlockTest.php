<?php
use PHPUnit\Framework\TestCase;

final class PersonalBestsBlockTest extends TestCase {
	public function test_personal_bests_block_is_native_dynamic_block() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/personal-bests/block.json' ), true );
		$this->assertSame( 'swimlog/personal-bests', $metadata['name'] );
		$this->assertSame( 'swimlog-block-personal-bests', $metadata['editorScript'] );
		$this->assertFalse( $metadata['supports']['html'] );
	}

	public function test_block_and_shortcode_share_personal_bests_renderer() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/personal-bests/index.js' );

		$this->assertStringContainsString( 'Shortcodes::render_bests(', $blocks );
		$this->assertStringContainsString( 'public static function bests($atts){return self::render_bests($atts);}', $shortcodes );
		$this->assertStringNotContainsString( '[swimlog_bests', $editor );
		$this->assertStringContainsString( "block: 'swimlog/personal-bests'", $editor );
	}

	public function test_personal_bests_attributes_preserve_shortcode_defaults() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/personal-bests/block.json' ), true );
		$a = $metadata['attributes'];
		$this->assertSame( 0, $a['user']['default'] );
		$this->assertSame( '', $a['course']['default'] );
		$this->assertSame( '', $a['stroke']['default'] );
		$this->assertSame( 0, $a['distance']['default'] );
		$this->assertSame( 'LB', $a['best']['default'] );
		$this->assertSame( 'post-date', $a['ageGroupMode']['default'] );
	}

	public function test_editor_exposes_supported_pb_filters_without_mixing_courses() {
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/personal-bests/index.js' );
		$this->assertStringContainsString( "Configured default", $editor );
		$this->assertStringContainsString( "value: 'm'", $editor );
		$this->assertStringContainsString( "value: 'yd'", $editor );
		$this->assertStringContainsString( 'All strokes with personal bests', $editor );
		$this->assertStringContainsString( "value: 'AG'", $editor );
		$this->assertStringContainsString( "value: 'current'", $editor );
		$this->assertStringContainsString( "value: 'post-date'", $editor );
	}

	public function test_block_maps_age_group_modes_to_existing_shortcode_semantics() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$this->assertStringContainsString( "'current' === \\$age_group_mode", $blocks );
		$this->assertStringContainsString( "'custom' === \\$age_group_mode", $blocks );
		$this->assertStringContainsString( "'ag'       => \\$ag", $blocks );
	}
}
