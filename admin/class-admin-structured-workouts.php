<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Admin_Structured_Workouts {
    public static function handle_actions() {
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
        wp_enqueue_script( 'swimlog-structured-editor', SWIMLOG_EVALUATION_URL . 'assets/js/structured-workout-editor.js', array(), SWIMLOG_EVALUATION_VERSION, true );
        ?>
        <div class="wrap swimlog-admin">
          <h1><?php esc_html_e( 'Structured Workouts', 'chriss-swim-training-progress-evaluation' ); ?></h1>
          <p><?php esc_html_e( 'Create reusable structured prescriptions. These are private and do not alter existing planned or recorded workouts.', 'chriss-swim-training-progress-evaluation' ); ?></p>
          <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
          <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only saved flag. ?>
          <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Structured workout saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
          <form method="post" id="swimlog-structure-form">
            <?php wp_nonce_field( 'swimlog_structure_save' ); ?>
            <input type="hidden" name="swimlog_structure_action" value="save">
            <input type="hidden" name="structure_id" value="<?php echo esc_attr( $current ? $current['id'] : 0 ); ?>">
            <p><label for="swimlog-structure-title"><strong><?php esc_html_e( 'Workout title', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
              <input id="swimlog-structure-title" class="regular-text" name="structure_title" maxlength="191" required value="<?php echo esc_attr( $current ? $current['title'] : '' ); ?>"></p>
            <div id="swimlog-structure-editor" data-document="<?php echo esc_attr( wp_json_encode( $document ) ); ?>"></div>
            <input type="hidden" id="swimlog-structure-document" name="structure_document">
            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save structured workout', 'chriss-swim-training-progress-evaluation' ); ?></button></p>
          </form>
          <h2><?php esc_html_e( 'Saved workouts', 'chriss-swim-training-progress-evaluation' ); ?></h2>
          <ul>
            <?php foreach ( Structured_Workout_Store::all( $owner ) as $item ) : ?>
              <li><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'edit' => $item->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $item->post_title ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php
    }
}
