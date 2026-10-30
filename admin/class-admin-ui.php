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

    public static function context_pages() {
        return array( 'swimlog-dashboard', 'swimlog-calendar', 'swimlog-workouts', 'swimlog-manual-workout', 'swimlog-personal-bests', 'swimlog-training-volume', 'swimlog-training-trends', 'swimlog-pool-analytics' );
    }

    public static function context_args( $user_id = null ) {
        if ( ! current_user_can( 'swimlog_manage_all_users' ) ) return array();
        $user_id = null === $user_id ? self::selected_user_id() : absint( $user_id );
        if ( ! $user_id || ! get_userdata( $user_id ) ) return array();
        return array( 'swimmer_id' => $user_id, '_swimlog_swimmer_nonce' => wp_create_nonce( 'swimlog_swimmer_selector' ) );
    }

    public static function context_fields( $user_id ) {
        foreach ( self::context_args( $user_id ) as $name => $value ) {
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
        }
    }

    /** Signed navigation only for screens that already support administrator swimmer selection. */
    public static function url( $args, $user_id = null ) {
        $page = $args['page'] ?? '';
        unset( $args['swimmer_id'], $args['_swimlog_swimmer_nonce'] );
        if ( in_array( $page, self::context_pages(), true ) ) $args = array_merge( $args, self::context_args( $user_id ) );
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

	public static function swimmer_selector( $user_id, $page ) {
		if ( ! current_user_can( 'swimlog_manage_all_users' ) ) return;
        $viewed = get_userdata( $user_id );
        $context = self::context_args( $user_id );
        if ( $viewed && $context ) {
            ?><div class="swimlog-swimmer-context" data-swimlog-context-owner="<?php echo esc_attr( $user_id ); ?>" data-swimlog-context-nonce="<?php echo esc_attr( $context['_swimlog_swimmer_nonce'] ); ?>" data-swimlog-context-pages="<?php echo esc_attr( wp_json_encode( self::context_pages() ) ); ?>">
            <p><strong><?php esc_html_e( 'Viewing swimmer:', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php echo esc_html( $viewed->display_name . ' (#' . $user_id . ')' ); ?>
            <?php if ( (int) $user_id !== (int) get_current_user_id() ) : ?><a href="<?php echo esc_url( self::url( array( 'page' => $page ), get_current_user_id() ) ); ?>"><?php esc_html_e( 'Return to my records', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?></p>
            <nav aria-label="<?php esc_attr_e( 'Selected swimmer pages', 'chriss-swim-training-progress-evaluation' ); ?>">
            <?php foreach ( array( 'swimlog-dashboard' => __( 'Dashboard', 'chriss-swim-training-progress-evaluation' ), 'swimlog-calendar' => __( 'Calendar', 'chriss-swim-training-progress-evaluation' ), 'swimlog-workouts' => __( 'Workouts', 'chriss-swim-training-progress-evaluation' ), 'swimlog-personal-bests' => __( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ), 'swimlog-training-volume' => __( 'Training Volume', 'chriss-swim-training-progress-evaluation' ), 'swimlog-training-trends' => __( 'Training Trends', 'chriss-swim-training-progress-evaluation' ), 'swimlog-pool-analytics' => __( 'Pool Analytics', 'chriss-swim-training-progress-evaluation' ) ) as $slug => $label ) : ?><a href="<?php echo esc_url( self::url( array( 'page' => $slug ), $user_id ) ); ?>" <?php if ( $slug === $page ) echo 'aria-current="page"'; ?>><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
            </nav></div><?php
        }
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

    public static function prescription_text( $title, $document ) {
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-structured-workout-export.php';
        $text = Structured_Workout_Export::text( $title, $document );
        if ( is_wp_error( $text ) ) return $text->get_error_message();
        $labels = array( 'FR' => __( 'Freestyle', 'chriss-swim-training-progress-evaluation' ), 'BR' => __( 'Breaststroke', 'chriss-swim-training-progress-evaluation' ), 'BK' => __( 'Backstroke', 'chriss-swim-training-progress-evaluation' ), 'FL' => __( 'Butterfly', 'chriss-swim-training-progress-evaluation' ), 'MX' => __( 'Mixed', 'chriss-swim-training-progress-evaluation' ) );
        return preg_replace_callback( '/^(\s*\d+(?:\.\d+)? (?:m|yd) |)(FR|BR|BK|FL|MX)(?= |:|$)/m', static function ( $match ) use ( $labels ) { return $match[1] . $labels[$match[2]]; }, $text );
    }

	public static function display_measurement( $value, $unit ) {
		if ( null === $value || '' === $value ) return '—';
		$number = (float) $value;
		$display = abs( $number - round( $number ) ) < 0.0005
			? number_format_i18n( $number, 0 )
			: rtrim( rtrim( number_format_i18n( $number, 3 ), '0' ), '.' );
		return $display . ( $unit ? ' ' . $unit : '' );
	}
    /** Read-only UI intent; never authorizes an operation or a record lookup. */
    public static function requested( $key ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation intent; all action handlers retain their own authorization and nonce checks.
        return isset( $_GET[$key] ) && is_scalar( $_GET[$key] ) && '' !== sanitize_text_field( wp_unslash( $_GET[$key] ) );
    }

    public static function page_header( $title, $purpose, $action, $url ) {
        ?><header class="swimlog-page-header"><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($purpose); ?></p><a class="button button-primary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($action); ?></a></header><?php
    }

    /** Dates are calendar dates; local recorded times are never reinterpreted as UTC. */
    public static function date_text( $value ) {
        return self::format_date_value( $value, false, false );
    }
    public static function datetime_text( $value, $utc = false ) {
        return self::format_date_value( $value, $utc, true );
    }
    private static function format_date_value( $value, $utc, $time ) {
        if ( ! is_string($value) ) return '—';
        $pattern = $time ? 'Y-m-d H:i:s' : 'Y-m-d';
        $zone = $utc ? new \DateTimeZone('UTC') : wp_timezone();
        $date = \DateTimeImmutable::createFromFormat('!' . $pattern, $value, $zone);
        if ( ! $date || $date->format($pattern) !== $value ) return '—';
        $format = get_option('date_format','F j, Y') . ( $time ? ' ' . get_option('time_format','g:i a') : '' );
        return wp_date($format, $date->getTimestamp(), wp_timezone()) . ( $time ? ' (' . wp_timezone()->getName() . ( $utc ? ', UTC' . wp_date('P',$date->getTimestamp(),wp_timezone()) : '' ) . ')' : '' );
    }
    public static function timestamp_text( $value ) {
        if ( ! is_numeric($value) || (int)$value <= 0 ) return '—';
        return self::datetime_text(gmdate('Y-m-d H:i:s',(int)$value),true);
    }

}


