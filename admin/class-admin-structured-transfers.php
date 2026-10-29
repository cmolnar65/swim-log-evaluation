<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owner-only, nonce-protected conversion and prescription download endpoints. */
final class Admin_Structured_Transfers {
    public static function load() {
        foreach ( array( 'structured-workout-export', 'structured-workout-fit', 'recorded-workout-structure' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'includes/class-' . $file . '.php';
    }
    public static function download_data( $id, $format ) {
        self::load();
        if ( ! is_user_logged_in() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return new \WP_Error( 'swimlog_export_permission', 'Permission denied.' );
        if ( ! in_array( $format, array( 'fit', 'pdf', 'txt' ), true ) ) return new \WP_Error( 'swimlog_export_format', 'Unsupported export format.' );
        $item = Structured_Workout_Store::get( absint( $id ), get_current_user_id() );
        if ( is_wp_error( $item ) ) return $item;
        if ( 'fit' === $format ) $bytes = Structured_Workout_FIT::encode( $item['title'], $item['document'] );
        elseif ( 'pdf' === $format ) $bytes = Structured_Workout_Export::pdf( $item['title'], $item['document'] );
        else $bytes = Structured_Workout_Export::text( $item['title'], $item['document'] );
        if ( is_wp_error( $bytes ) ) return $bytes;
        return array( 'bytes' => $bytes, 'type' => array( 'fit' => 'application/octet-stream', 'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=UTF-8' )[$format], 'filename' => 'swim-workout-' . absint( $id ) . '.' . $format );
    }
    public static function download() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Permission denied.', 'chriss-swim-training-progress-evaluation' ) );
        $id = isset( $_GET['structure_id'] ) ? absint( wp_unslash( $_GET['structure_id'] ) ) : 0;
        $format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
        check_admin_referer( 'swimlog_structure_export_' . $id . '_' . $format );
        $result = self::download_data( $id, $format );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: ' . $result['type'] );
        header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated binary FIT/PDF or UTF-8 text attachment, never an HTML response.
        echo $result['bytes'];
        exit;
    }
    public static function convert() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Permission denied.', 'chriss-swim-training-progress-evaluation' ) );
        $id = isset( $_POST['workout_id'] ) ? absint( wp_unslash( $_POST['workout_id'] ) ) : 0;
        check_admin_referer( 'swimlog_structure_convert_' . $id );
        self::load();
        $hash = isset( $_POST['source_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['source_hash'] ) ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $date = isset( $_POST['planned_date'] ) ? sanitize_text_field( wp_unslash( $_POST['planned_date'] ) ) : '';
        $result = Recorded_Workout_Structure::confirm( $id, get_current_user_id(), $hash, $title, $date );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'edit' => $result['structure_id'], 'saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function preview_panel() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owner-scoped read-only selector is verified immediately below before reading workout data.
        $id = isset( $_GET['from_workout'] ) ? absint( wp_unslash( $_GET['from_workout'] ) ) : 0;
        if ( ! $id ) return;
        check_admin_referer( 'swimlog_structure_preview_' . $id );
        self::load(); $preview = Recorded_Workout_Structure::preview( $id, get_current_user_id() );
        if ( is_wp_error( $preview ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $preview->get_error_message() ) . '</p></div>'; return; }
        $text = Structured_Workout_Export::text( $preview['title'], $preview['document'] );
        ?>
        <section class="swimlog-conversion-preview"><h2><?php esc_html_e( 'Review recorded-swim conversion', 'chriss-swim-training-progress-evaluation' ); ?></h2>
        <?php foreach ( $preview['warnings'] as $warning ) : ?><p><?php echo esc_html( $warning ); ?></p><?php endforeach; ?>
        <pre class="swimlog-prescription-text"><?php echo esc_html( $text ); ?></pre>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'swimlog_structure_convert_' . $id ); ?>
          <input type="hidden" name="action" value="swimlog_structure_convert"><input type="hidden" name="workout_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="source_hash" value="<?php echo esc_attr( $preview['hash'] ); ?>">
          <p><label><?php esc_html_e( 'New prescription title', 'chriss-swim-training-progress-evaluation' ); ?> <input class="regular-text" required maxlength="191" name="title" value="<?php echo esc_attr( $preview['title'] ); ?>"></label></p>
          <p><label><?php esc_html_e( 'Optional planned date', 'chriss-swim-training-progress-evaluation' ); ?> <input type="date" name="planned_date" min="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></label></p>
          <p><?php esc_html_e( 'Confirmation creates a new private prescription. Leave the date blank to save without scheduling. The recorded swim and personal bests remain unchanged.', 'chriss-swim-training-progress-evaluation' ); ?></p>
          <?php submit_button( __( 'Confirm and create private prescription', 'chriss-swim-training-progress-evaluation' ) ); ?>
        </form></section>
        <?php
    }
    public static function controls( $id, $details = false ) {
        self::load();
        $item = Structured_Workout_Store::get( $id, get_current_user_id() );
        if ( is_wp_error( $item ) || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
        foreach ( array( 'fit' => 'Download FIT', 'pdf' => 'Download PDF', 'txt' => 'Download text' ) as $format => $label ) {
            $url = wp_nonce_url( add_query_arg( array( 'action' => 'swimlog_structure_download', 'structure_id' => absint( $id ), 'format' => $format ), admin_url( 'admin-post.php' ) ), 'swimlog_structure_export_' . absint( $id ) . '_' . $format );
            echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
        }
        if ( ! $details ) return;
        $compiled = Structured_Workout_FIT::compile( $item['document'] );
        echo '<p>' . esc_html__( 'FIT is a standard planned-workout file. TrainingPeaks currently documents .zwo-only planned-library imports; FIT upload there is for completed activities. Do not treat this prescription as a recorded activity.', 'chriss-swim-training-progress-evaluation' ) . '</p>';
        if ( is_wp_error( $compiled ) ) echo '<p>' . esc_html( $compiled->get_error_message() ) . '</p>';
        else foreach ( $compiled['warnings'] as $warning ) echo '<p>' . esc_html( $warning ) . '</p>';
        $text = Structured_Workout_Export::text( $item['title'], $item['document'] );
        if ( is_wp_error( $text ) ) { echo '<p>' . esc_html( $text->get_error_message() ) . '</p>'; return; }
        ?><details><summary><?php esc_html_e( 'FORM text - copy and paste', 'chriss-swim-training-progress-evaluation' ); ?></summary>
        <p><?php esc_html_e( 'Copy this prescription into FORM and review its interpretation before saving.', 'chriss-swim-training-progress-evaluation' ); ?></p>
        <textarea readonly id="swimlog-form-text-<?php echo esc_attr( $id ); ?>" class="large-text swimlog-prescription-text" rows="14"><?php echo esc_textarea( $text ); ?></textarea>
        <button type="button" class="button" data-swimlog-copy="swimlog-form-text-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copy FORM text', 'chriss-swim-training-progress-evaluation' ); ?></button>
        <span data-swimlog-copy-status aria-live="polite"></span></details><?php
    }
}
