<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Current-owner historical controls; posts never accept an owner parameter. */
final class Admin_Garmin_History {
    private static function scalar( $values, $key, $fallback = '' ) {
        return isset( $values[$key] ) && is_string( $values[$key] ) ? sanitize_text_field( wp_unslash( $values[$key] ) ) : $fallback;
    }
    public static function ajax_status() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_send_json_error( array(), 403 );
        check_ajax_referer( 'swimlog_history_status', 'nonce' );
        $id = self::scalar( $_POST, 'job' );
        $job = Garmin_History_Jobs::get( $id );
        if ( ! is_array( $job ) ) wp_send_json_error( array(), 404 );
        $offset = isset( $_POST['offset'] ) && is_scalar( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
        $filter = self::scalar( $_POST, 'filter', 'all' );
        nocache_headers();
        ob_start(); self::status( $job, $offset, $filter ); $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }
    public static function render() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-history-jobs.php';
        $notice = null;
        $form_start = self::scalar( $_POST, 'history_start' );
        $form_end = self::scalar( $_POST, 'history_end' );
        $form_selected = isset( $_POST['history_categories'] ) && is_array( $_POST['history_categories'] ) ? map_deep( wp_unslash( $_POST['history_categories'] ), 'sanitize_text_field' ) : array();
        if ( isset( $_POST['swimlog_history_action'] ) ) {
            check_admin_referer( 'swimlog_history_control' );
            $action = self::scalar( $_POST, 'swimlog_history_action' );
            if ( 'start' === $action ) {
                $selected = isset( $_POST['history_categories'] ) && is_array( $_POST['history_categories'] ) ? map_deep( wp_unslash( $_POST['history_categories'] ), 'sanitize_text_field' ) : array();
                $notice = Garmin_History_Jobs::start( self::scalar( $_POST, 'history_start' ), self::scalar( $_POST, 'history_end' ), $selected );
            } else $notice = Garmin_History_Jobs::control( self::scalar( $_POST, 'history_job' ), $action );
        }
        $id = self::scalar( $_GET, 'history_job' );
        $job = is_array( $notice ) ? $notice : Garmin_History_Jobs::get( $id ? $id : null );
        $offset = isset( $_GET['history_offset'] ) && is_scalar( $_GET['history_offset'] ) ? absint( wp_unslash( $_GET['history_offset'] ) ) : 0;
        $filter = self::scalar( $_GET, 'history_filter', 'all' );
        wp_enqueue_style( 'swimlog-history', SWIMLOG_EVALUATION_URL . 'assets/css/garmin-history.css', array(), SWIMLOG_EVALUATION_VERSION );
        wp_enqueue_script( 'swimlog-history', SWIMLOG_EVALUATION_URL . 'assets/js/garmin-history.js', array(), SWIMLOG_EVALUATION_VERSION, true );
        wp_localize_script( 'swimlog-history', 'swimlogHistorySettings', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'swimlog_history_status' ) ) );
        ?>
        <section id="swimlog-garmin-history" aria-labelledby="swimlog-history-title">
            <h2 id="swimlog-history-title"><?php esc_html_e( 'Import historical Garmin data', 'chriss-swim-training-progress-evaluation' ); ?></h2>
            <p><?php esc_html_e( 'Choose inclusive dates and supported categories. Imports run in background batches and continue after you close this page. Garmin may not provide every historical record. Existing records are retained; exact duplicates are skipped and ambiguous matches require review.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <?php if ( is_wp_error( $notice ) ) : ?><p role="alert"><?php echo esc_html( $notice->get_error_message() ); ?></p><?php endif; ?>
            <form method="post" class="swimlog-history-start">
                <?php wp_nonce_field( 'swimlog_history_control' ); ?>
                <input type="hidden" name="swimlog_history_action" value="start">
                <p><label for="swimlog-history-start"><?php esc_html_e( 'Start date', 'chriss-swim-training-progress-evaluation' ); ?></label><br><input id="swimlog-history-start" type="date" name="history_start" value="<?php echo esc_attr( $form_start ); ?>" required max="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></p>
                <p><label for="swimlog-history-end"><?php esc_html_e( 'End date', 'chriss-swim-training-progress-evaluation' ); ?></label><br><input id="swimlog-history-end" type="date" name="history_end" value="<?php echo esc_attr( $form_end ); ?>" required max="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></p>
                <fieldset><legend><?php esc_html_e( 'Categories to import', 'chriss-swim-training-progress-evaluation' ); ?></legend>
                <?php foreach ( array( 'lap_swimming' => __( 'Pool swims', 'chriss-swim-training-progress-evaluation' ), 'open_water_swimming' => __( 'Open-water swims', 'chriss-swim-training-progress-evaluation' ), 'weight' => __( 'Weight', 'chriss-swim-training-progress-evaluation' ), 'body_composition' => __( 'Body composition (BMI only)', 'chriss-swim-training-progress-evaluation' ), 'spo2' => __( 'SpO2 provider summaries', 'chriss-swim-training-progress-evaluation' ) ) as $key => $label ) : ?>
                <label class="swimlog-history-choice"><input type="checkbox" name="history_categories[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $form_selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?></fieldset>
                <p><?php esc_html_e( 'One unfinished job per swimmer. Pause or resume it, or cancel before starting another range. This does not enable regular automatic synchronization. Blood pressure, unverified composition fields and individual/hourly SpO2 readings are not imported.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <?php submit_button( __( 'Start historical import', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'start_history', false ); ?>
            </form>
            <?php if ( is_array( $job ) ) self::status( $job, $offset, $filter ); ?>
            <h3><?php esc_html_e( 'Recent historical jobs', 'chriss-swim-training-progress-evaluation' ); ?></h3><ul>
            <?php foreach ( Garmin_History_Jobs::recent() as $recent ) : ?><li><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'history_job' => $recent['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $recent['start'] . ' — ' . $recent['end'] . ' · ' . self::label( $recent['status'] ) ); ?></a></li><?php endforeach; ?>
            </ul>
        </section>
        <?php
    }
    private static function button( $job, $action, $label ) {
        ?><form method="post"><?php wp_nonce_field( 'swimlog_history_control' ); ?><input type="hidden" name="swimlog_history_action" value="<?php echo esc_attr( $action ); ?>"><input type="hidden" name="history_job" value="<?php echo esc_attr( $job['id'] ); ?>"><?php submit_button( $label, 'secondary', 'history_control', false ); ?></form><?php
    }

    private static function label( $value ) {
        $labels = array(
            'page' => __( 'Activity discovery', 'chriss-swim-training-progress-evaluation' ),
            'activity' => __( 'Recorded swim', 'chriss-swim-training-progress-evaluation' ),
            'measurement' => __( 'Measurements', 'chriss-swim-training-progress-evaluation' ),
            'weight' => __( 'Weight', 'chriss-swim-training-progress-evaluation' ),
            'body_composition' => __( 'BMI', 'chriss-swim-training-progress-evaluation' ),
            'spo2' => __( 'SpO2 summaries', 'chriss-swim-training-progress-evaluation' ),
            'queued' => __( 'Queued', 'chriss-swim-training-progress-evaluation' ),
            'working' => __( 'In progress', 'chriss-swim-training-progress-evaluation' ),
            'running' => __( 'Running', 'chriss-swim-training-progress-evaluation' ),
            'waiting' => __( 'Waiting before retry', 'chriss-swim-training-progress-evaluation' ),
            'paused' => __( 'Paused', 'chriss-swim-training-progress-evaluation' ),
            'blocked' => __( 'Blocked', 'chriss-swim-training-progress-evaluation' ),
            'complete' => __( 'Finished', 'chriss-swim-training-progress-evaluation' ),
            'completed' => __( 'Completed', 'chriss-swim-training-progress-evaluation' ),
            'completed_with_errors' => __( 'Completed with errors', 'chriss-swim-training-progress-evaluation' ),
            'cancelled' => __( 'Cancelled', 'chriss-swim-training-progress-evaluation' ),
            'failed' => __( 'Failed', 'chriss-swim-training-progress-evaluation' ),
            'review' => __( 'Needs review', 'chriss-swim-training-progress-evaluation' ),
            'imported' => __( 'Imported', 'chriss-swim-training-progress-evaluation' ),
            'already' => __( 'Already present', 'chriss-swim-training-progress-evaluation' ),
            'no_data' => __( 'No data returned', 'chriss-swim-training-progress-evaluation' ),
            'not_attempted' => __( 'Not attempted', 'chriss-swim-training-progress-evaluation' ),
            'unsupported_data' => __( 'No supported values', 'chriss-swim-training-progress-evaluation' )
        );
        return $labels[$value] ?? $value;
    }

    private static function failure_message( $code ) {
        if ( false !== strpos( $code, 'capacity' ) ) return __( 'The job storage limit was reached. Cancel and choose a smaller range; existing imports are retained.', 'chriss-swim-training-progress-evaluation' );
        if ( 'authorization_or_connection' === $code ) return __( 'Check your Garmin key and import permissions, then resume. Progress has been retained.', 'chriss-swim-training-progress-evaluation' );
        if ( 'schedule' === $code ) return __( 'WordPress could not schedule the next task. Use Resume / repair schedule.', 'chriss-swim-training-progress-evaluation' );
        return __( 'This operation could not finish. Existing imports were retained. Resume a blocked job, or use Retry failed items after the scan finishes.', 'chriss-swim-training-progress-evaluation' );
    }
    public static function status( $job, $offset = 0, $filter = 'all' ) {
        if ( ! in_array( $filter, array( 'all', 'failed', 'review' ), true ) ) $filter = 'all';
        $active = in_array( $job['status'], array( 'queued', 'running', 'waiting' ), true );
        $next = Garmin_History_Jobs::next( $job['id'] );
        $units = Garmin_History_Jobs::units( $job['id'], $offset, $filter );
        $labels = array( 'queued' => __( 'Queued', 'chriss-swim-training-progress-evaluation' ), 'running' => __( 'Running', 'chriss-swim-training-progress-evaluation' ), 'waiting' => __( 'Waiting before retry', 'chriss-swim-training-progress-evaluation' ), 'paused' => __( 'Paused', 'chriss-swim-training-progress-evaluation' ), 'blocked' => __( 'Blocked — check connection or permissions, then resume', 'chriss-swim-training-progress-evaluation' ), 'completed' => __( 'Completed', 'chriss-swim-training-progress-evaluation' ), 'completed_with_errors' => __( 'Completed with errors — scan/import is incomplete', 'chriss-swim-training-progress-evaluation' ), 'cancelled' => __( 'Cancelled — imported records retained', 'chriss-swim-training-progress-evaluation' ) );
        ?>
        <div id="swimlog-history-status" data-job="<?php echo esc_attr( $job['id'] ); ?>" data-offset="<?php echo esc_attr( $offset ); ?>" data-filter="<?php echo esc_attr( $filter ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
            <h3><?php echo esc_html( $job['start'] . ' — ' . $job['end'] ); ?></h3>
            <p role="status"><strong><?php echo esc_html( $labels[$job['status']] ?? $job['status'] ); ?></strong></p>
            <?php if ( $job['error'] ) : ?><p role="alert"><?php echo esc_html( self::failure_message( $job['error'] ) ); ?></p><?php endif; ?>
            <p><?php echo esc_html( sprintf( /* translators: 1: queued units, 2: failed units, 3: discovered swim activities, 4: fully scanned activity days, 5: attempted measurement days. */ __( 'Unfinished checks/imports: %1$d; failed checks/imports: %2$d; discovered swims: %3$d; fully scanned activity days: %4$d; attempted measurement days: %5$d.', 'chriss-swim-training-progress-evaluation' ), count( $job['ready'] ), count( $job['failed'] ), $job['counts']['discovered'], $job['counts']['activity_days'], $job['counts']['measurement_days'] ) ); ?></p>
            <dl class="swimlog-history-counts">
            <?php foreach ( array( 'activity_imported' => __( 'Swims imported', 'chriss-swim-training-progress-evaluation' ), 'activity_already' => __( 'Swims already present', 'chriss-swim-training-progress-evaluation' ), 'activity_review' => __( 'Swims needing review', 'chriss-swim-training-progress-evaluation' ), 'added' => __( 'Readings added', 'chriss-swim-training-progress-evaluation' ), 'updated' => __( 'Readings updated', 'chriss-swim-training-progress-evaluation' ), 'skipped' => __( 'Duplicate readings skipped', 'chriss-swim-training-progress-evaluation' ), 'reports_added' => __( 'Daily category records added', 'chriss-swim-training-progress-evaluation' ), 'reports_updated' => __( 'Daily category records updated', 'chriss-swim-training-progress-evaluation' ), 'reports_skipped' => __( 'Unchanged daily category records', 'chriss-swim-training-progress-evaluation' ), 'measurement_no_data' => __( 'Empty date/category responses', 'chriss-swim-training-progress-evaluation' ), 'excluded' => __( 'Unsupported fields/rows excluded', 'chriss-swim-training-progress-evaluation' ) ) as $key => $label ) : ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( (string) ( $job['counts'][$key] ?? 0 ) ); ?></dd></div><?php endforeach; ?>
            </dl>
            <p><?php esc_html_e( 'Daily category records include import bookkeeping for weight/BMI as well as SpO2 summaries. Activity totals are discovered progressively. Counts describe distinct work items; network retries are not additional imports. After an interrupted commit, recovered records may appear as already present. Empty means Garmin returned no data, not proof that no historical record ever existed.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <?php if ( $next ) : ?><p><?php echo esc_html( sprintf( /* translators: %s: UTC date/time. */ __( 'Next background task (UTC): %s. WordPress cron depends on site traffic.', 'chriss-swim-training-progress-evaluation' ), gmdate( 'Y-m-d H:i:s', $next ) ) ); ?></p><?php elseif ( $active ) : ?><p role="alert"><?php esc_html_e( 'No background task is scheduled. Use Resume / repair schedule.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
            <div class="swimlog-history-controls">
            <?php
            if ( $active ) self::button( $job, 'pause', __( 'Pause', 'chriss-swim-training-progress-evaluation' ) );
            if ( in_array( $job['status'], array( 'paused', 'blocked' ), true ) || ( $active && ! $next ) ) self::button( $job, 'resume', __( 'Resume / repair schedule', 'chriss-swim-training-progress-evaluation' ) );
            if ( ! in_array( $job['status'], array( 'completed', 'completed_with_errors', 'cancelled' ), true ) ) self::button( $job, 'cancel', __( 'Cancel — keep imported records', 'chriss-swim-training-progress-evaluation' ) );
            if ( 'completed_with_errors' === $job['status'] ) self::button( $job, 'retry', __( 'Retry failed items', 'chriss-swim-training-progress-evaluation' ) );
            ?>
            </div>
            <p><?php esc_html_e( 'Pause/cancel stops new work at a safe boundary; a request already in progress may finish. Review items require explicit review and are not automatically approved by Retry failed.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <p class="swimlog-history-refresh-note"><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'history_job' => $job['id'], 'history_filter' => $filter, 'history_offset' => $offset ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Refresh job status', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
            <nav aria-label="<?php esc_attr_e( 'Historical job result filters', 'chriss-swim-training-progress-evaluation' ); ?>">
                <?php foreach ( array( 'all' => __( 'All results', 'chriss-swim-training-progress-evaluation' ), 'failed' => __( 'Failed items', 'chriss-swim-training-progress-evaluation' ), 'review' => __( 'Needs review', 'chriss-swim-training-progress-evaluation' ) ) as $key => $label ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'history_job' => $job['id'], 'history_filter' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a> <?php endforeach; ?>
            </nav>
            <div class="swimlog-history-results" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Historical import results', 'chriss-swim-training-progress-evaluation' ); ?>">
            <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Work item', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Outcome', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Details / review', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
            <?php foreach ( $units['items'] ?? array() as $unit ) :
                $date = $unit['data']['date'] ?? ( $unit['data']['cursor']['day'] ?? '' );
                ?>
                <tr><td><?php echo esc_html( $date . ' · ' . self::label( $unit['kind'] ) . ( isset( $unit['data']['activity_id'] ) ? ' #' . $unit['data']['activity_id'] : '' ) ); ?></td><td><?php echo esc_html( self::label( $unit['status'] ) ); ?></td><td>
                <?php if ( $unit['error'] ) : ?><p><?php echo esc_html( self::failure_message( $unit['error'] ) ); ?></p><details><summary><?php esc_html_e( 'Technical details', 'chriss-swim-training-progress-evaluation' ); ?></summary><?php echo esc_html( $unit['error'] ); ?></details><?php endif; ?>
                <?php foreach ( $unit['outcomes'] as $category => $row ) : ?><p><?php echo esc_html( self::label( $category ) . ': ' . self::label( $row['status'] ) ); ?></p><?php endforeach; ?>
                <?php if ( 'review' === $unit['status'] && 'activity' === $unit['kind'] ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'garmin_activity_id' => $unit['data']['activity_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Review Garmin activity / assign location', 'chriss-swim-training-progress-evaluation' ); ?></a>
                    <?php if ( ! empty( $unit['outcomes']['activity']['import_id'] ) ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review pending import', 'chriss-swim-training-progress-evaluation' ); ?></a></p><?php endif; ?>
                <?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <nav aria-label="<?php esc_attr_e( 'Historical result pages', 'chriss-swim-training-progress-evaluation' ); ?>">
            <?php foreach ( array( max( 0, $offset - 20 ) => __( 'Previous results', 'chriss-swim-training-progress-evaluation' ), $offset + 20 => __( 'Next results', 'chriss-swim-training-progress-evaluation' ) ) as $target => $label ) : if ( $target === $offset || $target >= ( $units['total'] ?? 0 ) ) continue; ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'history_job' => $job['id'], 'history_filter' => $filter, 'history_offset' => $target ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }
}
