<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Admin_Structured_Workouts {
    public static function handle_actions() {
        if ( isset( $_POST['swimlog_pdf_extract'] ) ) {
            if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
            check_admin_referer( 'swimlog_pdf_extract' );
            if ( empty( $_POST['swimlog_pdf_acknowledge'] ) ) {
                self::error( 'Please acknowledge the intellectual-property notice before importing.' );
                return;
            }
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-pdf.php';
            $file = isset( $_FILES['swimlog_training_pdf'] ) ? $_FILES['swimlog_training_pdf'] : array();
            $result = Training_Plan_PDF::extract( $file );
            if ( is_wp_error( $result ) ) { self::error( $result->get_error_message() ); return; }
            $owner = get_current_user_id();
            $token = wp_generate_password( 32, false, false );
            set_transient( 'swimlog_pdf_' . $owner . '_' . $token, $result, 15 * MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'pdf_preview' => $token ), admin_url( 'admin.php' ) );
            exit;
        }
        if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
        if ( ! isset( $_POST['swimlog_structure_action'] ) || 'save' !== sanitize_key( wp_unslash( $_POST['swimlog_structure_action'] ) ) ) return;
        check_admin_referer( 'swimlog_structure_save' );
        $id = isset( $_POST['structure_id'] ) ? absint( wp_unslash( $_POST['structure_id'] ) ) : 0;
        $title = isset( $_POST['structure_title'] ) && is_string( $_POST['structure_title'] ) ? sanitize_text_field( wp_unslash( $_POST['structure_title'] ) ) : '';
        $raw = isset( $_POST['structure_document'] ) && is_string( $_POST['structure_document'] ) ? wp_unslash( $_POST['structure_document'] ) : '';
        if ( strlen( $raw ) > 200000 ) {
            self::error( 'Workout definition is too large.' ); return;
        }
        $document = json_decode( $raw, true );
        $saved = Structured_Workout_Store::save( get_current_user_id(), $title, $document, $id );
        if ( is_wp_error( $saved ) ) { self::error( $saved->get_error_message() ); return; }
        wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'edit' => (int) $saved, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
    private static function error( $message ) { $GLOBALS['swimlog_structure_error'] = $message; }

    public static function page() {
        if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Permission denied.', 'chriss-swim-training-progress-evaluation' ) );
        $owner = get_current_user_id();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only owner-scoped edit selector.
        $id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $current = $id ? Structured_Workout_Store::get( $id, $owner ) : null;
        $error = isset( $GLOBALS['swimlog_structure_error'] ) ? $GLOBALS['swimlog_structure_error'] : '';
        if ( is_wp_error( $current ) ) { $error = $current->get_error_message(); $current = null; }
        $default = array( 'version' => 1, 'unit' => 'm', 'sections' => array( array( 'type' => 'section', 'label' => 'Main Set', 'children' => array( array( 'type' => 'step', 'distance' => 100, 'stroke' => 'FR' ) ) ) ) );
        $document = $current ? $current['document'] : $default;
        $locked = $current ? Structured_Workout_Store::is_locked( $current['id'], $owner ) : false;
        wp_enqueue_style( 'swimlog-structured-editor-layout', SWIMLOG_EVALUATION_URL . 'assets/css/structured-workout-editor.css', array(), SWIMLOG_EVALUATION_VERSION );
        wp_enqueue_script( 'swimlog-structured-editor', SWIMLOG_EVALUATION_URL . 'assets/js/structured-workout-editor.js', array(), SWIMLOG_EVALUATION_VERSION, true );
        ?>
        <div class="wrap swimlog-admin">
          <h1><?php esc_html_e( 'Structured Workouts', 'chriss-swim-training-progress-evaluation' ); ?></h1>
          <p><?php esc_html_e( 'Create reusable structured prescriptions. These are private and do not alter existing planned or recorded workouts.', 'chriss-swim-training-progress-evaluation' ); ?></p>
          <details><summary><strong><?php esc_html_e( 'Import a training plan PDF', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary>
            <p><?php esc_html_e( 'Extract selectable PDF text for private review. No workouts are created in this phase.', 'chriss-swim-training-progress-evaluation' ); ?></p>
            <form method="post" enctype="multipart/form-data">
              <?php wp_nonce_field( 'swimlog_pdf_extract' ); ?>
              <input type="hidden" name="swimlog_pdf_extract" value="1">
              <p><label for="swimlog-training-pdf"><?php esc_html_e( 'Training plan PDF (maximum 10 MB)', 'chriss-swim-training-progress-evaluation' ); ?></label>
              <input type="file" id="swimlog-training-pdf" name="swimlog_training_pdf" accept=".pdf,application/pdf" required></p>
              <p><?php esc_html_e( 'Training plans may be intellectual property of their respective owners. You are responsible for obtaining necessary rights or permissions. This plugin stores private data, does not determine copyright status, and does not automatically share imported content.', 'chriss-swim-training-progress-evaluation' ); ?></p>
              <p><label><input type="checkbox" name="swimlog_pdf_acknowledge" value="1" required> <?php esc_html_e( 'I acknowledge this notice.', 'chriss-swim-training-progress-evaluation' ); ?></label></p>
              <p><button class="button" type="submit"><?php esc_html_e( 'Extract PDF text for review', 'chriss-swim-training-progress-evaluation' ); ?></button></p>
            </form>
          </details>
          <?php
          // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only opaque preview token scoped to current user.
          $pdf_token = isset( $_GET['pdf_preview'] ) && is_string( $_GET['pdf_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['pdf_preview'] ) ) : '';
          if ( preg_match( '/^[a-zA-Z0-9]{32}$/', $pdf_token ) ) :
              $pdf_preview = get_transient( 'swimlog_pdf_' . $owner . '_' . $pdf_token );
              if ( is_array( $pdf_preview ) && isset( $pdf_preview['text'] ) ) : ?>
                <h2><?php esc_html_e( 'Extracted text preview (private, expires after 15 minutes)', 'chriss-swim-training-progress-evaluation' ); ?></h2>
                <p><?php echo esc_html( number_format_i18n( $pdf_preview['bytes'] ) ); ?> <?php esc_html_e( 'text bytes extracted. No workouts have been created.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <textarea readonly rows="16" style="width:100%;max-width:1100px"><?php echo esc_textarea( $pdf_preview['text'] ); ?></textarea>
              <?php endif;
          endif; ?>
          <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
          <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only saved flag. ?>
          <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Structured workout saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
          <?php if ( $locked ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'This prescription is locked because it is associated with a recorded swim. Duplicate it to create an editable copy.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
          <?php if ( ! $locked ) : ?><form method="post" id="swimlog-structure-form">
            <?php wp_nonce_field( 'swimlog_structure_save' ); ?>
            <input type="hidden" name="swimlog_structure_action" value="save">
            <input type="hidden" name="structure_id" value="<?php echo esc_attr( $current ? $current['id'] : 0 ); ?>">
            <p><label for="swimlog-structure-title"><strong><?php esc_html_e( 'Workout title', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
              <input id="swimlog-structure-title" class="regular-text" name="structure_title" maxlength="191" required value="<?php echo esc_attr( $current ? $current['title'] : '' ); ?>"></p>
            <div id="swimlog-structure-editor" data-document="<?php echo esc_attr( wp_json_encode( $document ) ); ?>"></div>
            <input type="hidden" id="swimlog-structure-document" name="structure_document">
            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save structured workout', 'chriss-swim-training-progress-evaluation' ); ?></button></p>
          </form><?php else : ?><pre><?php echo esc_html( wp_json_encode( $document, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?>
          <h2><?php esc_html_e( 'Saved workouts', 'chriss-swim-training-progress-evaluation' ); ?></h2>
          <?php $saved_workouts = Structured_Workout_Store::all( $owner ); ?>
          <?php if ( empty( $saved_workouts ) ) : ?>
            <p><?php esc_html_e( 'No structured workouts have been saved yet.', 'chriss-swim-training-progress-evaluation' ); ?></p>
          <?php else : ?>
            <table class="widefat striped">
              <thead><tr>
                <th scope="col"><?php esc_html_e( 'ID', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Workout', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Sections', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Steps', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Strokes', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Last Modified', 'chriss-swim-training-progress-evaluation' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Actions', 'chriss-swim-training-progress-evaluation' ); ?></th>
              </tr></thead>
              <tbody>
                <?php foreach ( $saved_workouts as $item ) : ?>
                  <?php
                  $summary = Structured_Workout::summarize( json_decode( $item->post_content, true ) );
                  $valid = ! is_wp_error( $summary );
                  $edit_url = add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'edit' => $item->ID ), admin_url( 'admin.php' ) );
                  $item_locked = Structured_Workout_Store::is_locked( $item->ID, $owner );
                  $stroke_parts = array();
                  if ( $valid ) {
                      foreach ( $summary['totals']['by_stroke'] as $stroke => $distance ) {
                          if ( $distance > 0 ) {
                              $stroke_parts[] = $stroke . ': ' . number_format_i18n( $distance, 0 );
                          }
                      }
                  }
                  ?>
                  <tr>
                    <td><?php echo esc_html( (string) $item->ID ); ?></td>
                    <td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $item->post_title ); ?></a></strong></td>
                    <td><?php echo $valid ? esc_html( number_format_i18n( $summary['totals']['distance'], 0 ) . ' ' . $summary['unit'] ) : esc_html__( 'Invalid definition', 'chriss-swim-training-progress-evaluation' ); ?></td>
                    <td><?php echo $valid ? esc_html( (string) count( $summary['document']['sections'] ) ) : '&mdash;'; ?></td>
                    <td><?php echo $valid ? esc_html( (string) $summary['totals']['expanded_steps'] ) : '&mdash;'; ?></td>
                    <td><?php echo $valid ? esc_html( implode( ', ', $stroke_parts ) ) : '&mdash;'; ?></td>
                    <td><?php echo esc_html( get_date_from_gmt( $item->post_modified_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
                    <td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php echo $item_locked ? esc_html__( 'View locked', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Edit', 'chriss-swim-training-progress-evaluation' ); ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <?php
    }
}
