<?php
use PHPUnit\Framework\TestCase;

final class BlockIntegrationTest extends TestCase {
	private function block_names() {
		return array(
			'latest-workouts' => 'swimlog/latest-workouts',
			'personal-bests' => 'swimlog/personal-bests',
			'upcoming-events' => 'swimlog/upcoming-events',
			'workout' => 'swimlog/workout',
			'training-log' => 'swimlog/training-log',
			'progress' => 'swimlog/progress',
			'calendar' => 'swimlog/calendar',
		);
	}

	public function test_all_public_features_have_native_dynamic_blocks() {
		foreach ( $this->block_names() as $dir => $name ) {
			$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/block.json' ), true );
			$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/index.js' );
			$this->assertSame( $name, $metadata['name'], $dir );
			$this->assertSame( 3, $metadata['apiVersion'], $dir );
			$this->assertSame( 'widgets', $metadata['category'], $dir );
			$this->assertSame( 'chriss-swim-training-progress-evaluation', $metadata['textdomain'], $dir );
			$this->assertFalse( $metadata['supports']['html'], $dir );
			$this->assertSame( array( 'wide', 'full' ), $metadata['supports']['align'], $dir );
			$this->assertStringContainsString( "registerBlockType", $editor, $dir );
			$this->assertStringContainsString( $name, $editor, $dir );
			$this->assertStringContainsString( 'ServerSideRender', $editor, $dir );
			$this->assertStringContainsString( 'save:', $editor, $dir );
			$this->assertStringContainsString( 'return null', $editor, $dir );
			$this->assertStringNotContainsString( '[swimlog_', $editor, $dir );
		}
	}

	public function test_public_table_css_preserves_readable_words_and_scrolls_when_constrained() {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/public.css' );
		$this->assertStringContainsString( 'overflow-x: auto', $css );
		$this->assertStringContainsString( 'min-width: 680px', $css );
		$this->assertStringContainsString( 'font-size: .85em', $css );
		$this->assertStringContainsString( 'overflow-wrap: normal', $css );
		$this->assertStringContainsString( 'white-space: nowrap', $css );
		$this->assertStringNotContainsString( 'overflow-wrap: anywhere', $css );
	}

	public function test_all_blocks_are_registered_with_php_render_callbacks() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		foreach ( array(
			'latest-workouts' => 'render_latest_workouts',
			'personal-bests' => 'render_personal_bests',
			'upcoming-events' => 'render_upcoming_events',
			'workout' => 'render_workout',
			'training-log' => 'render_training_log',
			'progress' => 'render_progress',
			'calendar' => 'render_calendar',
		) as $dir => $callback ) {
			$this->assertStringContainsString( "blocks/{$dir}", $blocks );
			$this->assertStringContainsString( "'{$callback}'", $blocks );
		}
	}

	public function test_legacy_shortcode_surface_remains_exactly_six_entries() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		preg_match_all( "/add_shortcode\('([^']+)'/", $shortcodes, $matches );
		$expected = array( 'swimlog_latest', 'swimlog_bests', 'swimlog_upcoming_events', 'swimlog_workout', 'swimlog_log', 'swimlog_progress' );
		$this->assertSame( $expected, $matches[1] );
		foreach ( array(
			'latest' => 'render_latest',
			'bests' => 'render_bests',
			'events' => 'render_events',
			'workout' => 'render_workout',
			'log' => 'render_log',
			'progress' => 'render_progress',
		) as $handler => $renderer ) {
			$this->assertStringContainsString( "public static function {$handler}(\$atts){return self::{$renderer}(\$atts);}", $shortcodes );
		}
	}

	public function test_shared_heading_contract_is_consistent_across_blocks() {
		foreach ( array_keys( $this->block_names() ) as $dir ) {
			$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/block.json' ), true );
			$this->assertSame( 'default', $metadata['attributes']['titleMode']['default'], $dir );
			$this->assertSame( '', $metadata['attributes']['customTitle']['default'], $dir );
		}
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$this->assertStringContainsString( "if ( 'hidden' === \$mode )", $blocks );
		$this->assertStringContainsString( "if ( 'custom' === \$mode )", $blocks );
		$this->assertStringContainsString( "return 'yes';", $blocks );
	}

	public function test_supported_distance_controls_match_evaluator_distances() {
		$evaluator = file_get_contents( dirname( __DIR__ ) . '/includes/class-evaluator.php' );
		preg_match( '/const DISTANCES=array\(([^)]+)\)/', $evaluator, $match );
		$distances = array_map( 'intval', explode( ',', $match[1] ) );

		$personal_bests = file_get_contents( dirname( __DIR__ ) . '/blocks/personal-bests/index.js' );
		$progress = file_get_contents( dirname( __DIR__ ) . '/blocks/progress/index.js' );
		foreach ( $distances as $distance ) {
			$this->assertStringContainsString( (string) $distance, $personal_bests, 'personal-bests ' . $distance );
			$this->assertStringContainsString( (string) $distance, $progress, 'progress ' . $distance );
		}
	}

	public function test_user_override_policy_is_consistent_except_workout_ownership() {
		foreach ( array( 'latest-workouts', 'personal-bests', 'upcoming-events', 'training-log', 'progress', 'calendar' ) as $dir ) {
			$metadata = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/block.json' ), true );
			$editor = file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/index.js' );
			$this->assertArrayHasKey( 'user', $metadata['attributes'], $dir );
			$this->assertStringContainsString( 'page or post author', $editor, $dir );
			$this->assertStringContainsString( 'no logged-in-viewer fallback', $editor, $dir );
		}
		$workout = json_decode( file_get_contents( dirname( __DIR__ ) . '/blocks/workout/block.json' ), true );
		$workout_editor = file_get_contents( dirname( __DIR__ ) . '/blocks/workout/index.js' );
		$this->assertArrayNotHasKey( 'user', $workout['attributes'] );
		$this->assertStringContainsString( 'ownership cannot be overridden', $workout_editor );
	}
}
