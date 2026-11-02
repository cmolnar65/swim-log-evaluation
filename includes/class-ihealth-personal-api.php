<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-oauth.php';

/** Server-side, read-only transport for confirmed iHealth Personal API resources. */
final class IHealth_Personal_API {
    const BASE_URL = 'https://api.ihealthlabs.com:8443/openapiv2/user/';
    const RESPONSE_LIMIT = 1048576;

    private static function error( $code, $message, $status = 400 ) {
        return new \WP_Error( 'swimlog_ihealth_api_' . $code, $message, array( 'status' => $status ) );
    }

    /**
     * Fetch one JSON resource for the currently signed-in grant owner.
     * Resource names must come from the official iHealth Personal API contract. This method
     * does not follow provider pagination URLs, log URLs, or expose data through WordPress routes.
     */
    public static function get_resource( $owner, $api_name, $resource, $params = array() ) {
        $context = IHealth_OAuth::personal_api_context( $owner, $api_name );
        if ( is_wp_error( $context ) ) return $context;

        if ( ! is_string( $resource ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,63}\.json$/D', $resource ) ) {
            return self::error( 'resource', __( 'The iHealth API resource name is invalid.', 'chriss-swim-training-progress-evaluation' ) );
        }
        if ( ! is_array( $params ) || count( $params ) > 20 ) return self::error( 'parameters', __( 'The iHealth API request parameters are invalid.', 'chriss-swim-training-progress-evaluation' ) );
        foreach ( $params as $name => $value ) {
            if ( ! is_string( $name ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name )
                || in_array( strtolower( $name ), array( 'access_token', 'callback', 'client_id', 'client_secret', 'redirect_uri', 'response_type' ), true )
                || ! is_scalar( $value ) || strlen( (string) $value ) > 256 ) {
                return self::error( 'parameters', __( 'The iHealth API request parameters are invalid.', 'chriss-swim-training-progress-evaluation' ) );
            }
        }

        $url = self::BASE_URL . rawurlencode( $context['ihealth_user_id'] ) . '/' . $resource . '/';
        $query = $params;
        $query['access_token'] = $context['access_token'];
        $url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        $response = wp_remote_get( $url, array(
            'timeout' => 20,
            'redirection' => 0,
            'limit_response_size' => self::RESPONSE_LIMIT,
            'sslverify' => true,
            'headers' => array( 'Accept' => 'application/json' ),
        ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return self::error( 'request', __( 'iHealth did not return the requested data. Reauthorize the account if its grant has expired, then try again.', 'chriss-swim-training-progress-evaluation' ), 502 );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $body ) || strlen( $body ) > self::RESPONSE_LIMIT ) return self::error( 'response_size', __( 'The iHealth response exceeded the allowed size.', 'chriss-swim-training-progress-evaluation' ), 502 );
        $data = json_decode( $body, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) return self::error( 'response', __( 'iHealth returned an invalid JSON response.', 'chriss-swim-training-progress-evaluation' ), 502 );

        return $data;
    }
}
