<?php
use PHPUnit\Framework\TestCase;

final class WorkoutBlockTest extends TestCase {
	public function test_workout_block_is_native_dynamic_block() {
		$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/workout/block.json' ), true );
		$this->assertSame( 'swimlog/workout', $metadata['name'] );
		$this->assertSame( 'swimlog-block-workout', $metadata['editorScript'] );
		$this->assertFalse( $metadata['supports']['html'] );
		$this->assertSame( 0, $metadata['attributes']['workoutId']['default'] );
		$this->assertArrayNotHasKey( 'user', $metadata['attributes'] );
	}

	public function test_block_and_shortcode_share_workout_renderer() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/workout/index.js' );
		$this->assertStringContainsString( 'Shortcodes::render_workout(', $blocks );
		$this->assertStringContainsString( 'public static function workout($atts){return self::render_workout($atts);}', $shortcodes );
		$this->assertStringNotContainsString( '[swimlog_workout', $editor );
		$this->assertStringContainsString( "block: 'swimlog/workout'", $editor );
	}

	public function test_workout_owner_remains_authoritative() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$this->assertStringContainsString( 'SELECT id,user_id', $shortcodes );
		$this->assertStringContainsString( 'self::owner($a,$base)', $shortcodes );
		$this->assertStringContainsString( 'self::allowed($uid)', $shortcodes );
		$start = strpos( $blocks, 'public static function render_workout' );
		$end = strpos( $blocks, 'public static function render_training_log', $start );
		$this->assertStringNotContainsString( "'user'  =>", substr( $blocks, $start, $end - $start ) );
	}

	public function test_open_water_route_privacy_gate_remains_in_shared_renderer() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$this->assertStringContainsString( "self::public_routes_allowed(\$uid)", $shortcodes );
		$this->assertStringContainsString( "Workout::trackpoints(\$id,\$uid)", $shortcodes );
		$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/workout/index.js' );
		$this->assertStringNotContainsString( 'trackpoints', $editor );
		$this->assertStringNotContainsString( 'latitude', $editor );
		$this->assertStringNotContainsString( 'longitude', $editor );
	}
}
