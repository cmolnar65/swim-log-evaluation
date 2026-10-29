<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Planned-workout administration UI and actions. */
final class Admin_Planned_Workouts {
	private static $action_error = null;
	private static $regeneration_preview = null;

	public static function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-planned-workouts' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_planned_workout_action'] ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-planned-workout.php';

		$user_id = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['swimlog_planned_workout_action'] ) );
		$id = isset( $_POST['planned_workout_id'] ) ? absint( wp_unslash( $_POST['planned_workout_id'] ) ) : 0;

		if ( 'link_structure' === $action || 'unlink_structure' === $action ) {
			check_admin_referer( 'swimlog_link_structure_' . $id );
			$result = 'unlink_structure' === $action
				? Planned_Structure_Link::detach( $id, $user_id )
				: Planned_Structure_Link::attach( $id, isset( $_POST['structure_id'] ) ? absint( wp_unslash( $_POST['structure_id'] ) ) : 0, $user_id );
			if ( is_wp_error( $result ) ) { self::$action_error = $result->get_error_message(); return; }
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'edit' => $id, 'structure_linked' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'apply_regeneration' === $action ) {
			check_admin_referer( 'swimlog_regenerate_structure_' . $id );
			$plan = Planned_Workout::get_for_user( $id, $user_id );
			$linked = $plan ? Planned_Structure_Link::for_plan( $id, $user_id ) : null;
			if ( ! $plan || is_wp_error( $linked ) || ! $linked ) { self::$action_error = __( 'Linked workout unavailable.', 'chriss-swim-training-progress-evaluation' ); return; }
			$expected = isset( $_POST['structure_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['structure_hash'] ) ) : '';
			$instructions_hash = isset( $_POST['instructions_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['instructions_hash'] ) ) : '';
			if ( ! hash_equals( hash( 'sha256', wp_json_encode( $linked['document'] ) ), $expected ) || ! hash_equals( hash( 'sha256', $plan->workout_instructions ), $instructions_hash ) ) {
				self::$action_error = __( 'The planned instructions or structured workout changed. Preview again before approving.', 'chriss-swim-training-progress-evaluation' ); return;
			}
			$parsed = self::parse_for_plan( $plan );
			if ( is_wp_error( $parsed ) ) { self::$action_error = $parsed->get_error_message(); return; }
			$result = Structured_Workout_Store::save( $user_id, $linked['title'], $parsed['document'], $linked['id'] );
			if ( is_wp_error( $result ) ) { self::$action_error = $result->get_error_message(); return; }
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'edit' => $id, 'regenerated' => 1 ), admin_url( 'admin.php' ) );
			exit;
		}

		if ( 'preview_regeneration' === $action ) {
			check_admin_referer( 'swimlog_regenerate_structure_' . $id );
			$plan = Planned_Workout::get_for_user( $id, $user_id );
			$linked = $plan ? Planned_Structure_Link::for_plan( $id, $user_id ) : null;
			if ( ! $plan || is_wp_error( $linked ) || ! $linked ) { self::$action_error = __( 'Linked workout unavailable.', 'chriss-swim-training-progress-evaluation' ); return; }
			$parsed = self::parse_for_plan( $plan );
			if ( is_wp_error( $parsed ) ) { self::$action_error = $parsed->get_error_message(); return; }
			self::$regeneration_preview = array( 'plan' => $plan, 'linked' => $linked, 'parsed' => $parsed );
			return;
		}

		if ( 'save' === $action ) {
			check_admin_referer( 'swimlog_save_planned_workout' );
			$prior = $id ? Planned_Workout::get_for_user( $id, $user_id ) : null;
			$submitted = wp_unslash( $_POST );
			$instructions_changed = $prior && isset( $submitted['workout_instructions'] ) && sanitize_textarea_field( $submitted['workout_instructions'] ) !== $prior->workout_instructions;
			$result = Planned_Workout::save( $user_id, $submitted, $id );
			if ( is_wp_error( $result ) ) {
				self::$action_error = $result->get_error_message();
				return;
			}
			$linked = $instructions_changed ? Planned_Structure_Link::for_plan( $id, $user_id ) : null;
			$needs_review = $linked && ! is_wp_error( $linked );
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'saved' => 1, 'edit' => $id ? $id : $result, 'structure_review' => $needs_review ? 1 : 0 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'cancel' === $action || 'skip' === $action ) {
			check_admin_referer( 'swimlog_status_planned_workout_' . $id );
			$status = 'cancel' === $action ? 'cancelled' : 'skipped';
			$result = Planned_Workout::set_status( $id, $user_id, $status );
			if ( is_wp_error( $result ) ) {
				self::$action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'status_changed' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'restore' === $action ) {
			check_admin_referer( 'swimlog_status_planned_workout_' . $id );
			$result = Planned_Workout::set_status( $id, $user_id, 'planned' );
			if ( is_wp_error( $result ) ) {
				self::$action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'status_changed' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_planned_workout_' . $id );
			$result = Planned_Workout::delete( $id, $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				self::$action_error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The planned workout could not be deleted.', 'chriss-swim-training-progress-evaluation' );
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	private static function parse_for_plan( $plan ) {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-structured-workout-parser.php';
		$unit = 'yd' === $plan->pool_course ? 'yd' : ( 'm' === $plan->pool_course ? 'm' : '' );
		return Structured_Workout_Parser::parse( $plan->workout_instructions, $unit );
	}

	public static function page() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage planned workouts.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-planned-workout.php';

		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only owner-scoped edit selector.
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$editing = $edit_id ? Planned_Workout::get_for_user( $edit_id, $user_id ) : null;
		$plans = Planned_Workout::all_for_user( $user_id );
		$error = self::$action_error;
		$strokes = array( '' => __( 'Not specified', 'chriss-swim-training-progress-evaluation' ), 'FR' => __( 'Freestyle', 'chriss-swim-training-progress-evaluation' ), 'BR' => __( 'Breaststroke', 'chriss-swim-training-progress-evaluation' ), 'BACK' => __( 'Backstroke', 'chriss-swim-training-progress-evaluation' ), 'FLY' => __( 'Butterfly', 'chriss-swim-training-progress-evaluation' ), 'MIXED' => __( 'Mixed', 'chriss-swim-training-progress-evaluation' ), 'IM' => __( 'Individual Medley', 'chriss-swim-training-progress-evaluation' ) );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Planned Workouts', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Plan future swims and preserve the complete workout prescription. Planned workouts are separate from recorded workouts until they are explicitly associated later.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flags. ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planned workout saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flags. ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planned workout deleted.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flags. ?>
			<?php if ( isset( $_GET['status_changed'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planned-workout status updated.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation notice. ?>
			<?php if ( isset( $_GET['regenerated'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Structured workout regenerated with your explicit approval.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only review flag. ?>
			<?php if ( isset( $_GET['structure_review'] ) && '1' === $_GET['structure_review'] ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'Written instructions changed. The linked structured workout was NOT modified. Review and explicitly approve regeneration below if you want to update it.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<?php if ( self::$regeneration_preview ) :
				$preview = self::$regeneration_preview;
				$old = Structured_Workout::summarize( $preview['linked']['document'] );
				$new = $preview['parsed'];
			?>
			<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Approval required: regenerate linked structured workout', 'chriss-swim-training-progress-evaluation' ); ?></strong></p>
			<p><?php esc_html_e( 'This will replace the linked structured definition, including any manual edits. The written instructions remain unchanged.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php echo esc_html( 'Current: ' . ( is_wp_error( $old ) ? 'Invalid' : $old['totals']['distance'] . ' ' . $old['unit'] . ', ' . $old['totals']['expanded_steps'] . ' steps' ) ); ?></p>
			<p><?php echo esc_html( 'Proposed: ' . $new['totals']['distance'] . ' ' . $new['unit'] . ', ' . $new['totals']['expanded_steps'] . ' steps' ); ?></p>
			<details><summary><?php esc_html_e( 'Review complete proposed structure (JSON)', 'chriss-swim-training-progress-evaluation' ); ?></summary><pre style="white-space:pre-wrap;overflow:auto"><?php echo esc_html( wp_json_encode( $new['document'], JSON_PRETTY_PRINT ) ); ?></pre></details>
			<form method="post">
			<?php wp_nonce_field( 'swimlog_regenerate_structure_' . $preview['plan']->id ); ?>
			<input type="hidden" name="swimlog_planned_workout_action" value="apply_regeneration">
			<input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $preview['plan']->id ); ?>">
			<input type="hidden" name="structure_hash" value="<?php echo esc_attr( hash( 'sha256', wp_json_encode( $preview['linked']['document'] ) ) ); ?>">
			<input type="hidden" name="instructions_hash" value="<?php echo esc_attr( hash( 'sha256', $preview['plan']->workout_instructions ) ); ?>">
			<?php submit_button( __( 'Approve and replace linked structure', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'edit' => $preview['plan']->id ), admin_url( 'admin.php' ) ); ?>"><?php esc_html_e( 'Keep existing structure', 'chriss-swim-training-progress-evaluation' ); ?></a>
			</form></div>
			<?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Planned Workout', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Add Planned Workout', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_planned_workout' ); ?>
				<input type="hidden" name="swimlog_planned_workout_action" value="save">
				<input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
				<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="swimlog-plan-title"><?php esc_html_e( 'Title', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required maxlength="191" class="regular-text" id="swimlog-plan-title" name="title" value="<?php echo esc_attr( $editing ? $editing->title : '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-date"><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required type="date" id="swimlog-plan-date" name="planned_date" value="<?php echo esc_attr( $editing ? $editing->planned_date : '' ); ?>"> <label for="swimlog-plan-time"><?php esc_html_e( 'Start time', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="time" id="swimlog-plan-time" name="planned_start_time" value="<?php echo esc_attr( $editing && $editing->planned_start_time ? substr( $editing->planned_start_time, 0, 5 ) : '' ); ?>"></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-type"><?php esc_html_e( 'Workout type', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><select id="swimlog-plan-type" name="workout_type"><option value="pool" <?php selected( $editing ? $editing->workout_type : 'pool', 'pool' ); ?>><?php esc_html_e( 'Pool Swim', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="open_water" <?php selected( $editing ? $editing->workout_type : 'pool', 'open_water' ); ?>><?php esc_html_e( 'Open Water', 'chriss-swim-training-progress-evaluation' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-distance"><?php esc_html_e( 'Planned distance', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input type="number" min="0.001" step="0.001" id="swimlog-plan-distance" name="planned_distance_m" value="<?php echo esc_attr( $editing && null !== $editing->planned_distance_m ? $editing->planned_distance_m : '' ); ?>"> <?php esc_html_e( 'meters', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-duration"><?php esc_html_e( 'Planned duration', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input type="number" min="1" step="1" id="swimlog-plan-duration" name="planned_duration_ms" value="<?php echo esc_attr( $editing && null !== $editing->planned_duration_ms ? $editing->planned_duration_ms : '' ); ?>"> <?php esc_html_e( 'milliseconds', 'chriss-swim-training-progress-evaluation' ); ?><p class="description"><?php esc_html_e( 'Optional. A friendlier duration entry can be added in a later UX refinement without changing stored precision.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label for="swimlog-plan-pool-length"><?php esc_html_e( 'Length in meters', 'chriss-swim-training-progress-evaluation' ); ?></label> <input type="number" min="0.001" step="0.001" id="swimlog-plan-pool-length" name="pool_length_m" value="<?php echo esc_attr( $editing && null !== $editing->pool_length_m ? $editing->pool_length_m : '' ); ?>"> <label for="swimlog-plan-course"><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></label> <select id="swimlog-plan-course" name="pool_course"><option value=""><?php esc_html_e( 'Not specified', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="m" <?php selected( $editing ? $editing->pool_course : '', 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="yd" <?php selected( $editing ? $editing->pool_course : '', 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-stroke"><?php esc_html_e( 'Primary stroke', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><select id="swimlog-plan-stroke" name="primary_stroke"><?php foreach ( $strokes as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $editing ? $editing->primary_stroke : '', $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<?php if ( $editing ) :
					$linked_structure = Planned_Structure_Link::for_plan( $editing->id, $user_id );
					$available_structures = Structured_Workout_Store::all( $user_id );
				?>
				<tr><th scope="row"><?php esc_html_e( 'Structured workout', 'chriss-swim-training-progress-evaluation' ); ?></th><td>
				<?php if ( is_wp_error( $linked_structure ) ) : ?>
					<p><?php echo esc_html( $linked_structure->get_error_message() ); ?></p>
				<?php elseif ( $linked_structure ) : ?>
					<strong><?php echo esc_html( $linked_structure['title'] ); ?></strong>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-structured-workouts', 'edit' => $linked_structure['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit structure', 'chriss-swim-training-progress-evaluation' ); ?></a>
				<?php else : ?>
					<p><?php esc_html_e( 'No structured workout linked.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Save the planned workout first. Linking does not replace or regenerate written instructions.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				</td></tr>
				<?php endif; ?>
				<tr><th scope="row"><label for="swimlog-plan-instructions"><?php esc_html_e( 'Workout Plan / Workout Instructions', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><textarea class="large-text code" rows="12" maxlength="<?php echo esc_attr( Planned_Workout::INSTRUCTIONS_MAX_BYTES ); ?>" id="swimlog-plan-instructions" name="workout_instructions"><?php echo esc_textarea( $editing ? $editing->workout_instructions : '' ); ?></textarea><p class="description"><?php esc_html_e( 'Enter the workout exactly as you want to swim it. Line breaks and free-form swim notation are preserved for future structured-workout parsing.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				<tr><th scope="row"><label for="swimlog-plan-notes"><?php esc_html_e( 'Notes', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><textarea class="large-text" rows="4" maxlength="<?php echo esc_attr( Planned_Workout::NOTES_MAX_BYTES ); ?>" id="swimlog-plan-notes" name="notes"><?php echo esc_textarea( $editing ? $editing->notes : '' ); ?></textarea><p class="description"><?php esc_html_e( 'Optional notes such as focus, cues, or context that are separate from the workout prescription.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update Planned Workout', 'chriss-swim-training-progress-evaluation' ) : __( 'Add Planned Workout', 'chriss-swim-training-progress-evaluation' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-planned-workouts' ) ); ?>"><?php esc_html_e( 'Cancel Edit', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?>
			</form>
			<?php if ( $editing && ! is_wp_error( $linked_structure ) && $linked_structure ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_regenerate_structure_' . $editing->id ); ?>
				<input type="hidden" name="swimlog_planned_workout_action" value="preview_regeneration">
				<input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $editing->id ); ?>">
				<?php submit_button( __( 'Preview regeneration from saved instructions', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php endif; ?>
			<?php if ( $editing && ! is_wp_error( $linked_structure ) ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_link_structure_' . $editing->id ); ?>
				<input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $editing->id ); ?>">
				<?php if ( $linked_structure ) : ?>
					<input type="hidden" name="swimlog_planned_workout_action" value="unlink_structure">
					<?php submit_button( __( 'Unlink structured workout', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				<?php else : ?>
					<input type="hidden" name="swimlog_planned_workout_action" value="link_structure">
					<label for="swimlog-link-structure"><?php esc_html_e( 'Link saved structure', 'chriss-swim-training-progress-evaluation' ); ?></label>
					<select id="swimlog-link-structure" name="structure_id" required>
						<option value=""><?php esc_html_e( 'Select a workout', 'chriss-swim-training-progress-evaluation' ); ?></option>
						<?php foreach ( $available_structures as $structure ) : ?>
						<option value="<?php echo esc_attr( $structure->ID ); ?>"><?php echo esc_html( $structure->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Link structured workout', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				<?php endif; ?>
			</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Your Planned Workouts', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( empty( $plans ) ) : ?><p><?php esc_html_e( 'No planned workouts have been added yet.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workout', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Planned', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Status', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
			<?php foreach ( $plans as $plan ) : ?><tr><td><?php echo esc_html( $plan->planned_date ); ?><?php if ( $plan->planned_start_time ) echo '<br>' . esc_html( substr( $plan->planned_start_time, 0, 5 ) ); ?></td><td><strong><?php echo esc_html( $plan->title ); ?></strong><?php if ( $plan->workout_instructions ) : ?><details><summary><?php esc_html_e( 'Workout instructions', 'chriss-swim-training-progress-evaluation' ); ?></summary><pre style="white-space:pre-wrap"><?php echo esc_html( $plan->workout_instructions ); ?></pre></details><?php endif; ?></td><td><?php echo esc_html( 'open_water' === $plan->workout_type ? __( 'Open Water', 'chriss-swim-training-progress-evaluation' ) : __( 'Pool', 'chriss-swim-training-progress-evaluation' ) ); ?></td><td><?php echo null !== $plan->planned_distance_m ? esc_html( $plan->planned_distance_m . ' m' ) : '&mdash;'; ?><?php if ( null !== $plan->planned_duration_ms ) echo '<br>' . esc_html( $plan->planned_duration_ms . ' ms' ); ?></td><td><?php echo esc_html( ucfirst( $plan->status ) ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-planned-workouts', 'edit' => $plan->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'chriss-swim-training-progress-evaluation' ); ?></a>
			<?php if ( 'planned' === $plan->status ) : ?> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_status_planned_workout_' . $plan->id ); ?><input type="hidden" name="swimlog_planned_workout_action" value="skip"><input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $plan->id ); ?>"><button type="submit" class="button button-small"><?php esc_html_e( 'Mark Skipped', 'chriss-swim-training-progress-evaluation' ); ?></button></form> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_status_planned_workout_' . $plan->id ); ?><input type="hidden" name="swimlog_planned_workout_action" value="cancel"><input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $plan->id ); ?>"><button type="submit" class="button button-small"><?php esc_html_e( 'Cancel Plan', 'chriss-swim-training-progress-evaluation' ); ?></button></form><?php elseif ( 'skipped' === $plan->status || 'cancelled' === $plan->status ) : ?> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_status_planned_workout_' . $plan->id ); ?><input type="hidden" name="swimlog_planned_workout_action" value="restore"><input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $plan->id ); ?>"><button type="submit" class="button button-small"><?php esc_html_e( 'Restore', 'chriss-swim-training-progress-evaluation' ); ?></button></form><?php endif; ?>
			 <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_delete_planned_workout_' . $plan->id ); ?><input type="hidden" name="swimlog_planned_workout_action" value="delete"><input type="hidden" name="planned_workout_id" value="<?php echo esc_attr( $plan->id ); ?>"><button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this planned workout?', 'chriss-swim-training-progress-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'chriss-swim-training-progress-evaluation' ); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div><?php
	}
}
