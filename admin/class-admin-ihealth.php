<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-oauth.php';

/** Administration screen and nonce-protected routes for global iHealth app setup. */
final class Admin_IHealth {
    const PAGE = 'swimlog-ihealth';

    public function register_menu() {
        add_submenu_page(
            'swimlog-dashboard',
            __( 'iHealth App Setup', 'chriss-swim-training-progress-evaluation' ),
            __( 'iHealth App Setup', 'chriss-swim-training-progress-evaluation' ),
            'manage_options',
            self::PAGE,
            array( $this, 'page' )
        );
    }

    private static function require_admin() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Only a signed-in WordPress administrator can manage iHealth app settings.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 403 ) );
        }
    }

    private static function return_to_page( $status ) {
        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'ihealth_status' => sanitize_key( $status ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function save() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_save_application' );
        $owner = (int) get_current_user_id();
        $client_id = isset( $_POST['client_id'] ) && is_string( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $client_secret = isset( $_POST['client_secret'] ) && is_string( $_POST['client_secret'] ) ? wp_unslash( $_POST['client_secret'] ) : '';
        $api_names = isset( $_POST['api_names'] ) && is_array( $_POST['api_names'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['api_names'] ) ) : array();
        $revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
        $saved = IHealth_OAuth::save_application( $owner, $client_id, $client_secret, $api_names, $revision );
        self::return_to_page( is_wp_error( $saved ) ? 'save_failed' : 'saved' );
    }

    public function connect() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_connect' );
        $url = IHealth_OAuth::begin_authorization( (int) get_current_user_id() );
        if ( is_wp_error( $url ) ) self::return_to_page( 'authorization_failed' );
        wp_redirect( $url, 303 );
        exit;
    }

    public function callback() {
        self::require_admin();
        if ( 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            wp_die( esc_html__( 'The iHealth authorization callback requires GET.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 405 ) );
        }
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
        $result = IHealth_OAuth::complete_authorization( (int) get_current_user_id(), wp_unslash( $_GET ) );
        self::return_to_page( is_wp_error( $result ) ? 'authorization_failed' : 'verified' );
    }

    public function page() {
        self::require_admin();
        $app = IHealth_OAuth::application_view();
        $connection = IHealth_OAuth::connection( (int) get_current_user_id() );
        $statuses = array(
            'saved' => __( 'The encrypted iHealth app settings were saved. Authorize below to verify them.', 'chriss-swim-training-progress-evaluation' ),
            'verified' => __( 'iHealth confirmed the selected categories and the app credentials were verified.', 'chriss-swim-training-progress-evaluation' ),
            'save_failed' => __( 'The iHealth app settings could not be saved. Check the fields and reload before trying again.', 'chriss-swim-training-progress-evaluation' ),
            'authorization_failed' => __( 'iHealth authorization did not complete. Check the credentials, selected categories and registered redirect, then try again.', 'chriss-swim-training-progress-evaluation' ),
        );
        $status = isset( $_GET['ihealth_status'] ) && is_string( $_GET['ihealth_status'] ) ? sanitize_key( wp_unslash( $_GET['ihealth_status'] ) ) : '';
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $redirect_domain = is_string( $host ) && '' !== $host ? 'https://' . strtolower( $host ) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'iHealth App Setup', 'chriss-swim-training-progress-evaluation' ); ?></h1>
            <?php if ( isset( $statuses[ $status ] ) ) : ?>
                <div class="notice <?php echo in_array( $status, array( 'saved', 'verified' ), true ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $statuses[ $status ] ); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e( 'Enter the iHealth Client ID and Client Secret from the developer portal. These app-level credentials are encrypted in WordPress and shared by the integration. Only a WordPress administrator can view or change this screen. A saved secret is never displayed; leave the secret field blank to retain it.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="swimlog_ihealth_save">
                <?php wp_nonce_field( 'swimlog_ihealth_save_application' ); ?>
                <input type="hidden" name="revision" value="<?php echo esc_attr( $app['revision'] ); ?>">
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="swimlog-ihealth-client-id"><?php esc_html_e( 'iHealth Client ID', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-client-id" name="client_id" type="text" class="regular-text" maxlength="128" autocomplete="off" required value="<?php echo esc_attr( $app['client_id'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="swimlog-ihealth-client-secret"><?php esc_html_e( 'iHealth Client Secret', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-client-secret" name="client_secret" type="password" class="regular-text" maxlength="1024" autocomplete="new-password" value=""><p class="description"><?php echo $app['has_secret'] ? esc_html__( 'A secret is saved. Enter a new value only to replace it.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'No secret is saved yet.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
                </table>
                <fieldset>
                    <legend><?php esc_html_e( 'Categories to request from iHealth', 'chriss-swim-training-progress-evaluation' ); ?></legend>
                    <?php foreach ( IHealth_OAuth::api_names() as $api_name => $label ) : ?>
                        <label style="display:block;margin:6px 0"><input type="checkbox" name="api_names[]" value="<?php echo esc_attr( $api_name ); ?>" <?php checked( in_array( $api_name, $app['api_names'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Select only the categories needed. This setup verifies authorization; it does not yet import data.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                </fieldset>
                <?php submit_button( __( 'Save iHealth app settings', 'chriss-swim-training-progress-evaluation' ) ); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'OAuth redirect', 'chriss-swim-training-progress-evaluation' ); ?></h2>
            <p><?php esc_html_e( 'Register the callback with iHealth. The app adds a short-lived state parameter for each authorization attempt; do not register a temporary state value.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <p><code><?php echo esc_html( IHealth_OAuth::callback_url() ); ?></code></p>
            <p><?php
                printf(
                    /* translators: %s: HTTPS host to register in the iHealth developer portal. */
                    esc_html__( 'Set the iHealth Redirect Domain to %s. The Authorization Removal Callback URL is separate and is not handled by this OAuth callback.', 'chriss-swim-training-progress-evaluation' ),
                    '<code>' . esc_html( $redirect_domain ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
                );
            ?></p>

            <hr>
            <h2><?php esc_html_e( 'Verify credentials and authorize this account', 'chriss-swim-training-progress-evaluation' ); ?></h2>
            <?php if ( is_array( $connection ) ) : ?>
                <p class="notice notice-success inline"><?php
                    printf(
                        /* translators: 1: selected API categories, 2: local expiry time. */
                        esc_html__( 'This WordPress administrator’s iHealth account is authorized for %1$s. The stored token expires at %2$s.', 'chriss-swim-training-progress-evaluation' ),
                        esc_html( implode( ', ', array_map( static function ( $name ) { return IHealth_OAuth::api_names()[ $name ]; }, $connection['api_names'] ) ) ),
                        esc_html( wp_date( 'Y-m-d H:i T', $connection['expires_at'], wp_timezone() ) )
                    );
                ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'No current iHealth authorization is stored for this WordPress administrator.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <?php endif; ?>
            <p><?php esc_html_e( 'Continue to iHealth to sign in and approve the selected categories. This user consent is required to verify the Client ID and Secret. Returned access and refresh tokens are encrypted in this administrator’s private WordPress user metadata. This step does not import measurements or activities.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="swimlog_ihealth_connect">
                <?php wp_nonce_field( 'swimlog_ihealth_connect' ); ?>
                <?php submit_button( __( 'Authorize with iHealth and verify', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false, $app['configured'] ? array() : array( 'disabled' => 'disabled' ) ); ?>
            </form>
        </div>
        <?php
    }
}
