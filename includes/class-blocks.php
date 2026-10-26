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

		wp_register_script(
			'swimlog-block-workout',
			SWIMLOG_EVALUATION_URL . 'blocks/workout/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/workout',
			array( 'render_callback' => array( __CLASS__, 'render_workout' ) )
		);

		wp_register_script(
			'swimlog-block-training-log',
			SWIMLOG_EVALUATION_URL . 'blocks/training-log/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/training-log',
			array( 'render_callback' => array( __CLASS__, 'render_training_log' ) )
		);

		wp_register_script(
			'swimlog-block-progress',
			SWIMLOG_EVALUATION_URL . 'blocks/progress/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/progress',
			array( 'render_callback' => array( __CLASS__, 'render_progress' ) )
		);

		wp_register_script(
			'swimlog-calendar-view',
			SWIMLOG_EVALUATION_URL . 'blocks/calendar/view.js',
			array(),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		wp_register_script(
			'swimlog-block-calendar',
			SWIMLOG_EVALUATION_URL . 'blocks/calendar/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			SWIMLOG_EVALUATION_VERSION,
			true
		);

		register_block_type(
			SWIMLOG_EVALUATION_DIR . 'blocks/calendar',
			array( 'render_callback' => array( __CLASS__, 'render_calendar' ) )
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

	public static function render_workout( $attributes ) {
		return Shortcodes::render_workout(
			array(
				'id'    => isset( $attributes['workoutId'] ) ? absint( $attributes['workoutId'] ) : 0,
				'title' => self::title_attribute( $attributes ),
			)
		);
	}

	public static function render_training_log( $attributes ) {
		$mode = isset( $attributes['mode'] ) ? sanitize_key( $attributes['mode'] ) : 'monthly';
		$atts = array(
			'user'  => isset( $attributes['user'] ) ? absint( $attributes['user'] ) : '',
			'title' => self::title_attribute( $attributes ),
		);
		if ( 'annual' === $mode ) {
			$atts['year'] = isset( $attributes['year'] ) ? absint( $attributes['year'] ) : 0;
		} else {
			if ( ! empty( $attributes['month'] ) ) {
				$atts['month'] = absint( $attributes['month'] );
			}
			if ( ! empty( $attributes['year'] ) ) {
				$atts['year'] = absint( $attributes['year'] );
			}
		}
		return Shortcodes::render_log( $atts );
	}

	public static function render_progress( $attributes ) {
		return Shortcodes::render_progress(
			array(
				'user'     => isset( $attributes['user'] ) ? absint( $attributes['user'] ) : '',
				'course'   => isset( $attributes['course'] ) ? sanitize_key( $attributes['course'] ) : '',
				'stroke'   => isset( $attributes['stroke'] ) ? strtoupper( sanitize_key( $attributes['stroke'] ) ) : '',
				'distance' => isset( $attributes['distance'] ) ? absint( $attributes['distance'] ) : 0,
				'title'    => self::title_attribute( $attributes ),
			)
		);
	}

	public static function render_calendar( $attributes ) {
		$user_id = self::calendar_owner( $attributes );
		if ( ! self::calendar_allowed( $user_id ) ) {
			return '<div class="swimlog-calendar swimlog-calendar-unavailable">' . esc_html__( 'Calendar unavailable.', 'chriss-swim-training-progress-evaluation' ) . '</div>';
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-calendar.php';
		$timezone = wp_timezone();
		$year = isset( $attributes['year'] ) ? absint( $attributes['year'] ) : 0;
		$month = isset( $attributes['month'] ) ? absint( $attributes['month'] ) : 0;
		if ( $year < 1970 || $year > 9999 || $month < 1 || $month > 12 ) {
			$year = (int) wp_date( 'Y', null, $timezone );
			$month = (int) wp_date( 'n', null, $timezone );
		}
		$first = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $timezone );
		$last = $first->modify( 'last day of this month' );
		$data = Calendar::for_user( $user_id, $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ) );
		if ( is_wp_error( $data ) ) {
			return '<div class="swimlog-calendar swimlog-calendar-unavailable">' . esc_html__( 'Calendar unavailable.', 'chriss-swim-training-progress-evaluation' ) . '</div>';
		}
		return self::calendar_month_html( $data, $first, $user_id );
	}

	private static function calendar_owner( $attributes ) {
		if ( ! empty( $attributes['user'] ) ) return absint( $attributes['user'] );
		$post = get_post();
		return ( $post && $post->post_author ) ? (int) $post->post_author : 0;
	}

	private static function calendar_allowed( $user_id ) {
		if ( ! $user_id || ! is_user_logged_in() ) return false;
		if ( get_current_user_id() === $user_id && current_user_can( 'swimlog_view_own_results' ) ) return true;
		return current_user_can( 'swimlog_manage_all_users' );
	}

	private static function calendar_month_html( $data, $month, $user_id ) {
		$first_day = $data['range']['first_day'];
		$labels = array(
			'sunday' => array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
			'monday' => array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ),
			'saturday' => array( 'Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ),
		);
		$target = array( 'sunday' => 0, 'monday' => 1, 'saturday' => 6 )[ $first_day ];
		$days_back = ( (int) $month->format( 'w' ) - $target + 7 ) % 7;
		$grid_start = $month->modify( '-' . $days_back . ' days' );
		$grid_end = $month->modify( 'last day of this month' );
		$days_forward = ( $target + 6 - (int) $grid_end->format( 'w' ) + 7 ) % 7;
		$grid_end = $grid_end->modify( '+' . $days_forward . ' days' );
		$items = array();
		foreach ( $data['workouts'] as $workout ) {
			$date = substr( $workout['workout_start'], 0, 10 );
			$items[ $date ][] = self::calendar_workout_html( $workout );
		}
		foreach ( $data['events'] as $event ) {
			$items[ $event['event_date'] ][] = self::calendar_event_html( $event );
		}
		wp_enqueue_script( 'swimlog-calendar-view' );
		$rest_url = rest_url( 'swimlog/v1/calendar' );
		$html = '<section class="swimlog-calendar" data-swimlog-calendar="1" data-rest-url="' . esc_url( $rest_url ) . '" data-user="' . esc_attr( $user_id ) . '" data-year="' . esc_attr( $month->format( 'Y' ) ) . '" data-month="' . esc_attr( $month->format( 'n' ) ) . '" data-first-day="' . esc_attr( $first_day ) . '" aria-label="' . esc_attr__( 'Swim Training Calendar', 'chriss-swim-training-progress-evaluation' ) . '">';
		$html .= '<div class="swimlog-calendar-toolbar">';
		$html .= '<div class="swimlog-calendar-nav" aria-label="' . esc_attr__( 'Calendar navigation', 'chriss-swim-training-progress-evaluation' ) . '"><button type="button" class="swimlog-calendar-button" data-calendar-action="previous">' . esc_html__( 'Previous', 'chriss-swim-training-progress-evaluation' ) . '</button><button type="button" class="swimlog-calendar-button" data-calendar-action="today">' . esc_html__( 'Today', 'chriss-swim-training-progress-evaluation' ) . '</button><button type="button" class="swimlog-calendar-button" data-calendar-action="next">' . esc_html__( 'Next', 'chriss-swim-training-progress-evaluation' ) . '</button></div>';
		$html .= '<div class="swimlog-calendar-view-switch" aria-label="' . esc_attr__( 'Calendar view', 'chriss-swim-training-progress-evaluation' ) . '"><button type="button" class="swimlog-calendar-button is-active" data-calendar-view="month" aria-pressed="true">' . esc_html__( 'Month', 'chriss-swim-training-progress-evaluation' ) . '</button><button type="button" class="swimlog-calendar-button" data-calendar-view="week" aria-pressed="false">' . esc_html__( 'Week', 'chriss-swim-training-progress-evaluation' ) . '</button></div>';
		$html .= '</div><h2 class="swimlog-calendar-title" aria-live="polite">' . esc_html( wp_date( 'F Y', $month->getTimestamp(), $month->getTimezone() ) ) . '</h2>';
		$html .= '<div class="swimlog-calendar-status" role="status" aria-live="polite"></div>';
		$html .= '<div class="swimlog-calendar-grid" role="grid">';
		foreach ( $labels[ $first_day ] as $label ) $html .= '<div class="swimlog-calendar-weekday" role="columnheader">' . esc_html( $label ) . '</div>';
		for ( $date = $grid_start; $date <= $grid_end; $date = $date->modify( '+1 day' ) ) {
			$key = $date->format( 'Y-m-d' );$outside = $date->format( 'm' ) !== $month->format( 'm' );
			$html .= '<div class="swimlog-calendar-day' . ( $outside ? ' is-outside-month' : '' ) . '" role="gridcell" data-date="' . esc_attr( $key ) . '"><span class="swimlog-calendar-date">' . esc_html( $date->format( 'j' ) ) . '</span>';
			if ( ! empty( $items[ $key ] ) ) $html .= implode( '', $items[ $key ] );
			$html .= '</div>';
		}
		$html .= '</div><div class="swimlog-calendar-agenda" data-calendar-agenda="1" aria-label="' . esc_attr__( 'Calendar agenda', 'chriss-swim-training-progress-evaluation' ) . '"></div><div class="swimlog-calendar-detail" data-calendar-detail="1" hidden aria-live="polite"></div></section>';
		return $html;
	}

	private static function calendar_workout_html( $workout ) {
		$type = 'open_water' === $workout['workout_type'] ? __( 'Open Water', 'chriss-swim-training-progress-evaluation' ) : __( 'Pool Swim', 'chriss-swim-training-progress-evaluation' );
		$parts = array( esc_html( $type ) );
		if ( null !== $workout['total_distance_m'] ) $parts[] = esc_html( number_format_i18n( $workout['total_distance_m'], 0 ) . ' m' );
		$active = (int) ( $workout['moving_time_ms'] ?: $workout['elapsed_time_ms'] );
		if ( $active > 0 ) $parts[] = esc_html( self::calendar_duration( $active ) );
		$detail = wp_json_encode( array(
			'id' => (int) $workout['id'],
			'workout_type' => $workout['workout_type'],
			'workout_start' => $workout['workout_start'],
			'location' => $workout['location'],
			'total_distance_m' => $workout['total_distance_m'],
			'elapsed_time_ms' => $workout['elapsed_time_ms'],
			'moving_time_ms' => $workout['moving_time_ms'],
			'pool_length_m' => $workout['pool_length_m'],
			'original_pool_length' => $workout['original_pool_length'],
			'pool_length_unit' => $workout['pool_length_unit'],
			'primary_stroke' => $workout['primary_stroke'],
		) );
		return '<button type="button" class="swimlog-calendar-item swimlog-calendar-workout" data-calendar-workout="' . esc_attr( $detail ) . '" aria-expanded="false">' . implode( '<span aria-hidden="true"> · </span>', $parts ) . '</button>';
	}

	private static function calendar_event_html( $event ) {
		$name = '' !== trim( $event['event_name'] ) ? $event['event_name'] : __( 'Swim Event', 'chriss-swim-training-progress-evaluation' );
		return '<div class="swimlog-calendar-item swimlog-calendar-event"><strong>' . esc_html( $name ) . '</strong></div>';
	}

	private static function calendar_duration( $milliseconds ) {
		$minutes = (int) floor( $milliseconds / 60000 );
		$hours = (int) floor( $minutes / 60 );$minutes %= 60;
		return $hours > 0 ? sprintf( '%dh %02dm', $hours, $minutes ) : sprintf( '%dm', $minutes );
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
