<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owner-only manual measurement import controls on the existing Garmin page. */
final class Admin_Garmin_Measurements {
    public static function render() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-health-measurement-store.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-measurement-import.php';
        $today = wp_date( 'Y-m-d', null, wp_timezone() );
        $start = $today;
        $end = $today;
        $selected = array();
        $results = null;
        if ( isset( $_POST['swimlog_measurement_import'] ) ) {
            check_admin_referer( 'swimlog_garmin_measurements' );
            $start = isset( $_POST['measurement_start'] ) && is_string( $_POST['measurement_start'] ) ? sanitize_text_field( wp_unslash( $_POST['measurement_start'] ) ) : '';
            $end = isset( $_POST['measurement_end'] ) && is_string( $_POST['measurement_end'] ) ? sanitize_text_field( wp_unslash( $_POST['measurement_end'] ) ) : '';
            $raw = isset( $_POST['measurement_categories'] ) && is_array( $_POST['measurement_categories'] ) ? map_deep( wp_unslash( $_POST['measurement_categories'] ), 'sanitize_text_field' ) : array();
            foreach ( $raw as $value ) {
                if ( is_string( $value ) ) $selected[] = sanitize_key( $value );
            }
            if ( count( $raw ) !== count( $selected ) ) $selected = array();
            $results = Garmin_Measurement_Import::run( (int) get_current_user_id(), $start, $end, $selected );
        }
        ?>
        <section id="swimlog-garmin-measurements" aria-labelledby="swimlog-measurement-title">
            <h2 id="swimlog-measurement-title"><?php esc_html_e( 'Private measurements', 'chriss-swim-training-progress-evaluation' ); ?></h2>
            <p><?php esc_html_e( 'Import measurements into your private records. Weight, BMI and Garmin-reported SpO2 summaries are currently supported. Body-fat, body-water and mass fields, blood pressure and individual/hourly SpO2 readings await format verification and are not imported. Calendar display is not available yet.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'swimlog_garmin_measurements' ); ?>
                <input type="hidden" name="swimlog_measurement_import" value="1">
                <p><label for="swimlog-measurement-start"><?php esc_html_e( 'Start date', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-measurement-start" type="date" name="measurement_start" value="<?php echo esc_attr( $start ); ?>" max="<?php echo esc_attr( $today ); ?>" required></p>
                <p><label for="swimlog-measurement-end"><?php esc_html_e( 'End date', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-measurement-end" type="date" name="measurement_end" value="<?php echo esc_attr( $end ); ?>" max="<?php echo esc_attr( $today ); ?>" required></p>
                <fieldset><legend><?php esc_html_e( 'Measurements to import', 'chriss-swim-training-progress-evaluation' ); ?></legend>
                    <?php foreach ( array( 'weight' => __( 'Weight', 'chriss-swim-training-progress-evaluation' ), 'body_composition' => __( 'Body composition (BMI only)', 'chriss-swim-training-progress-evaluation' ), 'spo2' => __( 'SpO2 provider summaries', 'chriss-swim-training-progress-evaluation' ) ) as $key => $label ) : ?>
                    <p><label><input type="checkbox" name="measurement_categories[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label></p>
                    <?php endforeach; ?>
                </fieldset>
                <p><?php esc_html_e( 'Choose up to seven inclusive days. Each submission has a time limit; dates marked Not attempted can be retried using the same range. Existing records are preserved, and duplicate readings are skipped. Scheduled swim synchronization does not import measurements.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <?php submit_button( __( 'Import private measurements', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit_measurements', false ); ?>
            </form>
            <?php if ( is_wp_error( $results ) ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( $results->get_error_message() ); ?></p></div>
            <?php elseif ( is_array( $results ) ) : ?>
                <div role="status"><h3><?php esc_html_e( 'Measurement import results', 'chriss-swim-training-progress-evaluation' ); ?></h3></div>
                <table class="widefat striped"><thead><tr>
                    <?php foreach ( array( __( 'Date', 'chriss-swim-training-progress-evaluation' ), __( 'Category', 'chriss-swim-training-progress-evaluation' ), __( 'Result', 'chriss-swim-training-progress-evaluation' ), __( 'Readings: added / updated / skipped', 'chriss-swim-training-progress-evaluation' ), __( 'Reports: added / updated / skipped', 'chriss-swim-training-progress-evaluation' ), __( 'Excluded fields or rows', 'chriss-swim-training-progress-evaluation' ), __( 'Failed requests or bundles', 'chriss-swim-training-progress-evaluation' ) ) as $label ) : ?><th scope="col"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
                </tr></thead><tbody>
                <?php
                $labels = array( 'complete' => __( 'Complete', 'chriss-swim-training-progress-evaluation' ), 'no_data' => __( 'No data returned', 'chriss-swim-training-progress-evaluation' ), 'unsupported_data' => __( 'No supported values', 'chriss-swim-training-progress-evaluation' ), 'failed' => __( 'Failed; existing data preserved', 'chriss-swim-training-progress-evaluation' ), 'not_attempted' => __( 'Not attempted; retry this range', 'chriss-swim-training-progress-evaluation' ) );
                foreach ( $results as $row ) :
                ?>
                    <tr><td><?php echo esc_html( $row['date'] ); ?></td><td><?php echo esc_html( $row['category'] ); ?></td><td><?php echo esc_html( $labels[ $row['status'] ] ); ?><?php if ( isset( $row['message'] ) ) : ?><br><?php echo esc_html( $row['message'] ); ?><?php endif; ?><?php if ( isset( $row['retry_after'] ) ) : ?><br><?php echo esc_html( sprintf( /* translators: %d: waiting period in seconds. */ __( 'Wait at least %d seconds before retrying.', 'chriss-swim-training-progress-evaluation' ), $row['retry_after'] ) ); ?><?php endif; ?></td>
                    <td><?php echo esc_html( $row['added'] . ' / ' . $row['updated'] . ' / ' . $row['skipped'] ); ?></td><td><?php echo esc_html( $row['reports_added'] . ' / ' . $row['reports_updated'] . ' / ' . $row['reports_skipped'] ); ?></td><td><?php echo esc_html( (string) $row['excluded'] ); ?></td><td><?php echo esc_html( (string) $row['failed'] ); ?><?php if ( in_array( $row['status'], array( 'failed', 'not_attempted' ), true ) ) : ?>
                        <form method="post">
                            <?php wp_nonce_field( 'swimlog_garmin_measurements' ); ?>
                            <input type="hidden" name="swimlog_measurement_import" value="1">
                            <input type="hidden" name="measurement_start" value="<?php echo esc_attr( $row['date'] ); ?>">
                            <input type="hidden" name="measurement_end" value="<?php echo esc_attr( $row['date'] ); ?>">
                            <input type="hidden" name="measurement_categories[]" value="<?php echo esc_attr( $row['category'] ); ?>">
                            <?php submit_button( __( 'Retry this date and category', 'chriss-swim-training-progress-evaluation' ), 'secondary small', 'retry_measurements', false ); ?>
                        </form>
                    <?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </section>
        <?php
    }
}
