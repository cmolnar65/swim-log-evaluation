<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Admin-configured iHealth OAuth credentials and private per-user grants. */
final class IHealth_OAuth {
    const APP_OPTION = 'swimlog_ihealth_application_v1';
    const CONNECTION_META = 'swimlog_ihealth_connection_v1';
    const PENDING_META = 'swimlog_ihealth_oauth_pending_v1';
    const AUTH_URL = 'https://api.ihealthlabs.com:8443/OpenApiV2/OAuthv2/userauthorization/';
    const CALLBACK_ACTION = 'swimlog_ihealth_oauth_callback';
    const STATE_TTL = 600;

    public static function api_names() {
        return array(
            'OpenApiWeight' => __( 'Weight and body composition', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiBP' => __( 'Blood pressure', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiBG' => __( 'Blood glucose', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiSpO2' => __( 'Blood oxygen', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiActivity' => __( 'Daily activity', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiSleep' => __( 'Sleep', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiSport' => __( 'Sport sessions', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiT' => __( 'Body temperature', 'chriss-swim-training-progress-evaluation' ),
            'OpenApiHR' => __( 'Heart rate', 'chriss-swim-training-progress-evaluation' ),
        );
    }

    private static function error( $code, $message, $status = 400 ) {
        return new \WP_Error( 'swimlog_ihealth_' . $code, $message, array( 'status' => $status ) );
    }

    private static function authorized_admin( $owner ) {
        return is_int( $owner ) && $owner > 0 && is_user_logged_in()
            && $owner === (int) get_current_user_id() && current_user_can( 'manage_options' );
    }

    private static function key() {
        return hash( 'sha256', wp_salt( 'auth' ) . '|swimlog-ihealth-v1', true );
    }

    private static function seal( $data, $purpose ) {
        $plain = wp_json_encode( $data );
        if ( ! is_string( $plain ) ) throw new \RuntimeException( 'iHealth protected storage encoding failed.' );
        $iv = random_bytes( 12 );
        $tag = '';
        $cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'swimlog-ihealth-v1|' . $purpose );
        if ( ! is_string( $cipher ) || '' === $cipher || 16 !== strlen( $tag ) ) throw new \RuntimeException( 'iHealth protected storage failed.' );
        return base64_encode( $iv . $tag . $cipher );
    }

    private static function open( $encoded, $purpose ) {
        if ( ! is_string( $encoded ) || '' === $encoded ) return null;
        $raw = base64_decode( $encoded, true );
        if ( ! is_string( $raw ) || strlen( $raw ) < 29 ) return null;
        $plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), 'swimlog-ihealth-v1|' . $purpose );
        if ( ! is_string( $plain ) ) return null;
        $data = json_decode( $plain, true );
        return is_array( $data ) ? $data : null;
    }

    private static function application() {
        $app = self::open( get_option( self::APP_OPTION, '' ), 'application' );
        if ( ! is_array( $app ) || ! is_string( $app['client_id'] ?? null ) || ! is_string( $app['client_secret'] ?? null ) || ! is_array( $app['api_names'] ?? null ) ) return null;
        $allowed = array_keys( self::api_names() );
        if ( ! $app['api_names'] || array_diff( $app['api_names'], $allowed ) ) return null;
        return $app;
    }

    public static function revision() {
        $app = self::application();
        if ( ! is_array( $app ) ) return hash_hmac( 'sha256', 'unconfigured', wp_salt( 'nonce' ) );
        $canonical = wp_json_encode( array(
            'client_id' => $app['client_id'],
            'secret_digest' => hash( 'sha256', $app['client_secret'] ),
            'api_names' => $app['api_names'],
        ) );
        return hash_hmac( 'sha256', (string) $canonical, wp_salt( 'nonce' ) );
    }

    public static function application_view() {
        $app = self::application();
        return array(
            'configured' => is_array( $app ),
            'client_id' => is_array( $app ) ? $app['client_id'] : '',
            'has_secret' => is_array( $app ) && '' !== $app['client_secret'],
            'has_sc' => is_array( $app ) && is_string( $app['sc'] ?? null ) && '' !== $app['sc'],
            'has_weight_sv' => is_array( $app ) && is_string( $app['weight_sv'] ?? null ) && '' !== $app['weight_sv'],
            'has_blood_pressure_sv' => is_array( $app ) && is_string( $app['blood_pressure_sv'] ?? null ) && '' !== $app['blood_pressure_sv'],
            'api_names' => is_array( $app ) ? array_values( array_intersect( array_keys( self::api_names() ), $app['api_names'] ) ) : array(),
            'revision' => self::revision(),
        );
    }

    /** Save global app credentials after the admin page has verified its nonce. */
    public static function save_application( $owner, $client_id, $client_secret, $api_names, $expected_revision, $security_codes = array() ) {
        if ( ! self::authorized_admin( $owner ) ) return self::error( 'forbidden', __( 'Only a signed-in WordPress administrator can manage iHealth app settings.', 'chriss-swim-training-progress-evaluation' ), 403 );
        if ( ! is_string( $expected_revision ) || ! hash_equals( self::revision(), $expected_revision ) ) return self::error( 'stale', __( 'The iHealth app settings changed. Reload the page before saving.', 'chriss-swim-training-progress-evaluation' ), 409 );
        $client_id = is_string( $client_id ) ? trim( $client_id ) : '';
        $client_secret = is_string( $client_secret ) ? $client_secret : '';
        $old = self::application();
        if ( '' === $client_secret && is_array( $old ) && hash_equals( $old['client_id'], $client_id ) ) $client_secret = $old['client_secret'];
        if ( ! preg_match( '/^[A-Za-z0-9._-]{1,128}$/D', $client_id ) || '' === $client_secret || strlen( $client_secret ) > 1024 || preg_match( '/[\x00-\x20\x7f]/', $client_secret ) ) {
            return self::error( 'credentials', __( 'Enter a valid iHealth Client ID and Client Secret.', 'chriss-swim-training-progress-evaluation' ) );
        }
        if ( ! is_array( $api_names ) || ! $api_names || count( $api_names ) > count( self::api_names() ) ) return self::error( 'categories', __( 'Select at least one supported iHealth data category.', 'chriss-swim-training-progress-evaluation' ) );
        foreach ( $api_names as $name ) if ( ! is_string( $name ) || ! array_key_exists( $name, self::api_names() ) ) return self::error( 'categories', __( 'Select only supported iHealth data categories.', 'chriss-swim-training-progress-evaluation' ) );
        $api_names = array_values( array_intersect( array_keys( self::api_names() ), array_unique( $api_names ) ) );
        if ( ! $api_names ) return self::error( 'categories', __( 'Select at least one supported iHealth data category.', 'chriss-swim-training-progress-evaluation' ) );
        if ( ! is_array( $security_codes ) ) return self::error( 'security_codes', __( 'The iHealth API security codes are invalid.', 'chriss-swim-training-progress-evaluation' ) );
        $sc = is_string( $security_codes['sc'] ?? null ) ? $security_codes['sc'] : '';
        $weight_sv = is_string( $security_codes['weight_sv'] ?? null ) ? $security_codes['weight_sv'] : '';
        $blood_pressure_sv = is_string( $security_codes['blood_pressure_sv'] ?? null ) ? $security_codes['blood_pressure_sv'] : '';
        $same_client = is_array( $old ) && hash_equals( $old['client_id'], $client_id );
        if ( '' === $sc && $same_client ) $sc = is_string( $old['sc'] ?? null ) ? $old['sc'] : '';
        if ( '' === $weight_sv && $same_client ) $weight_sv = is_string( $old['weight_sv'] ?? null ) ? $old['weight_sv'] : '';
        if ( '' === $blood_pressure_sv && $same_client ) $blood_pressure_sv = is_string( $old['blood_pressure_sv'] ?? null ) ? $old['blood_pressure_sv'] : '';
        foreach ( array( $sc, $weight_sv, $blood_pressure_sv ) as $code ) {
            if ( strlen( $code ) > 1024 || ( '' !== $code && preg_match( '/[\x00-\x20\x7f]/', $code ) ) ) return self::error( 'security_codes', __( 'Enter valid iHealth API security codes.', 'chriss-swim-training-progress-evaluation' ) );
        }
        try {
            $sealed = self::seal( array( 'client_id' => $client_id, 'client_secret' => $client_secret, 'api_names' => $api_names, 'sc' => $sc, 'weight_sv' => $weight_sv, 'blood_pressure_sv' => $blood_pressure_sv ), 'application' );
        } catch ( \Throwable $exception ) {
            return self::error( 'storage', __( 'The iHealth app credentials could not be protected for storage.', 'chriss-swim-training-progress-evaluation' ), 503 );
        }
        update_option( self::APP_OPTION, $sealed, false );
        $saved = self::application();
        if ( ! is_array( $saved ) || ! hash_equals( $client_id, $saved['client_id'] ) || ! hash_equals( $client_secret, $saved['client_secret'] ) || $api_names !== $saved['api_names'] || $sc !== ( $saved['sc'] ?? '' ) || $weight_sv !== ( $saved['weight_sv'] ?? '' ) || $blood_pressure_sv !== ( $saved['blood_pressure_sv'] ?? '' ) ) {
            return self::error( 'storage', __( 'The saved iHealth app settings could not be confirmed.', 'chriss-swim-training-progress-evaluation' ), 503 );
        }
        return true;
    }

    public static function callback_url() {
        return set_url_scheme( add_query_arg( 'action', self::CALLBACK_ACTION, admin_url( 'admin-post.php' ) ), 'https' );
    }

    /** Start an admin-only authorization-code flow; state is bound to this user. */
    public static function begin_authorization( $owner ) {
        if ( ! self::authorized_admin( $owner ) ) return self::error( 'forbidden', __( 'Only a signed-in WordPress administrator can verify iHealth app settings.', 'chriss-swim-training-progress-evaluation' ), 403 );
        $app = self::application();
        if ( ! is_array( $app ) ) return self::error( 'not_configured', __( 'Save the iHealth Client ID, Client Secret and at least one category first.', 'chriss-swim-training-progress-evaluation' ), 409 );
        $previous = get_user_meta( $owner, self::PENDING_META, true );
        if ( is_array( $previous ) && (int) ( $previous['expires'] ?? 0 ) >= time() ) return self::error( 'pending', __( 'An iHealth authorization is already pending. Finish it or wait ten minutes before starting another.', 'chriss-swim-training-progress-evaluation' ), 409 );
        if ( is_array( $previous ) ) delete_user_meta( $owner, self::PENDING_META );

        try {
            $state = bin2hex( random_bytes( 32 ) );
        } catch ( \Throwable $exception ) {
            return self::error( 'random', __( 'A secure iHealth authorization could not be started.', 'chriss-swim-training-progress-evaluation' ), 503 );
        }
        $redirect_uri = add_query_arg( 'state', $state, self::callback_url() );
        $pending = array(
            'owner' => (int) $owner,
            'state_hash' => hash( 'sha256', $state ),
            'expires' => time() + self::STATE_TTL,
            'redirect_uri' => $redirect_uri,
            'client_id' => $app['client_id'],
            'app_revision' => self::revision(),
            'api_names' => $app['api_names'],
        );
        if ( ! add_user_meta( $owner, self::PENDING_META, $pending, true ) ) return self::error( 'pending', __( 'The iHealth authorization could not be started. Reload and try again.', 'chriss-swim-training-progress-evaluation' ), 503 );
        $query = http_build_query( array(
            'client_id' => $app['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'APIName' => implode( ' ', $app['api_names'] ),
        ), '', '&', PHP_QUERY_RFC3986 );
        return self::AUTH_URL . '?' . $query;
    }

    private static function exchange_code( $owner, $app, $code, $redirect_uri ) {
        $url = self::AUTH_URL . '?' . http_build_query( array(
            'client_id' => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        ), '', '&', PHP_QUERY_RFC3986 );
        $response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 65536, 'sslverify' => true ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return self::error( 'exchange', __( 'iHealth could not verify the app credentials and authorization. Check the registered redirect and try again.', 'chriss-swim-training-progress-evaluation' ), 502 );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || ! is_string( $data['APIName'] ?? null ) ) return self::error( 'response', __( 'iHealth returned an incomplete authorization response.', 'chriss-swim-training-progress-evaluation' ), 502 );
        $granted_names = preg_split( '/\s+/', trim( $data['APIName'] ), -1, PREG_SPLIT_NO_EMPTY );
        $granted = array_values( array_unique( array_intersect( array_keys( self::api_names() ), $granted_names, $app['api_names'] ) ) );
        $requested = array_values( array_intersect( array_keys( self::api_names() ), $app['api_names'] ) );
        $expires = filter_var( $data['Expires'] ?? null, FILTER_VALIDATE_INT );
        if ( ! is_string( $data['AccessToken'] ?? null ) || '' === $data['AccessToken'] || strlen( $data['AccessToken'] ) > 4096
            || ! is_string( $data['RefreshToken'] ?? null ) || '' === $data['RefreshToken'] || strlen( $data['RefreshToken'] ) > 4096
            || ! is_scalar( $data['UserID'] ?? null ) || '' === (string) $data['UserID'] || strlen( (string) $data['UserID'] ) > 256
            || false === $expires || $expires < 1 || $granted !== $requested ) {
            return self::error( 'response', __( 'iHealth did not confirm all selected categories for this account.', 'chriss-swim-training-progress-evaluation' ), 502 );
        }
        return array(
            'owner' => (int) $owner,
            'client_id' => $app['client_id'],
            'app_revision' => self::revision(),
            'ihealth_user_id' => (string) $data['UserID'],
            'access_token' => $data['AccessToken'],
            'refresh_token' => $data['RefreshToken'],
            'expires_at' => time() + min( (int) $expires, 31536000 ),
            'api_names' => $granted,
            'connected_at' => time(),
        );
    }

    /** Complete a single-use OAuth callback; provider errors and payloads are never echoed. */
    public static function complete_authorization( $owner, $query ) {
        if ( ! self::authorized_admin( $owner ) ) return self::error( 'forbidden', __( 'Sign in again as a WordPress administrator to finish iHealth authorization.', 'chriss-swim-training-progress-evaluation' ), 403 );
        $state = is_string( $query['state'] ?? null ) ? $query['state'] : '';
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', $state ) ) return self::error( 'state', __( 'The iHealth authorization request is invalid or expired. Start again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 403 );
        $pending = get_user_meta( $owner, self::PENDING_META, true );
        if ( ! is_array( $pending ) || (int) ( $pending['owner'] ?? 0 ) !== (int) $owner
            || (int) ( $pending['expires'] ?? 0 ) < time()
            || ! is_string( $pending['state_hash'] ?? null )
            || ! hash_equals( $pending['state_hash'], hash( 'sha256', $state ) ) ) {
            return self::error( 'state', __( 'The iHealth authorization request is invalid or expired. Start again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 403 );
        }
        if ( ! delete_user_meta( $owner, self::PENDING_META, $pending ) ) return self::error( 'state', __( 'The iHealth authorization request was already used. Start again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 403 );
        $expected_redirect = add_query_arg( 'state', $state, self::callback_url() );
        if ( ! is_string( $pending['redirect_uri'] ?? null ) || ! hash_equals( $expected_redirect, $pending['redirect_uri'] ) ) return self::error( 'redirect', __( 'The iHealth authorization request is invalid. Start again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 403 );
        if ( ! hash_equals( self::revision(), (string) ( $pending['app_revision'] ?? '' ) ) ) return self::error( 'stale', __( 'The iHealth app settings changed during authorization. Start again.', 'chriss-swim-training-progress-evaluation' ), 409 );
        if ( isset( $query['error'] ) ) return self::error( 'declined', __( 'iHealth authorization did not complete. You can start again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 403 );
        $code = is_string( $query['code'] ?? null ) ? $query['code'] : '';
        if ( '' === $code || strlen( $code ) > 2048 || preg_match( '/[\x00-\x20\x7f]/', $code ) ) return self::error( 'code', __( 'iHealth did not return a valid authorization code.', 'chriss-swim-training-progress-evaluation' ), 400 );
        $app = self::application();
        if ( ! is_array( $app ) || ! hash_equals( $app['client_id'], (string) ( $pending['client_id'] ?? '' ) ) || $app['api_names'] !== ( $pending['api_names'] ?? null ) ) return self::error( 'stale', __( 'The saved iHealth app settings changed. Start again.', 'chriss-swim-training-progress-evaluation' ), 409 );
        $connection = self::exchange_code( $owner, $app, $code, $pending['redirect_uri'] );
        if ( is_wp_error( $connection ) ) return $connection;
        try {
            $sealed = self::seal( $connection, 'connection:' . (int) $owner );
        } catch ( \Throwable $exception ) {
            return self::error( 'storage', __( 'The iHealth authorization could not be protected for storage.', 'chriss-swim-training-progress-evaluation' ), 503 );
        }
        update_user_meta( $owner, self::CONNECTION_META, $sealed );
        if ( null === self::connection( $owner ) ) return self::error( 'storage', __( 'The iHealth authorization could not be confirmed after saving.', 'chriss-swim-training-progress-evaluation' ), 503 );
        return true;
    }

    /** Public projection for the admin screen; never returns token or provider user ID. */
    public static function connection( $owner ) {
        $connection = self::open( get_user_meta( (int) $owner, self::CONNECTION_META, true ), 'connection:' . (int) $owner );
        if ( ! is_array( $connection ) || (int) ( $connection['owner'] ?? 0 ) !== (int) $owner
            || ! is_string( $connection['client_id'] ?? null ) || ! is_string( $connection['app_revision'] ?? null )
            || ! is_string( $connection['access_token'] ?? null ) || ! is_string( $connection['refresh_token'] ?? null )
            || ! is_array( $connection['api_names'] ?? null ) || (int) ( $connection['expires_at'] ?? 0 ) <= time() ) return null;
        $app = self::application();
        if ( ! is_array( $app ) || ! hash_equals( $app['client_id'], $connection['client_id'] ) || ! hash_equals( self::revision(), $connection['app_revision'] ) ) return null;
        return array(
            'connected' => true,
            'expires_at' => (int) ( $connection['expires_at'] ?? 0 ),
            'api_names' => array_values( array_intersect( array_keys( self::api_names() ), $connection['api_names'] ) ),
        );
    }

    /**
     * Private input for server-side Personal API requests; never expose this from a route or view.
     * The API client enforces the same administrator and scope checks before making a request.
     */
    public static function personal_api_context( $owner, $api_name ) {
        if ( ! self::authorized_admin( $owner ) ) return self::error( 'forbidden', __( 'Only the connected WordPress administrator can request iHealth data.', 'chriss-swim-training-progress-evaluation' ), 403 );
        if ( ! is_string( $api_name ) || ! array_key_exists( $api_name, self::api_names() ) ) return self::error( 'category', __( 'The requested iHealth category is not supported.', 'chriss-swim-training-progress-evaluation' ) );

        $connection = self::open( get_user_meta( (int) $owner, self::CONNECTION_META, true ), 'connection:' . (int) $owner );
        if ( ! is_array( $connection ) || (int) ( $connection['owner'] ?? 0 ) !== (int) $owner
            || ! is_string( $connection['client_id'] ?? null ) || ! is_string( $connection['app_revision'] ?? null )
            || ! is_string( $connection['ihealth_user_id'] ?? null ) || '' === $connection['ihealth_user_id']
            || ! is_string( $connection['access_token'] ?? null ) || '' === $connection['access_token']
            || ! is_array( $connection['api_names'] ?? null ) ) {
            return self::error( 'reauthorize', __( 'The iHealth account is not connected. Authorize it again from iHealth App Setup.', 'chriss-swim-training-progress-evaluation' ), 409 );
        }
        $app = self::application();
        if ( ! is_array( $app ) || ! hash_equals( $app['client_id'], $connection['client_id'] )
            || ! hash_equals( self::revision(), $connection['app_revision'] )
            || ! in_array( $api_name, $app['api_names'], true ) || ! in_array( $api_name, $connection['api_names'], true ) ) {
            return self::error( 'scope', __( 'The iHealth account is not authorized for this category. Review app settings and authorize it again.', 'chriss-swim-training-progress-evaluation' ), 403 );
        }
        $api_sv = 'OpenApiWeight' === $api_name ? ( $app['weight_sv'] ?? '' ) : ( 'OpenApiBP' === $api_name ? ( $app['blood_pressure_sv'] ?? '' ) : '' );
        $api_label = 'OpenApiWeight' === $api_name ? __( 'Weight', 'chriss-swim-training-progress-evaluation' ) : ( 'OpenApiBP' === $api_name ? __( 'Blood pressure', 'chriss-swim-training-progress-evaluation' ) : __( 'requested', 'chriss-swim-training-progress-evaluation' ) );
        if ( ! is_string( $app['sc'] ?? null ) || '' === $app['sc'] || ! is_string( $api_sv ) || '' === $api_sv ) {
            /* translators: %s: iHealth data category, either Weight or Blood pressure. */
            return self::error( 'api_codes', sprintf( __( 'Enter the iHealth SC and %s API SV codes in iHealth App Setup before importing data.', 'chriss-swim-training-progress-evaluation' ), $api_label ), 409 );
        }
        // Leave a small safety margin so requests do not begin with a nearly expired token.
        if ( (int) ( $connection['expires_at'] ?? 0 ) <= time() + 60 ) return self::error( 'reauthorize', __( 'The iHealth access grant has expired or is about to expire. Authorize the account again.', 'chriss-swim-training-progress-evaluation' ), 409 );

        return array(
            'ihealth_user_id' => $connection['ihealth_user_id'],
            'access_token' => $connection['access_token'],
            'client_id' => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'sc' => $app['sc'],
            'sv' => $api_sv,
        );
    }
}
