<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per-swimmer presentation units. Stored workout and measurement values stay canonical. */
final class Unit_Preferences {
	const META_KEY = 'swimlog_unit_preferences';

	public static function defaults() {
		$course = get_option( 'swimlog_default_course_unit', 'm' );
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) $course = 'm';
		return array(
			'swim_course' => $course,
			'running' => 'km',
			'walking' => 'km',
			'cycling' => 'km',
			'weight' => 'kg',
			'show_secondary' => false,
		);
	}

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) return new \WP_Error( 'swimlog_units', __( 'Choose valid unit preferences.', 'chriss-swim-training-progress-evaluation' ) );
		$defaults = self::defaults();
		$clean = array();
		$clean['swim_course'] = isset( $input['swim_course'] ) && in_array( $input['swim_course'], array( 'm', 'yd' ), true ) ? $input['swim_course'] : null;
		foreach ( array( 'running', 'walking', 'cycling' ) as $type ) {
			$clean[ $type ] = isset( $input[ $type ] ) && in_array( $input[ $type ], array( 'km', 'mi' ), true ) ? $input[ $type ] : null;
		}
		$clean['weight'] = isset( $input['weight'] ) && in_array( $input['weight'], array( 'kg', 'lb' ), true ) ? $input['weight'] : null;
		foreach ( $clean as $key => $value ) {
			if ( null === $value ) return new \WP_Error( 'swimlog_units', __( 'Choose valid unit preferences.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$show_secondary = $input['show_secondary'] ?? false;
		if ( ! in_array( $show_secondary, array( true, false, 0, 1, '0', '1', '' ), true ) ) return new \WP_Error( 'swimlog_units', __( 'Choose valid unit preferences.', 'chriss-swim-training-progress-evaluation' ) );
		$clean['show_secondary'] = in_array( $show_secondary, array( true, 1, '1' ), true );
		return $clean;
	}

	public static function get( $owner ) {
		$owner = absint( $owner );
		$preferences = $owner ? get_user_meta( $owner, self::META_KEY, true ) : array();
		$defaults = self::defaults();
		if ( ! is_array( $preferences ) ) $preferences = array();
		$preferences = array_merge( $defaults, $preferences );
		$clean = self::sanitize( $preferences );
		return is_wp_error( $clean ) ? $defaults : $clean;
	}

	public static function save( $owner, $input ) {
		$owner = absint( $owner );
		$clean = self::sanitize( $input );
		if ( ! $owner || is_wp_error( $clean ) ) return is_wp_error( $clean ) ? $clean : new \WP_Error( 'swimlog_units', __( 'Choose valid unit preferences.', 'chriss-swim-training-progress-evaluation' ) );
		update_user_meta( $owner, self::META_KEY, $clean );
		return $clean;
	}

	private static function number( $value, $decimals ) {
		return number_format_i18n( (float) $value, $decimals );
	}

	/** Format canonical meters for land activities; swim course values are never converted here. */
	public static function distance( $meters, $activity, $owner, $decimals = 2 ) {
		if ( null === $meters || '' === $meters || ! is_numeric( $meters ) ) return '—';
		$activity = in_array( $activity, array( 'running', 'walking', 'cycling' ), true ) ? $activity : '';
		if ( '' === $activity ) return self::number( $meters, 0 ) . ' m';
		$prefs = self::get( $owner );
		$primary = $prefs[ $activity ];
		$meters_per_unit = 'mi' === $primary ? 1609.344 : 1000;
		$display = self::number( (float) $meters / $meters_per_unit, $decimals ) . ' ' . $primary;
		if ( $prefs['show_secondary'] ) {
			$secondary = 'mi' === $primary ? 'km' : 'mi';
			$secondary_per_unit = 'mi' === $secondary ? 1609.344 : 1000;
			$display .= ' (' . self::number( (float) $meters / $secondary_per_unit, $decimals ) . ' ' . $secondary . ')';
		}
		return $display;
	}

	/** Format canonical kilograms for private measurement displays. */
	public static function weight( $kilograms, $owner, $decimals = 1 ) {
		if ( null === $kilograms || '' === $kilograms || ! is_numeric( $kilograms ) ) return '';
		$prefs = self::get( $owner );
		$primary = $prefs['weight'];
		$display = self::number( (float) $kilograms * ( 'lb' === $primary ? 2.2046226218 : 1 ), $decimals ) . ' ' . $primary;
		if ( $prefs['show_secondary'] ) {
			$secondary = 'lb' === $primary ? 'kg' : 'lb';
			$display .= ' (' . self::number( (float) $kilograms * ( 'lb' === $secondary ? 2.2046226218 : 1 ), $decimals ) . ' ' . $secondary . ')';
		}
		return $display;
	}
}
