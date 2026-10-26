<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared presentation helpers for swim training administration. */
final class Admin_UI {
	public static function selected_user_id() {
		$uid = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin swimmer selector; no state is changed.
		if ( current_user_can( 'swimlog_manage_all_users' ) && ! empty( $_GET['swimmer_id'] ) ) {
			$requested = absint( wp_unslash( $_GET['swimmer_id'] ) );
			if ( $requested && get_userdata( $requested ) ) $uid = $requested;
		}
		return $uid;
	}

	public static function swimmer_selector( $user_id, $page ) {
		if ( ! current_user_can( 'swimlog_manage_all_users' ) ) return;
		$users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
		?><form method="get" class="swimlog-swimmer-selector"><input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>"><label for="swimlog-swimmer"><?php esc_html_e( 'Swimmer', 'chriss-swim-training-progress-evaluation' ); ?></label> <select id="swimlog-swimmer" name="swimmer_id"><?php foreach ( $users as $u ) : ?><option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>><?php echo esc_html( $u->display_name . ' (#' . $u->ID . ')' ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'View', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?></form><?php
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
