<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Native dynamic Block Editor integration. */
final class Blocks {
	public static function register() {
		wp_register_script(
			'swimlog-block-latest-workouts',
			SWIMLOG_EVALUATION_URL . 'blocks/latest-workouts/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/latest-workouts',
			array( 'render_callback' => array( __CLASS__, 'render_latest_workouts' ) )
		);
	}

	public static function render_latest_workouts( $attributes ) {
		return Shortcodes::render_latest(
			array(
				'user'  => isset( $attributes['user'] ) ? absint( $attributes['user'] ) : '',
				'count' => isset( $attributes['count'] ) ? absint( $attributes['count'] ) : '',
				'title' => self::title_attribute( $attributes ),
			)
		);
	}

	private static function title_attribute( $attributes ) {
		$mode = isset( $attributes['titleMode'] ) ? sanitize_key( $attributes['titleMode'] ) : 'default';
		if ( 'hidden' === $mode ) {
			return 'no';
		}
		if ( 'custom' === $mode ) {
			return sanitize_text_field( isset( $attributes['customTitle'] ) ? $attributes['customTitle'] : '' );
		}
		return 'yes';
	}
}
