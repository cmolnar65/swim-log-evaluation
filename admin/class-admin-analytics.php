<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';

/**
 * Training volume, trends, and pool performance analytics administration.
 */
final class Admin_Analytics {




	public static function training_volume() {
		if(!current_user_can('swimlog_manage_own_workouts'))wp_die(esc_html__('You do not have permission to access this swim training page.','chriss-swim-training-progress-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
		$user_id=Admin_UI::selected_user_id();Admin_UI::swimmer_selector($user_id,'swimlog-training-volume');
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'),'UNKNOWN'=>__('Unknown','chriss-swim-training-progress-evaluation'));
		$training_volume_months=(int)get_user_meta($user_id,'swimlog_training_volume_months',true);if($training_volume_months<1||$training_volume_months>24)$training_volume_months=3;
		$stroke_volume=Workout::stroke_volume_for_user($user_id,$training_volume_months);
		$stroke_volume_by_course=array('m'=>array(),'yd'=>array());foreach($stroke_volume as$volume){if(isset($stroke_volume_by_course[$volume->course_unit]))$stroke_volume_by_course[$volume->course_unit][]=$volume;}
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Pool Training Volume by Stroke','chriss-swim-training-progress-evaluation'); ?></h1>
		<p><?php echo esc_html(sprintf(/* translators: %d: training-volume period in months. */ __('Last %d months','chriss-swim-training-progress-evaluation'),$training_volume_months)); ?></p>
		<p class="description"><?php esc_html_e('Training volume is calculated from normalized active pool lengths within the swimmer-configured rolling period. Meter and yard courses are kept separate and strokes are ranked by total distance. Change the period on Swimmer Configuration.','chriss-swim-training-progress-evaluation'); ?></p>
		<?php if(!$stroke_volume):?><p><?php esc_html_e('No active pool lengths are available in the configured training-volume period.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?>
		<?php foreach(array('m'=>__('Meters','chriss-swim-training-progress-evaluation'),'yd'=>__('Yards','chriss-swim-training-progress-evaluation')) as$course_unit=>$course_label):if(empty($stroke_volume_by_course[$course_unit]))continue;?>
		<h2><?php echo esc_html($course_label); ?></h2>
		<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Workouts','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Active lengths','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Active swim time','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($stroke_volume_by_course[$course_unit] as$volume):?><tr><td><?php echo esc_html($strokes[$volume->stroke]??$volume->stroke); ?></td><td><?php echo esc_html(number_format_i18n((int)$volume->workout_count)); ?></td><td><?php echo esc_html(number_format_i18n((int)$volume->length_count)); ?></td><td><?php echo esc_html(Admin_UI::display_measurement((float)$volume->distance_native,$volume->course_unit)); ?></td><td><?php echo esc_html(Workout::format_duration((int)$volume->active_time_ms)); ?></td></tr><?php endforeach;?></tbody></table></div>
		<?php endforeach;endif;?></div><?php
	}


	public static function training_trends() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-training-trends.php';

		$user_id = Admin_UI::selected_user_id();
		$months = Training_Trends::DEFAULT_LOOKBACK_MONTHS;
		if ( isset( $_GET['months'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only trends filter.
			$months = absint( wp_unslash( $_GET['months'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$months = max( 1, min( Training_Trends::MAX_LOOKBACK_MONTHS, $months ) );
		$data = Training_Trends::for_user( $user_id, $months );
		$strokes = array(
			'FR' => __( 'Freestyle', 'chriss-swim-training-progress-evaluation' ),
			'BR' => __( 'Breaststroke', 'chriss-swim-training-progress-evaluation' ),
			'BACK' => __( 'Backstroke', 'chriss-swim-training-progress-evaluation' ),
			'FLY' => __( 'Butterfly', 'chriss-swim-training-progress-evaluation' ),
			'MIXED' => __( 'Mixed', 'chriss-swim-training-progress-evaluation' ),
			'UNKNOWN' => __( 'Unknown', 'chriss-swim-training-progress-evaluation' ),
		);
		$change = static function ( $value ) {
			if ( null === $value ) return __( 'New activity', 'chriss-swim-training-progress-evaluation' );
			return sprintf( '%+.1f%%', $value );
		};
		Admin_UI::swimmer_selector( $user_id, 'swimlog-training-trends' );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Training Trends / Training Load', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Longitudinal training volume derived from normalized workout history. This dashboard describes recorded training load; it does not estimate physiological stress, recovery, fitness, or injury risk.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="swimlog-training-trends">
				<?php if ( current_user_can( 'swimlog_manage_all_users' ) ) : ?><input type="hidden" name="swimmer_id" value="<?php echo esc_attr( $user_id ); ?>"><?php endif; ?>
				<label for="swimlog-trends-months"><strong><?php esc_html_e( 'Lookback months', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<input id="swimlog-trends-months" name="months" type="number" min="1" max="<?php echo esc_attr( Training_Trends::MAX_LOOKBACK_MONTHS ); ?>" step="1" value="<?php echo esc_attr( $months ); ?>">
				<?php submit_button( __( 'Apply', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Current Period vs Previous Period', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Metric', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Current', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Previous', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Change', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead>
				<tbody>
					<tr><th><?php esc_html_e( 'Total distance', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( Admin_UI::display_measurement( $data['current']['distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $data['previous']['distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( $change( $data['comparison']['distance_percent'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( number_format_i18n( $data['current']['workout_count'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $data['previous']['workout_count'] ) ); ?></td><td><?php echo esc_html( $change( $data['comparison']['workouts_percent'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Active swim time', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( Workout::format_duration( $data['current']['active_time_ms'] ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $data['previous']['active_time_ms'] ) ); ?></td><td><?php echo esc_html( $change( $data['comparison']['active_time_percent'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Workout frequency', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( number_format_i18n( $data['current']['workouts_per_week'], 2 ) . ' / week' ); ?></td><td><?php echo esc_html( number_format_i18n( $data['previous']['workouts_per_week'], 2 ) . ' / week' ); ?></td><td><?php echo esc_html( $change( $data['comparison']['frequency_percent'] ) ); ?></td></tr>
				</tbody>
			</table></div>

			<h2><?php esc_html_e( 'Pool vs Open Water', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
				<tr><td><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php echo esc_html( number_format_i18n( $data['current']['pool_workouts'] ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $data['current']['pool_distance_m'], 'm' ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Open Water', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php echo esc_html( number_format_i18n( $data['current']['open_water_workouts'] ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $data['current']['open_water_distance_m'], 'm' ) ); ?></td></tr>
			</tbody></table></div>

			<h2><?php esc_html_e( 'Weekly Trend', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( ! $data['weekly'] ) : ?><p><?php esc_html_e( 'No workouts are available in this period.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php else : ?>
			<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Week', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active time', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Open water', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( '4-week avg distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( '4-week avg workouts', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
			<?php foreach ( $data['weekly'] as $week ) : ?><tr><td><?php echo esc_html( $week['period'] ); ?></td><td><?php echo esc_html( $week['workout_count'] ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $week['distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $week['active_time_ms'] ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $week['pool_distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $week['open_water_distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $week['rolling_4_week_avg_distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( number_format_i18n( $week['rolling_4_week_avg_workouts'], 2 ) ); ?></td></tr><?php endforeach; ?>
			</tbody></table></div><?php endif; ?>

			<h2><?php esc_html_e( 'Monthly Trend', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( $data['monthly'] ) : ?><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Month', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active time', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Open water', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
			<?php foreach ( $data['monthly'] as $month ) : ?><tr><td><?php echo esc_html( $month['period'] ); ?></td><td><?php echo esc_html( $month['workout_count'] ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $month['distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $month['active_time_ms'] ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $month['pool_distance_m'], 'm' ) ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $month['open_water_distance_m'], 'm' ) ); ?></td></tr><?php endforeach; ?>
			</tbody></table></div><?php endif; ?>

			<h2><?php esc_html_e( 'Pool Course Volume', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active lengths', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active length time', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
			<?php foreach ( array( 'm' => __( 'Meters', 'chriss-swim-training-progress-evaluation' ), 'yd' => __( 'Yards', 'chriss-swim-training-progress-evaluation' ) ) as $unit => $label ) : $course = $data['pool_course'][ $unit ]; ?><tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( $course['workout_count'] ); ?></td><td><?php echo esc_html( $course['length_count'] ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $course['distance_native'], $unit ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $course['active_time_ms'] ) ); ?></td></tr><?php endforeach; ?>
			</tbody></table></div>

			<h2><?php esc_html_e( 'Pool Distance by Stroke', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php foreach ( array( 'm' => __( 'Meters', 'chriss-swim-training-progress-evaluation' ), 'yd' => __( 'Yards', 'chriss-swim-training-progress-evaluation' ) ) as $unit => $label ) : if ( empty( $data['stroke_volume'][ $unit ] ) ) continue; ?>
				<h3><?php echo esc_html( $label ); ?></h3><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active lengths', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Active length time', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $data['stroke_volume'][ $unit ] as $stroke => $volume ) : ?><tr><td><?php echo esc_html( $strokes[ $stroke ] ?? $stroke ); ?></td><td><?php echo esc_html( $volume['length_count'] ); ?></td><td><?php echo esc_html( Admin_UI::display_measurement( $volume['distance_native'], $unit ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $volume['active_time_ms'] ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table></div>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Total-distance trends use normalized meters so pool and open-water training can be compared together. Course and stroke tables preserve native meter/yard units. Active swim time uses recorded moving time when available and otherwise recorded elapsed time; pool stroke tables use recorded active-length timing only.', 'chriss-swim-training-progress-evaluation' ); ?></p>
		</div>
		<?php
	}


	public static function pool_analytics() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-pool-analytics.php';

		$user_id = Admin_UI::selected_user_id();
		$course = get_option( 'swimlog_default_course_unit', 'm' );
		$stroke = '';
		$months = Pool_Analytics::DEFAULT_LOOKBACK_MONTHS;
		if ( isset( $_GET['course'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
			$course = sanitize_key( wp_unslash( $_GET['course'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['stroke'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
			$stroke = strtoupper( sanitize_key( wp_unslash( $_GET['stroke'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['months'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
			$months = absint( wp_unslash( $_GET['months'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) $course = 'm';
		if ( ! in_array( $stroke, Pool_Analytics::STROKES, true ) ) $stroke = '';
		$months = max( 1, min( Pool_Analytics::MAX_LOOKBACK_MONTHS, $months ) );
		$analytics = Pool_Analytics::for_user( $user_id, array( 'course' => $course, 'stroke' => $stroke, 'months' => $months ) );
		$groups = $analytics['groups'];
		$strokes = array(
			'' => __( 'All strokes', 'chriss-swim-training-progress-evaluation' ),
			'FR' => __( 'Freestyle', 'chriss-swim-training-progress-evaluation' ),
			'BR' => __( 'Breaststroke', 'chriss-swim-training-progress-evaluation' ),
			'BACK' => __( 'Backstroke', 'chriss-swim-training-progress-evaluation' ),
			'FLY' => __( 'Butterfly', 'chriss-swim-training-progress-evaluation' ),
			'MIXED' => __( 'Mixed', 'chriss-swim-training-progress-evaluation' ),
			'UNKNOWN' => __( 'Unknown', 'chriss-swim-training-progress-evaluation' ),
		);
		Admin_UI::swimmer_selector( $user_id, 'swimlog-pool-analytics' );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Pool Performance & Efficiency Analytics', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'These metrics are derived only from recorded active pool lengths. Missing timing and stroke-count data are never estimated. Meter and yard courses, pool lengths, and strokes are kept separate.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="swimlog-pool-analytics">
				<?php if ( current_user_can( 'swimlog_manage_all_users' ) ) : ?><input type="hidden" name="swimmer_id" value="<?php echo esc_attr( $user_id ); ?>"><?php endif; ?>
				<label for="swimlog-analytics-course"><strong><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<select id="swimlog-analytics-course" name="course">
					<option value="m" <?php selected( $course, 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option>
					<option value="yd" <?php selected( $course, 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option>
				</select>
				<label for="swimlog-analytics-stroke"><strong><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<select id="swimlog-analytics-stroke" name="stroke">
					<?php foreach ( $strokes as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $stroke, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select>
				<label for="swimlog-analytics-months"><strong><?php esc_html_e( 'Lookback months', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<input id="swimlog-analytics-months" name="months" type="number" min="1" max="<?php echo esc_attr( Pool_Analytics::MAX_LOOKBACK_MONTHS ); ?>" step="1" value="<?php echo esc_attr( $months ); ?>">
				<?php submit_button( __( 'Apply', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?>
			</form>

			<p class="description"><?php esc_html_e( 'Average pace is distance-weighted from recorded length timing. Pace consistency is the coefficient of variation of per-length pace; lower values mean more consistent pacing. SWOLF is elapsed seconds plus recorded strokes for the same length and is pool-length dependent, so compare it only within the same pool length and similar recording conventions.', 'chriss-swim-training-progress-evaluation' ); ?></p>

			<?php if ( ! $groups ) : ?>
				<p><?php esc_html_e( 'No qualifying active length data are available for these filters.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php else : ?>
				<h2><?php esc_html_e( 'Summary', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Active lengths', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg pace / 100', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Fastest length pace / 100', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Pace CV', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg strokes / length', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Strokes / 25', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg SWOLF', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Stroke coverage', 'chriss-swim-training-progress-evaluation' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $groups as $group ) : ?>
						<tr>
							<td><?php echo esc_html( Admin_UI::display_measurement( $group['pool_length'], $group['course_unit'] ) ); ?></td>
							<td><?php echo esc_html( $strokes[ $group['stroke'] ] ?? $group['stroke'] ); ?></td>
							<td><?php echo esc_html( (int) $group['workout_count'] ); ?></td>
							<td><?php echo esc_html( (int) $group['active_lengths'] ); ?></td>
							<td><?php echo esc_html( Admin_UI::display_measurement( $group['distance_native'], $group['course_unit'] ) ); ?></td>
							<td><?php echo null !== $group['avg_pace_100_ms'] ? esc_html( Workout::format_duration( $group['avg_pace_100_ms'] ) ) : '—'; ?></td>
							<td><?php echo null !== $group['best_pace_100_ms'] ? esc_html( Workout::format_duration( $group['best_pace_100_ms'] ) ) : '—'; ?></td>
							<td><?php echo null !== $group['pace_consistency_cv'] ? esc_html( number_format_i18n( $group['pace_consistency_cv'], 1 ) . '%' ) : '—'; ?></td>
							<td><?php echo null !== $group['avg_stroke_count'] ? esc_html( number_format_i18n( $group['avg_stroke_count'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $group['normalized_strokes_per_25'] ? esc_html( number_format_i18n( $group['normalized_strokes_per_25'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $group['avg_swolf'] ? esc_html( number_format_i18n( $group['avg_swolf'], 1 ) ) : '—'; ?></td>
							<td><?php echo esc_html( number_format_i18n( $group['stroke_count_coverage_percent'], 0 ) . '%' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>

				<h2><?php esc_html_e( 'Monthly Trends', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Month', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg pace / 100', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Pace CV', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg strokes / length', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Strokes / 25', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Avg SWOLF', 'chriss-swim-training-progress-evaluation' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $groups as $group ) : foreach ( $group['months'] as $month ) : ?>
						<tr>
							<td><?php echo esc_html( $month['month'] ); ?></td>
							<td><?php echo esc_html( Admin_UI::display_measurement( $group['pool_length'], $group['course_unit'] ) ); ?></td>
							<td><?php echo esc_html( $strokes[ $group['stroke'] ] ?? $group['stroke'] ); ?></td>
							<td><?php echo esc_html( Admin_UI::display_measurement( $month['distance_native'], $group['course_unit'] ) ); ?></td>
							<td><?php echo null !== $month['avg_pace_100_ms'] ? esc_html( Workout::format_duration( $month['avg_pace_100_ms'] ) ) : '—'; ?></td>
							<td><?php echo null !== $month['pace_consistency_cv'] ? esc_html( number_format_i18n( $month['pace_consistency_cv'], 1 ) . '%' ) : '—'; ?></td>
							<td><?php echo null !== $month['avg_stroke_count'] ? esc_html( number_format_i18n( $month['avg_stroke_count'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $month['normalized_strokes_per_25'] ? esc_html( number_format_i18n( $month['normalized_strokes_per_25'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $month['avg_swolf'] ? esc_html( number_format_i18n( $month['avg_swolf'], 1 ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>
		</div>
		<?php
	}

}

