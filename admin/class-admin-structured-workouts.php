<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Admin_Structured_Workouts {
    public static function handle_actions() {
        if ( isset( $_POST['swimlog_structure_lifecycle'] ) ) {
            if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
            check_admin_referer( 'swimlog_structure_lifecycle' );
            $id = isset( $_POST['structure_id'] ) ? absint( wp_unslash( $_POST['structure_id'] ) ) : 0;
            $action = isset( $_POST['swimlog_structure_lifecycle'] ) && is_string( $_POST['swimlog_structure_lifecycle'] ) ? sanitize_key( wp_unslash( $_POST['swimlog_structure_lifecycle'] ) ) : '';
            $result = Structured_Workout_Store::lifecycle( $id, get_current_user_id(), $action );
            if ( is_wp_error( $result ) || ! $result ) { self::error( is_wp_error( $result ) ? $result->get_error_message() : 'Unable to update workout.' ); return; }
            wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'lifecycle_updated' => 1 ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( isset( $_POST['swimlog_plan_schedule'] ) ) {
            if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
            check_admin_referer( 'swimlog_plan_schedule' );
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-scheduler.php';
            $plan_id = isset( $_POST['swimlog_plan_id'] ) ? absint( wp_unslash( $_POST['swimlog_plan_id'] ) ) : 0;
            $dates = isset( $_POST['swimlog_schedule_dates'] ) && is_array( $_POST['swimlog_schedule_dates'] ) ? map_deep( wp_unslash( $_POST['swimlog_schedule_dates'] ), 'sanitize_text_field' ) : array();
            $assignments = array();
            foreach ( $dates as $id => $date ) {
                if ( ! is_string( $date ) ) { self::error( 'Invalid date input.' ); return; }
                $date = sanitize_text_field( $date );
                if ( '' !== $date ) $assignments[absint( $id )] = $date;
            }
            $result = Training_Plan_Scheduler::schedule( get_current_user_id(), $plan_id, $assignments );
            if ( is_wp_error( $result ) ) { self::error( $result->get_error_message() ); return; }
            wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'schedule_plan' => $plan_id, 'scheduled' => count( $result ) ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( isset( $_POST['swimlog_pdf_bulk_import'] ) ) {
            if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
            check_admin_referer( 'swimlog_pdf_bulk_import' );
            $owner = get_current_user_id();
            $token = isset( $_POST['swimlog_pdf_token'] ) && is_string( $_POST['swimlog_pdf_token'] ) ? sanitize_text_field( wp_unslash( $_POST['swimlog_pdf_token'] ) ) : '';
            if ( ! preg_match( '/^[a-zA-Z0-9]{32}$/', $token ) ) { self::error( 'Invalid preview token.' ); return; }
            $preview = get_transient( 'swimlog_pdf_' . $owner . '_' . $token );
            if ( ! is_array( $preview ) || empty( $preview['text'] ) ) { self::error( 'PDF preview expired. Upload the PDF again.' ); return; }
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-parser.php';
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-review.php';
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-bulk-import.php';
            $parsed = Training_Plan_Parser::parse( $preview['text'] );
            if ( is_wp_error( $parsed ) ) { self::error( $parsed->get_error_message() ); return; }
            $name = isset( $_POST['swimlog_pdf_plan_name'] ) && is_string( $_POST['swimlog_pdf_plan_name'] ) ? sanitize_text_field( wp_unslash( $_POST['swimlog_pdf_plan_name'] ) ) : '';
            $selected = isset( $_POST['swimlog_pdf_selected'] ) && is_array( $_POST['swimlog_pdf_selected'] ) ? array_map( 'absint', wp_unslash( $_POST['swimlog_pdf_selected'] ) ) : array();
            // Textarea sanitization retains JSON syntax while removing unsafe markup from scalar input.
            $reviews = isset( $_POST['swimlog_pdf_reviews'] ) && is_array( $_POST['swimlog_pdf_reviews'] ) ? map_deep( wp_unslash( $_POST['swimlog_pdf_reviews'] ), 'sanitize_textarea_field' ) : array();
            $GLOBALS['swimlog_pdf_review_input'] = array( 'reviews' => $reviews, 'selected' => $selected, 'name' => $name );
            $result = Training_Plan_Bulk_Import::run( $owner, $name, $parsed, $selected, hash( 'sha256', $preview['text'] ), $reviews );
            if ( is_wp_error( $result ) ) { self::error( $result->get_error_message() ); return; }
            delete_transient( 'swimlog_pdf_' . $owner . '_' . $token );
            wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'pdf_imported' => $result['count'], 'schedule_plan' => $result['plan_id'] ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( isset( $_POST['swimlog_pdf_extract'] ) ) {
            if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
            check_admin_referer( 'swimlog_pdf_extract' );
            if ( empty( $_POST['swimlog_pdf_acknowledge'] ) ) {
                self::error( 'Please acknowledge the intellectual-property notice before importing.' );
                return;
            }
            require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-pdf.php';
            // PHP's uploaded temporary filename is not user-authored text. Keep it intact for is_uploaded_file()/move_uploaded_file() validation in the PDF extractor.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload array must retain tmp_name and size for PHP upload validation.
            $file = isset( $_FILES['swimlog_training_pdf'] ) && is_array( $_FILES['swimlog_training_pdf'] ) ? $_FILES['swimlog_training_pdf'] : array();
            $result = Training_Plan_PDF::extract( $file );
            if ( is_wp_error( $result ) ) { self::error( $result->get_error_message() ); return; }
            $owner = get_current_user_id();
            $token = wp_generate_password( 32, false, false );
            set_transient( 'swimlog_pdf_' . $owner . '_' . $token, $result, 15 * MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'pdf_preview' => $token ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
        if ( ! isset( $_POST['swimlog_structure_action'] ) || 'save' !== sanitize_key( wp_unslash( $_POST['swimlog_structure_action'] ) ) ) return;
        check_admin_referer( 'swimlog_structure_save' );
        $id = isset( $_POST['structure_id'] ) ? absint( wp_unslash( $_POST['structure_id'] ) ) : 0;
        $title = isset( $_POST['structure_title'] ) && is_string( $_POST['structure_title'] ) ? sanitize_text_field( wp_unslash( $_POST['structure_title'] ) ) : '';
        $raw = isset( $_POST['structure_document'] ) && is_string( $_POST['structure_document'] ) ? sanitize_textarea_field( wp_unslash( $_POST['structure_document'] ) ) : '';
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
        require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-structured-transfers.php';
        wp_enqueue_script( 'swimlog-structured-transfers', SWIMLOG_EVALUATION_URL . 'assets/js/structured-workout-transfer.js', array(), SWIMLOG_EVALUATION_VERSION, true );
        wp_enqueue_style( 'swimlog-structured-transfers', SWIMLOG_EVALUATION_URL . 'assets/css/structured-workout-transfer.css', array(), SWIMLOG_EVALUATION_VERSION );
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
          <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
          <?php Admin_Structured_Transfers::preview_panel(); ?>
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
              if ( is_array( $pdf_preview ) && isset( $pdf_preview['text'] ) ) :
                  require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-parser.php';
                  require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-plan-review.php';
                  $candidate_plan = Training_Plan_Parser::parse( $pdf_preview['text'] );
                  $review_input = $GLOBALS['swimlog_pdf_review_input'] ?? array( 'reviews' => array(), 'selected' => array(), 'name' => '' );
                  ?>

                <h2><?php esc_html_e( 'Extracted text preview (private, expires after 15 minutes)', 'chriss-swim-training-progress-evaluation' ); ?></h2>
                <p><?php echo esc_html( number_format_i18n( $pdf_preview['bytes'] ) ); ?> <?php esc_html_e( 'text bytes extracted. No workouts have been created.', 'chriss-swim-training-progress-evaluation' ); ?></p>
                <textarea readonly rows="16" style="width:100%;max-width:1100px"><?php echo esc_textarea( $pdf_preview['text'] ); ?></textarea>
                <h3><?php esc_html_e( 'Review and select workouts for import', 'chriss-swim-training-progress-evaluation' ); ?></h3>
                <?php if ( is_wp_error( $candidate_plan ) ) : ?>
                  <p><?php echo esc_html( $candidate_plan->get_error_message() ); ?></p>
                <?php else : ?>
                  <p><?php echo esc_html( sprintf( '%d candidate workout boundaries found. No workouts have been saved.', count( $candidate_plan['workouts'] ) ) ); ?></p>
                  <?php foreach ( $candidate_plan['flags'] as $flag ) : ?><p><?php echo esc_html( $flag ); ?></p><?php endforeach; ?>
                  <form method="post" data-swimlog-pdf-import>
                    <?php wp_nonce_field( 'swimlog_pdf_bulk_import' ); ?>
                    <input type="hidden" name="swimlog_pdf_bulk_import" value="1">
                    <input type="hidden" name="swimlog_pdf_token" value="<?php echo esc_attr( $pdf_token ); ?>">
                    <p><label for="swimlog-pdf-plan-name">Private plan name</label>
                    <input type="text" id="swimlog-pdf-plan-name" name="swimlog_pdf_plan_name" maxlength="120" value="<?php echo esc_attr( $review_input['name'] ); ?>" required></p>
                    <p>For each workout you want to import: open its review, compare the original instructions with the proposed structure, correct the structure if needed, enter its confirmed total and unit, and check Review complete. Then select it for import. Only selected, explicitly reviewed workouts are saved.</p>
                  <?php foreach ( $candidate_plan['workouts'] as $candidate ) : $review = Training_Plan_Review::assess( $candidate ); $candidate_input = $review_input['reviews'][$candidate['sequence']] ?? array(); $candidate_input = is_array( $candidate_input ) ? array_filter( $candidate_input, 'is_string' ) : array(); $candidate_selected = in_array( $candidate['sequence'], $review_input['selected'], true ); ?>
                    <div class="swimlog-pdf-candidate">
                    <p><label><input type="checkbox" name="swimlog_pdf_selected[]" value="<?php echo esc_attr( $candidate['sequence'] ); ?>" <?php checked( $candidate_selected ); ?>> <?php echo esc_html( 'Select workout ' . $candidate['source_number'] . ' for import' ); ?></label></p>
                    <details <?php if ( $candidate_selected ) echo 'open'; ?>><summary><?php echo esc_html( $candidate['heading'] . ' — week ' . $candidate['week'] . ' — ' . ( null === $candidate['calculated_distance'] ? 'distance unknown' : $candidate['calculated_distance'] . ' ' . $candidate['unit'] ) . ' — ' . count( $review['flags'] ) . ' review flags' ); ?></summary>
                      <p><?php echo esc_html( 'Distance verification: ' . $review['status'] ); ?></p>
                      <p><?php echo esc_html( 'Stated total: ' . ( $review['stated'] ? $review['stated']['distance'] . ' ' . $review['stated']['unit'] : 'not found' ) ); ?></p>
                      <p><?php echo esc_html( 'Calculated total: ' . ( null !== $review['calculated'] ? $review['calculated'] . ' ' . $review['unit'] : 'unknown' ) ); ?></p>
                      <?php if ( null !== $review['difference'] ) : ?><p><?php echo esc_html( 'Difference: ' . $review['difference'] . ' ' . $review['unit'] ); ?></p><?php endif; ?>
                      <?php foreach ( $review['flags'] as $flag ) : ?><p><?php echo esc_html( $flag ); ?></p><?php endforeach; ?>
                      <h4>Original instructions</h4><pre style="white-space:pre-wrap"><?php echo esc_html( implode( "\n", $candidate['source_lines'] ) ); ?></pre>
                      <p>The verification figures above describe the original extraction. The live workout preview below shows your edited total.</p><h4>Proposed structure — review and edit</h4><noscript><p>Enable JavaScript to review and edit workout structures before importing.</p></noscript>
                      <p>Review every instruction, stroke, repetition, rest interval, drill and equipment item. The parser may have omitted or misread instructions flagged above; use the editor to correct them before approval. Totals alone do not prove the structure is correct.</p>
                      <?php $review_document = isset( $candidate_input['document'] ) && is_string( $candidate_input['document'] ) ? json_decode( $candidate_input['document'], true ) : $candidate['document']; if ( ! is_array( $review_document ) ) $review_document = $default; $review_input_id = 'swimlog-pdf-document-' . $candidate['sequence']; ?>
                      <?php if ( ! $candidate['document'] ) : ?><p><strong>No reliable structure was extracted. The editor contains a starter workout: replace it completely with the correct prescription before approving.</strong></p><?php endif; ?>
                      <div data-swimlog-pdf-editor data-input="<?php echo esc_attr( $review_input_id ); ?>" data-document="<?php echo esc_attr( wp_json_encode( $review_document ) ); ?>"></div>
                      <input type="hidden" id="<?php echo esc_attr( $review_input_id ); ?>" name="swimlog_pdf_reviews[<?php echo esc_attr( $candidate['sequence'] ); ?>][document]" value="<?php echo esc_attr( wp_json_encode( $review_document ) ); ?>">
                      <p><label>Confirmed total distance <input type="number" min="0.001" step="0.001" name="swimlog_pdf_reviews[<?php echo esc_attr( $candidate['sequence'] ); ?>][total]" value="<?php echo esc_attr( is_string( $candidate_input['total'] ?? null ) ? $candidate_input['total'] : '' ); ?>"></label>
                      <label>Confirmed unit <select name="swimlog_pdf_reviews[<?php echo esc_attr( $candidate['sequence'] ); ?>][unit]"><option value="">Choose unit</option><option value="m" <?php selected( $candidate_input['unit'] ?? '', 'm' ); ?>>Meters</option><option value="yd" <?php selected( $candidate_input['unit'] ?? '', 'yd' ); ?>>Yards</option></select></label></p>
                      <p><label><input type="checkbox" name="swimlog_pdf_reviews[<?php echo esc_attr( $candidate['sequence'] ); ?>][reviewed]" value="1" <?php checked( $candidate_input['reviewed'] ?? '', '1' ); ?>> Review complete: I compared the original instructions with this structure, corrected omissions and review flags, and confirm the distance and unit above. If the source total was missing or wrong, this is my corrected total.</label></p>
                      <p>Requires manual review before approval or import. Selection alone does not approve a workout. Original instructions and review evidence are retained privately with each imported workout.</p>
                    </details></div>
                  <?php endforeach; ?>
                    <p><button class="button button-primary" type="submit">Import selected workouts</button></p>
                  </form>
                <?php endif; ?>
              <?php endif;
          endif; ?>
          <?php
          // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only owner-scoped plan selector.
          $schedule_plan_id = isset( $_GET['schedule_plan'] ) ? absint( wp_unslash( $_GET['schedule_plan'] ) ) : 0;
          if ( $schedule_plan_id ) :
              $schedule_plan = Training_Plan_Collection::get( $schedule_plan_id, $owner );
              if ( ! is_wp_error( $schedule_plan ) ) :
                  $schedule_workouts = Training_Plan_Collection::workouts( $owner, $schedule_plan_id );
                  if ( ! is_wp_error( $schedule_workouts ) ) : ?>
                    <h2><?php echo esc_html( 'Schedule workouts from ' . $schedule_plan->post_title ); ?></h2>
                    <p>Enter any current or future date for each workout you wish to schedule. Leave dates blank to skip workouts. Dates do not need to be consecutive.</p>
                    <form method="post">
                      <?php wp_nonce_field( 'swimlog_plan_schedule' ); ?>
                      <input type="hidden" name="swimlog_plan_schedule" value="1">
                      <input type="hidden" name="swimlog_plan_id" value="<?php echo esc_attr( $schedule_plan_id ); ?>">
                      <?php foreach ( $schedule_workouts as $scheduled_structure ) :
                          $linked_plan_id = absint( get_post_meta( $scheduled_structure->ID, Planned_Structure_Link::META_KEY, true ) ); ?>
                          <p><label><?php echo esc_html( $scheduled_structure->post_title ); ?>
                          <?php if ( $linked_plan_id ) : ?> — already scheduled
                          <?php else : ?><input type="date" name="swimlog_schedule_dates[<?php echo esc_attr( $scheduled_structure->ID ); ?>]" min="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>">
                          <?php endif; ?></label></p>
                      <?php endforeach; ?>
                      <p><button type="submit" class="button button-primary">Schedule selected dates</button></p>
                    </form>
                  <?php endif;
              endif;
          endif; ?>
          <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scheduling result notice. ?>
          <?php if ( isset( $_GET['scheduled'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( absint( wp_unslash( $_GET['scheduled'] ) ) . ' workouts scheduled.' ); ?></p></div><?php endif; ?>
          <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only saved flag. ?>
          <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Structured workout saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
          <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result notice. ?>
          <?php if ( isset( $_GET['pdf_imported'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( absint( wp_unslash( $_GET['pdf_imported'] ) ) . ' reviewed workouts imported into a private training plan.' ); ?></p></div><?php endif; ?>
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
          <?php if ( $current ) Admin_Structured_Transfers::controls( $current['id'], true ); ?>
          <h2><?php esc_html_e( 'Saved workouts', 'chriss-swim-training-progress-evaluation' ); ?></h2>
          <?php
          // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
          $visibility = isset( $_GET['visibility'] ) ? sanitize_key( wp_unslash( $_GET['visibility'] ) ) : 'active';
          if ( ! in_array( $visibility, array( 'active', 'hidden', 'all' ), true ) ) $visibility = 'active';
          $saved_workouts = Structured_Workout_Store::all( $owner, $visibility );
          ?>
          <p>Show: <?php foreach ( array( 'active' => 'Active', 'hidden' => 'Hidden', 'all' => 'All' ) as $key => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'visibility' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a> &nbsp;
          <?php endforeach; ?></p>
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
                  $item_hidden = Structured_Workout_Store::is_hidden( $item->ID, $owner );
                  $item_history = Structured_Workout_Store::has_history( $item->ID, $owner );
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
                    <td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php echo $item_locked ? esc_html__( 'View locked', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Edit', 'chriss-swim-training-progress-evaluation' ); ?></a>
                    <?php Admin_Structured_Transfers::controls( $item->ID ); ?>
                    <form method="post" style="display:inline">
                      <?php wp_nonce_field( 'swimlog_structure_lifecycle' ); ?>
                      <input type="hidden" name="structure_id" value="<?php echo esc_attr( $item->ID ); ?>">
                      <button type="submit" class="button button-small" name="swimlog_structure_lifecycle" value="<?php echo esc_attr( $item_hidden ? 'unhide' : 'hide' ); ?>"><?php echo esc_html( $item_hidden ? 'Unhide' : 'Hide' ); ?></button>
                      <?php if ( ! $item_history ) : ?><button type="submit" class="button button-small" name="swimlog_structure_lifecycle" value="delete" onclick="return confirm('Permanently delete this unused workout?');">Delete</button><?php endif; ?>
                    </form></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <?php
    }
}

