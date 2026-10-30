<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared presentation helpers for swim training administration. */
final class Admin_UI {
	public static function selected_user_id() {
		$uid = get_current_user_id();
		if ( current_user_can( 'swimlog_manage_all_users' ) && isset( $_GET['swimmer_id'] ) && is_scalar( $_GET['swimmer_id'] ) && isset( $_GET['_swimlog_swimmer_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_swimlog_swimmer_nonce'] ) ), 'swimlog_swimmer_selector' ) ) {
			$requested = absint( wp_unslash( $_GET['swimmer_id'] ) );
			if ( $requested && get_userdata( $requested ) ) $uid = $requested;
		}
		return $uid;
	}

	public static function swimmer_selector( $user_id, $page ) {
		if ( ! current_user_can( 'swimlog_manage_all_users' ) ) return;
		$users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
		?><form method="get" class="swimlog-swimmer-selector"><?php wp_nonce_field( 'swimlog_swimmer_selector', '_swimlog_swimmer_nonce' ); ?><input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>"><label for="swimlog-swimmer"><?php esc_html_e( 'Swimmer', 'chriss-swim-training-progress-evaluation' ); ?></label> <select id="swimlog-swimmer" name="swimmer_id"><?php foreach ( $users as $u ) : ?><option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>><?php echo esc_html( $u->display_name . ' (#' . $u->ID . ')' ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'View', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?></form><?php
	}

    /** Request-local form recovery; caller supplies an owner-checked record and whitelisted defaults. */
    public static function form_record( $record, $submitted, $defaults ) {
        $values = $defaults;
        foreach ( $defaults as $key => $default ) {
            if ( is_object( $record ) && isset( $record->$key ) ) $values[$key] = $record->$key;
            if ( is_array( $submitted ) && array_key_exists( $key, $submitted ) && is_scalar( $submitted[$key] ) ) $values[$key] = $submitted[$key];
        }
        return (object) $values;
    }

    /** Exact duration text for editing, including stored fractional milliseconds. */
    public static function duration_text( $milliseconds ) {
        if ( null === $milliseconds || '' === $milliseconds ) return '';
        $milliseconds = (int) $milliseconds;
        $seconds = intdiv( $milliseconds, 1000 );
        $hours = intdiv( $seconds, 3600 );
        $minutes = intdiv( $seconds % 3600, 60 );
        $text = $hours > 0 ? sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds % 60 ) : sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
        return $text . ( $milliseconds % 1000 ? '.' . rtrim( sprintf( '%03d', $milliseconds % 1000 ), '0' ) : '' );
    }

    public static function duration_ms( $text ) {
        if ( ! is_string( $text ) ) return new \WP_Error( 'swimlog_duration_input', __( 'Enter duration as M:SS or H:MM:SS, for example 45:00 or 1:15:00.', 'chriss-swim-training-progress-evaluation' ) );
        $text = trim( $text );
        if ( '' === $text ) return null;
        if ( ! preg_match( '/^(?:(\d{1,6}):)?(\d{1,6}):([0-5]\d)(?:\.(\d{1,3}))?$/', $text, $match ) || ( '' !== $match[1] && (int) $match[2] > 59 ) ) {
            return new \WP_Error( 'swimlog_duration_input', __( 'Enter duration as M:SS or H:MM:SS, for example 45:00 or 1:15:00. Seconds must be 00–59.', 'chriss-swim-training-progress-evaluation' ) );
        }
        $milliseconds = ( (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) $match[3] ) * 1000 + (int) str_pad( $match[4] ?? '', 3, '0' );
        return $milliseconds > 0 ? $milliseconds : new \WP_Error( 'swimlog_duration_input', __( 'Duration must be greater than zero, or left blank.', 'chriss-swim-training-progress-evaluation' ) );
    }

    public static function native_distance( $meters, $unit ) {
        if ( null === $meters || '' === $meters ) return '';
        return rtrim( rtrim( number_format( (float) $meters / ( 'yd' === $unit ? 0.9144 : 1 ), 9, '.', '' ), '0' ), '.' );
    }

	public static function display_measurement( $value, $unit ) {
		if ( null === $value || '' === $value ) return '—';
		$number = (float) $value;
		$display = abs( $number - round( $number ) ) < 0.0005
			? number_format_i18n( $number, 0 )
			: rtrim( rtrim( number_format_i18n( $number, 3 ), '0' ), '.' );
		return $display . ( $unit ? ' ' . $unit : '' );
	}
}

