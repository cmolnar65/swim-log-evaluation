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

		wp_register_script(
			'swimlog-block-personal-bests',
			SWIMLOG_EVALUATION_URL . 'blocks/personal-bests/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/personal-bests',
			array( 'render_callback' => array( __CLASS__, 'render_personal_bests' ) )
		);

		wp_register_script(
			'swimlog-block-upcoming-events',
			SWIMLOG_EVALUATION_URL . 'blocks/upcoming-events/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/upcoming-events',
			array( 'render_callback' => array( __CLASS__, 'render_upcoming_events' ) )
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

	public static function render_personal_bests( $attributes ) {
		$best = isset( $attributes['best'] ) ? strtoupper( sanitize_key( $attributes['best'] ) ) : 'LB';
		$ag = '';
		if ( 'AG' === $best ) {
			$age_group_mode = isset( $attributes['ageGroupMode'] ) ? sanitize_key( $attributes['ageGroupMode'] ) : 'post-date';
			if ( 'current' === $age_group_mode ) {
				$ag = 'current';
			} elseif ( 'custom' === $age_group_mode ) {
				$ag = sanitize_text_field( isset( $attributes['ageGroup'] ) ? $attributes['ageGroup'] : '' );
			}
		}

		return Shortcodes::render_bests(
			array(
				'user'     => isset( $attributes['user'] ) ? absint( $attributes['user'] ) : '',
				'course'   => isset( $attributes['course'] ) ? sanitize_key( $attributes['course'] ) : '',
				'stroke'   => isset( $attributes['stroke'] ) ? strtoupper( sanitize_key( $attributes['stroke'] ) ) : '',
				'distance' => isset( $attributes['distance'] ) ? absint( $attributes['distance'] ) : '',
				'best'     => $best,
				'ag'       => $ag,
				'title'    => self::title_attribute( $attributes ),
			)
		);
	}

	public static function render_upcoming_events( $attributes ) {
		return Shortcodes::render_events(
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
