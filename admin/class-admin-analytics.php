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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filters; selected swimmer authorization is handled by Admin_UI.
        $query = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' );
        if ( isset( $query['months'] ) && is_scalar( $query['months'] ) ) $training_volume_months = max( 1, min( 24, absint( $query['months'] ) ) );
        $course = isset( $query['course'] ) && is_string( $query['course'] ) && in_array( $query['course'], array( 'm', 'yd' ), true ) ? $query['course'] : '';
        $stroke = isset( $query['stroke'] ) && is_string( $query['stroke'] ) && isset( $strokes[$query['stroke']] ) ? $query['stroke'] : '';
        $stroke_volume=Workout::stroke_volume_for_user($user_id,$training_volume_months);
        $stroke_volume = array_values( array_filter( $stroke_volume, static function ( $row ) use ( $course, $stroke ) { return ( '' === $course || $course === $row->course_unit ) && ( '' === $stroke || $stroke === $row->stroke ); } ) );
		$stroke_volume_by_course=array('m'=>array(),'yd'=>array());foreach($stroke_volume as$volume){if(isset($stroke_volume_by_course[$volume->course_unit]))$stroke_volume_by_course[$volume->course_unit][]=$volume;}
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Pool Training Volume by Stroke','chriss-swim-training-progress-evaluation'); ?></h1>
		<p><?php echo esc_html(sprintf(/* translators: %d: training-volume period in months. */ __('Last %d months','chriss-swim-training-progress-evaluation'),$training_volume_months)); ?></p>
		<p class="description"><?php esc_html_e('Swimming only: distance and time come from active pool lengths. Rest, open-water swims, running and walking are excluded. Meter and yard courses are kept separate and strokes are ranked by total distance. The rolling period below applies to this report; your saved preference is unchanged.','chriss-swim-training-progress-evaluation'); ?></p>
        <form method="get" class="swimlog-analytics-filters">
        <input type="hidden" name="page" value="swimlog-training-volume"><?php Admin_UI::context_fields( $user_id ); ?>
        <label for="swimlog-volume-months"><?php esc_html_e('Period (months)','chriss-swim-training-progress-evaluation'); ?></label>
        <input id="swimlog-volume-months" name="months" type="number" min="1" max="24" value="<?php echo esc_attr($training_volume_months); ?>">
        <label for="swimlog-volume-course"><?php esc_html_e('Course','chriss-swim-training-progress-evaluation'); ?></label>
        <select id="swimlog-volume-course" name="course"><option value=""><?php esc_html_e('All courses (shown separately)','chriss-swim-training-progress-evaluation'); ?></option><?php foreach(array('m'=>__('Meters','chriss-swim-training-progress-evaluation'),'yd'=>__('Yards','chriss-swim-training-progress-evaluation'))as$value=>$label):?><option value="<?php echo esc_attr($value); ?>" <?php selected($course,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select>
        <label for="swimlog-volume-stroke"><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></label>
        <select id="swimlog-volume-stroke" name="stroke"><option value=""><?php esc_html_e('All strokes','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($strokes as$value=>$label):?><option value="<?php echo esc_attr($value); ?>" <?php selected($stroke,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select>
        <?php submit_button(__('Apply','chriss-swim-training-progress-evaluation'),'secondary','',false); ?></form>
        <?php self::report_links( $user_id, $training_volume_months, $course, $stroke ); ?>
		<?php if(!$stroke_volume):?><p><?php esc_html_e('No active pool lengths match these filters.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?>
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
			<h1><?php esc_html_e( 'Swimming Volume Trends', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Swimming only: compare recorded pool and open-water distance, sessions and active time over rolling periods. Running and walking are excluded. This report does not estimate physiological stress, recovery, fitness, or injury risk. Course and stroke comparisons are available in the pool reports.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<form method="get" class="swimlog-analytics-filters">
				<input type="hidden" name="page" value="swimlog-training-trends">
				<?php Admin_UI::context_fields( $user_id ); ?>
				<label for="swimlog-trends-months"><strong><?php esc_html_e( 'Period (months)', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<input id="swimlog-trends-months" name="months" type="number" min="1" max="<?php echo esc_attr( Training_Trends::MAX_LOOKBACK_MONTHS ); ?>" step="1" value="<?php echo esc_attr( $months ); ?>">
                <?php submit_button( __( 'Apply', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?>
			</form>

            <?php self::report_links( $user_id, $months ); ?>
            <p><?php echo esc_html( sprintf( /* translators: 1: start of current period, 2: end, 3: start of previous period. */ __('Current: %1$s to %2$s; previous: %3$s up to the current-period start (site timezone).','chriss-swim-training-progress-evaluation'), $data['window']['current_start'], $data['window']['end'], $data['window']['previous_start'] ) ); ?></p>
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optional report columns.
        $additional = isset( $_GET['additional_metrics'] ) && '1' === $_GET['additional_metrics'];
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
			<form method="get" class="swimlog-analytics-filters">
				<input type="hidden" name="page" value="swimlog-pool-analytics">
				<?php Admin_UI::context_fields( $user_id ); ?>
				<label for="swimlog-analytics-course"><strong><?php esc_html_e( 'Course', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<select id="swimlog-analytics-course" name="course">
					<option value="m" <?php selected( $course, 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option>
					<option value="yd" <?php selected( $course, 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option>
				</select>
				<label for="swimlog-analytics-stroke"><strong><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<select id="swimlog-analytics-stroke" name="stroke">
					<?php foreach ( $strokes as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $stroke, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select>
				<label for="swimlog-analytics-months"><strong><?php esc_html_e( 'Period (months)', 'chriss-swim-training-progress-evaluation' ); ?></strong></label>
				<input id="swimlog-analytics-months" name="months" type="number" min="1" max="<?php echo esc_attr( Pool_Analytics::MAX_LOOKBACK_MONTHS ); ?>" step="1" value="<?php echo esc_attr( $months ); ?>">
                <label><input type="checkbox" name="additional_metrics" value="1" <?php checked($additional); ?>> <?php esc_html_e('Show additional metrics','chriss-swim-training-progress-evaluation'); ?></label>
                <?php submit_button( __( 'Apply', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?>
			</form>

            <?php self::report_links( $user_id, $months, $course, $stroke ); ?>
            <details class="swimlog-metric-help"><summary><?php esc_html_e('What do these metrics mean?','chriss-swim-training-progress-evaluation'); ?></summary><p class="description"><?php esc_html_e( 'Average pace is distance-weighted from recorded length timing. Pace consistency is the coefficient of variation of per-length pace; lower values mean more consistent pacing. SWOLF is elapsed seconds plus recorded strokes for the same length and is pool-length dependent, so compare it only within the same pool length and similar recording conventions.', 'chriss-swim-training-progress-evaluation' ); ?></p><dl>
                <dt><?php esc_html_e('Average pace','chriss-swim-training-progress-evaluation'); ?></dt><dd><?php esc_html_e('Recorded swim time per 100 meters or yards of timed active lengths. A lower time is faster. Untimed lengths do not enter the pace calculation.','chriss-swim-training-progress-evaluation'); ?></dd>
                <dt><?php esc_html_e('Pace variation (CV)','chriss-swim-training-progress-evaluation'); ?></dt><dd><?php esc_html_e('How much length pace varies relative to its average, expressed as a percentage. Lower means steadier pacing; at least two timed lengths are needed.','chriss-swim-training-progress-evaluation'); ?></dd>
                <dt><?php esc_html_e('Stroke counts and coverage','chriss-swim-training-progress-evaluation'); ?></dt><dd><?php esc_html_e('Strokes per length uses only lengths with recorded counts. Strokes per 25 rescales each count to 25 meters or yards. Coverage is the percentage of active lengths with counts; missing counts are not zero.','chriss-swim-training-progress-evaluation'); ?></dd>
                <dt><?php esc_html_e('SWOLF','chriss-swim-training-progress-evaluation'); ?></dt><dd><?php esc_html_e('Seconds plus strokes for the same length, averaged over lengths with both values. Compare only the same pool length, stroke and recording convention. This is not a universal fitness score. A dash means insufficient recorded data.','chriss-swim-training-progress-evaluation'); ?></dd>
                </dl></details>

			<?php if ( ! $groups ) : ?>
				<p><?php esc_html_e( 'No qualifying active length data are available for these filters.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php else : ?>
				<h2><?php esc_html_e( 'Summary', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Workouts', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Active lengths', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th><?php echo esc_html( sprintf( /* translators: %s: course unit m or yd. */ __('Average pace / 100 %s','chriss-swim-training-progress-evaluation'), $course ) ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo esc_html( sprintf( /* translators: %s: course unit m or yd. */ __('Fastest length pace / 100 %s','chriss-swim-training-progress-evaluation'), $course ) ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Pace variation (CV, %)', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Avg strokes / length', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo esc_html( sprintf( /* translators: %s: course unit m or yd. */ __('Strokes / 25 %s','chriss-swim-training-progress-evaluation'), $course ) ); ?></th>
						<th><?php esc_html_e( 'Avg SWOLF', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Lengths with stroke counts (%)', 'chriss-swim-training-progress-evaluation' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $groups as $group ) : ?>
						<tr>
							<td><?php echo esc_html( Admin_UI::display_measurement( $group['pool_length'], $group['course_unit'] ) ); ?></td>
							<td><?php echo esc_html( $strokes[ $group['stroke'] ] ?? $group['stroke'] ); ?></td>
							<td><?php echo esc_html( (int) $group['workout_count'] ); ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo esc_html( (int) $group['active_lengths'] ); ?></td>
							<td><?php echo esc_html( Admin_UI::display_measurement( $group['distance_native'], $group['course_unit'] ) ); ?></td>
							<td><?php echo null !== $group['avg_pace_100_ms'] ? esc_html( Workout::format_duration( $group['avg_pace_100_ms'] ) ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $group['best_pace_100_ms'] ? esc_html( Workout::format_duration( $group['best_pace_100_ms'] ) ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $group['pace_consistency_cv'] ? esc_html( number_format_i18n( $group['pace_consistency_cv'], 1 ) . '%' ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $group['avg_stroke_count'] ? esc_html( number_format_i18n( $group['avg_stroke_count'], 1 ) ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $group['normalized_strokes_per_25'] ? esc_html( number_format_i18n( $group['normalized_strokes_per_25'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $group['avg_swolf'] ? esc_html( number_format_i18n( $group['avg_swolf'], 1 ) ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo esc_html( number_format_i18n( $group['stroke_count_coverage_percent'], 0 ) . '%' ); ?></td>
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
						<th><?php echo esc_html( sprintf( /* translators: %s: course unit m or yd. */ __('Average pace / 100 %s','chriss-swim-training-progress-evaluation'), $course ) ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Pace variation (CV, %)', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php esc_html_e( 'Avg strokes / length', 'chriss-swim-training-progress-evaluation' ); ?></th>
						<th <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo esc_html( sprintf( /* translators: %s: course unit m or yd. */ __('Strokes / 25 %s','chriss-swim-training-progress-evaluation'), $course ) ); ?></th>
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
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $month['pace_consistency_cv'] ? esc_html( number_format_i18n( $month['pace_consistency_cv'], 1 ) . '%' ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $month['avg_stroke_count'] ? esc_html( number_format_i18n( $month['avg_stroke_count'], 1 ) ) : '—'; ?></td>
							<td <?php if ( ! $additional ) echo 'hidden'; ?>><?php echo null !== $month['normalized_strokes_per_25'] ? esc_html( number_format_i18n( $month['normalized_strokes_per_25'], 1 ) ) : '—'; ?></td>
							<td><?php echo null !== $month['avg_swolf'] ? esc_html( number_format_i18n( $month['avg_swolf'], 1 ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>
		</div>
		<?php
	}

    private static function report_links( $user_id, $months, $course = '', $stroke = '' ) {
        echo '<nav class="swimlog-report-links" aria-label="' . esc_attr__('Related swimming reports','chriss-swim-training-progress-evaluation') . '">';
        foreach ( array('swimlog-training-volume'=>__('Training Volume','chriss-swim-training-progress-evaluation'),'swimlog-training-trends'=>__('Training Trends','chriss-swim-training-progress-evaluation'),'swimlog-pool-analytics'=>__('Pool Analytics','chriss-swim-training-progress-evaluation')) as $page=>$label ) {
            $args=array('page'=>$page,'months'=>$months);
            if ('swimlog-training-trends' !== $page) { if ($course) $args['course']=$course; if ($stroke) $args['stroke']=$stroke; }
            echo '<a href="' . esc_url(Admin_UI::url($args,$user_id)) . '">' . esc_html($label) . '</a> ';
        }
        echo '</nav>';
    }

}


