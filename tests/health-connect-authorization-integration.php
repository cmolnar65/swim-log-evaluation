<?php
// Synthetic authorization-code integration test in disposable wp-env only; never shipped.
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Health_Connect_Authorization;
use SwimLogEvaluation\Health_Connect_Tokens;

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-connect-tokens.php';
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-connect-authorization.php';

global $wpdb;
$checks = 0;
$verify = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) throw new RuntimeException( $message );
};
$previous_user = get_current_user_id();
$previous_https = isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : null;
$previous_db_version = get_option( 'swimlog_db_version', false );
$owner = wp_create_user( 'swimlog_hc_auth_' . strtolower( wp_generate_password( 8, false ) ), wp_generate_password( 32 ) );
if ( is_wp_error( $owner ) ) throw new RuntimeException( 'Could not create synthetic authorization owner.' );
$owner = (int) $owner;
try {
	( new WP_User( $owner ) )->add_cap( 'swimlog_manage_own_workouts' );
	$_SERVER['HTTPS'] = 'on';
	$verify( Database::install_schema(), 'Database schema install/verification failed.' );
	update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION );
	wp_set_current_user( $owner );
	$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
	$challenge = Health_Connect_Authorization::challenge_for_verifier( $verifier );
	$verify( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM' === $challenge, 'PKCE S256 vector mismatch.' );
	$old_token = Health_Connect_Tokens::issue( $owner );
	$verify( is_string( $old_token ) && $owner === Health_Connect_Tokens::owner_for_token( $old_token ), 'Existing manual owner token is active before consent.' );
	$code = Health_Connect_Authorization::create_code_for_current_user( $challenge );
	$verify( is_string( $code ) && 1 === preg_match( '/^slhca_[A-Za-z0-9_-]{43}$/D', $code ), 'WordPress did not issue an authorization code.' );
	$code_hash = hash( 'sha256', $code );
	$stored_hash = Database::read( 'get_var', $wpdb->prepare( 'SELECT code_hash FROM %i WHERE code_hash = %s AND owner_id = %d', Database::table( 'health_connect_auth_codes' ), $code_hash, $owner ) );
	$verify( $code_hash === $stored_hash && $code !== $stored_hash, 'Only the authorization-code hash is stored.' );

	$base = array( 'grant_type' => 'authorization_code', 'client_id' => Health_Connect_Authorization::CLIENT_ID, 'redirect_uri' => Health_Connect_Authorization::REDIRECT_URI, 'code' => $code, 'code_verifier' => $verifier );
	$forged = $base;
	$forged['owner_id'] = $owner + 1;
	$verify( 401 === swimlog_hc_authorization_test_request_token( $forged )->get_status(), 'Caller-supplied owner ID must be rejected.' );
	$wrong_verifier = $base;
	$wrong_verifier['code_verifier'] = str_repeat( 'x', 43 );
	$verify( 401 === swimlog_hc_authorization_test_request_token( $wrong_verifier )->get_status(), 'Incorrect PKCE verifier must be rejected.' );
	$verify( $owner === Health_Connect_Tokens::owner_for_token( $old_token ), 'Failed exchanges leave the existing token active.' );
	$response = swimlog_hc_authorization_test_request_token( $base );
	$verify( 200 === $response->get_status(), 'Valid PKCE exchange failed.' );
	$data = $response->get_data();
	$verify( isset( $data['access_token'], $data['token_type'] ) && 'Bearer' === $data['token_type'] && ! isset( $data['owner_id'] ), 'Exchange returns only a bearer token, not owner identity.' );
	$verify( $owner === Health_Connect_Tokens::owner_for_token( $data['access_token'] ), 'Exchanged token is scoped to the WordPress session owner.' );
	$verify( 0 === Health_Connect_Tokens::owner_for_token( $old_token ), 'Successful authorization replaces the previous owner token.' );
	$verify( 401 === swimlog_hc_authorization_test_request_token( $base )->get_status(), 'A consumed authorization code cannot be replayed.' );

	$expired = Health_Connect_Authorization::create_code_for_current_user( $challenge );
	$expired_hash = hash( 'sha256', $expired );
	Database::write( 'update', Database::table( 'health_connect_auth_codes' ), array( 'expires_at_utc' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ), array( 'code_hash' => $expired_hash ) );
	$expired_payload = $base;
	$expired_payload['code'] = $expired;
	$verify( 401 === swimlog_hc_authorization_test_request_token( $expired_payload )->get_status(), 'Expired authorization code must be rejected.' );

	$_SERVER['HTTPS'] = 'off';
	$verify( 403 === swimlog_hc_authorization_test_request_token( $base )->get_status(), 'Token exchange requires HTTPS.' );
	WP_CLI::success( 'Health Connect browser consent and PKCE token exchange passed ' . $checks . ' checks.' );
} finally {
	$_SERVER['HTTPS'] = null === $previous_https ? '' : $previous_https;
	wp_set_current_user( $owner );
	Health_Connect_Tokens::revoke( $owner );
	Database::write( 'delete', Database::table( 'health_connect_auth_codes' ), array( 'owner_id' => $owner ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $owner );
	wp_set_current_user( $previous_user );
	if ( false === $previous_db_version ) delete_option( 'swimlog_db_version' );
	else update_option( 'swimlog_db_version', $previous_db_version );
}

function swimlog_hc_authorization_test_request_token( $payload ) {
	$request = new WP_REST_Request( 'POST', '/swimlog/v1/health-connect/token' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $payload ) );
	return rest_do_request( $request );
}
