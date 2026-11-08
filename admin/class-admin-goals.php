<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owner-scoped goal creation, listing and progress display. */
final class Admin_Goals {
	public static function handle_actions() {
		if ( empty( $_POST['swimlog_goal_action'] ) ) return;
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to manage goals.', 'chriss-swim-training-progress-evaluation' ) );
		check_admin_referer( 'swimlog_goal_action' );
		$user_id = Admin_UI::selected_user_id();
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'swimlog_manage_all_users' ) ) wp_die( esc_html__( 'You cannot manage goals for this swimmer.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-swim-goals.php';
		$action = sanitize_key( wp_unslash( $_POST['swimlog_goal_action'] ) );
		if ( 'create' === $action ) {
			$input = isset( $_POST['goal'] ) && is_array( $_POST['goal'] ) ? map_deep( wp_unslash( $_POST['goal'] ), 'sanitize_text_field' ) : array();
			if ( 'single_performance' === ( $input['goal_type'] ?? '' ) ) {
				$input['target_pace_ms'] = self::parse_pace( $input['target_pace'] ?? '' );
				$input['start_date'] = wp_date( 'Y-m-d', null, wp_timezone() );
			}
			$result = Swim_Goals::create( $user_id, $input );
			$redirect = Admin_UI::url( array( 'page' => 'swimlog-goals', 'goal_result' => is_wp_error( $result ) ? 'error' : 'created' ), $user_id );
			if ( is_wp_error( $result ) ) $redirect = add_query_arg( 'goal_error', rawurlencode( $result->get_error_message() ), $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}
		if ( 'delete' === $action ) {
			$goal_id = isset( $_POST['goal_id'] ) ? absint( wp_unslash( $_POST['goal_id'] ) ) : 0;
			$deleted = Swim_Goals::delete_for_user( $goal_id, $user_id );
			wp_safe_redirect( Admin_UI::url( array( 'page' => 'swimlog-goals', 'goal_result' => $deleted ? 'deleted' : 'error' ), $user_id ) );
			exit;
		}
	}

	public static function page() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to manage goals.', 'chriss-swim-training-progress-evaluation' ) );
		$user_id = Admin_UI::selected_user_id();
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'swimlog_manage_all_users' ) ) wp_die( esc_html__( 'You cannot manage goals for this swimmer.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-swim-goals.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-unit-preferences.php';
		$goals = Swim_Goals::list_for_user( $user_id );
		$prefs = Unit_Preferences::get( $user_id );
		$default_unit = in_array( $prefs['swim_course'] ?? 'm', array( 'm', 'yd' ), true ) ? $prefs['swim_course'] : 'm';
		Admin_UI::swimmer_selector( $user_id, 'swimlog-goals' );
		?>
		<div class="wrap swimlog-admin swimlog-goals">
			<h1><?php esc_html_e( 'Goals', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php self::notices(); ?>
			<p><?php esc_html_e( 'Set private swimming goals and track progress from recorded workouts. Pace goals use evaluated performances for the exact distance and course. Distance goals total recorded pool and open-water swimming within the inclusive dates.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<nav class="swimlog-goal-nav" aria-label="<?php esc_attr_e( 'Goals navigation', 'chriss-swim-training-progress-evaluation' ); ?>"><a class="button" href="#swimlog-set-goal"><?php esc_html_e( 'Set Goal', 'chriss-swim-training-progress-evaluation' ); ?></a> <a class="button" href="#swimlog-list-goals"><?php esc_html_e( 'List Goals', 'chriss-swim-training-progress-evaluation' ); ?></a></nav>
			<h2 id="swimlog-set-goal"><?php esc_html_e( 'Set Goal', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<div class="swimlog-goal-forms">
				<section class="swimlog-settings-section"><h3><?php esc_html_e( 'One-time distance and pace', 'chriss-swim-training-progress-evaluation' ); ?></h3>
					<p class="description"><?php esc_html_e( 'A goal is achieved by one recorded evaluated swim at the selected distance and course, meeting or beating the target pace before the due date. Any stroke can qualify.', 'chriss-swim-training-progress-evaluation' ); ?></p>
					<form method="post"><?php wp_nonce_field( 'swimlog_goal_action' ); ?><?php Admin_UI::context_fields( $user_id ); ?><input type="hidden" name="swimlog_goal_action" value="create"><input type="hidden" name="goal[goal_type]" value="single_performance">
						<table class="form-table" role="presentation"><tbody>
							<tr><th><label for="swimlog-goal-performance-title"><?php esc_html_e( 'Goal name (optional)', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input class="regular-text" id="swimlog-goal-performance-title" name="goal[title]" type="text" maxlength="191"></td></tr>
							<tr><th><label for="swimlog-goal-performance-course"><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><?php self::course_select( 'goal[course_unit]', 'swimlog-goal-performance-course', $default_unit ); ?><p class="description"><?php esc_html_e( 'Meters and yards remain separate. A performance in the other course does not qualify.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
							<tr><th><label for="swimlog-goal-performance-distance"><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><select required id="swimlog-goal-performance-distance" name="goal[target_distance_value]"><?php foreach ( Evaluator::DISTANCES as $distance ) : ?><option value="<?php echo esc_attr( $distance ); ?>"><?php echo esc_html( $distance ); ?></option><?php endforeach; ?></select></td></tr>
							<tr><th><label for="swimlog-goal-performance-pace"><?php esc_html_e( 'Target pace per 100', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required id="swimlog-goal-performance-pace" name="goal[target_pace]" type="text" inputmode="decimal" pattern="[0-9]{1,3}:[0-5][0-9](\.[0-9]{1,2})?" placeholder="2:00 or 2:00.50"><p class="description"><?php esc_html_e( 'Enter minutes:seconds per 100 meters or yards, according to the selected course.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
							<tr><th><label for="swimlog-goal-performance-due"><?php esc_html_e( 'Do it by', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required id="swimlog-goal-performance-due" name="goal[end_date]" type="date" min="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>" value="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></td></tr>
						</tbody></table><?php submit_button( __( 'Set Pace Goal', 'chriss-swim-training-progress-evaluation' ) ); ?>
					</form>
				</section>
				<section class="swimlog-settings-section"><h3><?php esc_html_e( 'Swimming distance over a date range', 'chriss-swim-training-progress-evaluation' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Pool and open-water swims both count. Swimming is totaled in canonical meters; the selected meter or yard unit controls goal entry and display. Check Single Workout to require one swim to meet the target; otherwise swims accumulate across the date range. Individual swim distances and course-specific records stay separate.', 'chriss-swim-training-progress-evaluation' ); ?></p>
					<form method="post"><?php wp_nonce_field( 'swimlog_goal_action' ); ?><?php Admin_UI::context_fields( $user_id ); ?><input type="hidden" name="swimlog_goal_action" value="create"><input type="hidden" name="goal[goal_type]" value="period_distance">
						<table class="form-table" role="presentation"><tbody>
							<tr><th><label for="swimlog-goal-distance-title"><?php esc_html_e( 'Goal name (optional)', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input class="regular-text" id="swimlog-goal-distance-title" name="goal[title]" type="text" maxlength="191"></td></tr>
							<tr><th><label for="swimlog-goal-distance-target"><?php esc_html_e( 'Target distance', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required id="swimlog-goal-distance-target" name="goal[target_distance]" type="number" min="0.001" max="100000000" step="0.001"> <?php self::course_select( 'goal[course_unit]', 'swimlog-goal-distance-course', $default_unit ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Goal condition', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label for="swimlog-goal-single-workout"><input id="swimlog-goal-single-workout" name="goal[single_workout]" type="checkbox" value="1"> <?php esc_html_e( 'Single Workout', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><?php esc_html_e( 'When checked, one pool or open-water swim must reach the target within the dates. Progress shows the longest single swim until achieved.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
							<tr><th><label for="swimlog-goal-distance-start"><?php esc_html_e( 'First day', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required id="swimlog-goal-distance-start" name="goal[start_date]" type="date" value="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></td></tr>
							<tr><th><label for="swimlog-goal-distance-end"><?php esc_html_e( 'Last day', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input required id="swimlog-goal-distance-end" name="goal[end_date]" type="date" value="<?php echo esc_attr( wp_date( 'Y-m-d', null, wp_timezone() ) ); ?>"></td></tr>
						</tbody></table><?php submit_button( __( 'Set Distance Goal', 'chriss-swim-training-progress-evaluation' ) ); ?>
					</form>
				</section>
			</div>
			<h2 id="swimlog-list-goals"><?php esc_html_e( 'List Goals', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( ! $goals ) : ?><p><?php esc_html_e( 'No goals have been set.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php else : ?>
				<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Goal', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Dates', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Target', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Progress', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Status', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $goals as $goal ) : ?><tr><td><strong><?php echo esc_html( $goal['title'] ); ?></strong><br><?php echo esc_html( self::goal_type_label( $goal ) ); ?></td><td><?php echo esc_html( self::date_range( $goal ) ); ?></td><td><?php echo esc_html( self::target_label( $goal ) ); ?></td><td><?php self::progress( $goal ); ?></td><td><?php echo esc_html( self::status_label( $goal['status'] ) ); ?><?php if ( 'achieved' === $goal['status'] && ! empty( $goal['completion_date'] ) ) : ?><br><small><?php echo esc_html( sprintf( /* translators: %s: goal completion date. */ __( 'Completed %s', 'chriss-swim-training-progress-evaluation' ), self::completion_date_label( $goal['completion_date'] ) ) ); ?></small><?php endif; ?></td><td><form method="post"><?php wp_nonce_field( 'swimlog_goal_action' ); ?><?php Admin_UI::context_fields( $user_id ); ?><input type="hidden" name="swimlog_goal_action" value="delete"><input type="hidden" name="goal_id" value="<?php echo esc_attr( $goal['id'] ); ?>"><button class="button-link-delete" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete this goal?', 'chriss-swim-training-progress-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'chriss-swim-training-progress-evaluation' ); ?></button></form></td></tr><?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
		$result = isset( $_GET['goal_result'] ) ? sanitize_key( wp_unslash( $_GET['goal_result'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only validation notice.
		$error = isset( $_GET['goal_error'] ) ? sanitize_text_field( wp_unslash( $_GET['goal_error'] ) ) : '';
		if ( $error ) echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		elseif ( in_array( $result, array( 'created', 'deleted' ), true ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'created' === $result ? __( 'Goal saved.', 'chriss-swim-training-progress-evaluation' ) : __( 'Goal deleted.', 'chriss-swim-training-progress-evaluation' ) ) . '</p></div>';
		elseif ( 'error' === $result ) echo '<div class="notice notice-error"><p>' . esc_html__( 'The goal could not be changed.', 'chriss-swim-training-progress-evaluation' ) . '</p></div>';
	}

	private static function course_select( $name, $id, $selected ) {
		?><select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><option value="m" <?php selected( $selected, 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="yd" <?php selected( $selected, 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option></select><?php
	}

	private static function goal_type_label( $goal ) {
		if ( Swim_Goals::SINGLE === $goal['goal_type'] ) return __( 'One-time performance', 'chriss-swim-training-progress-evaluation' );
		return Swim_Goals::SINGLE_WORKOUT === $goal['goal_type'] ? __( 'Date-range distance · single workout', 'chriss-swim-training-progress-evaluation' ) : __( 'Date-range distance', 'chriss-swim-training-progress-evaluation' );
	}

	private static function date_range( $goal ) {
		if ( Swim_Goals::SINGLE === $goal['goal_type'] ) return sprintf( /* translators: %s: due date. */ __( 'Due %s', 'chriss-swim-training-progress-evaluation' ), $goal['end_date'] );
		return $goal['start_date'] . ' – ' . $goal['end_date'];
	}

	private static function target_label( $goal ) {
		if ( Swim_Goals::SINGLE === $goal['goal_type'] ) return $goal['target_distance_value'] . ' ' . $goal['course_unit'] . ' · ' . self::format_pace( $goal['target_pace_ms'] ) . ' / 100 ' . $goal['course_unit'];
		$target = (float) $goal['target_distance_m'] / ( 'yd' === $goal['course_unit'] ? Swim_Goals::YARDS_TO_METERS : 1 );
		return rtrim( rtrim( number_format_i18n( $target, 3 ), '0' ), '.' ) . ' ' . $goal['course_unit'];
	}

	private static function progress( $goal ) {
		if ( Swim_Goals::SINGLE === $goal['goal_type'] ) {
			if ( null === $goal['best_pace_ms'] ) esc_html_e( 'No qualifying performance yet.', 'chriss-swim-training-progress-evaluation' );
			else echo esc_html( sprintf( /* translators: 1: current pace, 2: course unit, 3: target pace. */ __( 'Best %1$s / 100 %2$s · target %3$s', 'chriss-swim-training-progress-evaluation' ), self::format_pace( $goal['best_pace_ms'] ), $goal['course_unit'], self::format_pace( $goal['target_pace_ms'] ) ) );
			return;
		}
		$unit = $goal['course_unit'];
		$current = (float) $goal['progress_meters'] / ( 'yd' === $unit ? Swim_Goals::YARDS_TO_METERS : 1 );
		$target = (float) $goal['target_distance_m'] / ( 'yd' === $unit ? Swim_Goals::YARDS_TO_METERS : 1 );
		echo '<progress max="100" value="' . esc_attr( min( 100, (float) $goal['progress_percent'] ) ) . '" aria-label="' . esc_attr__( 'Goal progress', 'chriss-swim-training-progress-evaluation' ) . '"></progress> ' . esc_html( rtrim( rtrim( number_format_i18n( $current, 1 ), '0' ), '.' ) . ' / ' . rtrim( rtrim( number_format_i18n( $target, 1 ), '0' ), '.' ) . ' ' . $unit . ' (' . number_format_i18n( (float) $goal['progress_percent'], 1 ) . '%)' );
	}

	private static function completion_date_label( $date ) {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
		return $parsed ? wp_date( get_option( 'date_format' ), $parsed->getTimestamp(), wp_timezone() ) : (string) $date;
	}

	private static function status_label( $status ) {
		$labels = array( 'upcoming' => __( 'Upcoming', 'chriss-swim-training-progress-evaluation' ), 'in_progress' => __( 'In progress', 'chriss-swim-training-progress-evaluation' ), 'achieved' => __( 'Achieved', 'chriss-swim-training-progress-evaluation' ), 'missed' => __( 'Not achieved by date', 'chriss-swim-training-progress-evaluation' ) );
		return $labels[ $status ] ?? __( 'Unknown', 'chriss-swim-training-progress-evaluation' );
	}

	private static function parse_pace( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( ! preg_match( '/^(\d{1,3}):([0-5]\d)(?:\.(\d{1,2}))?$/', $value, $matches ) ) return 0;
		$hundredths = isset( $matches[3] ) ? (int) str_pad( $matches[3], 2, '0' ) : 0;
		return ( ( (int) $matches[1] * 60 + (int) $matches[2] ) * 1000 ) + $hundredths * 10;
	}

	private static function format_pace( $milliseconds ) {
		$milliseconds = max( 0, (int) $milliseconds );
		$seconds = (int) floor( $milliseconds / 1000 );
		$hundredths = (int) round( ( $milliseconds % 1000 ) / 10 );
		if ( 100 === $hundredths ) { $seconds++; $hundredths = 0; }
		return sprintf( '%d:%02d.%02d', floor( $seconds / 60 ), $seconds % 60, $hundredths );
	}
}
