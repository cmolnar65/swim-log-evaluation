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

    /**
     * Read only allowlisted query parameters used to display post/redirect/get notices.
     *
     * @param string $key Notice parameter name.
     * @return string
     */
    private static function notice_parameter( $key ) {
        $allowed = array( 'ihealth_status', 'ihealth_added', 'ihealth_updated', 'ihealth_skipped', 'ihealth_duplicates', 'ihealth_possible_duplicates', 'ihealth_conflicts', 'ihealth_invalid', 'ihealth_page', 'ihealth_has_more', 'ihealth_bp_added', 'ihealth_bp_updated', 'ihealth_bp_skipped', 'ihealth_bp_invalid', 'ihealth_bp_page', 'ihealth_bp_has_more' );
        if ( ! in_array( $key, $allowed, true ) ) {
            return '';
        }
        $query = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Allowlisted values only select sanitized, read-only notices after a state-changing action has already completed; they cannot trigger or authorize a write.
        if ( ! is_array( $query ) || ! isset( $query[ $key ] ) || ! is_string( $query[ $key ] ) ) {
            return '';
        }
        return sanitize_text_field( $query[ $key ] );
    }

    public function save() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_save_application' );
        $owner = (int) get_current_user_id();
        $client_id = isset( $_POST['client_id'] ) && is_string( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $client_secret = isset( $_POST['client_secret'] ) && is_string( $_POST['client_secret'] ) ? wp_unslash( $_POST['client_secret'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque credential is validated by IHealth_OAuth before encrypted storage and must not be transformed.
        $sc = isset( $_POST['sc'] ) && is_string( $_POST['sc'] ) ? wp_unslash( $_POST['sc'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque API signing code is validated by IHealth_OAuth before encrypted storage.
        $weight_sv = isset( $_POST['weight_sv'] ) && is_string( $_POST['weight_sv'] ) ? wp_unslash( $_POST['weight_sv'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque API signing code is validated by IHealth_OAuth before encrypted storage.
        $blood_pressure_sv = isset( $_POST['blood_pressure_sv'] ) && is_string( $_POST['blood_pressure_sv'] ) ? wp_unslash( $_POST['blood_pressure_sv'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque API signing code is validated by IHealth_OAuth before encrypted storage.
        $submitted_api_names = isset( $_POST['api_names'] ) ? map_deep( wp_unslash( $_POST['api_names'] ), 'sanitize_text_field' ) : array();
        $api_names = is_array( $submitted_api_names ) ? array_values( array_filter( $submitted_api_names, 'is_string' ) ) : array();
        $revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
        $saved = IHealth_OAuth::save_application( $owner, $client_id, $client_secret, $api_names, $revision, array( 'sc' => $sc, 'weight_sv' => $weight_sv, 'blood_pressure_sv' => $blood_pressure_sv ) );
        self::return_to_page( is_wp_error( $saved ) ? 'save_failed' : 'saved' );
    }

    public function connect() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_connect' );
        $url = IHealth_OAuth::begin_authorization( (int) get_current_user_id() );
        if ( is_wp_error( $url ) ) self::return_to_page( 'authorization_failed' );
        wp_redirect( esc_url_raw( $url ), 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- OAuth requires the fixed external iHealth authorization endpoint.
        exit;
    }

    /** Run one bounded, nonce-protected private iHealth weight page. */
    public function import_weight() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_import_weight' );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-weight-import.php';
        $start = isset( $_POST['start_date'] ) && is_string( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end = isset( $_POST['end_date'] ) && is_string( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $page = isset( $_POST['page_index'] ) ? absint( wp_unslash( $_POST['page_index'] ) ) : 1;
        $result = IHealth_Weight_Import::import_page( (int) get_current_user_id(), $start, $end, $page );
        $args = array( 'page' => self::PAGE, 'ihealth_status' => is_wp_error( $result ) ? 'import_failed' : 'imported' );
        if ( ! is_wp_error( $result ) ) foreach ( array( 'added', 'updated', 'skipped', 'duplicates', 'possible_duplicates', 'conflicts', 'invalid', 'page', 'has_more' ) as $key ) $args[ 'ihealth_' . $key ] = $result[ $key ];
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /** Run one bounded, nonce-protected private iHealth blood-pressure page. */
    public function import_blood_pressure() {
        self::require_admin();
        check_admin_referer( 'swimlog_ihealth_import_blood_pressure' );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-ihealth-blood-pressure-import.php';
        $start = isset( $_POST['start_date'] ) && is_string( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end = isset( $_POST['end_date'] ) && is_string( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $page = isset( $_POST['page_index'] ) ? absint( wp_unslash( $_POST['page_index'] ) ) : 1;
        $result = IHealth_Blood_Pressure_Import::import_page( (int) get_current_user_id(), $start, $end, $page );
        $args = array( 'page' => self::PAGE, 'ihealth_status' => is_wp_error( $result ) ? 'bp_import_failed' : 'bp_imported' );
        if ( ! is_wp_error( $result ) ) foreach ( array( 'added', 'updated', 'skipped', 'invalid', 'page', 'has_more' ) as $key ) $args[ 'ihealth_bp_' . $key ] = $result[ $key ];
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public function callback() {
        self::require_admin();
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'GET' !== strtoupper( $request_method ) ) {
            wp_die( esc_html__( 'The iHealth authorization callback requires GET.', 'chriss-swim-training-progress-evaluation' ), '', array( 'response' => 405 ) );
        }
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
        $result = IHealth_OAuth::complete_authorization( (int) get_current_user_id(), wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state is random, short-lived, owner-bound and consumed once as callback CSRF protection.
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
            'import_failed' => __( 'The private iHealth import could not be completed. No provider payload was displayed or logged.', 'chriss-swim-training-progress-evaluation' ),
            'bp_import_failed' => __( 'The private iHealth blood-pressure import could not be completed. No provider payload was displayed or logged.', 'chriss-swim-training-progress-evaluation' ),
        );
        $status = sanitize_key( self::notice_parameter( 'ihealth_status' ) );
        $host = wp_parse_url( IHealth_OAuth::callback_url(), PHP_URL_HOST );
        $redirect_domain = is_string( $host ) && '' !== $host ? 'https://' . strtolower( $host ) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'iHealth App Setup', 'chriss-swim-training-progress-evaluation' ); ?></h1>
            <?php if ( isset( $statuses[ $status ] ) ) : ?>
                <div class="notice <?php echo in_array( $status, array( 'saved', 'verified' ), true ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $statuses[ $status ] ); ?></p></div>
            <?php endif; ?>
            <?php if ( 'imported' === $status ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        /* translators: 1: added count, 2: updated count, 3: skipped count, 4: exact duplicate count, 5: possible duplicate count, 6: conflict count, 7: invalid count, 8: provider page number. */
                        esc_html__( 'iHealth page %8$d import complete: %1$d added, %2$d updated, %3$d unchanged, %4$d exact cross-provider duplicates, %5$d possible same-day duplicates, %6$d same-time conflicts, %7$d unusable records.', 'chriss-swim-training-progress-evaluation' ),
                        absint( self::notice_parameter( 'ihealth_added' ) ), absint( self::notice_parameter( 'ihealth_updated' ) ), absint( self::notice_parameter( 'ihealth_skipped' ) ),
                        absint( self::notice_parameter( 'ihealth_duplicates' ) ), absint( self::notice_parameter( 'ihealth_possible_duplicates' ) ), absint( self::notice_parameter( 'ihealth_conflicts' ) ),
                        absint( self::notice_parameter( 'ihealth_invalid' ) ), absint( self::notice_parameter( 'ihealth_page' ) )
                    );
                    if ( '1' === self::notice_parameter( 'ihealth_has_more' ) ) esc_html_e( ' More provider records may be available on a later page.', 'chriss-swim-training-progress-evaluation' );
                ?></p></div>
            <?php endif; ?>
            <?php if ( 'bp_imported' === $status ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        /* translators: 1: added count, 2: updated count, 3: unchanged count, 4: invalid count, 5: page number. */
                        esc_html__( 'iHealth blood-pressure page %5$d import complete: %1$d added, %2$d updated, %3$d unchanged, %4$d unusable records.', 'chriss-swim-training-progress-evaluation' ),
                        absint( self::notice_parameter( 'ihealth_bp_added' ) ), absint( self::notice_parameter( 'ihealth_bp_updated' ) ), absint( self::notice_parameter( 'ihealth_bp_skipped' ) ), absint( self::notice_parameter( 'ihealth_bp_invalid' ) ), absint( self::notice_parameter( 'ihealth_bp_page' ) )
                    );
                    if ( '1' === self::notice_parameter( 'ihealth_bp_has_more' ) ) esc_html_e( ' More provider records may be available on a later page.', 'chriss-swim-training-progress-evaluation' );
                ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e( 'Enter the iHealth Client ID and Client Secret from the developer portal. These app-level credentials are encrypted in WordPress and shared by the integration. Only a WordPress administrator can view or change this screen. Saved secrets are never displayed; leave a secret field blank to retain its existing value.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="swimlog_ihealth_save">
                <?php wp_nonce_field( 'swimlog_ihealth_save_application' ); ?>
                <input type="hidden" name="revision" value="<?php echo esc_attr( $app['revision'] ); ?>">
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="swimlog-ihealth-client-id"><?php esc_html_e( 'iHealth Client ID', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-client-id" name="client_id" type="text" class="regular-text" maxlength="128" autocomplete="off" required value="<?php echo esc_attr( $app['client_id'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="swimlog-ihealth-client-secret"><?php esc_html_e( 'iHealth Client Secret', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-client-secret" name="client_secret" type="password" class="regular-text" maxlength="1024" autocomplete="new-password" value=""><p class="description"><?php echo $app['has_secret'] ? esc_html__( 'A secret is saved. Enter a new value only to replace it.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'No secret is saved yet.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="swimlog-ihealth-sc"><?php esc_html_e( 'iHealth SC', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-sc" name="sc" type="password" class="regular-text" maxlength="1024" autocomplete="new-password" value=""><p class="description"><?php echo $app['has_sc'] ? esc_html__( 'An SC code is saved. Enter a new value only to replace it.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Required by iHealth for Personal API data requests. This is separate from Client ID and Client Secret.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="swimlog-ihealth-weight-sv"><?php esc_html_e( 'iHealth Weight API SV', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-weight-sv" name="weight_sv" type="password" class="regular-text" maxlength="1024" autocomplete="new-password" value=""><p class="description"><?php echo $app['has_weight_sv'] ? esc_html__( 'A Weight API SV code is saved. Enter a new value only to replace it.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Required by iHealth for weight data requests. Obtain the app-specific code from iHealth; this is separate from Client ID and Client Secret.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="swimlog-ihealth-blood-pressure-sv"><?php esc_html_e( 'iHealth Blood Pressure API SV', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-ihealth-blood-pressure-sv" name="blood_pressure_sv" type="password" class="regular-text" maxlength="1024" autocomplete="new-password" value=""><p class="description"><?php echo $app['has_blood_pressure_sv'] ? esc_html__( 'A Blood Pressure API SV code is saved. Enter a new value only to replace it.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Required for blood-pressure requests. Obtain the app-specific Blood Pressure SV code from iHealth; it may differ from the Weight API SV.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
                </table>
                <fieldset>
                    <legend><?php esc_html_e( 'Categories to request from iHealth', 'chriss-swim-training-progress-evaluation' ); ?></legend>
                    <?php foreach ( IHealth_OAuth::api_names() as $api_name => $label ) : ?>
                        <label class="ihealth-api-category"><input type="checkbox" name="api_names[]" value="<?php echo esc_attr( $api_name ); ?>" <?php checked( in_array( $api_name, $app['api_names'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Select only the categories needed. Weight import is a separate manual action below and requires the confirmed Weight API authorization.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                </fieldset>
                <?php submit_button( __( 'Save iHealth app settings', 'chriss-swim-training-progress-evaluation' ) ); ?>
            </form>

            <?php if ( is_array( $connection ) && in_array( 'OpenApiWeight', $connection['api_names'], true ) && $app['has_sc'] && $app['has_weight_sv'] ) : ?>
                <hr>
                <h2><?php esc_html_e( 'Import private iHealth weight data', 'chriss-swim-training-progress-evaluation' ); ?></h2>
                <p><?php esc_html_e( 'This manual import requests one bounded Personal API page. Normalized weight is stored in kilograms when iHealth supplies a recognized weight unit. Other returned composition numbers are retained privately with their original field names and unit marked unknown; they are not displayed, converted, interpreted or used in calculations. Same-instant weights matching another provider at gram precision are marked as duplicates and omitted from the calendar; same-day readings at different times are retained and flagged as possible duplicates. No automatic sync is enabled.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="swimlog_ihealth_import_weight">
                    <?php wp_nonce_field( 'swimlog_ihealth_import_weight' ); ?>
                    <p><label for="swimlog-ihealth-start"><?php esc_html_e( 'From', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="date" id="swimlog-ihealth-start" name="start_date" required value="<?php echo esc_attr( wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS, wp_timezone() ) ); ?>"> <label for="swimlog-ihealth-end"><?php esc_html_e( 'Through', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="date" id="swimlog-ihealth-end" name="end_date" required value="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"> <label for="swimlog-ihealth-page"><?php esc_html_e( 'Page', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="number" min="1" max="20" id="swimlog-ihealth-page" name="page_index" value="1" required></p>
                    <?php submit_button( __( 'Import iHealth weight page', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
                </form>
            <?php endif; ?>

            <?php if ( is_array( $connection ) && in_array( 'OpenApiBP', $connection['api_names'], true ) && $app['has_sc'] && $app['has_blood_pressure_sv'] ) : ?>
                <hr>
                <h2><?php esc_html_e( 'Import private iHealth blood pressure and pulse', 'chriss-swim-training-progress-evaluation' ); ?></h2>
                <p><?php esc_html_e( 'This manual import requests one bounded Personal API page. Pressure is imported only when iHealth reports mmHg. Readings remain private to this connected WordPress administrator and are shown only in the private calendar. Device flags, notes, location fields and source metadata are not imported. Repeating a page is safe and reports counts only; no automatic sync is enabled.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="swimlog_ihealth_import_blood_pressure">
                    <?php wp_nonce_field( 'swimlog_ihealth_import_blood_pressure' ); ?>
                    <p><label for="swimlog-ihealth-bp-start"><?php esc_html_e( 'From', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="date" id="swimlog-ihealth-bp-start" name="start_date" required value="<?php echo esc_attr( wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS, wp_timezone() ) ); ?>"> <label for="swimlog-ihealth-bp-end"><?php esc_html_e( 'Through', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="date" id="swimlog-ihealth-bp-end" name="end_date" required value="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"> <label for="swimlog-ihealth-bp-page"><?php esc_html_e( 'Page', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="number" min="1" max="20" id="swimlog-ihealth-bp-page" name="page_index" value="1" required></p>
                    <?php submit_button( __( 'Import iHealth blood-pressure page', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
                </form>
            <?php endif; ?>

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
