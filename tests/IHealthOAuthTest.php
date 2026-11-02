<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\IHealth_OAuth as IHealth;

require_once dirname( __DIR__ ) . '/includes/class-ihealth-oauth.php';

$GLOBALS['swimlog_test_ihealth_meta'] = array();
$GLOBALS['swimlog_test_ihealth_http'] = array();
$GLOBALS['swimlog_test_ihealth_url'] = '';

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) { return 'synthetic-ihealth-test-salt-' . $scheme; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value, $autoload = null ) { global $swimlog_test_options; $swimlog_test_options[ $name ] = $value; return true; }
}
if ( ! function_exists( 'add_user_meta' ) ) {
    function add_user_meta( $user_id, $key, $value, $unique = false ) {
        global $swimlog_test_user_meta;
        if ( $unique && isset( $swimlog_test_user_meta[ $user_id ][ $key ] ) ) return false;
        $swimlog_test_user_meta[ $user_id ][ $key ] = $value;
        return 1;
    }
}
if ( ! function_exists( 'delete_user_meta' ) ) {
    function delete_user_meta( $user_id, $key, $value = '' ) {
        global $swimlog_test_user_meta;
        if ( ! isset( $swimlog_test_user_meta[ $user_id ][ $key ] ) ) return false;
        if ( '' !== $value && $swimlog_test_user_meta[ $user_id ][ $key ] !== $value ) return false;
        unset( $swimlog_test_user_meta[ $user_id ][ $key ] );
        return true;
    }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'set_url_scheme' ) ) {
    function set_url_scheme( $url, $scheme = null ) {
        $parts = parse_url( $url );
        if ( ! is_array( $parts ) ) return $url;
        $host = ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
        return $scheme . '://' . $host . ( $parts['path'] ?? '' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
    }
}
if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $key, $value, $url ) {
        $parts = parse_url( $url );
        $query = array();
        parse_str( $parts['query'] ?? '', $query );
        $query[ $key ] = $value;
        return ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        global $swimlog_test_ihealth_http, $swimlog_test_ihealth_url;
        $swimlog_test_ihealth_url = $url;
        return $swimlog_test_ihealth_http;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['response']['code'] ?? 0 ); }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }
}

final class IHealthOAuthTest extends TestCase {
    protected function setUp(): void {
        global $swimlog_test_options, $swimlog_test_user_meta, $swimlog_test_ihealth_http, $swimlog_test_ihealth_url, $swimlog_test_current_user_id, $swimlog_test_capabilities, $swimlog_test_logged_in;
        $swimlog_test_options = array();
        $swimlog_test_user_meta = array();
        $swimlog_test_ihealth_http = array();
        $swimlog_test_ihealth_url = '';
        $swimlog_test_current_user_id = 42;
        $swimlog_test_capabilities = array( 'manage_options' => true );
        $swimlog_test_logged_in = true;
    }

    private function start_authorization( $owner ) {
        $url = IHealth::begin_authorization( $owner );
        $this->assertIsString( $url );
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $authorization );
        parse_str( (string) parse_url( $authorization['redirect_uri'] ?? '', PHP_URL_QUERY ), $redirect );
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $redirect['state'] ?? '' );
        return array( $url, $redirect['state'] );
    }

    public function test_credentials_are_encrypted_and_secret_is_not_returned_to_the_view() {
        global $swimlog_test_options;
        $owner = 42;
        $view = IHealth::application_view();
        $saved = IHealth::save_application( $owner, 'synthetic-ihealth-client', 'synthetic-ihealth-secret', array( 'OpenApiWeight', 'OpenApiBP' ), $view['revision'] );
        $this->assertTrue( $saved );
        $view = IHealth::application_view();
        $this->assertTrue( $view['configured'] );
        $this->assertSame( 'synthetic-ihealth-client', $view['client_id'] );
        $this->assertTrue( $view['has_secret'] );
        $this->assertSame( array( 'OpenApiWeight', 'OpenApiBP' ), $view['api_names'] );
        $this->assertArrayNotHasKey( 'client_secret', $view );
        $this->assertStringNotContainsString( 'synthetic-ihealth-secret', $swimlog_test_options[ IHealth::APP_OPTION ] );
    }

    public function test_admin_capability_stale_revision_and_blank_secret_rules() {
        global $swimlog_test_capabilities, $swimlog_test_current_user_id, $swimlog_test_options;
        $view = IHealth::application_view();
        $this->assertTrue( is_wp_error( IHealth::save_application( 43, 'synthetic-client', 'synthetic-secret', array( 'OpenApiWeight' ), $view['revision'] ) ) );
        $swimlog_test_capabilities['manage_options'] = false;
        $this->assertTrue( is_wp_error( IHealth::save_application( 42, 'synthetic-client', 'synthetic-secret', array( 'OpenApiWeight' ), $view['revision'] ) ) );
        $swimlog_test_capabilities['manage_options'] = true;
        $swimlog_test_current_user_id = 42;
        $this->assertTrue( IHealth::save_application( 42, 'synthetic-client', 'synthetic-secret', array( 'OpenApiWeight' ), $view['revision'] ) );
        $saved = IHealth::application_view();
        $this->assertTrue( IHealth::save_application( 42, 'synthetic-client', '', array( 'OpenApiWeight' ), $saved['revision'] ) );
        $this->assertTrue( $this->has_secret( $swimlog_test_options[ IHealth::APP_OPTION ] ) );
        $this->assertTrue( is_wp_error( IHealth::save_application( 42, 'synthetic-client', '', array( 'OpenApiWeight' ), $view['revision'] ) ) );
    }

    private function has_secret( $sealed ) {
        $view = IHealth::application_view();
        return $view['has_secret'] && is_string( $sealed ) && '' !== $sealed;
    }

    public function test_oauth_is_owner_bound_single_use_and_stores_tokens_encrypted() {
        global $swimlog_test_ihealth_http, $swimlog_test_ihealth_url, $swimlog_test_user_meta;
        $owner = 42;
        $view = IHealth::application_view();
        $this->assertTrue( IHealth::save_application( $owner, 'synthetic-ihealth-client', 'synthetic-ihealth-secret', array( 'OpenApiWeight', 'OpenApiBP' ), $view['revision'] ) );
        list( $url, $state ) = $this->start_authorization( $owner );
        $this->assertStringStartsWith( IHealth::AUTH_URL . '?', $url );
        $this->assertStringNotContainsString( 'synthetic-ihealth-secret', $url );
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $authorization );
        $this->assertSame( 'code', $authorization['response_type'] );
        $this->assertSame( 'synthetic-ihealth-client', $authorization['client_id'] );
        parse_str( (string) parse_url( $authorization['redirect_uri'], PHP_URL_QUERY ), $redirect );
        $this->assertSame( 'swimlog_ihealth_oauth_callback', $redirect['action'] ?? '' );

        $swimlog_test_ihealth_http = array(
            'response' => array( 'code' => 200 ),
            'body' => wp_json_encode( array(
                'APIName' => 'OpenApiWeight OpenApiBP',
                'AccessToken' => 'synthetic-ihealth-access-token',
                'RefreshToken' => 'synthetic-ihealth-refresh-token',
                'UserID' => 'synthetic-ihealth-user',
                'Expires' => 3600,
            ) ),
        );
        $GLOBALS['swimlog_test_current_user_id'] = 43;
        $this->assertTrue( is_wp_error( IHealth::complete_authorization( 43, array( 'state' => $state, 'code' => 'synthetic-code' ) ) ) );
        $GLOBALS['swimlog_test_current_user_id'] = $owner;
        $this->assertTrue( IHealth::complete_authorization( $owner, array( 'state' => $state, 'code' => 'synthetic-code' ) ) );
        $this->assertStringContainsString( 'client_secret=synthetic-ihealth-secret', $swimlog_test_ihealth_url );
        $this->assertSame( array( 'OpenApiWeight', 'OpenApiBP' ), IHealth::connection( $owner )['api_names'] );
        $this->assertNull( IHealth::connection( 43 ) );
        $stored = $swimlog_test_user_meta[ $owner ][ IHealth::CONNECTION_META ];
        $this->assertStringNotContainsString( 'synthetic-ihealth-access-token', $stored );
        $this->assertStringNotContainsString( 'synthetic-ihealth-refresh-token', $stored );
        $this->assertArrayNotHasKey( 'access_token', IHealth::connection( $owner ) );
        $this->assertTrue( is_wp_error( IHealth::complete_authorization( $owner, array( 'state' => $state, 'code' => 'synthetic-code' ) ) ) );
    }

    public function test_incomplete_scopes_are_rejected_without_creating_a_grant() {
        global $swimlog_test_ihealth_http, $swimlog_test_user_meta;
        $owner = 42;
        $view = IHealth::application_view();
        $this->assertTrue( IHealth::save_application( $owner, 'synthetic-ihealth-client', 'synthetic-ihealth-secret', array( 'OpenApiWeight', 'OpenApiBP' ), $view['revision'] ) );
        list( , $state ) = $this->start_authorization( $owner );
        $swimlog_test_ihealth_http = array(
            'response' => array( 'code' => 200 ),
            'body' => wp_json_encode( array( 'APIName' => 'OpenApiWeight', 'AccessToken' => 'synthetic-access', 'RefreshToken' => 'synthetic-refresh', 'UserID' => 'synthetic-user', 'Expires' => 3600 ) ),
        );
        $this->assertTrue( is_wp_error( IHealth::complete_authorization( $owner, array( 'state' => $state, 'code' => 'synthetic-code' ) ) ) );
        $this->assertNull( IHealth::connection( $owner ) );
    }

    public function test_incomplete_scopes_do_not_replace_an_existing_grant() {
        global $swimlog_test_ihealth_http, $swimlog_test_user_meta;
        $owner = 42;
        $view = IHealth::application_view();
        $this->assertTrue( IHealth::save_application( $owner, 'synthetic-ihealth-client', 'synthetic-ihealth-secret', array( 'OpenApiWeight', 'OpenApiBP' ), $view['revision'] ) );
        list( , $state ) = $this->start_authorization( $owner );
        $swimlog_test_ihealth_http = array(
            'response' => array( 'code' => 200 ),
            'body' => wp_json_encode( array( 'APIName' => 'OpenApiWeight OpenApiBP', 'AccessToken' => 'old-access', 'RefreshToken' => 'old-refresh', 'UserID' => 'synthetic-user', 'Expires' => 3600 ) ),
        );
        $this->assertTrue( IHealth::complete_authorization( $owner, array( 'state' => $state, 'code' => 'initial-code' ) ) );
        $original_grant = $swimlog_test_user_meta[ $owner ][ IHealth::CONNECTION_META ];

        list( , $state ) = $this->start_authorization( $owner );
        $swimlog_test_ihealth_http['body'] = wp_json_encode( array( 'APIName' => 'OpenApiWeight', 'AccessToken' => 'new-access', 'RefreshToken' => 'new-refresh', 'UserID' => 'synthetic-user', 'Expires' => 3600 ) );
        $this->assertTrue( is_wp_error( IHealth::complete_authorization( $owner, array( 'state' => $state, 'code' => 'incomplete-code' ) ) ) );
        $this->assertSame( $original_grant, $swimlog_test_user_meta[ $owner ][ IHealth::CONNECTION_META ] );
        $this->assertTrue( IHealth::connection( $owner )['connected'] );
    }
}
