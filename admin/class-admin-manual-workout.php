<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-ui.php';

/** Manual pool workout administration and form processing. */
final class Admin_Manual_Workout {

	private static $manual_workout_action_error = null;

	private static function posted_array( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) return array();
		return map_deep( wp_unslash( $_POST[ $key ] ), 'sanitize_text_field' );
	}




	public static function handle_manual_workout_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-manual-workout' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_manual_workout_action'] ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['swimlog_manual_workout_action'] ) ); if ( ! in_array( $action, array( 'create', 'update' ), true ) ) return;
		$workout_id = 'update' === $action ? absint( wp_unslash( $_POST['workout_id'] ?? 0 ) ) : 0;
		check_admin_referer( 'update' === $action ? 'swimlog_update_manual_workout_' . $workout_id : 'swimlog_create_manual_workout' );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-manual-workout.php';
		$user_id = Admin_UI::selected_user_id();
		$date = sanitize_text_field( wp_unslash( $_POST['workout_date'] ?? '' ) );
		$time = sanitize_text_field( wp_unslash( $_POST['workout_time'] ?? '' ) );
		$duration_minutes = isset( $_POST['duration_minutes'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['duration_minutes'] ) ) ) ? (float) sanitize_text_field( wp_unslash( $_POST['duration_minutes'] ) ) : null;
		$document = array( 'workout' => array(
			'workout_start' => trim( $date . ' ' . ( '' !== $time ? $time . ':00' : '12:00:00' ) ),
			'location_id' => absint( wp_unslash( $_POST['location_id'] ?? 0 ) ),
			'original_pool_length' => isset( $_POST['pool_length'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['pool_length'] ) ) : 0,
			'pool_length_unit' => sanitize_key( wp_unslash( $_POST['course_unit'] ?? '' ) ),
			'original_distance' => isset( $_POST['total_distance'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['total_distance'] ) ) ) ? (float) sanitize_text_field( wp_unslash( $_POST['total_distance'] ) ) : null,
			'elapsed_time_ms' => null === $duration_minutes ? null : (int) round( $duration_minutes * 60000 ),
			'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		), 'segments' => array() );
		$segment_reps = self::posted_array( 'segment_repetitions' );
		$segment_distances = self::posted_array( 'segment_distance' );
		$segment_strokes = self::posted_array( 'segment_stroke' );
		$segment_notes = self::posted_array( 'segment_notes' );
		$segment_timing = self::posted_array( 'segment_timing_type' );
		$segment_rep_times = self::posted_array( 'segment_rep_times' );
		$segment_set_times = self::posted_array( 'segment_set_time' );
		$segment_intervals = self::posted_array( 'segment_interval' );
		$segment_rests = self::posted_array( 'segment_rest' );
		$segment_count = max( count( $segment_reps ), count( $segment_distances ), count( $segment_strokes ), count( $segment_notes ), count( $segment_timing ), count( $segment_rep_times ), count( $segment_set_times ), count( $segment_intervals ), count( $segment_rests ) );
		for ( $i = 0; $i < $segment_count; $i++ ) {
			$reps_raw = trim( (string) ( $segment_reps[$i] ?? '' ) );
			$distance_raw = trim( (string) ( $segment_distances[$i] ?? '' ) );
			$stroke_raw = sanitize_key( (string) ( $segment_strokes[$i] ?? '' ) );
			$notes_raw = sanitize_textarea_field( (string) ( $segment_notes[$i] ?? '' ) );
			$timing_raw = sanitize_key( (string) ( $segment_timing[$i] ?? 'unknown' ) );
			$rep_times_raw = sanitize_text_field( (string) ( $segment_rep_times[$i] ?? '' ) );
			$set_time_raw = sanitize_text_field( (string) ( $segment_set_times[$i] ?? '' ) );
			$interval_raw = sanitize_text_field( (string) ( $segment_intervals[$i] ?? '' ) );
			$rest_raw = sanitize_text_field( (string) ( $segment_rests[$i] ?? '' ) );
			if ( '' === $reps_raw && '' === $distance_raw && '' === $stroke_raw && '' === $notes_raw && '' === $rep_times_raw && '' === $set_time_raw && '' === $interval_raw && '' === $rest_raw ) continue;
			$rep_times_ms = array();
			if ( 'measured_rep' === $timing_raw && '' !== $rep_times_raw ) foreach ( preg_split( '/[\\s,]+/', $rep_times_raw ) as $time_value ) { $milliseconds = self::manual_time_to_ms( $time_value ); if ( null !== $milliseconds ) $rep_times_ms[] = $milliseconds; }
			$document['segments'][] = array( 'type' => 'active', 'repetitions' => $reps_raw, 'distance_per_rep' => $distance_raw, 'stroke' => strtoupper( $stroke_raw ), 'timing_type' => $timing_raw, 'rep_times_ms' => $rep_times_ms, 'set_time_ms' => self::manual_time_to_ms( $set_time_raw ), 'interval_ms' => self::manual_time_to_ms( $interval_raw ), 'rest_time_ms' => self::manual_time_to_ms( $rest_raw ), 'notes' => $notes_raw );
		}
		$result = Manual_Workout::save( $user_id, $document, $workout_id );
		if ( is_wp_error( $result ) ) { self::$manual_workout_action_error = $result->get_error_message(); return; }
		wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => (int) $result['workout_id'], 'manual_saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function manual_time_to_ms( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) return null;
		if ( preg_match( '/^(?:(\\d+):)?(\\d{1,2})(?:\\.(\\d{1,3}))?$/', $value, $m ) ) {
			$minutes = isset( $m[1] ) && '' !== $m[1] ? (int) $m[1] : 0; $seconds = (int) $m[2];
			if ( $seconds >= 60 ) return null;
			$fraction = isset( $m[3] ) ? str_pad( $m[3], 3, '0' ) : '000';
			return ( ( $minutes * 60 + $seconds ) * 1000 ) + (int) $fraction;
		}
		return null;
	}


	public static function manual_workout() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to manage workouts.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php'; require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php'; require_once SWIMLOG_EVALUATION_DIR . 'includes/class-manual-workout.php';
		$user_id = Admin_UI::selected_user_id(); $locations = Location::all_for_user( $user_id ); $error = self::$manual_workout_action_error;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only manual workout selector; ownership is enforced by Manual_Workout::get_document().
		$workout_id = absint( wp_unslash( $_GET['workout_id'] ?? 0 ) ); $editing = $workout_id > 0; $source = null;
		if ( $editing ) { $source = Manual_Workout::get_document( $workout_id, $user_id ); if ( is_wp_error( $source ) ) wp_die( esc_html( $source->get_error_message() ) ); }
		Admin_UI::swimmer_selector( $user_id, 'swimlog-manual-workout' );
		$workout = $source['workout'] ?? array(); $segments = $source['segments'] ?? array(); $start_value = (string) ( $workout['workout_start'] ?? '' );
		$date = sanitize_text_field( wp_unslash( $_POST['workout_date'] ?? ( $start_value ? substr( $start_value, 0, 10 ) : wp_date( 'Y-m-d' ) ) ) );
		$time = sanitize_text_field( wp_unslash( $_POST['workout_time'] ?? ( $start_value && '12:00:00' !== substr( $start_value, 11, 8 ) ? substr( $start_value, 11, 5 ) : '' ) ) );
		$ms_to_time = static function( $ms ){ if(null===$ms||$ms<=0)return'';$seconds=$ms/1000;$minutes=(int)floor($seconds/60);$seconds-=$minutes*60;$formatted=number_format($seconds,3,'.','');$formatted=rtrim(rtrim($formatted,'0'),'.');return $minutes>0?$minutes.':'.str_pad($formatted,2,'0',STR_PAD_LEFT):$formatted; };
		if ( empty( $_POST ) && $editing ) { $_POST['location_id']=$workout['location_id']??0; $_POST['pool_length']=$workout['original_pool_length']??25; $_POST['course_unit']=$workout['pool_length_unit']??'yd'; $_POST['total_distance']=$workout['original_distance']??''; $_POST['duration_minutes']=null!==($workout['elapsed_time_ms']??null)?$workout['elapsed_time_ms']/60000:''; $_POST['notes']=$workout['notes']??''; foreach($segments as$segment){$_POST['segment_repetitions'][]=$segment['repetitions']??'';$_POST['segment_distance'][]=$segment['distance_per_rep']??'';$_POST['segment_stroke'][]=$segment['stroke']??'';$_POST['segment_timing_type'][]=$segment['timing_type']??'unknown';$_POST['segment_rep_times'][]=implode(', ',array_map($ms_to_time,$segment['rep_times_ms']??array()));$_POST['segment_set_time'][]=$ms_to_time($segment['set_time_ms']??null);$_POST['segment_interval'][]=$ms_to_time($segment['interval_ms']??null);$_POST['segment_rest'][]=$ms_to_time($segment['rest_time_ms']??null);$_POST['segment_notes'][]=$segment['notes']??'';} }
		$posted_reps=self::posted_array( 'segment_repetitions' );$posted_distance=self::posted_array( 'segment_distance' );$posted_stroke=self::posted_array( 'segment_stroke' );$posted_notes=self::posted_array( 'segment_notes' );$posted_timing=self::posted_array( 'segment_timing_type' );$posted_rep_times=self::posted_array( 'segment_rep_times' );$posted_set_time=self::posted_array( 'segment_set_time' );$posted_interval=self::posted_array( 'segment_interval' );$posted_rest=self::posted_array( 'segment_rest' ); $row_count=max(12,count($posted_reps));
		?><div class="wrap swimlog-admin" data-swimlog-manual-workout="1"><h1><?php echo esc_html($editing?__('Edit Manual Workout','chriss-swim-training-progress-evaluation'):__('Add Manual Workout','chriss-swim-training-progress-evaluation')); ?></h1>
		<p><?php esc_html_e( 'Record the workout as you know it. Timing is optional; only explicitly measured repetition times can become performance evidence.', 'chriss-swim-training-progress-evaluation' ); ?></p><p class="description"><?php esc_html_e( 'You may enter only workout totals, or add ordered sets. Measured reps require one actual swim time per repetition. Measured-set times and interval/send-off values describe the workout but are not split into personal-best times. Untimed sets still count toward training volume. Manual entry is for pool workouts and does not create open-water GPS routes.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif;?>
		<form method="post" id="swimlog-manual-workout-form"><?php wp_nonce_field($editing?'swimlog_update_manual_workout_'.$workout_id:'swimlog_create_manual_workout'); ?><input type="hidden" name="swimlog_manual_workout_action" value="<?php echo esc_attr($editing?'update':'create'); ?>"><?php if($editing):?><input type="hidden" name="workout_id" value="<?php echo esc_attr($workout_id); ?>"><?php endif;?>
		<table class="form-table" role="presentation"><tr><th><label for="swimlog-manual-date"><?php esc_html_e('Date','chriss-swim-training-progress-evaluation');?></label></th><td><input required id="swimlog-manual-date" name="workout_date" type="date" value="<?php echo esc_attr($date); ?>"></td></tr><tr><th><label for="swimlog-manual-time"><?php esc_html_e('Start time','chriss-swim-training-progress-evaluation');?></label></th><td><input id="swimlog-manual-time" name="workout_time" type="time" value="<?php echo esc_attr($time); ?>"><p class="description"><?php esc_html_e('Optional; if omitted, noon is stored to preserve the selected local date.','chriss-swim-training-progress-evaluation');?></p></td></tr>
		<tr><th><label for="swimlog-manual-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation');?></label></th><td><select id="swimlog-manual-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation');?></option><?php foreach($locations as$loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected(absint(wp_unslash($_POST['location_id']??0)),(int)$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select></td></tr>
		<tr><th><label for="swimlog-manual-pool-length"><?php esc_html_e('Pool length','chriss-swim-training-progress-evaluation');?></label></th><td><input required id="swimlog-manual-pool-length" name="pool_length" type="number" min="1" step="0.001" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['pool_length']??'25'))); ?>"> <select required name="course_unit"><option value="yd" <?php selected(sanitize_key(wp_unslash($_POST['course_unit']??'yd')),'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation');?></option><option value="m" <?php selected(sanitize_key(wp_unslash($_POST['course_unit']??'yd')),'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation');?></option></select></td></tr>
		<tr><th><label for="swimlog-manual-distance"><?php esc_html_e('Total distance','chriss-swim-training-progress-evaluation');?></label></th><td><input id="swimlog-manual-distance" name="total_distance" type="number" min="0" step="0.001" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['total_distance']??''))); ?>"></td></tr><tr><th><label for="swimlog-manual-duration"><?php esc_html_e('Workout duration','chriss-swim-training-progress-evaluation');?></label></th><td><input id="swimlog-manual-duration" name="duration_minutes" type="number" min="0" step="0.01" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['duration_minutes']??''))); ?>"> <?php esc_html_e('minutes','chriss-swim-training-progress-evaluation');?></td></tr><tr><th><label for="swimlog-manual-notes"><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation');?></label></th><td><textarea class="large-text" rows="4" id="swimlog-manual-notes" name="notes"><?php echo esc_textarea(wp_unslash($_POST['notes']??'')); ?></textarea></td></tr></table>
		<h2><?php esc_html_e('Workout Sets','chriss-swim-training-progress-evaluation');?></h2><p class="description"><?php esc_html_e('Rows are ordered. Add more rows as needed. Times use M:SS[.mmm] or SS[.mmm].','chriss-swim-training-progress-evaluation');?></p><div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Set','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Reps','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Timing','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Rep times','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Set time','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Interval','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Rest','chriss-swim-training-progress-evaluation');?></th><th><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation');?></th></tr></thead><tbody id="swimlog-manual-set-rows">
		<?php for($set_row=0;$set_row<$row_count;$set_row++):?><tr class="swimlog-manual-set-row"><th class="swimlog-set-number"><?php echo esc_html((string)($set_row+1));?></th><td><input name="segment_repetitions[]" type="number" min="1" step="1" value="<?php echo esc_attr(sanitize_text_field($posted_reps[$set_row]??''));?>"></td><td><input name="segment_distance[]" type="number" min="0.001" step="0.001" value="<?php echo esc_attr(sanitize_text_field($posted_distance[$set_row]??''));?>"></td><td><select name="segment_stroke[]"><option value=""><?php esc_html_e('Unknown','chriss-swim-training-progress-evaluation');?></option><?php foreach(array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'))as$code=>$label):?><option value="<?php echo esc_attr($code);?>" <?php selected(strtoupper(sanitize_key($posted_stroke[$set_row]??'')),$code);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></td><td><select name="segment_timing_type[]"><?php foreach(array('unknown'=>__('Unknown','chriss-swim-training-progress-evaluation'),'measured_rep'=>__('Measured reps','chriss-swim-training-progress-evaluation'),'measured_set'=>__('Measured set','chriss-swim-training-progress-evaluation'),'interval'=>__('Interval/send-off','chriss-swim-training-progress-evaluation'))as$code=>$label):?><option value="<?php echo esc_attr($code);?>" <?php selected(sanitize_key($posted_timing[$set_row]??'unknown'),$code);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></td><td><input name="segment_rep_times[]" type="text" value="<?php echo esc_attr(sanitize_text_field($posted_rep_times[$set_row]??''));?>"></td><td><input name="segment_set_time[]" type="text" value="<?php echo esc_attr(sanitize_text_field($posted_set_time[$set_row]??''));?>"></td><td><input name="segment_interval[]" type="text" value="<?php echo esc_attr(sanitize_text_field($posted_interval[$set_row]??''));?>"></td><td><input name="segment_rest[]" type="text" value="<?php echo esc_attr(sanitize_text_field($posted_rest[$set_row]??''));?>"></td><td><input name="segment_notes[]" type="text" value="<?php echo esc_attr(sanitize_text_field($posted_notes[$set_row]??''));?>"></td></tr><?php endfor;?></tbody></table></div>
		<p><button type="button" class="button" id="swimlog-add-set-row"><?php esc_html_e('Add Set Row','chriss-swim-training-progress-evaluation');?></button></p><?php submit_button($editing?__('Save Manual Workout','chriss-swim-training-progress-evaluation'):__('Create Manual Workout','chriss-swim-training-progress-evaluation')); ?><?php if($editing):?> <a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$workout_id),admin_url('admin.php'))); ?>"><?php esc_html_e('Cancel','chriss-swim-training-progress-evaluation');?></a><?php endif;?></form></div><?php
	}



}
