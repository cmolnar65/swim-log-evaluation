<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One high-entropy Health Connect app token per owner; only its keyed hash is stored. */
final class Health_Connect_Tokens {
	private static function digest( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function issue( $owner ) {
		if ( ! is_int( $owner ) || $owner <= 0 || ! is_user_logged_in() || get_current_user_id() !== $owner || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_hc_forbidden', __( 'You cannot manage this Health Connect connection.', 'chriss-swim-training-progress-evaluation' ) );
		return self::issue_for_authorized_owner( $owner );
	}

	/** Issue after Health_Connect_Authorization has consumed a one-time PKCE code. */
	public static function issue_for_authorized_owner( $owner ) {
		if ( ! is_int( $owner ) || $owner <= 0 || ! user_can( $owner, 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_hc_forbidden', __( 'This swimmer cannot authorize a Health Connect connection.', 'chriss-swim-training-progress-evaluation' ) );
		try {
			$token = 'slhc_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'swimlog_hc_token', __( 'A Health Connect token could not be generated.', 'chriss-swim-training-progress-evaluation' ) );
		}
		global $wpdb;
		$saved = Database::write( 'replace', Database::table( 'health_connect_tokens' ), array( 'owner_id' => $owner, 'token_hash' => self::digest( $token ), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
		if ( false === $saved ) return new \WP_Error( 'swimlog_hc_token', __( 'A Health Connect token could not be saved.', 'chriss-swim-training-progress-evaluation' ) );
		return $token;
	}

	public static function revoke( $owner ) {
		if ( ! is_int( $owner ) || $owner <= 0 || ! is_user_logged_in() || get_current_user_id() !== $owner || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return false;
		return false !== Database::write( 'delete', Database::table( 'health_connect_tokens' ), array( 'owner_id' => $owner ) );
	}

	public static function is_active( $owner ) {
		global $wpdb;
		if ( ! is_int( $owner ) || $owner <= 0 ) return false;
		$row = Database::read( 'get_var', $wpdb->prepare( 'SELECT owner_id FROM %i WHERE owner_id = %d', Database::table( 'health_connect_tokens' ), $owner ) );
		return (int) $row === $owner;
	}

	/** Resolve a bearer token to its owner without accepting an owner ID from the caller. */
	public static function owner_for_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^slhc_[A-Za-z0-9_-]{40,64}$/D', $token ) ) return 0;
		global $wpdb;
		$row = Database::read( 'get_row', $wpdb->prepare( 'SELECT owner_id FROM %i WHERE token_hash = %s', Database::table( 'health_connect_tokens' ), self::digest( $token ) ) );
		if ( ! $row || ! empty( $wpdb->last_error ) ) return 0;
		$owner = (int) $row->owner_id;
		return user_can( $owner, 'swimlog_manage_own_workouts' ) ? $owner : 0;
	}
}
