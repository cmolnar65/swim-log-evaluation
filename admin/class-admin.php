<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers the swim training wp-admin shell and capability-gated pages. */
final class Admin {
	public function register_menu() {
		add_menu_page(
			__( 'Swim Training', 'chriss-swim-training-progress-evaluation' ),
			__( 'Swim Training', 'chriss-swim-training-progress-evaluation' ),
			'swimlog_view_own_results',
			'swimlog-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-chart-line',
			56
		);

		$this->submenu( 'swimlog-dashboard', __( 'Dashboard', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-dashboard', 'dashboard' );
		$this->submenu( 'swimlog-dashboard', __( 'Workouts', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-workouts', 'workouts' );
		$this->submenu( 'swimlog-dashboard', __( 'Upload Workout', 'chriss-swim-training-progress-evaluation' ), 'swimlog_upload_workouts', 'swimlog-upload', 'upload' );
		$this->submenu( 'swimlog-dashboard', __( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-personal-bests', 'personal_bests' );
		$this->submenu( 'swimlog-dashboard', __( 'Events', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_events', 'swimlog-events', 'events' );
		$this->submenu( 'swimlog-dashboard', __( 'Locations', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_locations', 'swimlog-locations', 'locations' );
		$this->submenu( 'swimlog-dashboard', __( 'Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-privacy', 'privacy' );
		$this->submenu( 'swimlog-dashboard', __( 'Garmin Connect', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-garmin', 'garmin' );

		if ( current_user_can( 'swimlog_manage_settings' ) ) {
			$this->submenu( 'swimlog-dashboard', __( 'Shortcodes', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcodes', 'shortcodes' );
			add_submenu_page( null, __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcode-bests', array( $this, 'shortcode_bests' ) );
			$this->submenu( 'swimlog-dashboard', __( 'Settings', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-settings', 'settings' );
		}
	}

	private function selected_user_id(){
		$uid=get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin swimmer selector; no state is changed.
		if(current_user_can('swimlog_manage_all_users')&&!empty($_GET['swimmer_id'])){$requested=absint(wp_unslash($_GET['swimmer_id']));if($requested&&get_userdata($requested))$uid=$requested;}return$uid;
	}
	private function swimmer_selector($user_id,$page){
		if(!current_user_can('swimlog_manage_all_users'))return;$users=get_users(array('fields'=>array('ID','display_name'),'orderby'=>'display_name'));?><form method="get" class="swimlog-swimmer-selector"><input type="hidden" name="page" value="<?php echo esc_attr($page); ?>"><label for="swimlog-swimmer"><?php esc_html_e('Swimmer','chriss-swim-training-progress-evaluation'); ?></label> <select id="swimlog-swimmer" name="swimmer_id"><?php foreach($users as$u):?><option value="<?php echo esc_attr($u->ID); ?>" <?php selected($user_id,$u->ID); ?>><?php echo esc_html($u->display_name.' (#'.$u->ID.')'); ?></option><?php endforeach;?></select> <?php submit_button(__('View','chriss-swim-training-progress-evaluation'),'secondary','',false); ?></form><?php
	}
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'swimlog' ) ) {
			return;
		}

		$admin_css = SWIMLOG_EVALUATION_DIR . 'assets/css/admin.css';
		wp_enqueue_style(
			'swimlog-admin',
			SWIMLOG_EVALUATION_URL . 'assets/css/admin.css',
			array(),
			is_file( $admin_css ) ? SWIMLOG_EVALUATION_VERSION . '.' . filemtime( $admin_css ) : SWIMLOG_EVALUATION_VERSION
		);
		$admin_js = SWIMLOG_EVALUATION_DIR . 'assets/js/admin.js';
		if ( is_file( $admin_js ) ) {
			wp_enqueue_script(
				'swimlog-admin',
				SWIMLOG_EVALUATION_URL . 'assets/js/admin.js',
				array(),
				SWIMLOG_EVALUATION_VERSION . '.' . filemtime( $admin_js ),
				true
			);
		}
	}
	private function submenu( $parent, $title, $capability, $slug, $method ) {
		add_submenu_page( $parent, $title, $title, $capability, $slug, array( $this, $method ) );
	}

	private function display_measurement( $value, $unit ) {
		if ( null === $value || '' === $value ) return '—';
		$number = (float) $value;
		$display = abs( $number - round( $number ) ) < 0.0005
			? number_format_i18n( $number, 0 )
			: rtrim( rtrim( number_format_i18n( $number, 3 ), '0' ), '.' );
		return $display . ( $unit ? ' ' . $unit : '' );
	}


	private function recording_device_label( $workout ) {
		$manufacturer = trim( (string) ( $workout->device_manufacturer ?? '' ) );
		$model = trim( (string) ( $workout->device_model ?? '' ) );
		$combined = trim( $manufacturer . ' ' . $model );

		if ( false !== stripos( $combined, 'FORM' ) ) {
			return false !== stripos( $model, 'FORM' ) ? $model : 'FORM Swim Goggles';
		}
		if ( '' === $manufacturer ) return '' !== $model ? $model : '—';
		if ( '' === $model ) return $manufacturer;
		if ( 0 === stripos( $model, $manufacturer ) ) return $model;
		return $manufacturer . ' ' . $model;
	}

	private function open_water_pace_per_100m( $workout ) {
		$distance_m = (float) ( $workout->total_distance_m ?? 0 );
		$elapsed_ms = (int) ( $workout->elapsed_time_ms ?? 0 );
		if ( $distance_m <= 0 || $elapsed_ms <= 0 ) return '—';
		return Workout::format_duration( (int) round( $elapsed_ms / ( $distance_m / 100 ) ) ) . ' / 100 m';
	}

	private function open_water_route_svg( $trackpoints ) {
		$points=array();foreach((array)$trackpoints as$point){if(null===$point->latitude||null===$point->longitude)continue;$points[]=array((float)$point->latitude,(float)$point->longitude);}if(count($points)<2)return '';
		$lats=array_column($points,0);$lons=array_column($points,1);$min_lat=min($lats);$max_lat=max($lats);$min_lon=min($lons);$max_lon=max($lons);$lat_span=max(0.000001,$max_lat-$min_lat);$lon_span=max(0.000001,$max_lon-$min_lon);$width=800;$height=400;$padding=35;$scale=min(($width-2*$padding)/$lon_span,($height-2*$padding)/$lat_span);$route_width=$lon_span*$scale;$route_height=$lat_span*$scale;$x_offset=($width-$route_width)/2;$y_offset=($height-$route_height)/2;$svg_points=array();
		foreach($points as$p){$x=$x_offset+($p[1]-$min_lon)*$scale;$y=$height-($y_offset+($p[0]-$min_lat)*$scale);$svg_points[]=number_format($x,2,'.','').','.number_format($y,2,'.','');}$start=explode(',',$svg_points[0]);$finish=explode(',',$svg_points[count($svg_points)-1]);$grid='';foreach(array(.25,.5,.75)as$ratio){$gx=round($padding+($width-2*$padding)*$ratio,1);$gy=round($padding+($height-2*$padding)*$ratio,1);$grid.='<line x1="'.$gx.'" y1="'.$padding.'" x2="'.$gx.'" y2="'.($height-$padding).'" stroke="currentColor" stroke-width="1" opacity=".14"/><line x1="'.$padding.'" y1="'.$gy.'" x2="'.($width-$padding).'" y2="'.$gy.'" stroke="currentColor" stroke-width="1" opacity=".14"/>';}
		return '<svg class="swimlog-ows-route" viewBox="0 0 800 400" role="img" aria-label="'.esc_attr__('Open-water GPS route map','chriss-swim-training-progress-evaluation').'" style="width:100%;max-width:900px;height:auto;border:1px solid #c3c4c7;background:#fff">'.$grid.'<text x="20" y="28" font-size="15" font-weight="600">N</text><polyline points="'.esc_attr(implode(' ',$svg_points)).'" fill="none" stroke="currentColor" stroke-width="3" vector-effect="non-scaling-stroke"/><circle cx="'.esc_attr($start[0]).'" cy="'.esc_attr($start[1]).'" r="6" fill="#fff" stroke="currentColor" stroke-width="3"/><circle cx="'.esc_attr($finish[0]).'" cy="'.esc_attr($finish[1]).'" r="6" fill="currentColor" stroke="currentColor" stroke-width="3"/><text x="'.esc_attr((float)$start[0]+9).'" y="'.esc_attr((float)$start[1]-9).'" font-size="15">'.esc_html__('Start','chriss-swim-training-progress-evaluation').'</text><text x="'.esc_attr((float)$finish[0]+9).'" y="'.esc_attr((float)$finish[1]+16).'" font-size="15">'.esc_html__('Finish','chriss-swim-training-progress-evaluation').'</text></svg>';
	}
	private function open_water_metric_svg( $trackpoints, $field, $label, $unit ) {
		$samples = array();
		$start = null;
		foreach ( (array) $trackpoints as $point ) {
			$value = $point->{$field} ?? null;
			if ( null === $value || '' === $value ) continue;
			try { $time = new \DateTimeImmutable( (string) $point->recorded_at, wp_timezone() ); } catch ( \Exception $e ) { continue; }
			if ( null === $start ) $start = $time;
			$seconds = max( 0, $time->getTimestamp() - $start->getTimestamp() );
			$samples[] = array( $seconds, (float) $value );
		}
		if ( count( $samples ) < 2 ) return '';
		$values = array_column( $samples, 1 ); $max_time = max( 1, max( array_column( $samples, 0 ) ) );
		$min_value = min( $values ); $max_value = max( $values ); $span = max( 1.0, $max_value - $min_value );
		$width = 800.0; $height = 240.0; $left = 55.0; $right = 20.0; $top = 20.0; $bottom = 38.0;
		$plot_width = $width - $left - $right; $plot_height = $height - $top - $bottom; $svg_points = array();
		foreach ( $samples as $sample ) {
			$x = $left + ( $sample[0] / $max_time ) * $plot_width;
			$y = $top + ( 1 - ( ( $sample[1] - $min_value ) / $span ) ) * $plot_height;
			$svg_points[] = number_format( $x, 2, '.', '' ) . ',' . number_format( $y, 2, '.', '' );
		}
		$duration = Workout::format_duration( $max_time * 1000 );
		return '<svg class="swimlog-ows-metric" viewBox="0 0 800 240" role="img" aria-label="' . esc_attr( $label ) . '" style="width:100%;max-width:900px;height:auto;border:1px solid #c3c4c7;background:#fff">'
			. '<line x1="55" y1="20" x2="55" y2="202" stroke="currentColor" stroke-width="1"/><line x1="55" y1="202" x2="780" y2="202" stroke="currentColor" stroke-width="1"/>'
			. '<polyline points="' . esc_attr( implode( ' ', $svg_points ) ) . '" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/>'
			. '<text x="8" y="28" font-size="12">' . esc_html( number_format_i18n( $max_value, 0 ) . ' ' . $unit ) . '</text>'
			. '<text x="8" y="202" font-size="12">' . esc_html( number_format_i18n( $min_value, 0 ) . ' ' . $unit ) . '</text>'
			. '<text x="55" y="225" font-size="12">0:00</text><text x="780" y="225" text-anchor="end" font-size="12">' . esc_html( $duration ) . '</text>'
			. '</svg>';
	}


	private function page( $title, $description, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}
		?>
		<div class="wrap swimlog-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	public function dashboard() {
		if(!current_user_can('swimlog_view_own_results'))wp_die(esc_html__('You do not have permission to view these results.','chriss-swim-training-progress-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-event.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-records.php';
		$user_id=$this->selected_user_id();$this->swimmer_selector($user_id,'swimlog-dashboard');
		$course = get_option('swimlog_default_course_unit','m');
		if ( isset( $_GET['course'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only course filter.
			$course = sanitize_key( wp_unslash( $_GET['course'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}if(!in_array($course,array('m','yd'),true))$course='m';
		$recent=Workout::recent_for_user($user_id,5);$latest=$recent[0]??null;$pbs=Records::personal_bests($user_id,$course);$event_count=max(1,min(50,(int)get_option('swimlog_default_event_count',5)));$events=Event::upcoming_for_user($user_id,$event_count);
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'),'UNKNOWN'=>__('Unknown','chriss-swim-training-progress-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Training Dashboard','chriss-swim-training-progress-evaluation'); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-upload')); ?>"><?php esc_html_e('Upload Workout','chriss-swim-training-progress-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Add Event','chriss-swim-training-progress-evaluation'); ?></a></p>
		<h2><?php esc_html_e('Latest Swim','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$latest):?><p><?php esc_html_e('No workouts have been imported yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><tbody><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(mysql2date('F j, Y g:i a',$latest->workout_start,false)); ?></td></tr><tr><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($latest->location_name?:'—'); ?></td></tr><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$latest->original_distance?$latest->original_distance.' '.$latest->original_distance_unit:($latest->total_distance_m?$latest->total_distance_m.' m':'—')); ?></td></tr><tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($latest->elapsed_time_ms)); ?></td></tr></tbody></table><p><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$latest->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View latest workout','chriss-swim-training-progress-evaluation'); ?></a></p><?php endif;?>
		<h2><?php esc_html_e('Personal Best Summary','chriss-swim-training-progress-evaluation'); ?></h2><p><a class="button <?php echo $course==='m'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=m')); ?>"><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></a> <a class="button <?php echo $course==='yd'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=yd')); ?>"><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></a> <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course='.$course)); ?>"><?php esc_html_e('View all personal bests','chriss-swim-training-progress-evaluation'); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Overall','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Freestyle','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Breaststroke','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach(array(50,100,200,500,800,1500) as $d):?><tr><th><?php echo esc_html($d.' '.$course); ?></th><?php foreach(array('OVERALL','FR','BR') as $s):$p=$pbs[$d][$s]??null;?><td><?php echo $p?esc_html(Workout::format_duration($p->duration_ms)):'—'; ?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
		<h2><?php esc_html_e('Upcoming Events','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$events):?><p><?php esc_html_e('No upcoming events are scheduled.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('Readiness is descriptive, not predictive. Recent training uses the last 42 days and only exact course/stroke matches.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Event','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Current PB','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent matching volume','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent distance evidence','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($events as $e):$pb=Records::exact_personal_best($user_id,(int)$e->distance_value,$e->course_unit,$e->stroke);$ready=Workout::event_readiness($user_id,$e,42);?><tr><td><?php echo esc_html(wp_date('F j, Y',(new \DateTimeImmutable($e->event_date.' 12:00:00',wp_timezone()))->getTimestamp(),wp_timezone())); ?><?php if($e->event_time):?><br><small><?php echo esc_html(wp_date( get_option( 'time_format', 'g:i a' ), ( new \DateTimeImmutable( $e->event_date . ' ' . $e->event_time, wp_timezone() ) )->getTimestamp(), wp_timezone() )); ?></small><?php endif;?></td><td><?php echo esc_html($e->event_name); ?><?php if($e->location_name):?><br><small><?php echo esc_html($e->location_name); ?></small><?php endif;?></td><td><?php echo esc_html($e->distance_value.' '.$e->course_unit); ?></td><td><?php echo esc_html($strokes[$e->stroke]??$e->stroke); ?></td><td><?php echo $pb?esc_html(Workout::format_duration($pb->duration_ms)):'—'; ?></td><td><?php if(!empty($ready)):?><?php echo esc_html($this->display_measurement((float)$ready['distance_native'],$e->course_unit)); ?><br><small><?php echo esc_html(sprintf(__('%d matching workouts / %d active lengths','chriss-swim-training-progress-evaluation'),(int)$ready['workout_count'],(int)$ready['length_count'])); ?></small><?php else:?>—<?php endif;?></td><td><?php if(!empty($ready['longest_recent_distance'])):?><strong><?php echo $ready['event_distance_or_longer']?esc_html__('Event distance or longer recorded','chriss-swim-training-progress-evaluation'):esc_html__('Shorter than event distance','chriss-swim-training-progress-evaluation'); ?></strong><br><small><?php echo esc_html(sprintf(__('Longest recent evaluated %1$s: %2$d %3$s in %4$s','chriss-swim-training-progress-evaluation'),$strokes[$e->stroke]??$e->stroke,(int)$ready['longest_recent_distance'],$e->course_unit,Workout::format_duration($ready['longest_recent_duration_ms']))); ?></small><?php else:?><?php esc_html_e('No recent evaluated matching-distance evidence','chriss-swim-training-progress-evaluation'); ?><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		<h2><?php esc_html_e('Recent Workouts','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$recent):?><p><?php esc_html_e('No workout history is available yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Action','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($recent as $w):?><tr><td><?php echo esc_html(wp_date('M j, Y',(new \DateTimeImmutable($w->workout_start,wp_timezone()))->getTimestamp(),wp_timezone())); ?></td><td><?php echo esc_html('open_water'===($w->workout_type??'pool')?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','chriss-swim-training-progress-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		</div><?php
	}
	public function workouts() {
		if(!current_user_can('swimlog_manage_own_workouts')) wp_die(esc_html__('You do not have permission to access this swim training page.','chriss-swim-training-progress-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-location.php';
		$user_id=$this->selected_user_id();$this->swimmer_selector($user_id,'swimlog-workouts');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only workout detail selector.
		$detail=absint(wp_unslash($_GET['workout_id']??0));
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'),'UNKNOWN'=>__('Unknown','chriss-swim-training-progress-evaluation'));

		if($detail){
			$w=Workout::get_for_user($detail,$user_id);
			if(!$w) wp_die(esc_html__('Workout not found.','chriss-swim-training-progress-evaluation'));
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only workout edit-mode selector.
			$edit=isset($_GET['edit'])&&'1'===sanitize_text_field(wp_unslash($_GET['edit'])); $metadata_error=$this->workout_action_error;
			if($metadata_error)$edit=true;
			$w=Workout::get_for_user($detail,$user_id); $locations=Location::all_for_user($user_id);
			$is_open_water=('open_water'===($w->workout_type??'pool')); $laps=$is_open_water?Workout::laps($detail,$user_id):array(); $lengths=$is_open_water?array():Workout::lengths($detail,$user_id); $perfs=$is_open_water?array():Workout::performances($detail,$user_id); $trackpoints=$is_open_water?Workout::trackpoints($detail,$user_id):array(); $analytics=$is_open_water?Workout::open_water_analytics($detail,$user_id):array(); $imports=Workout::imports($detail,$user_id);
			?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Workout Details','chriss-swim-training-progress-evaluation'); ?></h1><p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-workouts')); ?>">&larr; <?php esc_html_e('Back to Workouts','chriss-swim-training-progress-evaluation'); ?></a></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Workout metadata saved. Imported swim data and historical pool snapshot were unchanged.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?>
			<?php if($metadata_error):?><div class="notice notice-error"><p><?php echo esc_html($metadata_error); ?></p></div><?php endif;?>
			<?php if($edit):?>
			<form method="post"><?php wp_nonce_field('swimlog_save_workout_metadata_'.$detail); ?><input type="hidden" name="swimlog_workout_action" value="save_metadata">
			<table class="form-table" role="presentation"><tr><th scope="row"><label for="swimlog-workout-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-workout-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected((int)$w->location_id,(int)$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select><p class="description"><?php echo esc_html($is_open_water?__('Changing the location does not change imported open-water distance or GPS track data.','chriss-swim-training-progress-evaluation'):__('Changing the location does not change the pool length or course stored with this workout.','chriss-swim-training-progress-evaluation')); ?></p></td></tr>
			<tr><th scope="row"><label for="swimlog-workout-notes"><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></label></th><td><textarea class="large-text" rows="5" id="swimlog-workout-notes" name="notes"><?php echo esc_textarea($w->notes??''); ?></textarea></td></tr></table>
			<?php submit_button(__('Save Changes','chriss-swim-training-progress-evaluation'),'primary','',false); ?> <a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$detail),admin_url('admin.php'))); ?>"><?php esc_html_e('Cancel','chriss-swim-training-progress-evaluation'); ?></a></form>
			<?php else:?><p><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$detail,'edit'=>1),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit Workout','chriss-swim-training-progress-evaluation'); ?></a></p>
			<table class="widefat striped"><tbody>
			<tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(mysql2date('F j, Y g:i a',$w->workout_start,false)); ?></td></tr>
			<tr><th><?php esc_html_e('Workout type','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($is_open_water?__('Open water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td></tr>
			<tr><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($w->location_name?:'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->original_distance?$this->display_measurement($w->original_distance,$w->original_distance_unit):($w->total_distance_m?$this->display_measurement($w->total_distance_m,'m'):'—')); ?></td></tr>
			<?php if($is_open_water):?>
			<tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td></tr>
			<tr><th><?php esc_html_e('Moving / active','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($w->moving_time_ms)); ?></td></tr>
			<tr><th><?php esc_html_e('Average pace','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($this->open_water_pace_per_100m($w)); ?></td></tr>
			<tr><th><?php esc_html_e('Recording device','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($this->recording_device_label($w)); ?></td></tr>
			<?php else:?>
			<tr><th><?php esc_html_e('Pool','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->original_pool_length?$this->display_measurement($w->original_pool_length,$w->pool_length_unit):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td></tr>
			<tr><th><?php esc_html_e('Primary stroke','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($strokes[$w->primary_stroke]??($w->primary_stroke?:'—')); ?></td></tr>
			<?php endif;?>
			<tr><th><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo ''!==trim((string)($w->notes??''))?nl2br(esc_html($w->notes)):'—'; ?></td></tr>
			</tbody></table><?php endif;?>
			<?php if(!$is_open_water):?>
			<h2><?php esc_html_e('Workout Performances','chriss-swim-training-progress-evaluation'); ?></h2>
			<?php if(!$perfs):?><p><?php esc_html_e('No evaluated performances are stored for this workout yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Time','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Current PB','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($perfs as $p):?><tr><td><?php echo esc_html($p->distance_value.' '.$p->course_unit); ?></td><td><?php echo esc_html($strokes[$p->stroke]??$p->stroke); ?></td><td><?php echo esc_html(Workout::format_duration($p->duration_ms)); ?></td><td><?php echo $p->is_personal_best?esc_html__('Yes','chriss-swim-training-progress-evaluation'):'—'; ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<?php endif;?>
			<?php if($is_open_water):?>
			<h2><?php esc_html_e('Open Water Summary','chriss-swim-training-progress-evaluation'); ?></h2>
			<div class="swimlog-ows-summary">
				<div><strong><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(null!==$w->original_distance?$this->display_measurement($w->original_distance,$w->original_distance_unit):$this->display_measurement($w->total_distance_m,'m')); ?></span></div>
				<div><strong><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></span></div>
				<div><strong><?php esc_html_e('Moving / active','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(null!==$w->moving_time_ms?Workout::format_duration($w->moving_time_ms):'—'); ?></span></div>
				<div><strong><?php esc_html_e('Elapsed pace','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(null!==($analytics['elapsed_pace_100m_ms']??null)?Workout::format_duration($analytics['elapsed_pace_100m_ms']).' / 100 m':'—'); ?></span></div>
				<div><strong><?php esc_html_e('Moving pace','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(null!==($analytics['moving_pace_100m_ms']??null)?Workout::format_duration($analytics['moving_pace_100m_ms']).' / 100 m':'—'); ?></span></div>
				<div><strong><?php esc_html_e('Active time','chriss-swim-training-progress-evaluation'); ?></strong><span><?php echo esc_html(null!==($analytics['active_percent']??null)?number_format_i18n($analytics['active_percent'],1).'%':'—'); ?></span></div>
			</div>
			<h3><?php esc_html_e('Timing and Recording','chriss-swim-training-progress-evaluation'); ?></h3>
			<table class="widefat striped"><tbody>
			<tr><th><?php esc_html_e('Non-moving time','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==($analytics['non_moving_time_ms']??null)?Workout::format_duration($analytics['non_moving_time_ms']):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Heart rate','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->average_heart_rate?sprintf(__('%1$d bpm average / %2$d bpm max','chriss-swim-training-progress-evaluation'),(int)$w->average_heart_rate,(int)$w->max_heart_rate):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Cadence','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->average_cadence?sprintf(__('%1$s average / %2$s max','chriss-swim-training-progress-evaluation'),$this->display_measurement($w->average_cadence,''),$this->display_measurement($w->max_cadence,'')):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Recorded samples','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(sprintf(__('%1$d total / %2$d GPS / %3$d cadence','chriss-swim-training-progress-evaluation'),(int)($analytics['trackpoint_count']??0),(int)($analytics['gps_point_count']??0),(int)($analytics['cadence_point_count']??0))); ?></td></tr>
			<tr><th><?php esc_html_e('Source laps / splits','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(number_format_i18n(count($laps),0)); ?></td></tr>
			</tbody></table>
			<h3><?php esc_html_e('Recorded Trends','chriss-swim-training-progress-evaluation'); ?></h3>
			<?php $hr_chart=$this->open_water_metric_svg($trackpoints,'heart_rate',__('Heart rate over elapsed recording time','chriss-swim-training-progress-evaluation'),'bpm'); $cadence_chart=$this->open_water_metric_svg($trackpoints,'cadence',__('Cadence over elapsed recording time','chriss-swim-training-progress-evaluation'),__('spm','chriss-swim-training-progress-evaluation')); ?>
			<?php if(!$hr_chart&&!$cadence_chart):?><p><?php esc_html_e('No usable heart-rate or cadence trend series is stored for this workout.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?>
			<?php if($hr_chart):?><h4><?php esc_html_e('Heart Rate','chriss-swim-training-progress-evaluation'); ?></h4><?php echo wp_kses($hr_chart,array('svg'=>array('class'=>true,'viewbox'=>true,'role'=>true,'aria-label'=>true,'style'=>true),'line'=>array('x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true,'stroke'=>true,'stroke-width'=>true),'polyline'=>array('points'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'vector-effect'=>true),'text'=>array('x'=>true,'y'=>true,'font-size'=>true,'text-anchor'=>true))); ?><?php endif;?>
			<?php if($cadence_chart):?><h4><?php esc_html_e('Cadence','chriss-swim-training-progress-evaluation'); ?></h4><?php echo wp_kses($cadence_chart,array('svg'=>array('class'=>true,'viewbox'=>true,'role'=>true,'aria-label'=>true,'style'=>true),'line'=>array('x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true,'stroke'=>true,'stroke-width'=>true),'polyline'=>array('points'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'vector-effect'=>true),'text'=>array('x'=>true,'y'=>true,'font-size'=>true,'text-anchor'=>true))); ?><?php endif;?>
			<p class="description"><?php esc_html_e('Charts use only recorded FIT samples. Missing samples are not estimated or filled in.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?>
			<h3><?php esc_html_e('1 km Splits','chriss-swim-training-progress-evaluation'); ?></h3>
			<?php if(empty($analytics['splits'])):?><p><?php esc_html_e('No complete 1 km splits can be derived from the stored distance/timestamp records.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('Elapsed splits are derived from FIT Record distance and timestamps using interpolation at each complete kilometer. Moving split values are estimates based on the workout-level moving-to-elapsed time ratio because the stored FIT trackpoints do not contain a per-point moving-time counter. These are open-water analytics only and are not pool performances or personal bests.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed split','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed pace / 100 m','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Est. moving split','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Est. moving pace / 100 m','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed cumulative','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Est. moving cumulative','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($analytics['splits'] as$split):?><tr><td><?php echo esc_html(number_format_i18n($split['distance_m']/1000,0).' km'); ?></td><td><?php echo esc_html(Workout::format_duration($split['split_time_ms'])); ?></td><td><?php echo esc_html(Workout::format_duration((int)round($split['split_time_ms']/10)).' / 100 m'); ?></td><td><?php echo esc_html(null!==($split['moving_split_time_ms']??null)?Workout::format_duration($split['moving_split_time_ms']):'—'); ?></td><td><?php echo esc_html(null!==($split['moving_split_time_ms']??null)?Workout::format_duration((int)round($split['moving_split_time_ms']/10)).' / 100 m':'—'); ?></td><td><?php echo esc_html(Workout::format_duration($split['cumulative_time_ms'])); ?></td><td><?php echo esc_html(null!==($split['moving_cumulative_time_ms']??null)?Workout::format_duration($split['moving_cumulative_time_ms']):'—'); ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<?php endif;?>
			<?php if($is_open_water):$route_svg=$this->open_water_route_svg($trackpoints);$gps_points=(int)($analytics['gps_point_count']??0);?>
			<h2><?php esc_html_e('GPS Route','chriss-swim-training-progress-evaluation'); ?></h2>
			<?php if($route_svg):?><p><?php /* translators: %d: number of GPS trackpoints used for the route. */ echo esc_html(sprintf(__('%d GPS trackpoints are stored for this route.','chriss-swim-training-progress-evaluation'),$gps_points)); ?></p><?php echo wp_kses($route_svg,array('svg'=>array('class'=>true,'viewbox'=>true,'role'=>true,'aria-label'=>true,'style'=>true),'line'=>array('x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true,'stroke'=>true,'stroke-width'=>true,'opacity'=>true),'polyline'=>array('points'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'vector-effect'=>true),'circle'=>array('cx'=>true,'cy'=>true,'r'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true),'text'=>array('x'=>true,'y'=>true,'font-size'=>true,'font-weight'=>true))); ?><?php else:?><p><?php esc_html_e('No usable GPS route is stored for this open-water workout.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?>
			<?php endif;?>
			<?php if($is_open_water):?>
			<h2><?php esc_html_e('Source FIT Laps / Splits','chriss-swim-training-progress-evaluation'); ?></h2>
			<?php if(!$laps):?><p><?php esc_html_e('No FIT lap or split records are stored for this open-water workout.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('These are source-provided FIT laps or splits. They are preserved as recorded and are not treated as standardized race distances or pool performances.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e('Start','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Moving / active','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($laps as$lap):?><tr><td><?php echo esc_html($lap->sequence_no); ?></td><td><?php echo esc_html($lap->start_time?mysql2date('g:i:s a',$lap->start_time,false):'—'); ?></td><td><?php echo esc_html(null!==$lap->distance_m?$this->display_measurement($lap->distance_m,'m'):'—'); ?></td><td><?php echo esc_html(Workout::format_duration($lap->elapsed_time_ms)); ?></td><td><?php echo esc_html(Workout::format_duration($lap->moving_time_ms)); ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<?php endif;?>
			<h2><?php esc_html_e('Source Imports','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$imports):?><p><?php esc_html_e('No source import records are attached.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Original file','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Status','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Imported','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($imports as $i):?><tr><td><?php echo esc_html(strtoupper($i->source_type)); ?></td><td><?php echo esc_html($i->original_filename); ?></td><td><?php echo esc_html($i->import_status); ?></td><td><?php echo esc_html(wp_date('F j, Y g:i a',(new \DateTimeImmutable($i->imported_at,wp_timezone()))->getTimestamp(),wp_timezone())); ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
			<?php if(!$is_open_water):?>
			<h2><?php esc_html_e('Normalized Lengths and Rest','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$lengths):?><p><?php esc_html_e('No normalized length records are stored.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><details><summary><?php /* translators: %d: number of normalized swim length and rest records. */ echo esc_html(sprintf(__('Show %d length/rest records','chriss-swim-training-progress-evaluation'),count($lengths))); ?></summary><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Time','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($lengths as $l):?><tr><td><?php echo esc_html($l->sequence_no); ?></td><td><?php echo esc_html($l->length_type); ?></td><td><?php echo esc_html($l->distance_m.' m'); ?></td><td><?php echo esc_html($strokes[$l->stroke]??($l->stroke?:'—')); ?></td><td><?php echo esc_html(Workout::format_duration($l->elapsed_time_ms)); ?></td></tr><?php endforeach;?></tbody></table></details><?php endif;?>
			<?php endif;?>
			<hr><h2><?php esc_html_e('Delete Workout','chriss-swim-training-progress-evaluation'); ?></h2>
			<p><?php esc_html_e('Permanently remove this workout, its source files, normalized swim data, and derived performances. Personal bests will be recalculated from the remaining workouts.','chriss-swim-training-progress-evaluation'); ?></p>
			<form method="post" style="margin-bottom:12px"><?php wp_nonce_field('swimlog_rebuild_workout_'.$detail); ?><input type="hidden" name="swimlog_workout_action" value="rebuild"><button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Rebuild this workout from its preserved source files? Normalized workout data and derived performances will be regenerated while the original source files and import records are retained.','chriss-swim-training-progress-evaluation')); ?>');"><?php esc_html_e('Rebuild from Preserved Sources','chriss-swim-training-progress-evaluation'); ?></button><p class="description"><?php esc_html_e('Reparse the preserved FIT/CSV/GPX/TCX sources and regenerate normalized workout data and derived performances. The operation is transactional and does not delete the preserved source files.','chriss-swim-training-progress-evaluation'); ?></p></form>
			<form method="post"><?php wp_nonce_field('swimlog_delete_workout_'.$detail); ?><input type="hidden" name="swimlog_workout_action" value="delete"><button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Permanently delete this workout? The workout, source files, normalized swim data, and derived performances will be removed. This cannot be undone.','chriss-swim-training-progress-evaluation')); ?>');"><?php esc_html_e('Delete Workout','chriss-swim-training-progress-evaluation'); ?></button></form>
			</div><?php return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only workout filters.
		$filters=array('date_from'=>sanitize_text_field(wp_unslash($_GET['date_from']??'')),'date_to'=>sanitize_text_field(wp_unslash($_GET['date_to']??'')),'location_id'=>absint(wp_unslash($_GET['location_id']??0)),'course_unit'=>sanitize_key(wp_unslash($_GET['course_unit']??'')),'stroke'=>strtoupper(sanitize_key(wp_unslash($_GET['stroke']??''))),'workout_type'=>sanitize_key(wp_unslash($_GET['workout_type']??'')));
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only workout pagination selector.
		$paged=max(1,absint(wp_unslash($_GET['paged']??1))); $result=Workout::query_for_user($user_id,$filters,$paged,20); $locations=Location::all_for_user($user_id); $stroke_volume=Workout::stroke_volume_for_user($user_id,$filters);
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Workouts','chriss-swim-training-progress-evaluation'); ?></h1><p><?php esc_html_e('Browse your normalized swim workout history.','chriss-swim-training-progress-evaluation'); ?></p>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
		<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Workout deleted. Personal bests were recalculated from the remaining workouts.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?>
		<form method="get"><input type="hidden" name="page" value="swimlog-workouts"><select name="workout_type"><option value=""><?php esc_html_e('All workout types','chriss-swim-training-progress-evaluation'); ?></option><option value="pool" <?php selected($filters['workout_type'],'pool'); ?>><?php esc_html_e('Pool','chriss-swim-training-progress-evaluation'); ?></option><option value="open_water" <?php selected($filters['workout_type'],'open_water'); ?>><?php esc_html_e('Open Water','chriss-swim-training-progress-evaluation'); ?></option></select> <label><?php esc_html_e('From','chriss-swim-training-progress-evaluation'); ?> <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"></label> <label><?php esc_html_e('To','chriss-swim-training-progress-evaluation'); ?> <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>"></label> <select name="location_id"><option value="0"><?php esc_html_e('All locations','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($filters['location_id'],$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select> <select name="course_unit"><option value=""><?php esc_html_e('All courses','chriss-swim-training-progress-evaluation'); ?></option><option value="m" <?php selected($filters['course_unit'],'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($filters['course_unit'],'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select> <select name="stroke"><option value=""><?php esc_html_e('All strokes','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($filters['stroke'],$code); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select> <?php submit_button(__('Filter','chriss-swim-training-progress-evaluation'),'secondary','',false); ?></form>
		<h2><?php esc_html_e('Pool Training Volume by Stroke','chriss-swim-training-progress-evaluation'); ?></h2>
		<?php if(!$stroke_volume):?><p><?php esc_html_e('No active pool lengths are available for the selected filters.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('Training volume is calculated from normalized active pool lengths, not personal-best records. Meter and yard courses are kept separate.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e('Course','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Workouts','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Active lengths','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Active swim time','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($stroke_volume as$volume):?><tr><td><?php echo esc_html('yd'===$volume->course_unit?__('Yards','chriss-swim-training-progress-evaluation'):__('Meters','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($strokes[$volume->stroke]??$volume->stroke); ?></td><td><?php echo esc_html(number_format_i18n((int)$volume->workout_count)); ?></td><td><?php echo esc_html(number_format_i18n((int)$volume->length_count)); ?></td><td><?php echo esc_html($this->display_measurement((float)$volume->distance_native,$volume->course_unit)); ?></td><td><?php echo esc_html(Workout::format_duration((int)$volume->active_time_ms)); ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		<?php if(!$result['rows']):?><p><?php esc_html_e('No workouts found. Upload a FIT, CSV, GPX, or TCX workout to begin your history.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Course','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Action','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($result['rows'] as $w):$is_open_water=('open_water'===($w->workout_type??'pool'));?><tr><td><?php echo esc_html(mysql2date('F j, Y g:i a',$w->workout_start,false)); ?></td><td><?php echo esc_html($is_open_water?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html($is_open_water?__('Open Water','chriss-swim-training-progress-evaluation'):(null!==$w->original_pool_length?$this->display_measurement($w->original_pool_length,$w->pool_length_unit):($w->pool_length_m?$this->display_measurement($w->pool_length_m,'m'):'—'))); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><?php echo esc_html($is_open_water?'—':($strokes[$w->primary_stroke]??($w->primary_stroke?:'—'))); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','chriss-swim-training-progress-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table>
		<?php if($result['pages']>1):?><p class="tablenav-pages"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$result['pages']))); ?></p><?php endif; endif;?></div><?php
	}
	public function upload() {
		if(!current_user_can('swimlog_upload_workouts'))wp_die(esc_html__('You do not have permission to upload workouts.','chriss-swim-training-progress-evaluation'));
		require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-location.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-fit-importer.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-csv-importer.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-importer.php';
		$user_id=get_current_user_id();$locations=Location::all_for_user($user_id);$results=array();$errors=array();$pending=array();

		if(isset($_POST['swimlog_match_action'])&&'resolve'===sanitize_key(wp_unslash($_POST['swimlog_match_action']))){
			check_admin_referer('swimlog_resolve_match');$import_id=absint(wp_unslash($_POST['import_id']??0));$choice=sanitize_key(wp_unslash($_POST['match_choice']??''));$loc=absint(wp_unslash($_POST['location_id']??0));
			$res=Importer::resolve_pending($import_id,$user_id,$choice,$loc);if(is_wp_error($res))$errors[]=$res->get_error_message();else$results[]=$res;
		}
		if(isset($_POST['swimlog_upload_action'])&&'import'===sanitize_key(wp_unslash($_POST['swimlog_upload_action']))){
			check_admin_referer('swimlog_upload_workout');$loc=absint(wp_unslash($_POST['location_id']??0));
			$files = array();
			if ( isset( $_FILES['workout_files'] ) && is_array( $_FILES['workout_files'] ) ) {
				// WordPress upload handling requires the temporary path to remain unchanged.
				// Sanitize user-controlled file metadata while preserving tmp_name for Importer::import_upload().
				$upload = $_FILES['workout_files']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$files = array(
					'name'     => array_map( 'sanitize_file_name', (array) ( $upload['name'] ?? array() ) ),
					'type'     => array_map( 'sanitize_mime_type', (array) ( $upload['type'] ?? array() ) ),
					'tmp_name' => array_map( 'sanitize_text_field', (array) ( $upload['tmp_name'] ?? array() ) ),
					'error'    => array_map( 'absint', (array) ( $upload['error'] ?? array() ) ),
					'size'     => array_map( 'absint', (array) ( $upload['size'] ?? array() ) ),
				);
			}
			if(empty($files['name']))$errors[]=__('Choose at least one FIT, CSV, GPX, or TCX file.','chriss-swim-training-progress-evaluation');
			else{$items=array();foreach((array)$files['name'] as $i=>$name){$items[]=array('name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0);}usort($items,function($x,$y){return(strtolower(pathinfo($x['name'],PATHINFO_EXTENSION))==='fit'?-1:1);});
				foreach($items as $file){$res=Importer::import_upload($user_id,$file,$loc);if(is_wp_error($res))$errors[]=$file['name'].': '.$res->get_error_message();elseif(!empty($res['pending']))$pending[]=$res;else$results[]=$res;}
			}
		}
		foreach(Importer::pending_all_for_user($user_id) as $row){$already=false;foreach($pending as $p){if((int)$p['import_id']===(int)$row->id){$already=true;break;}}if(!$already)$pending[]=array('pending'=>true,'import_id'=>(int)$row->id,'candidate_workout_id'=>(int)$row->workout_id,'source'=>$row->source_type,'workout'=>null);}
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Upload Workout','chriss-swim-training-progress-evaluation'); ?></h1>
		<p><?php esc_html_e('Upload FORM CSV files, FIT pool-swim files, or both files from the same workout. High-confidence matches attach automatically. Probable matches require your confirmation before any workout history is changed.','chriss-swim-training-progress-evaluation'); ?></p>
		<?php foreach($errors as $e):?><div class="notice notice-error"><p><?php echo esc_html($e); ?></p></div><?php endforeach;?>
		<?php foreach($results as $res):?><div class="notice notice-success"><p><?php echo esc_html($res['attached']?__('Source file attached to an existing matching workout.','chriss-swim-training-progress-evaluation'):__('Workout imported successfully.','chriss-swim-training-progress-evaluation')); ?> <a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$res['workout_id']),admin_url('admin.php'))); ?>"><?php esc_html_e('View workout','chriss-swim-training-progress-evaluation'); ?></a></p></div><?php endforeach;?>
		<?php foreach($pending as $p):
			$row=Importer::pending_for_user($p['import_id'],$user_id);if(!$row)continue;$nw=$p['workout'];$review_error='';
			if(!$nw){if(!is_file($row->stored_path)){$review_error=__('The preserved source file is missing. This pending review cannot be completed until the source is restored.','chriss-swim-training-progress-evaluation');}else{$reparsed=Importer::parse_source($row->source_type,$row->stored_path);if(is_wp_error($reparsed))/* translators: 1: source file type, 2: parser error message. */
					$review_error=sprintf(__('The preserved %1$s source could not be reparsed: %2$s','chriss-swim-training-progress-evaluation'),strtoupper($row->source_type),$reparsed->get_error_message());else{$valid=Importer::validate($reparsed);if(is_wp_error($valid))$review_error=$valid->get_error_message();else$nw=$reparsed['workout'];}}}
			$saved_location=Importer::pending_location($user_id,$p['import_id']);
			?><div class="notice notice-warning inline swimlog-match-review"><h2><?php esc_html_e('Possible matching workout','chriss-swim-training-progress-evaluation'); ?></h2>
			<p><strong><?php /* translators: %s: source file type (FIT or CSV). */ echo esc_html(sprintf(__('Uploaded source: %s','chriss-swim-training-progress-evaluation'),strtoupper($row->source_type))); ?></strong> <?php /* translators: %s: localized date and time when the source was preserved. */ echo esc_html(sprintf(__('Preserved for review on %s.','chriss-swim-training-progress-evaluation'),wp_date('F j, Y g:i a',(new \DateTimeImmutable($row->imported_at,wp_timezone()))->getTimestamp(),wp_timezone()))); ?></p>
			<?php if($review_error):?><div class="notice notice-error inline"><p><?php echo esc_html($review_error); ?></p><?php if($row->error_message):?><p><?php /* translators: %s: pending import resolution error message. */ echo esc_html(sprintf(__('Last resolution error: %s','chriss-swim-training-progress-evaluation'),$row->error_message)); ?></p><?php endif;?></div><?php continue;endif;?>
			<p><?php esc_html_e('This source may belong to the existing workout below. Compare the values before choosing. Nothing is attached until you confirm.','chriss-swim-training-progress-evaluation'); ?></p>
			<?php $start_delta=abs((new \DateTimeImmutable($nw['workout_start'],wp_timezone()))->getTimestamp()-(new \DateTimeImmutable($row->candidate_start,wp_timezone()))->getTimestamp());$distance_delta=abs((float)($nw['total_distance_m']??0)-(float)$row->candidate_distance_m);$elapsed_delta=abs((int)($nw['elapsed_time_ms']??0)-(int)$row->candidate_elapsed_ms); ?>
			<div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Field','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Existing workout','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Uploaded source','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Difference','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody>
			<tr><th><?php esc_html_e('Start','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(mysql2date('F j, Y g:i:s a',$row->candidate_start,false)); ?></td><td><?php echo esc_html(mysql2date('F j, Y g:i:s a',$nw['workout_start'],false)); ?></td><td><?php echo esc_html(number_format_i18n($start_delta).' s'); ?></td></tr>
			<tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($row->candidate_original_distance.' '.$row->candidate_distance_unit); ?></td><td><?php echo esc_html($nw['original_distance'].' '.$nw['original_distance_unit']); ?></td><td><?php echo esc_html(number_format_i18n($distance_delta,3).' m'); ?></td></tr>
			<tr><th><?php esc_html_e('Pool','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($row->candidate_pool_length.' '.$row->candidate_course); ?></td><td><?php echo esc_html($nw['original_pool_length'].' '.$nw['pool_length_unit']); ?></td><td><?php echo esc_html(($row->candidate_course===$nw['pool_length_unit'])?__('Same course','chriss-swim-training-progress-evaluation'):__('Different course','chriss-swim-training-progress-evaluation')); ?></td></tr>
			<tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(number_format((int)$row->candidate_elapsed_ms/1000,3).' s'); ?></td><td><?php echo esc_html(number_format((int)$nw['elapsed_time_ms']/1000,3).' s'); ?></td><td><?php echo esc_html(number_format($elapsed_delta/1000,3).' s'); ?></td></tr></tbody></table></div>
			<?php if($row->error_message):?><div class="notice notice-error inline"><p><?php /* translators: %s: pending import resolution attempt error message. */ echo esc_html(sprintf(__('Last resolution attempt: %s','chriss-swim-training-progress-evaluation'),$row->error_message)); ?></p></div><?php endif;?>
			<form method="post" style="margin-top:12px"><?php wp_nonce_field('swimlog_resolve_match'); ?><input type="hidden" name="swimlog_match_action" value="resolve"><input type="hidden" name="import_id" value="<?php echo esc_attr($p['import_id']); ?>">
			<p><label for="swimlog-pending-location-<?php echo esc_attr($p['import_id']); ?>"><strong><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></strong></label> <select id="swimlog-pending-location-<?php echo esc_attr($p['import_id']); ?>" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $review_location):?><option value="<?php echo esc_attr($review_location->id); ?>" <?php selected($saved_location,$review_location->id); ?>><?php echo esc_html($review_location->name); ?></option><?php endforeach;?></select></p>
			<div class="swimlog-actions"><button class="button button-primary" name="match_choice" value="attach"><?php esc_html_e('Attach to existing workout','chriss-swim-training-progress-evaluation'); ?></button><button class="button" name="match_choice" value="separate"><?php esc_html_e('Import as separate workout','chriss-swim-training-progress-evaluation'); ?></button></div>
			<p class="description"><?php esc_html_e('Attach combines this source with the existing workout. Import separately creates another workout. The preserved source remains pending if resolution fails.','chriss-swim-training-progress-evaluation'); ?></p></form></div><?php endforeach;?>
		<form method="post" enctype="multipart/form-data"><?php wp_nonce_field('swimlog_upload_workout'); ?><input type="hidden" name="swimlog_upload_action" value="import"><table class="form-table" role="presentation"><tr><th><label for="swimlog-workout-files"><?php esc_html_e('Workout files','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required id="swimlog-workout-files" type="file" name="workout_files[]" accept=".fit,.csv,.gpx,.tcx" multiple><p class="description"><?php esc_html_e('FIT, CSV, GPX, and TCX. Maximum 10 MB per file. CSV is limited to FORM swim-export files; GPX/TCX are imported as open-water GPS tracks.','chriss-swim-training-progress-evaluation'); ?></p></td></tr>
		<tr><th><label for="swimlog-upload-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-upload-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select></td></tr></table><?php submit_button(__('Upload and Import','chriss-swim-training-progress-evaluation')); ?></form></div><?php
	}
	public function personal_bests() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) wp_die( esc_html__( 'You do not have permission to view these results.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-records.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-age-group.php';

		$user_id = $this->selected_user_id();
		$this->swimmer_selector( $user_id, 'swimlog-personal-bests' );

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
		if ( current_user_can( 'swimlog_manage_all_users' ) ) $base_args['swimmer_id'] = $user_id;
		$current_args = array_merge( $base_args, array( 'period' => 'current' ) );
		$lifetime_args = array_merge( $base_args, array( 'period' => 'lifetime' ) );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ); ?></h1>

			<h2><?php esc_html_e( 'Record Period', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p>
				<?php if ( $current_group ) : ?><a class="button <?php echo 'current' === $period ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( $current_args, admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( sprintf(
					/* translators: %s: current swimming age-group label. */
					__( 'Current Age Group (%s)', 'chriss-swim-training-progress-evaluation' ),
					$current_label
				) ); ?></a><?php endif; ?>
				<a class="button <?php echo 'lifetime' === $period ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( $lifetime_args, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Lifetime', 'chriss-swim-training-progress-evaluation' ); ?></a>
			</p>
			<?php if ( $historical_groups ) : ?>
				<form method="get">
					<input type="hidden" name="page" value="swimlog-personal-bests">
					<input type="hidden" name="course" value="<?php echo esc_attr( $course ); ?>">
					<input type="hidden" name="period" value="historical">
					<?php if ( current_user_can( 'swimlog_manage_all_users' ) ) : ?><input type="hidden" name="swimmer_id" value="<?php echo esc_attr( $user_id ); ?>"><?php endif; ?>
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
				if ( current_user_can( 'swimlog_manage_all_users' ) ) { $meter_args['swimmer_id'] = $user_id; $yard_args['swimmer_id'] = $user_id; }
				?>
				<a class="button <?php echo 'm' === $course ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( $meter_args, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></a>
				<a class="button <?php echo 'yd' === $course ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( $yard_args, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></a>
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
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><?php foreach ( $cols as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead><tbody>
				<?php foreach ( Evaluator::DISTANCES as $d ) : ?><tr><th><?php echo esc_html( $d . ' ' . $course ); ?></th><?php foreach ( $cols as $code => $label ) : $p = $pbs[ $d ][ $code ] ?? null; ?><td><?php if ( $p ) :
					$is_lifetime = 'lifetime' !== $period && ! empty( $lifetime_pbs[ $d ][ $code ] ) && (int) $lifetime_pbs[ $d ][ $code ]->id === (int) $p->id;
					?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => $p->workout_id, 'swimmer_id' => $user_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( Workout::format_duration( $p->duration_ms ) ); ?></a><?php if ( $is_lifetime ) : ?> <span aria-label="<?php esc_attr_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?>" title="<?php esc_attr_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?>">★</span><?php endif; ?><br><small><?php echo esc_html( wp_date( 'M j, Y', ( new \DateTimeImmutable( $p->achieved_at, wp_timezone() ) )->getTimestamp(), wp_timezone() ) ); ?></small><?php else : ?>&mdash;<?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
				</tbody></table>
				<?php if ( 'lifetime' !== $period ) : ?><p><span aria-hidden="true">★</span> <?php esc_html_e( 'Lifetime Personal Best', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'PB Progression', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<form method="get"><input type="hidden" name="page" value="swimlog-personal-bests"><input type="hidden" name="course" value="<?php echo esc_attr( $course ); ?>"><?php if ( current_user_can( 'swimlog_manage_all_users' ) ) : ?><input type="hidden" name="swimmer_id" value="<?php echo esc_attr( $user_id ); ?>"><?php endif; ?><select name="history_distance"><option value="0"><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( Evaluator::DISTANCES as $d ) : ?><option value="<?php echo esc_attr( $d ); ?>" <?php selected( $history_distance, $d ); ?>><?php echo esc_html( $d . ' ' . $course ); ?></option><?php endforeach; ?></select> <select name="history_stroke"><option value=""><?php esc_html_e( 'Stroke', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( array( 'FR' => 'Freestyle', 'BR' => 'Breaststroke', 'BACK' => 'Backstroke', 'FLY' => 'Butterfly', 'MIXED' => 'Mixed' ) as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $history_stroke, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'Show progression', 'chriss-swim-training-progress-evaluation' ), 'secondary', '', false ); ?></form>
			<?php if ( $history_distance && $history_stroke ) : ?><?php if ( ! $progression ) : ?><p><?php esc_html_e( 'No progression records found for that distance and stroke.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php else : ?><div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Time', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Workout', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $progression as $p ) : ?><tr><td><?php echo esc_html( wp_date( 'F j, Y', ( new \DateTimeImmutable( $p->achieved_at, wp_timezone() ) )->getTimestamp(), wp_timezone() ) ); ?></td><td><?php echo esc_html( Workout::format_duration( $p->duration_ms ) ); ?></td><td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => $p->workout_id, 'swimmer_id' => $user_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View workout', 'chriss-swim-training-progress-evaluation' ); ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?>
		</div>
		<?php
	}
	private $workout_action_error = null;
	private $location_action_error = null;

	public function handle_workout_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-workouts' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_workout_action'] ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['swimlog_workout_action'] ) );
		if ( ! in_array( $action, array( 'save_metadata', 'rebuild', 'delete' ), true ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';

		$user_id = $this->selected_user_id();
		$detail = isset( $_GET['workout_id'] ) ? absint( wp_unslash( $_GET['workout_id'] ) ) : 0;
		if ( ! $detail || ! Workout::get_for_user( $detail, $user_id ) ) {
			wp_die( esc_html__( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		}

		if ( 'rebuild' === $action ) {
			check_admin_referer( 'swimlog_rebuild_workout_' . $detail );
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-importer.php';
			$result = Importer::rebuild_workout( $detail, $user_id );
			if ( is_wp_error( $result ) ) {
				$this->workout_action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => $detail, 'rebuilt' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_workout_' . $detail );
			$result = Workout::delete( $detail, $user_id );
			if ( is_wp_error( $result ) ) {
				$this->workout_action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-workouts', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		check_admin_referer( 'swimlog_save_workout_metadata_' . $detail );
		$result = Workout::update_metadata(
			$detail,
			$user_id,
			isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0,
			sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) )
		);

		if ( is_wp_error( $result ) ) {
			$this->workout_action_error = $result->get_error_message();
			return;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => $detail, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_location_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_locations' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-locations' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_location_action'] ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['swimlog_location_action'] ) );

		if ( 'save' === $action ) {
			check_admin_referer( 'swimlog_save_location' );
			$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
			$result = Location::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				$this->location_action_error = $result->get_error_message();
				return;
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_location' );
			$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
			$result = Location::delete( $id, $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				$this->location_action_error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The location could not be deleted.', 'chriss-swim-training-progress-evaluation' );
				return;
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	private $event_action_error = null;

	public function handle_event_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_events' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-events' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_event_action'] ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['swimlog_event_action'] ) );

		if ( 'save' === $action ) {
			check_admin_referer( 'swimlog_save_event' );
			$id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
			$result = Event::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				$this->event_action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','saved'=>1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_event' );
			$result = Event::delete( absint( wp_unslash( $_POST['event_id'] ?? 0 ) ), $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				$this->event_action_error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The event could not be deleted.', 'chriss-swim-training-progress-evaluation' );
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','deleted'=>1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public function events() {
		if ( ! current_user_can( 'swimlog_manage_own_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$error = $this->event_action_error;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only event list filter.
		$view=sanitize_key(wp_unslash($_GET['view'] ?? 'upcoming'));
		if(!in_array($view,array('upcoming','past','all'),true)) $view='upcoming';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only event edit selector.
		$edit_id=absint(wp_unslash($_GET['edit'] ?? 0));
		$editing=$edit_id?Event::get_for_user($edit_id,$user_id):null;
		$events=Event::all_for_user($user_id,$view);
		$locations=Location::all_for_user($user_id);
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'));
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e('Events','chriss-swim-training-progress-evaluation'); ?></h1>
			<p><?php esc_html_e('Manage upcoming and historical swim events. Past events remain in your history.','chriss-swim-training-progress-evaluation'); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event saved.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event deleted.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif; ?>
			<?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

			<h2><?php echo $editing?esc_html__('Edit Event','chriss-swim-training-progress-evaluation'):esc_html__('Add Event','chriss-swim-training-progress-evaluation'); ?></h2>
			<form method="post"><?php wp_nonce_field('swimlog_save_event'); ?><input type="hidden" name="swimlog_event_action" value="save"><input type="hidden" name="event_id" value="<?php echo esc_attr($editing?$editing->id:0); ?>">
			<table class="form-table" role="presentation">
			<tr><th><label for="swimlog-event-name"><?php esc_html_e('Event name','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required class="regular-text" maxlength="191" id="swimlog-event-name" name="event_name" value="<?php echo esc_attr($editing?$editing->event_name:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-date"><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required type="date" id="swimlog-event-date" name="event_date" value="<?php echo esc_attr($editing?$editing->event_date:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-time"><?php esc_html_e('Time','chriss-swim-training-progress-evaluation'); ?></label></th><td><input type="time" id="swimlog-event-time" name="event_time" value="<?php echo esc_attr($editing&&$editing->event_time?substr($editing->event_time,0,5):''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-event-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($editing?$editing->location_id:0,$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-distance"><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></label></th><td><select required id="swimlog-event-distance" name="distance_value"><?php foreach(Event::DISTANCES as $d):?><option value="<?php echo esc_attr($d); ?>" <?php selected($editing?$editing->distance_value:0,$d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select> <select required name="course_unit"><option value="m" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select></td></tr>
			<tr><th><label for="swimlog-event-stroke"><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></label></th><td><select required id="swimlog-event-stroke" name="stroke"><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($editing?$editing->stroke:'FR',$code); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-notes"><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-event-notes" name="notes"><?php echo esc_textarea($editing?$editing->notes:''); ?></textarea></td></tr>
			</table><?php submit_button($editing?__('Update Event','chriss-swim-training-progress-evaluation'):__('Add Event','chriss-swim-training-progress-evaluation')); ?><?php if($editing):?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Cancel','chriss-swim-training-progress-evaluation'); ?></a><?php endif; ?></form>

			<h2><?php esc_html_e('Your Events','chriss-swim-training-progress-evaluation'); ?></h2>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=upcoming')); ?>"><?php esc_html_e('Upcoming','chriss-swim-training-progress-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=past')); ?>"><?php esc_html_e('Past','chriss-swim-training-progress-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=all')); ?>"><?php esc_html_e('All','chriss-swim-training-progress-evaluation'); ?></a></p>
			<?php if(empty($events)):?><p><?php esc_html_e('No events found for this view.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Event','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Actions','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody>
			<?php foreach($events as $event): $locname='—'; foreach($locations as $loc){if((int)$loc->id===(int)$event->location_id){$locname=$loc->name;break;}} ?><tr><td><?php echo esc_html(wp_date('F j, Y',(new \DateTimeImmutable($event->event_date.' 12:00:00',wp_timezone()))->getTimestamp(),wp_timezone())); ?><?php if($event->event_time) echo '<br>'.esc_html(wp_date( get_option( 'time_format', 'g:i a' ), ( new \DateTimeImmutable( $event->event_date . ' ' . $event->event_time, wp_timezone() ) )->getTimestamp(), wp_timezone() )); ?></td><td><?php echo esc_html($event->event_name); ?></td><td><?php echo esc_html($event->distance_value.' '.$event->course_unit); ?></td><td><?php echo esc_html($strokes[$event->stroke]??$event->stroke); ?></td><td><?php echo esc_html($locname); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-events','view'=>$view,'edit'=>$event->id),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit','chriss-swim-training-progress-evaluation'); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field('swimlog_delete_event'); ?><input type="hidden" name="swimlog_event_action" value="delete"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><button class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this event?','chriss-swim-training-progress-evaluation')); ?>');"><?php esc_html_e('Delete','chriss-swim-training-progress-evaluation'); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div><?php
	}
	public function locations() {
		if ( ! current_user_can( 'swimlog_manage_own_locations' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$error = $this->location_action_error;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only location edit selector.
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$editing = $edit_id ? Location::get_for_user( $edit_id, $user_id ) : null;
		$locations = Location::all_for_user( $user_id );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Locations', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Manage your pool and swim locations. Editing a location does not change the pool information stored with historical workouts.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location deleted. Historical workout snapshots were preserved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Location', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Add Location', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_location' ); ?>
				<input type="hidden" name="swimlog_location_action" value="save">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="swimlog-location-name"><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input class="regular-text" required id="swimlog-location-name" name="name" type="text" maxlength="191" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="swimlog-pool-length"><?php esc_html_e( 'Pool length', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-pool-length" name="pool_length" type="number" min="1" step="1" value="<?php echo esc_attr( $editing && null !== $editing->pool_length ? (string) (int) round( (float) $editing->pool_length ) : '' ); ?>"> <select name="pool_unit" aria-label="<?php esc_attr_e( 'Pool length unit', 'chriss-swim-training-progress-evaluation' ); ?>"><option value=""><?php esc_html_e( 'Select unit', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="m" <?php selected( $editing ? $editing->pool_unit : '', 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="yd" <?php selected( $editing ? $editing->pool_unit : '', 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Open-water reference coordinates', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label for="swimlog-location-latitude"><?php esc_html_e( 'Latitude', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-location-latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?php echo esc_attr( $editing && null !== $editing->latitude ? $editing->latitude : '' ); ?>"> <label for="swimlog-location-longitude"><?php esc_html_e( 'Longitude', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-location-longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?php echo esc_attr( $editing && null !== $editing->longitude ? $editing->longitude : '' ); ?>"><p class="description"><?php esc_html_e( 'Optional. Open-water imports whose first valid GPS fix is within 500 meters can be assigned to this saved location automatically.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><label for="swimlog-location-notes"><?php esc_html_e( 'Notes', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-location-notes" name="notes"><?php echo esc_textarea( $editing ? $editing->notes : '' ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update Location', 'chriss-swim-training-progress-evaluation' ) : __( 'Add Location', 'chriss-swim-training-progress-evaluation' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-locations' ) ); ?>"><?php esc_html_e( 'Cancel', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Your Locations', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( empty( $locations ) ) : ?>
				<p><?php esc_html_e( 'No locations have been added yet.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'OWS GPS', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Notes', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $locations as $location ) : ?>
					<tr><td><?php echo esc_html( $location->name ); ?></td><td><?php echo null !== $location->pool_length ? esc_html( number_format_i18n( (float) $location->pool_length, 0 ) . ' ' . $location->pool_unit ) : '&mdash;'; ?></td><td><?php echo null !== $location->latitude && null !== $location->longitude ? esc_html( $location->latitude . ', ' . $location->longitude ) : '&mdash;'; ?></td><td><?php echo esc_html( $location->notes ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-locations', 'edit' => $location->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'chriss-swim-training-progress-evaluation' ); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_delete_location' ); ?><input type="hidden" name="swimlog_location_action" value="delete"><input type="hidden" name="location_id" value="<?php echo esc_attr( $location->id ); ?>"><button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this location? Historical workout snapshots will remain unchanged.', 'chriss-swim-training-progress-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'chriss-swim-training-progress-evaluation' ); ?></button></form></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
	public function shortcodes() {
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to access this swim training page.','chriss-swim-training-progress-evaluation'));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Shortcodes','chriss-swim-training-progress-evaluation'); ?></h1><p><?php esc_html_e('Public swim output is display-only and private by default. Without a user attribute, the current page or post author is used. There is no logged-in-viewer fallback.','chriss-swim-training-progress-evaluation'); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Shortcode','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Purpose','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody>
		<tr><td><code>[swimlog_latest]</code></td><td><?php esc_html_e('Latest swim summary. Supports count and title attributes.','chriss-swim-training-progress-evaluation'); ?></td></tr><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-shortcode-bests' ) ); ?>"><code>[swimlog_bests]</code></a></td><td><?php esc_html_e('Native-course personal best table. Supports user, course, stroke, distance, title, best, and ag attributes. best="LB" shows lifetime bests (default). best="AG" shows the age group for the current page/post published date. Use ag="current" for today\'s age group or ag="50-54" for a specific age group; supplying ag also implies age-group mode. Use title="no" to hide the heading or supply custom title text.','chriss-swim-training-progress-evaluation'); ?></td></tr><tr><td><code>[swimlog_upcoming_events]</code></td><td><?php esc_html_e('Upcoming events with exact matching current PB. Supports user, count, and title attributes. Use title="no" to hide the heading or supply custom title text.','chriss-swim-training-progress-evaluation'); ?></td></tr><tr><td><code>[swimlog_workout id="123"]</code></td><td><?php esc_html_e('One public workout summary and evaluated workout bests. Ownership is derived from the workout.','chriss-swim-training-progress-evaluation'); ?></td></tr><tr><td><code>[swimlog_log]</code></td><td><?php esc_html_e('Training log. With no period attribute it shows the current month. Use month="Sep" with optional year="2025" for a monthly workout list, or year="2026" by itself for monthly totals across a year. Meter and yard totals remain separate. Supports user and title attributes.','chriss-swim-training-progress-evaluation'); ?></td></tr>
		</tbody></table><h2><?php esc_html_e('Examples','chriss-swim-training-progress-evaluation'); ?></h2><p><code>[swimlog_bests course="m" stroke="BR" distance="200"]</code><br><code>[swimlog_bests best="AG" course="m"]</code><br><code>[swimlog_bests best="AG" ag="current" course="m"]</code><br><code>[swimlog_bests best="AG" ag="50-54" course="m"]</code><br><code>[swimlog_upcoming_events count="3" title="no"]</code><br><code>[swimlog_latest user="123" count="5" title="Recent Swims"]</code><br><code>[swimlog_log]</code><br><code>[swimlog_log month="Sep"]</code><br><code>[swimlog_log month="Sep" year="2025"]</code><br><code>[swimlog_log year="2026"]</code></p></div><?php
	}
	public function shortcode_bests() {
		if ( ! current_user_can( 'swimlog_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$return_url = admin_url( 'admin.php?page=swimlog-shortcodes' );
		?>
		<div class="wrap swimlog-admin">
			<p><a href="<?php echo esc_url( $return_url ); ?>">&larr; <?php esc_html_e( 'Return to Shortcodes', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
			<h1><code>[swimlog_bests]</code></h1>
			<p><?php esc_html_e( 'Displays a swimmer\'s personal bests. Lifetime Bests remain the default for backward compatibility. Age-Group Personal Bests can be selected for the age group associated with the page/post published date, the swimmer\'s current age group, or a specific age group.', 'chriss-swim-training-progress-evaluation' ); ?></p>

			<h2><?php esc_html_e( 'Options', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Attribute', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Values', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Description', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead>
				<tbody>
					<tr><td><code>best</code></td><td><code>LB</code>, <code>AG</code></td><td><?php esc_html_e( 'Selects Lifetime Bests or Age-Group Personal Bests. LB is the default. With best="AG" and no ag attribute, the age group is determined from the swimmer\'s chronological age on the current page/post published date.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>ag</code></td><td><code>current</code> or an age group such as <code>50-54</code></td><td><?php esc_html_e( 'Selects an Age-Group Personal Best period. current uses the swimmer\'s age today. A specific group selects that historical age group. Supplying ag implies Age-Group mode. Hyphenated age groups are normalized to the plugin\'s internal age-group label.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>user</code></td><td><?php esc_html_e( 'WordPress numeric user ID', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php esc_html_e( 'Overrides swimmer resolution. When omitted, the swimmer is the current page/post author. There is no logged-in-viewer fallback.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>course</code></td><td><code>m</code>, <code>yd</code></td><td><?php esc_html_e( 'Selects meter or yard personal bests. Meter and yard records remain separate.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>stroke</code></td><td><code>FR</code>, <code>BR</code>, <code>BACK</code>, <code>FLY</code>, <code>MIXED</code></td><td><?php esc_html_e( 'Limits output to one stroke.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>distance</code></td><td><?php esc_html_e( 'Supported evaluation distance', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php esc_html_e( 'Limits output to one supported distance, such as 50, 100, or 200.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
					<tr><td><code>title</code></td><td><code>yes</code>, <code>no</code>, <?php esc_html_e( 'or custom text', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php esc_html_e( 'Controls the shortcode heading. yes uses the standard heading, no suppresses it, and any other text is used as a custom heading.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Age-Group Behavior', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><code>[swimlog_bests best="AG"]</code> &mdash; <?php esc_html_e( 'Uses the swimmer\'s age on the current page/post published date. This keeps historical pages tied to the age group that applied when the page/post was published.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><code>[swimlog_bests best="AG" ag="current"]</code> &mdash; <?php esc_html_e( 'Uses the swimmer\'s chronological age today.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><code>[swimlog_bests best="AG" ag="50-54"]</code> &mdash; <?php esc_html_e( 'Uses the explicitly selected 50–54 age group regardless of the page/post date or the swimmer\'s current age.', 'chriss-swim-training-progress-evaluation' ); ?></p>

			<h2><?php esc_html_e( 'Table Width and Page Layout', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'The personal-bests table fills the width provided by the WordPress page and active theme. For pages containing several personal-best tables, first use a Wide or Full Width Group block when the theme provides that option.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php esc_html_e( 'If the theme still constrains the shortcode output to a narrow content column, place the shortcode sections inside a Group block with the additional CSS class swim-pb-page. Set the Group block layout to Default rather than Constrained. In the WordPress code editor, the opening Group block can use the following markup:', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<pre><code>&lt;!-- wp:group {"className":"swim-pb-page","layout":{"type":"default"}} --&gt;
&lt;div class="wp-block-group swim-pb-page"&gt;</code></pre>
			<p><?php esc_html_e( 'Then add the following example to the site\'s Additional CSS. This expands the Group to the available viewport width and also removes a constrained theme width from the blocks inside the Group.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<pre><code>/* Swimming Personal Bests - full-width content area */
.swim-pb-page {
    width: calc(100vw - 32px) !important;
    max-width: none !important;
    position: relative !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding: 0 !important;
    float: none !important;
    clear: both !important;
}

/* Override the theme's constrained width on blocks inside this group */
.swim-pb-page &gt; * {
    width: 100% !important;
    max-width: none !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.swim-pb-page,
.swim-pb-page * {
    box-sizing: border-box;
}</code></pre>
			<p><?php esc_html_e( 'This CSS is an optional theme-layout workaround and is not required when the theme already provides an appropriate Wide or Full Width layout. The 32px subtraction leaves approximately 16px of space on each side of the viewport.', 'chriss-swim-training-progress-evaluation' ); ?></p>

			<h2><?php esc_html_e( 'Examples', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><code>[swimlog_bests]</code><br>
			<code>[swimlog_bests best="LB" course="m"]</code><br>
			<code>[swimlog_bests best="AG" course="m"]</code><br>
			<code>[swimlog_bests best="AG" ag="current" course="m"]</code><br>
			<code>[swimlog_bests best="AG" ag="50-54" course="m"]</code><br>
			<code>[swimlog_bests best="AG" ag="50-54" course="m" stroke="BR"]</code><br>
			<code>[swimlog_bests best="AG" ag="50-54" course="m" stroke="BR" distance="200"]</code><br>
			<code>[swimlog_bests course="yd" title="Yard Personal Bests"]</code><br>
			<code>[swimlog_bests course="m" title="no"]</code></p>

			<p><a href="<?php echo esc_url( $return_url ); ?>">&larr; <?php esc_html_e( 'Return to Shortcodes', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
		</div>
		<?php
	}

	public function privacy() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage swimmer configuration.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$uid = get_current_user_id();
		$saved = false;
		$error = '';
		if ( isset( $_POST['swimlog_privacy_action'] ) && 'save' === sanitize_key( wp_unslash( $_POST['swimlog_privacy_action'] ) ) ) {
			check_admin_referer( 'swimlog_save_privacy' );
			$public = isset( $_POST['public_results'] ) ? '1' : '0';
			$public_routes = isset( $_POST['public_ows_routes'] ) ? '1' : '0';
			$birthdate = isset( $_POST['birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['birthdate'] ) ) : '';
			if ( '' !== $birthdate ) {
				$parsed_birthdate = \DateTimeImmutable::createFromFormat( '!Y-m-d', $birthdate, wp_timezone() );
				$birthdate_errors = \DateTimeImmutable::getLastErrors();
				$valid_birthdate = $parsed_birthdate && ( false === $birthdate_errors || ( 0 === $birthdate_errors['warning_count'] && 0 === $birthdate_errors['error_count'] ) ) && $parsed_birthdate->format( 'Y-m-d' ) === $birthdate;
				$today = new \DateTimeImmutable( 'today', wp_timezone() );
				if ( ! $valid_birthdate || $parsed_birthdate > $today ) {
					$error = __( 'Enter a valid date of birth that is not in the future.', 'chriss-swim-training-progress-evaluation' );
				}
			}
			if ( '' === $error ) {
				if ( '' === $birthdate ) {
					delete_user_meta( $uid, 'swimlog_birthdate' );
				} else {
					update_user_meta( $uid, 'swimlog_birthdate', $birthdate );
				}
				update_user_meta( $uid, 'swimlog_public_results', $public );
				update_user_meta( $uid, 'swimlog_public_ows_routes', $public_routes );
				$saved = true;
			}
		}
		$value = get_user_meta( $uid, 'swimlog_public_results', true );
		if ( '' === $value ) {
			$value = get_option( 'swimlog_public_results_default', '0' );
		}
		$birthdate = (string) get_user_meta( $uid, 'swimlog_birthdate', true );
		$public_routes = (string) get_user_meta( $uid, 'swimlog_public_ows_routes', true );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-age-group.php';
		$current_age_group = Age_Group::for_user( $uid );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Swimmer configuration saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_privacy' ); ?>
				<input type="hidden" name="swimlog_privacy_action" value="save">
				<h2><?php esc_html_e( 'Swimmer Information', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="swimlog-birthdate"><?php esc_html_e( 'Date of Birth', 'chriss-swim-training-progress-evaluation' ); ?></label></th>
						<td><input id="swimlog-birthdate" type="date" name="birthdate" value="<?php echo esc_attr( $birthdate ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"><p class="description"><?php esc_html_e( 'Private. Used only to calculate your swimming age group. Your date of birth is not displayed by public swim output.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php if ( $current_age_group ) : ?><p><strong><?php esc_html_e( 'Current Age Group:', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php echo esc_html( $current_age_group['label'] ); ?></p><?php endif; ?></td>
					</tr>
				</table>
				<hr>
				<h2><?php esc_html_e( 'Privacy', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<p><?php esc_html_e( 'Your swim results are private unless you explicitly enable public results. This controls the public swim shortcodes; it does not change who can manage your workouts in WordPress administration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Public Results', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_results" value="1" <?php checked( $value, '1' ); ?>> <?php esc_html_e( 'Allow my swim results to be displayed by public swim shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><?php esc_html_e( 'Default is OFF. When OFF, you can still preview your own shortcode output while logged in. Administrators can also preview private results, but private results are not made public.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Public Open-Water Routes', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_ows_routes" value="1" <?php checked( $public_routes, '1' ); ?>> <?php esc_html_e( 'Allow GPS route shapes from my open-water workouts to appear in public workout shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><strong><?php esc_html_e( 'Default is OFF.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'GPS routes can reveal where you swim, including start and finish locations. This setting is separate from Public Results and must be enabled explicitly.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ) ); ?>
			</form>
		</div>
		<?php
	}
	private function garmin_activity_import_state( $user_id, $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) return array( 'status' => 'none', 'workout_id' => 0 );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		$imports = Database::table( 'imports' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT workout_id,import_status FROM $imports WHERE user_id=%d AND source_provider=%s AND external_activity_id=%s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id, 'garmin', (string) $activity_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $row ) return array( 'status' => 'none', 'workout_id' => 0 );
		if ( 'pending' === (string) $row->import_status ) return array( 'status' => 'pending', 'workout_id' => (int) $row->workout_id );
		if ( 'complete' === (string) $row->import_status ) return array( 'status' => 'imported', 'workout_id' => (int) $row->workout_id );
		return array( 'status' => 'recorded', 'workout_id' => (int) $row->workout_id );
	}

	private function garmin_activity_match_status( $user_id, $activity ) {
		$type = isset( $activity['activityType']['typeKey'] ) ? (string) $activity['activityType']['typeKey'] : '';
		if ( ! in_array( $type, array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
			return 'none';
		}

		$start = '';
		if ( ! empty( $activity['startTimeGMT'] ) ) {
			$start = (string) $activity['startTimeGMT'] . 'Z';
		} elseif ( ! empty( $activity['startTimeLocal'] ) ) {
			$start = (string) $activity['startTimeLocal'];
		}
		if ( '' === $start ) {
			return 'none';
		}

		$workout = array(
			'workout_type'    => 'open_water_swimming' === $type ? 'open_water' : 'pool',
			'workout_start'   => $start,
			'total_distance_m'=> isset( $activity['distance'] ) && is_numeric( $activity['distance'] ) ? (float) $activity['distance'] : 0.0,
			'elapsed_time_ms' => isset( $activity['duration'] ) && is_numeric( $activity['duration'] ) ? (int) round( (float) $activity['duration'] * 1000 ) : 0,
			'moving_time_ms'  => isset( $activity['movingDuration'] ) && is_numeric( $activity['movingDuration'] ) ? (int) round( (float) $activity['movingDuration'] * 1000 ) : 0,
		);

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-importer.php';
		$match = Importer::classify_match( $user_id, $workout );
		if ( 'exact' === $match['status'] ) {
			return 'exact';
		}
		if ( in_array( $match['status'], array( 'probable', 'disagreement' ), true ) ) {
			return 'similar';
		}
		return 'none';
	}

	public function handle_garmin_fit_download() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to download Garmin activity files.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_die( esc_html__( 'Garmin activity file diagnostics are available only when WordPress debugging is enabled.', 'chriss-swim-training-progress-evaluation' ) );
		}
		check_admin_referer( 'swimlog_garmin_download_fit' );
		$activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
		if ( ! $activity_id ) {
			wp_die( esc_html__( 'Invalid Garmin activity file request.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
		$result = Garmin_Personal_API::prepare_activity_fit( get_current_user_id(), $activity_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$fit_path = $result['tmp_name'];
		$filename = $result['filename'];
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . (string) $result['size'] );
		readfile( $fit_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Validated temporary Garmin FIT download.
		wp_delete_file( $fit_path );
		exit;
	}

	public function garmin() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage your Garmin Connect configuration.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		$user_id = get_current_user_id();
		$garmin_locations = Location::all_for_user( $user_id );
		$debugging = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$notice = '';
		$error = '';
		$activities = null;
		$activity_detail = null;
		$activity_full_detail = null;
		$import_preflight = null;
		$batch_results = array();
		$batch_summary = null;
		$garmin_today = wp_date( 'Y-m-d' );
		$garmin_start_date = wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( 10 * DAY_IN_SECONDS ) );
		$garmin_end_date = $garmin_today;
		$garmin_pool_swim = true;
		$garmin_open_water = false;
		$garmin_batch_location_id = 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Garmin activity detail selector.
		$detail_id = isset( $_GET['garmin_activity_id'] ) ? absint( wp_unslash( $_GET['garmin_activity_id'] ) ) : 0;
		if ( $detail_id ) {
			$activity_detail = Garmin_Personal_API::activity( $user_id, $detail_id );
			if ( is_wp_error( $activity_detail ) ) {
				$error = $activity_detail->get_error_message();
				$activity_detail = null;
			}
		}

		if ( isset( $_POST['swimlog_garmin_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['swimlog_garmin_action'] ) );

			if ( 'save_sync' === $action ) {
				check_admin_referer( 'swimlog_save_garmin_sync' );
				$enabled=isset($_POST['garmin_sync_enabled']);require_once SWIMLOG_EVALUATION_DIR.'includes/class-plugin.php';Plugin::set_garmin_sync_schedule($user_id,$enabled);
				$notice=$enabled?__('Scheduled Garmin pool-swim synchronization enabled.','chriss-swim-training-progress-evaluation'):__('Scheduled Garmin synchronization disabled.','chriss-swim-training-progress-evaluation');
			}
			if ( 'import_preflight' === $action ) {
				check_admin_referer( 'swimlog_garmin_import_preflight' );
				$requested_activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
				if ( ! $requested_activity_id || $requested_activity_id !== $detail_id ) {
					$error = __( 'Invalid Garmin activity import request.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( ! is_array( $activity_detail ) || ! isset( $activity_detail['activityTypeDTO']['typeKey'] ) || ! in_array( (string) $activity_detail['activityTypeDTO']['typeKey'], array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
					$error = __( 'Only Garmin pool-swimming and open-water-swimming activities are eligible for swim workout import.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$requested_location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
					if ( $requested_location_id && ! Location::get_for_user( $requested_location_id, $user_id ) ) {
						$error = __( 'Select one of your own locations.', 'chriss-swim-training-progress-evaluation' );
					} else {
						$import_preflight = Garmin_Personal_API::import_activity( $user_id, $requested_activity_id, $requested_location_id );
					}
					if ( is_wp_error( $import_preflight ) ) {
						$error = $import_preflight->get_error_message();
						$import_preflight = null;
					}
				}
			} elseif ( 'import_selected' === $action ) {
				check_admin_referer( 'swimlog_garmin_import_selected' );
				$garmin_start_date = isset( $_POST['garmin_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_start_date'] ) ) : $garmin_start_date;
				$garmin_end_date = isset( $_POST['garmin_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_end_date'] ) ) : $garmin_end_date;
				$garmin_pool_swim = isset( $_POST['garmin_pool_swim'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['garmin_pool_swim'] ) );
				$garmin_open_water = isset( $_POST['garmin_open_water'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['garmin_open_water'] ) );
				$garmin_batch_location_id = isset( $_POST['garmin_batch_location_id'] ) ? absint( wp_unslash( $_POST['garmin_batch_location_id'] ) ) : 0;
				$selected = isset( $_POST['garmin_activity_ids'] ) && is_array( $_POST['garmin_activity_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['garmin_activity_ids'] ) ) ) ) ) : array();
				if ( empty( $selected ) ) {
					$error = __( 'Select at least one Garmin activity to import.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( count( $selected ) > 25 ) {
					$error = __( 'A maximum of 25 Garmin activities may be imported at one time.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( $garmin_batch_location_id && ! Location::get_for_user( $garmin_batch_location_id, $user_id ) ) {
					$error = __( 'Select one of your own locations.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$counts = array( 'imported' => 0, 'already' => 0, 'pending' => 0, 'failed' => 0 );
					foreach ( $selected as $selected_id ) {
						$detail = Garmin_Personal_API::activity( $user_id, $selected_id );
						if ( is_wp_error( $detail ) || ! is_array( $detail ) || ! isset( $detail['activityTypeDTO']['typeKey'] ) || ! in_array( (string) $detail['activityTypeDTO']['typeKey'], array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
							$batch_results[] = array( 'activity_id' => $selected_id, 'status' => 'failed', 'message' => is_wp_error( $detail ) ? $detail->get_error_message() : __( 'Only Garmin pool-swimming and open-water-swimming activities are eligible for swim workout import.', 'chriss-swim-training-progress-evaluation' ) );
							$counts['failed']++;
							continue;
						}
						$selected_type = (string) $detail['activityTypeDTO']['typeKey'];
						$location_id = 'open_water_swimming' === $selected_type ? $garmin_batch_location_id : 0;
						$result = Garmin_Personal_API::import_activity( $user_id, $selected_id, $location_id );
						if ( is_wp_error( $result ) ) {
							$batch_results[] = array( 'activity_id' => $selected_id, 'status' => 'failed', 'message' => $result->get_error_message() );
							$counts['failed']++;
							continue;
						}
						$status = (string) ( $result['status'] ?? '' );
						if ( 'imported' === $status ) $counts['imported']++;
						elseif ( in_array( $status, array( 'already_associated', 'associated' ), true ) ) $counts['already']++;
						elseif ( 'pending_review' === $status ) $counts['pending']++;
						else $counts['failed']++;
						$batch_results[] = array_merge( array( 'activity_id' => $selected_id, 'status' => $status ), $result );
					}
					$batch_summary = array_merge( array( 'selected' => count( $selected ) ), $counts );
					$start_ts = strtotime( $garmin_start_date . ' 00:00:00' );$end_ts = strtotime( $garmin_end_date . ' 00:00:00' );
					if ( false !== $start_ts && false !== $end_ts && $end_ts >= $start_ts && ( $end_ts - $start_ts ) <= ( 30 * DAY_IN_SECONDS ) ) {
						$list = Garmin_Personal_API::recent_activities( $user_id, 100, $garmin_start_date, $garmin_end_date, array_filter( array( $garmin_pool_swim ? 'lap_swimming' : '', $garmin_open_water ? 'open_water_swimming' : '' ) ) );
						if ( ! is_wp_error( $list ) ) $activities = $list;
					}
				}
			} elseif ( 'activity_detail' === $action && $debugging ) {
				check_admin_referer( 'swimlog_garmin_activity_detail' );
				$requested_activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
				if ( ! $requested_activity_id || $requested_activity_id !== $detail_id ) {
					$error = __( 'Invalid Garmin activity detail request.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$result = Garmin_Personal_API::activity( $user_id, $requested_activity_id );
					if ( is_wp_error( $result ) ) {
						$error = $result->get_error_message();
					} else {
						$activity_full_detail = $result;
					}
				}
			} elseif ( 'save' === $action ) {
				check_admin_referer( 'swimlog_save_garmin_key' );
				$api_key = isset( $_POST['garmin_api_key'] ) ? trim( (string) wp_unslash( $_POST['garmin_api_key'] ) ) : '';
				$result = Garmin_Personal_API::save_key( $user_id, $api_key );
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$notice = __( 'Garmin Connect Personal API key saved securely.', 'chriss-swim-training-progress-evaluation' );
				}
			} elseif ( 'test' === $action ) {
				check_admin_referer( 'swimlog_test_garmin_connection' );
				$result = Garmin_Personal_API::test_connection( $user_id );
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$notice = __( 'Garmin Connect connection successful. An access token was returned.', 'chriss-swim-training-progress-evaluation' );
				}
			} elseif ( 'activities' === $action ) {
				check_admin_referer( 'swimlog_garmin_recent_activities' );
				$garmin_start_date = isset( $_POST['garmin_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_start_date'] ) ) : '';
				$garmin_end_date = isset( $_POST['garmin_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_end_date'] ) ) : '';
				$garmin_pool_swim = isset( $_POST['garmin_pool_swim'] );
				$garmin_open_water = isset( $_POST['garmin_open_water'] );
				$start_ts = strtotime( $garmin_start_date . ' 00:00:00' );
				$end_ts = strtotime( $garmin_end_date . ' 00:00:00' );
				$valid_dates = (bool) preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $garmin_start_date ) && (bool) preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $garmin_end_date );
				if ( ! $valid_dates || false === $start_ts || false === $end_ts || $end_ts < $start_ts || ( $end_ts - $start_ts ) > ( 30 * DAY_IN_SECONDS ) ) {
					$error = __( 'Choose a valid Garmin activity date range of no more than 30 days.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$result = Garmin_Personal_API::recent_activities( $user_id, 100, $garmin_start_date, $garmin_end_date, array_filter( array( $garmin_pool_swim ? 'lap_swimming' : '', $garmin_open_water ? 'open_water_swimming' : '' ) ) );
					if ( is_wp_error( $result ) ) {
						$error = $result->get_error_message();
					} else {
						$activities = $result;
					}
				}
			} elseif ( 'remove' === $action ) {
				check_admin_referer( 'swimlog_remove_garmin_key' );
				Garmin_Personal_API::remove_key( $user_id );
				$notice = __( 'Garmin Connect Personal API key removed.', 'chriss-swim-training-progress-evaluation' );
			}
		}

		$configured = Garmin_Personal_API::is_configured( $user_id );
		$sync_enabled='1'===(string)get_user_meta($user_id,'swimlog_garmin_sync_enabled',true);$sync_last=(string)get_user_meta($user_id,'swimlog_garmin_sync_last_run',true);$sync_result=(array)get_user_meta($user_id,'swimlog_garmin_sync_last_result',true);
		$activity_mode = $detail_id > 0 || is_array( $activities );
		?>
		<div class="wrap swimlog-admin"<?php echo ( $configured && ! $activity_mode ) ? ' data-swimlog-garmin-configured="1"' : ''; ?>>
			<h1><?php esc_html_e( 'Garmin Connect Personal API', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php if ( ! $configured ) : ?><p><?php esc_html_e( 'Configure your own Garmin Connect Personal API key. The key is encrypted before it is stored and is never displayed again after saving.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
			<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<?php if ( ! $activity_mode ) : ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Status', 'chriss-swim-training-progress-evaluation' ); ?></th><td><strong><?php echo $configured ? esc_html__( 'Configured', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Not configured', 'chriss-swim-training-progress-evaluation' ); ?></strong></td></tr>
			</table>

			<?php if ( $configured ) : ?><div id="swimlog-garmin-configuration-options" style="margin-top:32px;padding-top:20px;border-top:1px solid #c3c4c7"><h2 style="margin-top:0"><?php esc_html_e( 'Configuration Options — Click to Expand', 'chriss-swim-training-progress-evaluation' ); ?></h2></div><details id="swimlog-garmin-key-settings"><summary><strong><?php esc_html_e( 'Replace Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px"><?php else : ?><h2><?php esc_html_e( 'Add Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></h2><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_garmin_key' ); ?>
				<input type="hidden" name="swimlog_garmin_action" value="save">
				<table class="form-table" role="presentation"><tr><th scope="row"><label for="swimlog-garmin-api-key"><?php esc_html_e( 'Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-garmin-api-key" class="regular-text" type="password" name="garmin_api_key" value="" autocomplete="new-password" required><p class="description"><?php esc_html_e( 'The key is accepted only over this POST form, encrypted for storage, and is not written back into the page.', 'chriss-swim-training-progress-evaluation' ); ?></p><details style="margin-top:12px;max-width:760px"><summary><strong><?php esc_html_e( 'How do I get a Personal API Key?', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:10px"><ol><li><?php esc_html_e( 'Sign in to your Garmin Connect account.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Open Settings.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Select Personal API Key from the menu on the left.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Create a new Personal API Key.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Copy the generated API key.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Return to this page and paste the value into the Personal API Key field above.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php echo $configured ? esc_html__( 'Click Replace API Key.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Click Save API Key.', 'chriss-swim-training-progress-evaluation' ); ?></li></ol><p><strong><?php esc_html_e( 'Keep your API key private.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'Treat it like a password and do not share it. After saving, this plugin encrypts the key for storage and does not display the saved value again.', 'chriss-swim-training-progress-evaluation' ); ?></p></div></details></td></tr></table>
				<?php submit_button( $configured ? __( 'Replace API Key', 'chriss-swim-training-progress-evaluation' ) : __( 'Save API Key', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', true, array( 'id' => 'swimlog-garmin-api-key-submit', 'disabled' => 'disabled' ) ); ?>
			</form>
			<?php if ( $configured ) : ?></div></details><?php endif; ?>

			<?php endif; ?>

			<?php if ( $configured ) : ?>
	
			<?php if ( $configured && ! $activity_mode ) : ?><hr><h2><?php esc_html_e('Scheduled Synchronization','chriss-swim-training-progress-evaluation'); ?></h2><form method="post"><?php wp_nonce_field('swimlog_save_garmin_sync'); ?><input type="hidden" name="swimlog_garmin_action" value="save_sync"><p><label><input type="checkbox" name="garmin_sync_enabled" value="1" <?php checked($sync_enabled); ?>> <?php esc_html_e('Check Garmin hourly for new pool-swimming activities.','chriss-swim-training-progress-evaluation'); ?></label></p><p class="description"><?php esc_html_e('The sync looks back seven days. Pool swims can import automatically. Open-water swims are left for manual review so a saved location can be chosen. Duplicate activities are skipped, and ambiguous matches remain pending for review.','chriss-swim-training-progress-evaluation'); ?></p><?php if($sync_last):?><p><strong><?php esc_html_e('Last run:','chriss-swim-training-progress-evaluation'); ?></strong> <?php echo esc_html($sync_last); ?><?php if($sync_result):?> — <?php echo esc_html(sprintf(__('Imported %1$d; already known %2$d; pending %3$d; open water for review %4$d; failed %5$d.','chriss-swim-training-progress-evaluation'),(int)($sync_result['imported']??0),(int)($sync_result['already']??0),(int)($sync_result['pending']??0),(int)($sync_result['open_water_review']??0),(int)($sync_result['failed']??0))); ?><?php endif;?></p><?php endif;?><?php submit_button(__('Save Synchronization Setting','chriss-swim-training-progress-evaluation'),'secondary'); ?></form><?php endif; ?>
			<?php if ( ! $activity_mode ) : ?>
				<details id="swimlog-garmin-connection"><summary><strong><?php esc_html_e( 'Test Connection', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px">
				<p><?php esc_html_e( 'Test the saved credential by requesting a short-lived access token from Garmin Connect. The access token is not stored by this test.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'swimlog_test_garmin_connection' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="test">
					<?php submit_button( __( 'Test Connection', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				</form>
				</div></details>
				<?php endif; ?>

				<?php if ( is_array( $activity_detail ) ) :
					$summary = isset( $activity_detail['summaryDTO'] ) && is_array( $activity_detail['summaryDTO'] ) ? $activity_detail['summaryDTO'] : array();
					$atype = isset( $activity_detail['activityTypeDTO']['typeKey'] ) ? (string) $activity_detail['activityTypeDTO']['typeKey'] : '';
					$aname = isset( $activity_detail['activityName'] ) ? (string) $activity_detail['activityName'] : '';
					$astart = isset( $activity_detail['startTimeLocal'] ) ? (string) $activity_detail['startTimeLocal'] : '';
					$adistance = isset( $summary['distance'] ) && is_numeric( $summary['distance'] ) ? number_format_i18n( (float) $summary['distance'], 1 ) . ' m' : '—';
					$aduration = isset( $summary['duration'] ) && is_numeric( $summary['duration'] ) ? gmdate( 'H:i:s', max( 0, (int) round( (float) $summary['duration'] ) ) ) : '—';
					$acalories = isset( $summary['calories'] ) && is_numeric( $summary['calories'] ) ? number_format_i18n( (float) $summary['calories'], 0 ) : '—';
					$aavg_hr = isset( $summary['averageHR'] ) && is_numeric( $summary['averageHR'] ) ? number_format_i18n( (float) $summary['averageHR'], 0 ) . ' bpm' : '—';
					$amax_hr = isset( $summary['maxHR'] ) && is_numeric( $summary['maxHR'] ) ? number_format_i18n( (float) $summary['maxHR'], 0 ) . ' bpm' : '—';
					$detail_import_state = $this->garmin_activity_import_state( $user_id, $detail_id );
				?>
					<hr><h2><?php esc_html_e( 'Garmin Activity Details', 'chriss-swim-training-progress-evaluation' ); ?></h2>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-garmin' ) ); ?>"><?php esc_html_e( 'Back to Garmin Connect', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
					<table class="widefat striped"><tbody>
						<tr><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( (string) $detail_id ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aname ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $atype ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Start', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $astart ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $adistance ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Duration', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aduration ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Calories', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $acalories ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Average HR', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aavg_hr ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Maximum HR', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $amax_hr ); ?></td></tr>
					</tbody></table>
					<?php if ( 'imported' === $detail_import_state['status'] ) : ?>
						<p style="margin-top:16px"><strong><?php esc_html_e( 'Already imported.', 'chriss-swim-training-progress-evaluation' ); ?></strong><?php if ( $detail_import_state['workout_id'] ) : ?> <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'swimlog-workouts', 'workout_id'=>$detail_import_state['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Workout', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?></p>
					<?php elseif ( 'pending' === $detail_import_state['status'] ) : ?>
						<p style="margin-top:16px"><strong><?php esc_html_e( 'Review required.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
					<?php else : ?>
					<form method="post" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_import_preflight' ); ?>
						<input type="hidden" name="swimlog_garmin_action" value="import_preflight">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php if ( 'open_water_swimming' === $atype ) : ?>
							<p><label for="swimlog-garmin-location"><strong><?php esc_html_e( 'Open Water Location', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br>
							<select id="swimlog-garmin-location" name="location_id"><option value="0"><?php esc_html_e( 'No location selected', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( $garmin_locations as $garmin_location ) : ?><option value="<?php echo esc_attr( $garmin_location->id ); ?>"><?php echo esc_html( $garmin_location->name ); ?></option><?php endforeach; ?></select></p>
							<p class="description"><?php esc_html_e( 'Assign the saved swim location while importing. GPS track and distance data remain unchanged.', 'chriss-swim-training-progress-evaluation' ); ?></p>
						<?php endif; ?>
						<?php submit_button( __( 'Import Activity', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false ); ?>
					</form>
					<?php endif; ?>
					<?php if ( is_array( $import_preflight ) ) : ?>
						<?php if ( 'already_associated' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Preflight stopped safely: Garmin activity %1$d is already associated with import #%2$d and workout #%3$d. No data was changed.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['workout_id']
								) );
							?></p></div>
						<?php elseif ( 'associated' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Garmin activity %1$d was safely associated with existing import #%2$d / workout #%3$d after an exact FIT SHA-256 match. No workout data was changed.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['workout_id']
								) );
							?></p></div>
						<?php elseif ( 'file_exists' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin filename, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Preflight stopped safely: %1$s has the same SHA-256 fingerprint as existing import #%2$d / workout #%3$d. No association or data change was made.', 'chriss-swim-training-progress-evaluation' ),
									(string) $import_preflight['filename'],
									(int) $import_preflight['existing_import_id'],
									(int) $import_preflight['existing_workout_id']
								) );
							?></p></div>
						<?php elseif ( 'imported' === $import_preflight['status'] ) : ?>
							<div class="notice notice-success inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: imported workout ID. */
									__( 'Activity Imported: Garmin activity %1$d was successfully imported as workout #%2$d.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['workout_id']
								) );
							?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => (int) $import_preflight['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Activity', 'chriss-swim-training-progress-evaluation' ); ?></a></p></div>
						<?php elseif ( 'pending_review' === $import_preflight['status'] ) : ?>
							<div class="notice notice-warning inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: pending import ID, 3: candidate workout ID. */
									__( 'Import Review Required: Garmin activity %1$d was preserved as pending import #%2$d because it may match workout #%3$d. No automatic attachment or duplicate workout was created.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['candidate_workout_id']
								) );
							?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a></p></div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $debugging ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_download_fit' ); ?>
						<input type="hidden" name="action" value="swimlog_garmin_download_fit">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php submit_button( __( 'Download Garmin FIT', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_activity_detail' ); ?>
						<input type="hidden" name="swimlog_garmin_action" value="activity_detail">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php submit_button( __( 'Get Activity Detail', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
					</form>
					<?php endif; ?>
					<?php if ( $debugging && is_array( $activity_full_detail ) ) :
						$detail_sections = array(
							'metadataDTO' => __( 'Metadata', 'chriss-swim-training-progress-evaluation' ),
							'summaryDTO' => __( 'Summary / Measurements', 'chriss-swim-training-progress-evaluation' ),
							'splitSummaries' => __( 'Split Summaries', 'chriss-swim-training-progress-evaluation' ),
							'activityWorkout' => __( 'Associated Workout', 'chriss-swim-training-progress-evaluation' ),
						);
					?>
						<h3><?php esc_html_e( 'Activity Detail Structure', 'chriss-swim-training-progress-evaluation' ); ?></h3>
						<p><?php esc_html_e( 'This diagnostic view shows which documented detail sections Garmin returned for this activity. Nothing is imported or stored.', 'chriss-swim-training-progress-evaluation' ); ?></p>
						<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Section', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Returned', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Fields / Items', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
						<?php foreach ( $detail_sections as $detail_key => $detail_label ) :
							$detail_value = $activity_full_detail[ $detail_key ] ?? null;
							$returned = null !== $detail_value && array() !== $detail_value && '' !== $detail_value;
							$count = is_array( $detail_value ) ? count( $detail_value ) : ( $returned ? 1 : 0 );
						?>
							<tr><th><?php echo esc_html( $detail_label ); ?></th><td><?php echo $returned ? esc_html__( 'Yes', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'No', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
						<?php foreach ( array( 'metadataDTO' => __( 'Metadata Fields', 'chriss-swim-training-progress-evaluation' ), 'summaryDTO' => __( 'Summary / Measurement Fields', 'chriss-swim-training-progress-evaluation' ) ) as $field_section_key => $field_section_label ) :
							$field_section = isset( $activity_full_detail[ $field_section_key ] ) && is_array( $activity_full_detail[ $field_section_key ] ) ? $activity_full_detail[ $field_section_key ] : array();
							if ( empty( $field_section ) ) {
								continue;
							}
						?>
							<h4><?php echo esc_html( $field_section_label ); ?></h4>
							<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Field', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Value', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
							<?php foreach ( $field_section as $field_name => $field_value ) :
								if ( is_array( $field_value ) ) {
									$display_value = sprintf(
										/* translators: %d: number of nested items. */
										__( '[nested structure: %d items]', 'chriss-swim-training-progress-evaluation' ),
										count( $field_value )
									);
								} elseif ( is_bool( $field_value ) ) {
									$display_value = $field_value ? __( 'true', 'chriss-swim-training-progress-evaluation' ) : __( 'false', 'chriss-swim-training-progress-evaluation' );
								} elseif ( null === $field_value || '' === $field_value ) {
									$display_value = '—';
								} elseif ( is_scalar( $field_value ) ) {
									$display_value = (string) $field_value;
								} else {
									$display_value = __( '[non-scalar value]', 'chriss-swim-training-progress-evaluation' );
								}
							?>
								<tr><th><code><?php echo esc_html( (string) $field_name ); ?></code></th><td><?php echo esc_html( $display_value ); ?></td></tr>
							<?php endforeach; ?>
							</tbody></table>
						<?php endforeach; ?>
						<h4><?php esc_html_e( 'Top-level Garmin fields returned', 'chriss-swim-training-progress-evaluation' ); ?></h4>
						<p><code><?php echo esc_html( implode( ', ', array_keys( $activity_full_detail ) ) ); ?></code></p>
					<?php endif; ?>
				<?php endif; ?>

				<div id="swimlog-garmin-activities"><h2><?php esc_html_e( 'Garmin Activities', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<p><?php esc_html_e( 'Retrieve Garmin activities for a date range of up to 30 days. This inspection does not import or change swim training data.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'swimlog_garmin_recent_activities' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="activities">
					<p><label for="swimlog-garmin-start-date"><strong><?php esc_html_e( 'Start date', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br><input id="swimlog-garmin-start-date" type="date" name="garmin_start_date" value="<?php echo esc_attr( $garmin_start_date ); ?>" required></p>
					<p><label for="swimlog-garmin-end-date"><strong><?php esc_html_e( 'End date', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br><input id="swimlog-garmin-end-date" type="date" name="garmin_end_date" value="<?php echo esc_attr( $garmin_end_date ); ?>" max="<?php echo esc_attr( $garmin_today ); ?>" required></p>
					<fieldset><legend><strong><?php esc_html_e( 'Activity types', 'chriss-swim-training-progress-evaluation' ); ?></strong></legend>
						<label><input type="checkbox" name="garmin_pool_swim" value="1" <?php checked( $garmin_pool_swim ); ?>> <?php esc_html_e( 'Pool Swim', 'chriss-swim-training-progress-evaluation' ); ?></label><br>
						<label><input type="checkbox" name="garmin_open_water" value="1" <?php checked( $garmin_open_water ); ?>> <?php esc_html_e( 'Open Water', 'chriss-swim-training-progress-evaluation' ); ?></label>
					</fieldset>
					<?php submit_button( __( 'Retrieve Activities', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( is_array( $activities ) ) : ?>
					<?php if ( $batch_summary ) : ?>
						<div class="notice notice-info inline"><p><?php echo esc_html( sprintf(
							/* translators: 1: selected count, 2: imported, 3: already imported, 4: pending review, 5: failed. */
							__( '%1$d selected — %2$d imported, %3$d already imported, %4$d require review, %5$d failed.', 'chriss-swim-training-progress-evaluation' ),
							(int) $batch_summary['selected'], (int) $batch_summary['imported'], (int) $batch_summary['already'], (int) $batch_summary['pending'], (int) $batch_summary['failed']
						) ); ?></p></div>
						<table class="widefat striped" style="margin-bottom:16px"><thead><tr><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Result', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Action', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
						<?php foreach ( $batch_results as $batch_result ) : $batch_status = (string) ( $batch_result['status'] ?? 'failed' ); ?>
							<tr><td><?php echo esc_html( (string) $batch_result['activity_id'] ); ?></td><td><?php
								if ( 'imported' === $batch_status ) esc_html_e( 'Imported', 'chriss-swim-training-progress-evaluation' );
								elseif ( in_array( $batch_status, array( 'already_associated', 'associated' ), true ) ) esc_html_e( 'Already imported', 'chriss-swim-training-progress-evaluation' );
								elseif ( 'pending_review' === $batch_status ) esc_html_e( 'Review required', 'chriss-swim-training-progress-evaluation' );
								else echo esc_html( (string) ( $batch_result['message'] ?? __( 'Import failed.', 'chriss-swim-training-progress-evaluation' ) ) );
							?></td><td>
							<?php if ( 'imported' === $batch_status && ! empty( $batch_result['workout_id'] ) ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => (int) $batch_result['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Activity', 'chriss-swim-training-progress-evaluation' ); ?></a>
							<?php elseif ( 'pending_review' === $batch_status ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a>
							<?php else : ?>—<?php endif; ?></td></tr>
						<?php endforeach; ?></tbody></table>
					<?php endif; ?>
					<?php if ( empty( $activities ) ) : ?>
						<p><?php esc_html_e( 'Garmin returned no activities.', 'chriss-swim-training-progress-evaluation' ); ?></p>
					<?php else : ?>
						<form method="post" id="swimlog-garmin-batch-form">
							<?php wp_nonce_field( 'swimlog_garmin_import_selected' ); ?>
							<input type="hidden" name="swimlog_garmin_action" value="import_selected">
							<input type="hidden" name="garmin_start_date" value="<?php echo esc_attr( $garmin_start_date ); ?>">
							<input type="hidden" name="garmin_end_date" value="<?php echo esc_attr( $garmin_end_date ); ?>">
							<input type="hidden" name="garmin_pool_swim" value="<?php echo $garmin_pool_swim ? '1' : '0'; ?>">
							<input type="hidden" name="garmin_open_water" value="<?php echo $garmin_open_water ? '1' : '0'; ?>">
							<p><label for="swimlog-garmin-batch-location"><strong><?php esc_html_e( 'Open Water Location', 'chriss-swim-training-progress-evaluation' ); ?></strong></label> <select id="swimlog-garmin-batch-location" name="garmin_batch_location_id"><option value="0"><?php esc_html_e( 'No location selected', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( $garmin_locations as $location ) : ?><option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $garmin_batch_location_id, (int) $location->id ); ?>><?php echo esc_html( $location->name ); ?></option><?php endforeach; ?></select> <span class="description"><?php esc_html_e( 'Applied only to selected open-water activities. Pool imports ignore this setting.', 'chriss-swim-training-progress-evaluation' ); ?></span></p>
							<p><?php submit_button( __( 'Import Selected', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false, array( 'id' => 'swimlog-garmin-import-selected', 'data-swimlog-garmin-import-selected' => '1', 'disabled' => 'disabled' ) ); ?> <span class="description"><?php esc_html_e( 'Select up to 25 activities.', 'chriss-swim-training-progress-evaluation' ); ?></span></p>
							<div id="swimlog-garmin-import-progress" hidden aria-live="polite"><span class="spinner" aria-hidden="true"></span><strong class="swimlog-garmin-import-progress-text"></strong></div>
							<table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Select', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Duration', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Import Status', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
							<?php foreach ( $activities as $activity ) :
								$activity_id = isset( $activity['activityId'] ) ? (string) $activity['activityId'] : '';
								$date = isset( $activity['startTimeLocal'] ) ? (string) $activity['startTimeLocal'] : '';
								$name = isset( $activity['activityName'] ) ? (string) $activity['activityName'] : '';
								$type = isset( $activity['activityType']['typeKey'] ) ? (string) $activity['activityType']['typeKey'] : '';
								$distance = isset( $activity['distance'] ) && is_numeric( $activity['distance'] ) ? number_format_i18n( (float) $activity['distance'], 1 ) . ' m' : '—';
								$duration = isset( $activity['duration'] ) && is_numeric( $activity['duration'] ) ? gmdate( 'H:i:s', max( 0, (int) round( (float) $activity['duration'] ) ) ) : '—';
								$match_status = $this->garmin_activity_match_status( $user_id, $activity );
								$import_state = $this->garmin_activity_import_state( $user_id, $activity_id );
							?>
							<?php
								$row_state = in_array( $import_state['status'], array( 'imported', 'pending', 'recorded' ), true )
									? $import_state['status']
									: ( 'exact' === $match_status ? 'exact-match' : ( 'similar' === $match_status ? 'possible-match' : 'new' ) );
							?>
							<tr class="<?php echo esc_attr( 'swimlog-garmin-state-' . $row_state ); ?>"><td><input class="swimlog-garmin-select" type="checkbox" name="garmin_activity_ids[]" value="<?php echo esc_attr( $activity_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select Garmin activity %s', 'chriss-swim-training-progress-evaluation' ), $activity_id ) ); ?>" <?php disabled( '' === $activity_id || in_array( $import_state['status'], array( 'imported', 'pending' ), true ) ); ?>></td><td><?php if ( $activity_id ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'garmin_activity_id' => $activity_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $activity_id ); ?></a><?php else : ?>—<?php endif; ?></td><td><?php echo esc_html( $date ?: '—' ); ?></td><td><?php echo esc_html( $name ?: '—' ); ?></td><td><?php echo esc_html( $type ?: '—' ); ?></td><td><?php echo esc_html( $distance ); ?></td><td><?php echo esc_html( $duration ); ?></td><td><?php
								if ( 'imported' === $import_state['status'] ) {
									esc_html_e( 'Imported', 'chriss-swim-training-progress-evaluation' );
									if ( $import_state['workout_id'] ) echo ' — <a href="' . esc_url( add_query_arg( array( 'page'=>'swimlog-workouts', 'workout_id'=>$import_state['workout_id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'View Workout', 'chriss-swim-training-progress-evaluation' ) . '</a>';
								} elseif ( 'pending' === $import_state['status'] ) {
									echo esc_html__( 'Review required', 'chriss-swim-training-progress-evaluation' ) . ' — <a href="' . esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ) . '">' . esc_html__( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ) . '</a>';
								} elseif ( 'recorded' === $import_state['status'] ) {
									esc_html_e( 'Import recorded', 'chriss-swim-training-progress-evaluation' );
								} elseif ( 'exact' === $match_status ) {
									esc_html_e( 'Exact match — not yet associated', 'chriss-swim-training-progress-evaluation' );
								} elseif ( 'similar' === $match_status ) {
									esc_html_e( 'Possible match — not yet associated', 'chriss-swim-training-progress-evaluation' );
								} else {
									esc_html_e( 'New — not imported', 'chriss-swim-training-progress-evaluation' );
								}
							?></td></tr>
							<?php endforeach; ?></tbody></table>
							<p><?php submit_button( __( 'Import Selected', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit_bottom', false, array( 'data-swimlog-garmin-import-selected' => '1', 'disabled' => 'disabled' ) ); ?></p>
						</form>
					<?php endif; ?>
				<?php endif; ?>

				</div>
				<?php if ( ! $activity_mode ) : ?>
				<details id="swimlog-garmin-remove"><summary><strong><?php esc_html_e( 'Remove Garmin Connect Configuration', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px">
				<form method="post">
					<?php wp_nonce_field( 'swimlog_remove_garmin_key' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="remove">
					<?php submit_button( __( 'Remove API Key', 'chriss-swim-training-progress-evaluation' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Remove your stored Garmin Connect Personal API key?', 'chriss-swim-training-progress-evaluation' ) ) . "');" ) ); ?>
				</form>
				</div></details>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function settings() {
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to manage swim training settings.','chriss-swim-training-progress-evaluation'));$saved=false;$settings_error=null;$rebuild=null;$rebuild_error=null;
		if(isset($_POST['swimlog_rebuild_action'])&&'rebuild'===sanitize_key(wp_unslash($_POST['swimlog_rebuild_action']))){
			check_admin_referer('swimlog_rebuild_performances');
			require_once SWIMLOG_EVALUATION_DIR.'includes/class-database.php';require_once SWIMLOG_EVALUATION_DIR.'includes/class-evaluator.php';
			$rebuild=Evaluator::rebuild_all_users();if(is_wp_error($rebuild)){$rebuild_error=$rebuild->get_error_message();$rebuild=null;}
		}
		if(isset($_POST['swimlog_settings_action'])&&'save'===sanitize_key(wp_unslash($_POST['swimlog_settings_action']))){
			check_admin_referer('swimlog_save_settings');
			$course=sanitize_key(wp_unslash($_POST['default_course_unit']??'m'));if(!in_array($course,array('m','yd'),true))$course='m';
			$count=max(1,min(50,absint(wp_unslash($_POST['default_event_count']??5))));
			$types = array();
			if ( isset( $_POST['allowed_upload_types'] ) && is_array( $_POST['allowed_upload_types'] ) ) {
				$submitted_types = array_map( 'sanitize_key', wp_unslash( $_POST['allowed_upload_types'] ) );
				$types = array_values( array_intersect( $submitted_types, array( 'fit', 'csv', 'gpx', 'tcx' ) ) );
			}
			if(!$types)$types=array('fit','csv','gpx','tcx');
			$storage_dir=isset($_POST['private_storage_dir'])?sanitize_text_field(wp_unslash($_POST['private_storage_dir'])):'';
			if(defined('SWIMLOG_PRIVATE_STORAGE_DIR')){
				$storage_dir=trim((string)SWIMLOG_PRIVATE_STORAGE_DIR);
			}else{
				require_once SWIMLOG_EVALUATION_DIR.'includes/class-importer.php';
				if($storage_dir!==''){
					$storage_check=Importer::validate_storage_directory($storage_dir,true);
					if(is_wp_error($storage_check))$settings_error=$storage_check->get_error_message();else $storage_dir=$storage_check;
				}
			}
			if(!$settings_error){
				update_option('swimlog_default_course_unit',$course);update_option('swimlog_default_event_count',$count);update_option('swimlog_allowed_upload_types',array_values(array_unique($types)));
				if(!defined('SWIMLOG_PRIVATE_STORAGE_DIR'))update_option('swimlog_private_storage_dir',$storage_dir);
				// v0.2 preservation remains enabled; destructive uninstall is deliberately not exposed here.
				update_option('swimlog_data_preservation','1');update_option('swimlog_public_results_default','0');$saved=true;
			}
		}
		$course=get_option('swimlog_default_course_unit','m');$count=(int)get_option('swimlog_default_event_count',5);$types=(array)get_option('swimlog_allowed_upload_types',array('fit','csv','gpx','tcx'));
		$storage_locked=defined('SWIMLOG_PRIVATE_STORAGE_DIR');$storage_dir=$storage_locked?trim((string)SWIMLOG_PRIVATE_STORAGE_DIR):trim((string)get_option('swimlog_private_storage_dir',''));
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Swim Training Settings','chriss-swim-training-progress-evaluation'); ?></h1><?php if($saved):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?><?php if($settings_error):?><div class="notice notice-error"><p><?php echo esc_html($settings_error); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_save_settings'); ?><input type="hidden" name="swimlog_settings_action" value="save"><h2><?php esc_html_e('Defaults','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation">
		<tr><th><label for="swimlog-default-course"><?php esc_html_e('Default course','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-default-course" name="default_course_unit"><option value="m" <?php selected($course,'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($course,'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select></td></tr>
		<tr><th><label for="swimlog-event-count"><?php esc_html_e('Upcoming event count','chriss-swim-training-progress-evaluation'); ?></label></th><td><input id="swimlog-event-count" type="number" min="1" max="50" name="default_event_count" value="<?php echo esc_attr($count); ?>"></td></tr></table>
		<h2><?php esc_html_e('Upload Controls','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Allowed workout files','chriss-swim-training-progress-evaluation'); ?></th><td><label><input type="checkbox" name="allowed_upload_types[]" value="fit" <?php checked(in_array('fit',$types,true)); ?>> FIT</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="csv" <?php checked(in_array('csv',$types,true)); ?>> CSV</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="gpx" <?php checked(in_array('gpx',$types,true)); ?>> GPX</label><br><label><input type="checkbox" name="allowed_upload_types[]" value="tcx" <?php checked(in_array('tcx',$types,true)); ?>> TCX</label><p class="description"><?php esc_html_e('At least one type must remain enabled. File content is still validated during import.','chriss-swim-training-progress-evaluation'); ?></p></td></tr>
		<tr><th><label for="swimlog-private-storage"><?php esc_html_e('Private Workout Storage Directory','chriss-swim-training-progress-evaluation'); ?></label></th><td><input id="swimlog-private-storage" class="regular-text code" type="text" name="private_storage_dir" value="<?php echo esc_attr($storage_dir); ?>" <?php disabled($storage_locked); ?>><p class="description"><?php esc_html_e('Absolute server filesystem path outside the public web root. WordPress must be able to create or write to this directory. Original FIT, CSV, GPX, and TCX files are preserved here.','chriss-swim-training-progress-evaluation'); ?></p><?php if($storage_locked):?><p class="description"><strong><?php esc_html_e('This path is locked by SWIMLOG_PRIVATE_STORAGE_DIR in server configuration.','chriss-swim-training-progress-evaluation'); ?></strong></p><?php elseif($storage_dir===''):?><p class="description"><?php esc_html_e('If left blank, the plugin will try to derive a private directory one level above the web document root.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?></td></tr></table>
		<h2><?php esc_html_e('Privacy and Data Preservation','chriss-swim-training-progress-evaluation'); ?></h2><table class="form-table" role="presentation"><tr><th><?php esc_html_e('Public Results default','chriss-swim-training-progress-evaluation'); ?></th><td><strong><?php esc_html_e('OFF','chriss-swim-training-progress-evaluation'); ?></strong><p class="description"><?php esc_html_e('New swimmers remain private until they explicitly enable Public Results on their Privacy page.','chriss-swim-training-progress-evaluation'); ?></p></td></tr><tr><th><?php esc_html_e('Historical data','chriss-swim-training-progress-evaluation'); ?></th><td><strong><?php esc_html_e('Preserve data','chriss-swim-training-progress-evaluation'); ?></strong><p class="description"><?php esc_html_e('Deactivation, normal updates, and derived-performance rebuilds do not delete workout history, original source files, normalized laps or lengths, locations, or events. Data preservation is mandatory in v0.2.','chriss-swim-training-progress-evaluation'); ?></p></td></tr></table>
		<?php submit_button(__('Save Settings','chriss-swim-training-progress-evaluation')); ?></form>
		<hr><h2><?php esc_html_e('Rebuild Derived Performances','chriss-swim-training-progress-evaluation'); ?></h2>
		<p><?php esc_html_e('Recalculate all derived performances and personal-best flags from the authoritative normalized workout history for every swimmer. Use this after evaluator changes or when calculated results need to be regenerated.','chriss-swim-training-progress-evaluation'); ?></p>
		<div class="notice notice-info inline"><p><strong><?php esc_html_e('Historical data is protected.','chriss-swim-training-progress-evaluation'); ?></strong> <?php esc_html_e('This operation replaces only derived performance records. It does not delete or modify original FIT/CSV/GPX/TCX source files, imports, workouts, laps, lengths, locations, events, or swimmer privacy settings.','chriss-swim-training-progress-evaluation'); ?></p></div>
		<?php if($rebuild_error):?><div class="notice notice-error inline"><p><?php echo esc_html($rebuild_error); ?></p></div><?php endif;?>
		<?php if($rebuild):?><div class="notice notice-success inline"><p><?php /* translators: 1: swimmers rebuilt, 2: workouts processed, 3: derived performances generated, 4: previous derived rows replaced. */ echo esc_html(sprintf(__('Rebuild complete: %1$d swimmers, %2$d workouts processed, and %3$d derived performances generated. %4$d previous derived performance rows were replaced.','chriss-swim-training-progress-evaluation'),$rebuild['users'],$rebuild['workouts'],$rebuild['performances'],$rebuild['deleted'])); ?></p></div><?php endif;?>
		<form method="post"><?php wp_nonce_field('swimlog_rebuild_performances'); ?><input type="hidden" name="swimlog_rebuild_action" value="rebuild"><?php submit_button(__('Rebuild Derived Performances','chriss-swim-training-progress-evaluation'),'secondary','submit',false,array('onclick'=>"return confirm('".esc_js(__('Rebuild all derived performances and personal-best flags? Historical workout and source data will not be changed.','chriss-swim-training-progress-evaluation'))."');")); ?></form>
		</div><?php
	}
}
