<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Health_Connect_Authorization;
use SwimLogEvaluation\Health_Connect_Tokens;

require_once dirname( __DIR__ ) . '/includes/class-health-connect-tokens.php';
require_once dirname( __DIR__ ) . '/includes/class-health-connect-authorization.php';

/** Narrow synthetic database for authorization-code lifetime and replay tests. */
final class HealthConnectAuthorizationTestDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $codes = array();
	public $tokens = array();

	public function prepare( $sql, ...$args ) { return json_encode( array( $sql, $args ) ); }
	public function get_row( $prepared ) {
		list( $sql, $args ) = json_decode( $prepared, true );
		if ( false !== strpos( $sql, 'health_connect_auth_codes' ) ) {
			$hash = $args[1];
			$code = isset( $this->codes[ $hash ] ) ? $this->codes[ $hash ] : null;
			if ( ! $code || $code['client_id'] !== $args[2] || $code['redirect_uri'] !== $args[3] || $code['code_challenge'] !== $args[4] || $code['expires_at_utc'] <= $args[5] || null !== $code['consumed_at_utc'] ) return null;
			return (object) array( 'owner_id' => $code['owner_id'] );
		}
		if ( false !== strpos( $sql, 'token_hash = %s' ) ) {
			foreach ( $this->tokens as $owner => $token ) if ( hash_equals( $token['token_hash'], $args[1] ) ) return (object) array( 'owner_id' => $owner );
		}
		return null;
	}
	public function insert( $table, $data ) { $this->codes[ $data['code_hash'] ] = $data; return 1; }
	public function replace( $table, $data ) { $this->tokens[ (int) $data['owner_id'] ] = $data; return 1; }
	public function delete( $table, $where ) {
		if ( false !== strpos( $table, 'health_connect_auth_codes' ) ) {
			$count = 0;
			foreach ( $this->codes as $hash => $code ) {
				$matches = true;
				foreach ( $where as $key => $value ) if ( (string) $code[ $key ] !== (string) $value ) $matches = false;
				if ( $matches ) { unset( $this->codes[ $hash ] ); ++$count; }
			}
			return $count;
		}
		$owner = (int) $where['owner_id'];
		if ( ! isset( $this->tokens[ $owner ] ) ) return 0;
		unset( $this->tokens[ $owner ] );
		return 1;
	}
	public function query( $prepared ) {
		list( $sql, $args ) = json_decode( $prepared, true );
		if ( 0 === strpos( $sql, 'UPDATE' ) ) {
			$hash = $args[2];
			if ( ! isset( $this->codes[ $hash ] ) ) return 0;
			$code =& $this->codes[ $hash ];
			if ( (int) $code['owner_id'] !== (int) $args[3] || $code['client_id'] !== $args[4] || $code['redirect_uri'] !== $args[5] || $code['code_challenge'] !== $args[6] || $code['expires_at_utc'] <= $args[7] || null !== $code['consumed_at_utc'] ) return 0;
			$code['consumed_at_utc'] = $args[1];
			return 1;
		}
		return 0;
	}
}

final class HealthConnectAuthorizationTest extends TestCase {
	private $previous;

	protected function setUp(): void {
		global $wpdb, $swimlog_test_current_user_id, $swimlog_test_capabilities, $swimlog_test_logged_in;
		$this->previous = array(
			'wpdb' => isset( $wpdb ) ? $wpdb : null,
			'user' => isset( $swimlog_test_current_user_id ) ? $swimlog_test_current_user_id : null,
			'caps' => isset( $swimlog_test_capabilities ) ? $swimlog_test_capabilities : null,
			'logged_in' => isset( $swimlog_test_logged_in ) ? $swimlog_test_logged_in : null,
		);
		$wpdb = new HealthConnectAuthorizationTestDB();
		$swimlog_test_current_user_id = 42;
		$swimlog_test_capabilities = array( 'swimlog_manage_own_workouts' => true );
		$swimlog_test_logged_in = true;
	}

	protected function tearDown(): void {
		global $wpdb, $swimlog_test_current_user_id, $swimlog_test_capabilities, $swimlog_test_logged_in;
		$wpdb = $this->previous['wpdb'];
		$swimlog_test_current_user_id = $this->previous['user'];
		$swimlog_test_capabilities = $this->previous['caps'];
		$swimlog_test_logged_in = $this->previous['logged_in'];
	}

	public function test_pkce_uses_rfc7636_s256_and_rejects_invalid_verifiers() {
		$this->assertSame( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Health_Connect_Authorization::challenge_for_verifier( 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk' ) );
		$this->assertInstanceOf( WP_Error::class, Health_Connect_Authorization::challenge_for_verifier( 'short' ) );
	}

	public function test_browser_consent_binds_fixed_client_callback_and_state() {
		$valid = array(
			'response_type' => 'code',
			'client_id' => Health_Connect_Authorization::CLIENT_ID,
			'redirect_uri' => Health_Connect_Authorization::REDIRECT_URI,
			'code_challenge' => str_repeat( 'A', 43 ),
			'code_challenge_method' => 'S256',
			'state' => str_repeat( 'B', 43 ),
		);
		$this->assertTrue( Health_Connect_Authorization::valid_authorization_parameters( $valid ) );
		$this->assertFalse( Health_Connect_Authorization::valid_authorization_parameters( array_merge( $valid, array( 'redirect_uri' => 'https://attacker.example/callback' ) ) ) );
		$this->assertFalse( Health_Connect_Authorization::valid_authorization_parameters( array_merge( $valid, array( 'code_challenge_method' => 'plain' ) ) ) );
		$this->assertFalse( Health_Connect_Authorization::valid_authorization_parameters( array_merge( $valid, array( 'state' => 'guessable' ) ) ) );
	}

	public function test_exchange_is_owner_scoped_pkce_checked_one_time_and_replaces_token_only_after_consent() {
		global $wpdb;
		$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$challenge = Health_Connect_Authorization::challenge_for_verifier( $verifier );
		$code = Health_Connect_Authorization::create_code_for_current_user( $challenge );
		$this->assertIsString( $code );
		$this->assertSame( 1, count( $wpdb->codes ) );
		$this->assertNotSame( $code, reset( $wpdb->codes )['code_hash'] );

		$payload = array( 'grant_type' => 'authorization_code', 'client_id' => Health_Connect_Authorization::CLIENT_ID, 'redirect_uri' => Health_Connect_Authorization::REDIRECT_URI, 'code' => $code, 'code_verifier' => str_repeat( 'x', 43 ) );
		$this->assertInstanceOf( WP_Error::class, Health_Connect_Authorization::exchange( $payload ) );
		$payload['code_verifier'] = $verifier;
		$result = Health_Connect_Authorization::exchange( $payload );
		$this->assertIsArray( $result );
		$this->assertSame( array( 'access_token', 'token_type' ), array_keys( $result ) );
		$this->assertSame( 'Bearer', $result['token_type'] );
		$this->assertSame( 1, count( $wpdb->tokens ) );
		$this->assertSame( 'slhc_', substr( $result['access_token'], 0, 5 ) );
		$this->assertSame( 42, Health_Connect_Tokens::owner_for_token( $result['access_token'] ) );
		$this->assertInstanceOf( WP_Error::class, Health_Connect_Authorization::exchange( $payload ), 'An authorization code cannot be replayed.' );

		$payload['owner_id'] = 99;
		$this->assertInstanceOf( WP_Error::class, Health_Connect_Authorization::exchange( $payload ), 'A client-supplied owner ID is rejected.' );
	}

	public function test_only_logged_in_swimmer_can_create_authorization_code() {
		global $swimlog_test_logged_in;
		$swimlog_test_logged_in = false;
		$this->assertInstanceOf( WP_Error::class, Health_Connect_Authorization::create_code_for_current_user( str_repeat( 'A', 43 ) ) );
	}
}
