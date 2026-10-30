<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';
/** Personal-best and progression administration. */
final class Admin_Personal_Bests {

	public static function personal_bests() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) wp_die( esc_html__( 'You do not have permission to view these results.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-records.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-age-group.php';

		$user_id = Admin_UI::selected_user_id();
		Admin_UI::swimmer_selector( $user_id, 'swimlog-personal-bests' );

		$course = get_option( 'swimlog_default_course_unit', 'm' );
		if ( isset( $_GET['course'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only PB course filter.
			$course = sanitize_key( wp_unslash( $_GET['course'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) $course = 'm';

		$current_group = Age_Group::for_user( $user_id );
		$available_groups = Records::age_groups_with_performances( $user_id, $course );
		$current_label = $current_group ? $current_group['label'] : '';
		$historical_groups = array_values( array_filter( $available_groups, function( $label ) use ( $current_label ) { return $label !== $current_label; } ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only PB period filter.
		$period = sanitize_key( wp_unslash( $_GET['period'] ?? ( $current_group ? 'current' : 'lifetime' ) ) );
		if ( ! in_array( $period, array( 'current', 'lifetime', 'historical' ), true ) ) $period = $current_group ? 'current' : 'lifetime';
		if ( 'current' === $period && ! $current_group ) $period = 'lifetime';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only historical age-group filter.
		$requested_group = sanitize_text_field( wp_unslash( $_GET['age_group'] ?? '' ) );
		$selected_group = '';
		if ( 'historical' === $period ) {
			if ( in_array( $requested_group, $historical_groups, true ) ) {
				$selected_group = $requested_group;
			} elseif ( $historical_groups ) {
				$selected_group = $historical_groups[0];
			} else {
				$period = $current_group ? 'current' : 'lifetime';
			}
		}

		$lifetime_pbs = Records::personal_bests( $user_id, $course );
		if ( 'lifetime' === $period ) {
			$pbs = $lifetime_pbs;
			$period_title = __( 'Lifetime', 'chriss-swim-training-progress-evaluation' );
		} else {
			$group_label = 'historical' === $period ? $selected_group : $current_label;
			$pbs = Records::age_group_personal_bests( $user_id, $course, $group_label );
			$period_title = sprintf(
				/* translators: %s: swimming age-group label. */
				__( 'Age Group %s', 'chriss-swim-training-progress-evaluation' ),
				$group_label
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only PB history filters.
		$history_distance = absint( wp_unslash( $_GET['history_distance'] ?? 0 ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only PB history filter.
		$history_stroke = strtoupper( sanitize_key( wp_unslash( $_GET['history_stroke'] ?? '' ) ) );
		$progression = ( $history_distance && in_array( $history_stroke, array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED' ), true ) ) ? Records::progression( $user_id, $history_distance, $course, $history_stroke ) : array();

		$cols = array(
			'OVERALL' => __( 'Overall', 'chriss-swim-training-progress-evaluation' ),
			'FR'      => __( 'Freestyle', 'chriss-swim-training-progress-evaluation' ),
			'BR'      => __( 'Breaststroke', 'chriss-swim-training-progress-evaluation' ),
			'BACK'    => __( 'Backstroke', 'chriss-swim-training-progress-evaluation' ),
			'FLY'     => __( 'Butterfly', 'chriss-swim-training-progress-evaluation' ),
			'MIXED'   => __( 'Mixed', 'chriss-swim-training-progress-evaluation' ),
		);
		foreach ( array_keys( $cols ) as $code ) {
			$has_value = false;
			foreach ( Evaluator::DISTANCES as $distance ) {
				if ( ! empty( $pbs[ $distance ][ $code ] ) ) { $has_value = true; break; }
			}
			if ( ! $has_value ) unset( $cols[ $code ] );
		}

		$base_args = array( 'page' => 'swimlog-personal-bests', 'course' => $course );
		
		$current_args = array_merge( $base_args, array( 'period' => 'current' ) );
		$lifetime_args = array_merge( $base_args, array( 'period' => 'lifetime' ) );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ); ?></h1>

			<h2><?php esc_html_e( 'Record Period', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p>
				<?php if ( $current_group ) : ?><a class="button <?php echo 'current' === $period ? 'button-primary' : ''; ?>" href="<?php echo esc_url( Admin_UI::url(  $current_args, $user_id ) ); ?>"><?php echo esc_html( sprintf(
					/* translators: %s: current swimming age-group label. */
					__( 'Current Age Group (%s)', 'chriss-swim-training-progress-evaluation' ),
					$current_label
				) ); ?></a><?php endif; ?>
				<a class="button <?php echo 'lifetime' === $period ? 'button-primary' : ''; ?>" href="<?php echo esc_url( Admin_UI::url(  $lifetime_args, $user_id ) ); ?>"><?php esc_html_e( 'Lifetime', 'chriss-swim-training-progress-evaluation' ); ?></a>
			</p>
			<?php if ( $historical_groups ) : ?>
				<form method="get">
					<input type="hidden" name="page" value="swimlog-personal-bests">
					<input type="hidden" name="course" value="<?php echo esc_attr( $course ); ?>">
					<input type="hidden" name="period" value="historical">
					<?php Admin_UI::context_fields( $user_id ); ?>
					<label for="swimlog-pb-age-group"><strong><?php esc_html_e( 'Historical Age Group', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
					<select id="swimlog-pb-age-group" name="age_group"><?php foreach ( $historical_groups as $group_label ) : ?><option value="<?php echo esc_attr( $group_label ); ?>" <?php selected( $selected_group, $group_label ); ?>><?php echo esc_html( $group_label ); ?></option><?php endforeach; ?></select>
					<?php submit_button( __( 'View', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p>
				<?php
				$meter_args = array( 'page' => 'swimlog-personal-bests', 'course' => 'm', 'period' => $period );
				$yard_args = array( 'page' => 'swimlog-personal-bests', 'course' => 'yd', 'period' => $period );
				if ( 'historical' === $period && $selected_group ) { $meter_args['age_group'] = $selected_group; $yard_args['age_group'] = $selected_group; }
				
				?>
				<a class="button <?php echo 'm' === $course ? 'button-primary' : ''; ?>" href="<?php echo esc_url( Admin_UI::url(  $meter_args, $user_id ) ); ?>"><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></a>
				<a class="button <?php echo 'yd' === $course ? 'button-primary' : ''; ?>" href="<?php echo esc_url( Admin_UI::url(  $yard_args, $user_id ) ); ?>"><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></a>
			</p>

			<h2><?php echo esc_html( sprintf(
				/* translators: 1: record period, 2: course name. */
				__( 'Personal Bests — %1$s — %2$s', 'chriss-swim-training-progress-evaluation' ),
				$period_title,
				'm' === $course ? __( 'Meters', 'chriss-swim-training-progress-evaluation' ) : __( 'Yards', 'chriss-swim-training-progress-evaluation' )
			) ); ?></h2>
			<?php if ( 'lifetime' !== $period ) : ?><p class="description"><?php esc_html_e( 'Age-Group Personal Bests are performances achieved while the swimmer was in this chronological age group. Date of birth is used only to determine the age group and is not displayed publicly.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>

			<?php if ( empty( $pbs ) ) : ?>
				<p><?php esc_html_e( 'No personal bests are available for this record period and course.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php else : ?>
				<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><?php foreach ( $cols as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead><tbody>
				<?php foreach ( Evaluator::DISTANCES as $d ) : ?><tr><th><?php echo esc_html( $d . ' ' . $course ); ?></th><?php foreach ( $cols as $code => $label ) : $p = $pbs[ $d ][ $code ] ?? null; ?><td><?php if ( $p ) :
					$is_lifetime = 'lifetime' !== $period && ! empty( $lifetime_pbs[ $d ][ $code ] ) && (int) $lifetime_pbs[ $d ][ $code ]->id === (int) $p->id;
					?><a href="<?php echo esc_url( Admin_UI::url(  array( 'page' => 'swimlog-workouts', 'workout_id' => $p->workout_id, 'swimmer_id' => $user_id ), $user_id ) ); ?>"><?php echo esc_html( Workout::format_duration( $p->duration_ms ) ); ?></a><?php if ( $is_lifetime ) : ?> <span aria-label="<?php esc_attr_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?>" title="<?php esc_attr_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?>">★</span><?php endif; ?><br><small><?php echo esc_html( wp_date( 'M j, Y', ( new \DateTimeImmutable( $p->achieved_at, wp_timezone() ) )->getTimestamp(), wp_timezone() ) ); ?></small><?php else : ?>&mdash;<?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
				</tbody></table></div>
				<?php if ( 'lifetime' !== $period ) : ?><p><span aria-hidden="true">★</span> <?php esc_html_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'PB Progression', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<form method="get"><input type="hidden" name="page" value="swimlog-personal-bests"><input type="hidden" name="course" value="<?php echo esc_attr( $course ); ?>"><?php Admin_UI::context_fields( $user_id ); ?><select name="history_distance"><option value="0"><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( Evaluator::DISTANCES as $d ) : ?><option value="<?php echo esc_attr( $d ); ?>" <?php selected( $history_distance, $d ); ?>><?php echo esc_html( $d . ' ' . $course ); ?></option><?php endforeach; ?></select> <select name="history_stroke"><option value=""><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( array( 'FR' => 'Freestyle', 'BR' => 'Breaststroke', 'BACK' => 'Backstroke', 'FLY' => 'Butterfly', 'MIXED' => 'Mixed' ) as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $history_stroke, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'Show progression', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?></form>
			<?php if ( $history_distance && $history_stroke ) : ?><?php if ( ! $progression ) : ?><p><?php esc_html_e( 'No progression records found for that distance and stroke.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php else : ?><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Time', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workout', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $progression as $p ) : ?><tr><td><?php echo esc_html( wp_date( 'F j, Y', ( new \DateTimeImmutable( $p->achieved_at, wp_timezone() ) )->getTimestamp(), wp_timezone() ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $p->duration_ms ) ); ?></td><td><a href="<?php echo esc_url( Admin_UI::url(  array( 'page' => 'swimlog-workouts', 'workout_id' => $p->workout_id, 'swimmer_id' => $user_id ), $user_id ) ); ?>"><?php esc_html_e( 'View workout', 'chriss-swim-training-progress-evaluation' ); ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?>
		</div>
		<?php
	}
}

