<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One high-entropy Health Connect app token per owner; only its keyed hash is stored. */
final class Health_Connect_Tokens {
	const META = 'swimlog_health_connect_token_hash';

	private static function digest( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function issue( $owner ) {
		if ( ! is_int( $owner ) || $owner <= 0 || ! is_user_logged_in() || get_current_user_id() !== $owner || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_hc_forbidden', __( 'You cannot manage this Health Connect connection.', 'chriss-swim-training-progress-evaluation' ) );
		try {
			$token = 'slhc_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'swimlog_hc_token', __( 'A Health Connect token could not be generated.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( false === update_user_meta( $owner, self::META, self::digest( $token ) ) ) return new \WP_Error( 'swimlog_hc_token', __( 'A Health Connect token could not be saved.', 'chriss-swim-training-progress-evaluation' ) );
		return $token;
	}

	public static function revoke( $owner ) {
		if ( ! is_int( $owner ) || $owner <= 0 || ! is_user_logged_in() || get_current_user_id() !== $owner || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return false;
		delete_user_meta( $owner, self::META );
		return true;
	}

	/** Resolve a bearer token to its owner without accepting an owner ID from the caller. */
	public static function owner_for_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^slhc_[A-Za-z0-9_-]{40,64}$/D', $token ) ) return 0;
		$hash = self::digest( $token );
		$ids = get_users( array( 'fields' => 'ID', 'number' => 2, 'meta_key' => self::META, 'meta_value' => $hash ) );
		if ( ! is_array( $ids ) || 1 !== count( $ids ) ) return 0;
		$owner = (int) $ids[0];
		return user_can( $owner, 'swimlog_manage_own_workouts' ) ? $owner : 0;
	}
}
