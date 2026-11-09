<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Browser consent and one-time PKCE authorization-code exchange for the Android client. */
final class Health_Connect_Authorization {
	const CLIENT_ID = 'swimlog_android';
	const REDIRECT_URI = 'com.cmolnar65.swimlog:/oauth2redirect';
	const CODE_TTL = 300;
	const AUTHORIZE_PAGE = 'swimlog-health-connect-authorize';

	/** Return true only for the one native client and its fixed callback URL. */
	public static function valid_authorization_parameters( $parameters ) {
		if ( ! is_array( $parameters ) ) return false;
		return isset( $parameters['response_type'], $parameters['client_id'], $parameters['redirect_uri'], $parameters['code_challenge'], $parameters['code_challenge_method'], $parameters['state'] )
			&& is_string( $parameters['response_type'] ) && 'code' === $parameters['response_type']
			&& is_string( $parameters['client_id'] ) && self::CLIENT_ID === $parameters['client_id']
			&& is_string( $parameters['redirect_uri'] ) && self::REDIRECT_URI === $parameters['redirect_uri']
			&& is_string( $parameters['code_challenge'] ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $parameters['code_challenge'] )
			&& is_string( $parameters['code_challenge_method'] ) && 'S256' === $parameters['code_challenge_method']
			&& is_string( $parameters['state'] ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43,128}$/D', $parameters['state'] );
	}

	/** PKCE S256 transformation. The verifier is never stored or returned. */
	public static function challenge_for_verifier( $verifier ) {
		if ( ! is_string( $verifier ) || 1 !== preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $verifier ) ) return new \WP_Error( 'swimlog_hc_authorization', __( 'The Health Connect authorization could not be completed.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 401 ) );
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Render the logged-in WordPress consent screen. The owner is always the
	 * authenticated WordPress user; the browser request cannot choose an owner ID.
	 */
	public static function admin_page() {
		if ( ! is_ssl() ) wp_die( esc_html__( 'Health Connect authorization requires HTTPS.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 403 ) );
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You cannot authorize a Health Connect connection for this account.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 403 ) );

		nocache_headers();
		if ( ! headers_sent() ) header( 'Referrer-Policy: no-referrer' );
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $request_method, array( 'GET', 'POST' ), true ) ) wp_die( esc_html__( 'This Health Connect authorization request method is not allowed.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 405 ) );
		$input = 'POST' === $request_method ? wp_unslash( $_POST ) : wp_unslash( $_GET );
		if ( ! self::valid_authorization_parameters( $input ) ) wp_die( esc_html__( 'This Health Connect authorization request is invalid or expired. Return to the Android app and try again.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 400 ) );
		$error = '';
		if ( 'POST' === $request_method ) {
			check_admin_referer( 'swimlog_hc_authorize' );
			$action = isset( $input['swimlog_hc_authorization_action'] ) && is_string( $input['swimlog_hc_authorization_action'] ) ? sanitize_key( $input['swimlog_hc_authorization_action'] ) : '';
			if ( 'cancel' === $action ) {
				self::redirect_to_app( array( 'error' => 'access_denied', 'state' => $input['state'] ) );
			}
			if ( 'authorize' !== $action ) wp_die( esc_html__( 'This Health Connect authorization request is invalid. Return to the Android app and try again.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 400 ) );
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-connect-tokens.php';
			$code = self::create_code_for_current_user( $input['code_challenge'] );
			if ( is_wp_error( $code ) ) {
				$error = $code->get_error_message();
			} else {
				self::redirect_to_app( array( 'code' => $code, 'state' => $input['state'] ) );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Swim Log Android', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
			<p><?php esc_html_e( 'You are signed in to the WordPress account that will receive these records. Allowing this connection creates a private app token for this account.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php esc_html_e( 'The app can upload the Health Connect workout summaries and measurements selected in Android, including swimming, running, walking, heart-rate samples matched to workouts, weight, body fat, lean body mass, and blood pressure. Imported records remain private to your account. This connection does not grant the app access to your WordPress password, other accounts, or the WordPress administration interface.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php esc_html_e( 'There is one active Health Connect app token per Swim Log account. Authorizing this app replaces any previous Health Connect app token for this account. You can revoke the connection in Swimmer Configuration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::AUTHORIZE_PAGE ) ); ?>">
				<?php wp_nonce_field( 'swimlog_hc_authorize' ); ?>
				<input type="hidden" name="response_type" value="code">
				<input type="hidden" name="client_id" value="<?php echo esc_attr( $input['client_id'] ); ?>">
				<input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $input['redirect_uri'] ); ?>">
				<input type="hidden" name="code_challenge" value="<?php echo esc_attr( $input['code_challenge'] ); ?>">
				<input type="hidden" name="code_challenge_method" value="S256">
				<input type="hidden" name="state" value="<?php echo esc_attr( $input['state'] ); ?>">
				<p><button class="button button-primary" type="submit" name="swimlog_hc_authorization_action" value="authorize"><?php esc_html_e( 'Authorize Swim Log Android', 'chriss-swim-training-progress-evaluation' ); ?></button>
				<button class="button" type="submit" name="swimlog_hc_authorization_action" value="cancel"><?php esc_html_e( 'Cancel', 'chriss-swim-training-progress-evaluation' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/** Create one short-lived, hashed code for the currently authenticated owner. */
	public static function create_code_for_current_user( $challenge ) {
		$owner = (int) get_current_user_id();
		if ( $owner <= 0 || ! is_user_logged_in() || ! current_user_can( 'swimlog_manage_own_workouts' ) || ! is_string( $challenge ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $challenge ) ) return new \WP_Error( 'swimlog_hc_authorization', __( 'The Health Connect authorization could not be completed.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 401 ) );
		try {
			$code = 'slhca_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'swimlog_hc_authorization', __( 'A Health Connect authorization code could not be created.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 500 ) );
		}
		global $wpdb;
		$table = Database::table( 'health_connect_auth_codes' );
		$now = gmdate( 'Y-m-d H:i:s' );
		$purge = $wpdb->prepare( 'DELETE FROM %i WHERE expires_at_utc <= %s OR consumed_at_utc IS NOT NULL', $table, $now );
		Database::write( 'query', $purge );
		Database::write( 'delete', $table, array( 'owner_id' => $owner ) );
		$inserted = Database::write( 'insert', $table, array(
			'code_hash' => hash( 'sha256', $code ),
			'owner_id' => $owner,
			'client_id' => self::CLIENT_ID,
			'redirect_uri' => self::REDIRECT_URI,
			'code_challenge' => $challenge,
			'expires_at_utc' => gmdate( 'Y-m-d H:i:s', time() + self::CODE_TTL ),
			'consumed_at_utc' => null,
			'created_at_utc' => $now,
		) );
		return false === $inserted ? new \WP_Error( 'swimlog_hc_authorization', __( 'A Health Connect authorization code could not be saved. Try again.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 500 ) ) : $code;
	}

	/** Exchange one code after an atomic consume and S256 verifier check. */
	public static function exchange( $payload ) {
		$fields = array( 'grant_type', 'client_id', 'redirect_uri', 'code', 'code_verifier' );
		if ( ! is_array( $payload ) || array_diff( array_keys( $payload ), $fields ) || array_diff( $fields, array_keys( $payload ) ) ) return self::invalid_code();
		foreach ( $fields as $field ) if ( ! is_string( $payload[ $field ] ) ) return self::invalid_code();
		if ( 'authorization_code' !== $payload['grant_type'] || self::CLIENT_ID !== $payload['client_id'] || self::REDIRECT_URI !== $payload['redirect_uri'] || 1 !== preg_match( '/^slhca_[A-Za-z0-9_-]{43}$/D', $payload['code'] ) ) return self::invalid_code();
		$challenge = self::challenge_for_verifier( $payload['code_verifier'] );
		if ( is_wp_error( $challenge ) ) return self::invalid_code();

		global $wpdb;
		$table = Database::table( 'health_connect_auth_codes' );
		$code_hash = hash( 'sha256', $payload['code'] );
		$now = gmdate( 'Y-m-d H:i:s' );
		$row = Database::read( 'get_row', $wpdb->prepare( 'SELECT owner_id FROM %i WHERE code_hash = %s AND client_id = %s AND redirect_uri = %s AND code_challenge = %s AND expires_at_utc > %s AND consumed_at_utc IS NULL', $table, $code_hash, self::CLIENT_ID, self::REDIRECT_URI, $challenge, $now ) );
		if ( ! $row ) return self::invalid_code();
		$consumed = Database::write( 'query', $wpdb->prepare( 'UPDATE %i SET consumed_at_utc = %s WHERE code_hash = %s AND owner_id = %d AND client_id = %s AND redirect_uri = %s AND code_challenge = %s AND expires_at_utc > %s AND consumed_at_utc IS NULL', $table, $now, $code_hash, (int) $row->owner_id, self::CLIENT_ID, self::REDIRECT_URI, $challenge, $now ) );
		if ( 1 !== (int) $consumed ) return self::invalid_code();

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-connect-tokens.php';
		$token = Health_Connect_Tokens::issue_for_authorized_owner( (int) $row->owner_id );
		if ( is_wp_error( $token ) ) return new \WP_Error( 'swimlog_hc_authorization', __( 'This Swim Log account can no longer authorize Health Connect.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 401 ) );
		Database::write( 'delete', $table, array( 'code_hash' => $code_hash ) );
		return array( 'access_token' => $token, 'token_type' => 'Bearer' );
	}

	private static function invalid_code() {
		return new \WP_Error( 'swimlog_hc_authorization', __( 'The Health Connect authorization code is invalid, expired, or already used.', 'chriss-swim-training-progress-evaluation' ), array( 'status' => 401 ) );
	}

	private static function redirect_to_app( $parameters ) {
		$query = http_build_query( $parameters, '', '&', PHP_QUERY_RFC3986 );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect uses only the fixed native-app URI constant and the validated, URL-encoded one-time code/state or cancellation state.
		wp_redirect( self::REDIRECT_URI . '?' . $query, 302, 'Swim Log Health Connect' );
		exit;
	}
}
