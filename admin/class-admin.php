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
		$this->submenu( 'swimlog-dashboard', __( 'Training Volume', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-training-volume', 'training_volume' );
		$this->submenu( 'swimlog-dashboard', __( 'Training Trends', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-training-trends', 'training_trends' );
		$this->submenu( 'swimlog-dashboard', __( 'Pool Analytics', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-pool-analytics', 'pool_analytics' );
		$this->submenu( 'swimlog-dashboard', __( 'Upload Workout', 'chriss-swim-training-progress-evaluation' ), 'swimlog_upload_workouts', 'swimlog-upload', 'upload' );
		$this->submenu( 'swimlog-dashboard', __( 'Add Manual Workout', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-manual-workout', 'manual_workout' );
		$this->submenu( 'swimlog-dashboard', __( 'Personal Bests', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-personal-bests', 'personal_bests' );
		$this->submenu( 'swimlog-dashboard', __( 'Events', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_events', 'swimlog-events', 'events' );
		$this->submenu( 'swimlog-dashboard', __( 'Locations', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_own_locations', 'swimlog-locations', 'locations' );
		$this->submenu( 'swimlog-dashboard', __( 'Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-privacy', 'privacy' );
		$this->submenu( 'swimlog-dashboard', __( 'Garmin Connect', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-garmin', 'garmin' );
		add_submenu_page( null, __( 'REST API Documentation', 'chriss-swim-training-progress-evaluation' ), __( 'REST API Documentation', 'chriss-swim-training-progress-evaluation' ), 'swimlog_view_own_results', 'swimlog-api-help', array( $this, 'api_help' ) );

		if ( current_user_can( 'swimlog_manage_settings' ) ) {
			$this->submenu( 'swimlog-dashboard', __( 'Shortcodes', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcodes', 'shortcodes' );
			add_submenu_page( null, __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), __( 'swimlog_bests Shortcode', 'chriss-swim-training-progress-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcode-bests', array( $this, 'shortcode_bests' ) );
			foreach(array('latest'=>'Latest','upcoming-events'=>'Upcoming Events','workout'=>'Workout','log'=>'Training Log','progress'=>'Progress')as$slug=>$label)add_submenu_page(null,sprintf(/* translators: %s: shortcode feature name. */ __('%s Shortcode','chriss-swim-training-progress-evaluation'),$label),sprintf(/* translators: %s: shortcode feature name. */ __('%s Shortcode','chriss-swim-training-progress-evaluation'),$label),'swimlog_manage_settings','swimlog-shortcode-'.$slug,array($this,'shortcode_'.str_replace('-','_',$slug)));
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
			if ( empty( $point->recorded_at ) ) continue;
			try { $time = new \DateTimeImmutable( (string) $point->recorded_at, wp_timezone() ); } catch ( \Exception $e ) { continue; }
			if ( null === $start ) $start = $time;
			$value = $point->{$field} ?? null;
			if ( null === $value || '' === $value ) continue;
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
		<p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-upload')); ?>"><?php esc_html_e('Upload Workout','chriss-swim-training-progress-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-manual-workout')); ?>"><?php esc_html_e('Add Manual Workout','chriss-swim-training-progress-evaluation'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Add Event','chriss-swim-training-progress-evaluation'); ?></a></p>
		<h2><?php esc_html_e('Latest Swim','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$latest):?><p><?php esc_html_e('No workouts have been imported yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><tbody><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(mysql2date('F j, Y g:i a',$latest->workout_start,false)); ?></td></tr><tr><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html($latest->location_name?:'—'); ?></td></tr><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$latest->original_distance?$latest->original_distance.' '.$latest->original_distance_unit:($latest->total_distance_m?$latest->total_distance_m.' m':'—')); ?></td></tr><tr><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(Workout::format_duration($latest->elapsed_time_ms)); ?></td></tr></tbody></table><p><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$latest->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View latest workout','chriss-swim-training-progress-evaluation'); ?></a></p><?php endif;?>
		<h2><?php esc_html_e('Personal Best Summary','chriss-swim-training-progress-evaluation'); ?></h2><p><a class="button <?php echo $course==='m'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=m')); ?>"><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></a> <a class="button <?php echo $course==='yd'?'button-primary':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-dashboard&course=yd')); ?>"><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></a> <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-personal-bests&course='.$course)); ?>"><?php esc_html_e('View all personal bests','chriss-swim-training-progress-evaluation'); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Overall','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Freestyle','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Breaststroke','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach(array(50,100,200,500,800,1500) as $d):?><tr><th><?php echo esc_html($d.' '.$course); ?></th><?php foreach(array('OVERALL','FR','BR') as $s):$p=$pbs[$d][$s]??null;?><td><?php echo $p?esc_html(Workout::format_duration($p->duration_ms)):'—'; ?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
		<h2><?php esc_html_e('Upcoming Events','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$events):?><p><?php esc_html_e('No upcoming events are scheduled.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><p class="description"><?php esc_html_e('Readiness is descriptive, not predictive. Recent training uses the last 42 days and only exact course/stroke matches.','chriss-swim-training-progress-evaluation'); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Event','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Current PB','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent matching volume','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Recent distance evidence','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($events as $e):$pb=Records::exact_personal_best($user_id,(int)$e->distance_value,$e->course_unit,$e->stroke);$ready=Workout::event_readiness($user_id,$e,Workout::EVENT_READINESS_LOOKBACK_DAYS);?><tr><td><?php echo esc_html(wp_date('F j, Y',(new \DateTimeImmutable($e->event_date.' 12:00:00',wp_timezone()))->getTimestamp(),wp_timezone())); ?><?php if($e->event_time):?><br><small><?php echo esc_html(wp_date( get_option( 'time_format', 'g:i a' ), ( new \DateTimeImmutable( $e->event_date . ' ' . $e->event_time, wp_timezone() ) )->getTimestamp(), wp_timezone() )); ?></small><?php endif;?></td><td><?php echo esc_html($e->event_name); ?><?php if($e->location_name):?><br><small><?php echo esc_html($e->location_name); ?></small><?php endif;?></td><td><?php echo esc_html($e->distance_value.' '.$e->course_unit); ?></td><td><?php echo esc_html($strokes[$e->stroke]??$e->stroke); ?></td><td><?php echo $pb?esc_html(Workout::format_duration($pb->duration_ms)):'—'; ?></td><td><?php if(!empty($ready)):?><?php echo esc_html($this->display_measurement((float)$ready['distance_native'],$e->course_unit)); ?><br><small><?php echo esc_html(sprintf(/* translators: 1: matching workout count, 2: active length count. */ __('%1$d matching workouts / %2$d active lengths','chriss-swim-training-progress-evaluation'),(int)$ready['workout_count'],(int)$ready['length_count'])); ?></small><?php else:?>—<?php endif;?></td><td><?php if(!empty($ready['longest_recent_distance'])):?><strong><?php echo $ready['event_distance_or_longer']?esc_html__('Event distance or longer recorded','chriss-swim-training-progress-evaluation'):esc_html__('Shorter than event distance','chriss-swim-training-progress-evaluation'); ?></strong><br><small><?php echo esc_html(sprintf(/* translators: 1: stroke, 2: distance, 3: course unit, 4: duration. */ __('Longest recent evaluated %1$s: %2$d %3$s in %4$s','chriss-swim-training-progress-evaluation'),$strokes[$e->stroke]??$e->stroke,(int)$ready['longest_recent_distance'],$e->course_unit,Workout::format_duration($ready['longest_recent_duration_ms']))); ?></small><?php else:?><?php esc_html_e('No recent evaluated matching-distance evidence','chriss-swim-training-progress-evaluation'); ?><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
		<h2><?php esc_html_e('Recent Workouts','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$recent):?><p><?php esc_html_e('No workout history is available yet.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Workout #','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Action','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($recent as $w):?><tr><td><?php echo esc_html((int)$w->id); ?></td><td><?php echo esc_html(wp_date('M j, Y',(new \DateTimeImmutable($w->workout_start,wp_timezone()))->getTimestamp(),wp_timezone())); ?></td><td><?php echo esc_html('open_water'===($w->workout_type??'pool')?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','chriss-swim-training-progress-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table><?php endif;?>
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
			$photos=Workout::photos($detail,$user_id); $is_open_water=('open_water'===($w->workout_type??'pool')); $laps=$is_open_water?Workout::laps($detail,$user_id):array(); $lengths=$is_open_water?array():Workout::lengths($detail,$user_id); $stroke_count_values=array_values(array_filter(array_map(static function($length){return isset($length->stroke_count)&&null!==$length->stroke_count?(int)$length->stroke_count:null;},$lengths),static function($count){return null!==$count;})); $total_strokes=$stroke_count_values?array_sum($stroke_count_values):null; $perfs=$is_open_water?array():Workout::performances($detail,$user_id); $trackpoints=$is_open_water?Workout::trackpoints($detail,$user_id):array(); $analytics=$is_open_water?Workout::open_water_analytics($detail,$user_id):array(); $imports=Workout::imports($detail,$user_id);
			?><div class="wrap swimlog-admin"><h1><?php echo esc_html(sprintf(/* translators: %d: workout ID. */ __('Workout Details — Workout #%d','chriss-swim-training-progress-evaluation'),(int)$detail)); ?></h1><p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-workouts')); ?>">&larr; <?php esc_html_e('Back to Workouts','chriss-swim-training-progress-evaluation'); ?></a></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Workout metadata saved. Imported swim data and historical pool snapshot were unchanged.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?>
			<?php if($metadata_error):?><div class="notice notice-error"><p><?php echo esc_html($metadata_error); ?></p></div><?php endif;?>
			<?php if($edit):?>
			<form method="post" enctype="multipart/form-data"><?php wp_nonce_field('swimlog_save_workout_metadata_'.$detail); ?><input type="hidden" name="swimlog_workout_action" value="save_metadata">
			<table class="form-table" role="presentation"><tr><th scope="row"><label for="swimlog-workout-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-workout-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected((int)$w->location_id,(int)$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select><p class="description"><?php echo esc_html($is_open_water?__('Changing the location does not change imported open-water distance or GPS track data.','chriss-swim-training-progress-evaluation'):__('Changing the location does not change the pool length or course stored with this workout.','chriss-swim-training-progress-evaluation')); ?></p></td></tr>
			<tr><th scope="row"><label for="swimlog-workout-notes"><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></label></th><td><textarea class="large-text" rows="5" id="swimlog-workout-notes" name="notes"><?php echo esc_textarea($w->notes??''); ?></textarea></td></tr><tr><th scope="row"><?php esc_html_e('Workout Photos','chriss-swim-training-progress-evaluation'); ?></th><td><p class="description"><?php esc_html_e('Attach up to three JPEG, PNG, GIF, or WebP photos. Check Delete to remove a photo, or choose a replacement image for that slot.','chriss-swim-training-progress-evaluation'); ?></p><?php for($photo_slot=0;$photo_slot<3;$photo_slot++):$photo_id=$photos[$photo_slot]??0;?><div style="margin:0 0 16px"><?php if($photo_id):?><?php echo wp_get_attachment_image($photo_id,'thumbnail',false,array('style'=>'display:block;margin-bottom:6px;max-width:150px;height:auto')); ?><label><input type="checkbox" name="delete_photo[]" value="<?php echo esc_attr($photo_slot); ?>"> <?php esc_html_e('Delete','chriss-swim-training-progress-evaluation'); ?></label><br><label><?php esc_html_e('Replace','chriss-swim-training-progress-evaluation'); ?> <input type="file" name="workout_photo_<?php echo esc_attr($photo_slot); ?>" accept="image/jpeg,image/png,image/gif,image/webp"></label><?php else:?><label><?php echo esc_html(sprintf(/* translators: %d: photo slot number. */ __('Photo %d','chriss-swim-training-progress-evaluation'),$photo_slot+1)); ?> <input type="file" name="workout_photo_<?php echo esc_attr($photo_slot); ?>" accept="image/jpeg,image/png,image/gif,image/webp"></label><?php endif;?></div><?php endfor;?></td></tr></table>
			<?php submit_button(__('Save Changes','chriss-swim-training-progress-evaluation'),'primary','',false); ?> <a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$detail),admin_url('admin.php'))); ?>"><?php esc_html_e('Cancel','chriss-swim-training-progress-evaluation'); ?></a></form>
			<?php else:?><?php $linked_posts=get_posts(array('post_type'=>'post','post_status'=>array('draft','pending','future','publish','private'),'posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_swimlog_workout_id','meta_value'=>$detail)); ?><p><a class="button" href="<?php echo esc_url('manual'===($w->source_type??'')?add_query_arg(array('page'=>'swimlog-manual-workout','workout_id'=>$detail,'swimmer_id'=>$user_id),admin_url('admin.php')):add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$detail,'edit'=>1),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit Workout','chriss-swim-training-progress-evaluation'); ?></a> <?php if($linked_posts):?><a class="button button-primary" href="<?php echo esc_url(get_edit_post_link((int)$linked_posts[0],'raw')); ?>"><?php esc_html_e('Edit Post','chriss-swim-training-progress-evaluation'); ?></a><?php elseif(current_user_can('edit_posts')):?><form method="post" style="display:inline-block;margin-left:4px"><?php wp_nonce_field('swimlog_create_workout_post_'.$detail); ?><input type="hidden" name="swimlog_workout_action" value="create_post"><?php submit_button(__('Create Post','chriss-swim-training-progress-evaluation'),'primary','',false); ?></form><?php endif;?></p>
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
			<?php if(null!==$total_strokes):?><tr><th><?php esc_html_e('Total strokes','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(number_format_i18n($total_strokes)); ?></td></tr><?php endif;?>
			<?php endif;?>
			<tr><th><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo ''!==trim((string)($w->notes??''))?nl2br(esc_html($w->notes)):'—'; ?></td></tr>
			<tr><th><?php esc_html_e('Photos','chriss-swim-training-progress-evaluation'); ?></th><td><?php if(!$photos):?>—<?php else:?><?php foreach($photos as$photo_id):?><a href="<?php echo esc_url(wp_get_attachment_url($photo_id)); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_get_attachment_image($photo_id,'thumbnail',false,array('style'=>'margin:0 8px 8px 0;max-width:150px;height:auto')); ?></a><?php endforeach;?><?php endif;?></td></tr>
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
			<tr><th><?php esc_html_e('Heart rate','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->average_heart_rate?sprintf(/* translators: 1: average heart rate, 2: maximum heart rate. */ __('%1$d bpm average / %2$d bpm max','chriss-swim-training-progress-evaluation'),(int)$w->average_heart_rate,(int)$w->max_heart_rate):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Cadence','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(null!==$w->average_cadence?sprintf(/* translators: 1: average cadence, 2: maximum cadence. */ __('%1$s average / %2$s max','chriss-swim-training-progress-evaluation'),$this->display_measurement($w->average_cadence,''),$this->display_measurement($w->max_cadence,'')):'—'); ?></td></tr>
			<tr><th><?php esc_html_e('Recorded samples','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(sprintf(/* translators: 1: total samples, 2: GPS samples, 3: cadence samples. */ __('%1$d total / %2$d GPS / %3$d cadence','chriss-swim-training-progress-evaluation'),(int)($analytics['trackpoint_count']??0),(int)($analytics['gps_point_count']??0),(int)($analytics['cadence_point_count']??0))); ?></td></tr>
			<tr><th><?php esc_html_e('Source laps / splits','chriss-swim-training-progress-evaluation'); ?></th><td><?php echo esc_html(number_format_i18n(count($laps),0)); ?></td></tr>
			</tbody></table>
			<h3><?php esc_html_e('Recorded Trends','chriss-swim-training-progress-evaluation'); ?></h3>
			<?php $hr_chart=$this->open_water_metric_svg($trackpoints,'heart_rate',__('Heart rate over elapsed recording time','chriss-swim-training-progress-evaluation'),'bpm'); $cadence_chart=$this->open_water_metric_svg($trackpoints,'cadence',__('FIT cadence over elapsed recording time','chriss-swim-training-progress-evaluation'),__('FIT cadence','chriss-swim-training-progress-evaluation')); ?>
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
			<h2><?php esc_html_e('Normalized Lengths and Rest','chriss-swim-training-progress-evaluation'); ?></h2><?php if(!$lengths):?><p><?php esc_html_e('No normalized length records are stored.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><details><summary><?php /* translators: %d: number of normalized swim length and rest records. */ echo esc_html(sprintf(__('Show %d length/rest records','chriss-swim-training-progress-evaluation'),count($lengths))); ?></summary><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Strokes','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Time','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($lengths as $l):?><tr><td><?php echo esc_html($l->sequence_no); ?></td><td><?php echo esc_html($l->length_type); ?></td><td><?php echo esc_html($l->distance_m.' m'); ?></td><td><?php echo esc_html($strokes[$l->stroke]??($l->stroke?:'—')); ?></td><td><?php echo esc_html(isset($l->stroke_count)&&null!==$l->stroke_count?(int)$l->stroke_count:'—'); ?></td><td><?php echo esc_html(Workout::format_duration($l->elapsed_time_ms)); ?></td></tr><?php endforeach;?></tbody></table></details><?php endif;?>
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
		$paged=max(1,absint(wp_unslash($_GET['paged']??1))); $result=Workout::query_for_user($user_id,$filters,$paged,20); $locations=Location::all_for_user($user_id);
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Workouts','chriss-swim-training-progress-evaluation'); ?></h1><p><?php esc_html_e('Browse your normalized swim workout history.','chriss-swim-training-progress-evaluation'); ?></p>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
		<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Workout deleted. Personal bests were recalculated from the remaining workouts.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif;?>
		<form method="get"><input type="hidden" name="page" value="swimlog-workouts"><select name="workout_type"><option value=""><?php esc_html_e('All workout types','chriss-swim-training-progress-evaluation'); ?></option><option value="pool" <?php selected($filters['workout_type'],'pool'); ?>><?php esc_html_e('Pool','chriss-swim-training-progress-evaluation'); ?></option><option value="open_water" <?php selected($filters['workout_type'],'open_water'); ?>><?php esc_html_e('Open Water','chriss-swim-training-progress-evaluation'); ?></option></select> <label><?php esc_html_e('From','chriss-swim-training-progress-evaluation'); ?> <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"></label> <label><?php esc_html_e('To','chriss-swim-training-progress-evaluation'); ?> <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>"></label> <select name="location_id"><option value="0"><?php esc_html_e('All locations','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($filters['location_id'],$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach;?></select> <select name="course_unit"><option value=""><?php esc_html_e('All courses','chriss-swim-training-progress-evaluation'); ?></option><option value="m" <?php selected($filters['course_unit'],'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($filters['course_unit'],'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select> <select name="stroke"><option value=""><?php esc_html_e('All strokes','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($filters['stroke'],$code); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select> <?php submit_button(__('Filter','chriss-swim-training-progress-evaluation'),'secondary','',false); ?></form>
		<?php if(!$result['rows']):?><p><?php esc_html_e('No workouts found. Upload a FIT, CSV, GPX, or TCX workout to begin your history.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Workout #','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Type','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Course','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Elapsed','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Action','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($result['rows'] as $w):$is_open_water=('open_water'===($w->workout_type??'pool'));?><tr><td><strong><?php echo esc_html((int)$w->id); ?></strong></td><td><?php echo esc_html(mysql2date('F j, Y g:i a',$w->workout_start,false)); ?></td><td><?php echo esc_html($is_open_water?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation')); ?></td><td><?php echo esc_html($w->location_name?:'—'); ?></td><td><?php echo esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')); ?></td><td><?php echo esc_html($is_open_water?__('Open Water','chriss-swim-training-progress-evaluation'):(null!==$w->original_pool_length?$this->display_measurement($w->original_pool_length,$w->pool_length_unit):($w->pool_length_m?$this->display_measurement($w->pool_length_m,'m'):'—'))); ?></td><td><?php echo esc_html(Workout::format_duration($w->elapsed_time_ms)); ?></td><td><?php echo esc_html($is_open_water?'—':($strokes[$w->primary_stroke]??($w->primary_stroke?:'—'))); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-workouts','workout_id'=>$w->id),admin_url('admin.php'))); ?>"><?php esc_html_e('View','chriss-swim-training-progress-evaluation'); ?></a></td></tr><?php endforeach;?></tbody></table>
		<?php if($result['pages']>1):?><p class="tablenav-pages"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$result['pages']))); ?></p><?php endif; endif;?></div><?php
	}
	public function training_volume() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::training_volume();
	}
	private $manual_workout_action_error = null;

	public function handle_manual_workout_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-manual-workout' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_manual_workout_action'] ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['swimlog_manual_workout_action'] ) ); if ( ! in_array( $action, array( 'create', 'update' ), true ) ) return;
		$workout_id = 'update' === $action ? absint( wp_unslash( $_POST['workout_id'] ?? 0 ) ) : 0;
		check_admin_referer( 'update' === $action ? 'swimlog_update_manual_workout_' . $workout_id : 'swimlog_create_manual_workout' );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-manual-workout.php';
		$user_id = $this->selected_user_id();
		$date = sanitize_text_field( wp_unslash( $_POST['workout_date'] ?? '' ) );
		$time = sanitize_text_field( wp_unslash( $_POST['workout_time'] ?? '' ) );
		$duration_minutes = isset( $_POST['duration_minutes'] ) && '' !== trim( (string) wp_unslash( $_POST['duration_minutes'] ) ) ? (float) wp_unslash( $_POST['duration_minutes'] ) : null;
		$document = array( 'workout' => array(
			'workout_start' => trim( $date . ' ' . ( '' !== $time ? $time . ':00' : '12:00:00' ) ),
			'location_id' => absint( wp_unslash( $_POST['location_id'] ?? 0 ) ),
			'original_pool_length' => isset( $_POST['pool_length'] ) ? (float) wp_unslash( $_POST['pool_length'] ) : 0,
			'pool_length_unit' => sanitize_key( wp_unslash( $_POST['course_unit'] ?? '' ) ),
			'original_distance' => isset( $_POST['total_distance'] ) && '' !== trim( (string) wp_unslash( $_POST['total_distance'] ) ) ? (float) wp_unslash( $_POST['total_distance'] ) : null,
			'elapsed_time_ms' => null === $duration_minutes ? null : (int) round( $duration_minutes * 60000 ),
			'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		), 'segments' => array() );
		$segment_reps = isset( $_POST['segment_repetitions'] ) ? (array) wp_unslash( $_POST['segment_repetitions'] ) : array();
		$segment_distances = isset( $_POST['segment_distance'] ) ? (array) wp_unslash( $_POST['segment_distance'] ) : array();
		$segment_strokes = isset( $_POST['segment_stroke'] ) ? (array) wp_unslash( $_POST['segment_stroke'] ) : array();
		$segment_notes = isset( $_POST['segment_notes'] ) ? (array) wp_unslash( $_POST['segment_notes'] ) : array();
		$segment_timing = isset( $_POST['segment_timing_type'] ) ? (array) wp_unslash( $_POST['segment_timing_type'] ) : array();
		$segment_rep_times = isset( $_POST['segment_rep_times'] ) ? (array) wp_unslash( $_POST['segment_rep_times'] ) : array();
		$segment_set_times = isset( $_POST['segment_set_time'] ) ? (array) wp_unslash( $_POST['segment_set_time'] ) : array();
		$segment_intervals = isset( $_POST['segment_interval'] ) ? (array) wp_unslash( $_POST['segment_interval'] ) : array();
		$segment_rests = isset( $_POST['segment_rest'] ) ? (array) wp_unslash( $_POST['segment_rest'] ) : array();
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
		if ( is_wp_error( $result ) ) { $this->manual_workout_action_error = $result->get_error_message(); return; }
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

	public function manual_workout() {
		if ( ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'You do not have permission to manage workouts.', 'chriss-swim-training-progress-evaluation' ) );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php'; require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php'; require_once SWIMLOG_EVALUATION_DIR . 'includes/class-manual-workout.php';
		$user_id = $this->selected_user_id(); $locations = Location::all_for_user( $user_id ); $error = $this->manual_workout_action_error;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only manual workout selector; ownership is enforced by Manual_Workout::get_document().
		$workout_id = absint( wp_unslash( $_GET['workout_id'] ?? 0 ) ); $editing = $workout_id > 0; $source = null;
		if ( $editing ) { $source = Manual_Workout::get_document( $workout_id, $user_id ); if ( is_wp_error( $source ) ) wp_die( esc_html( $source->get_error_message() ) ); }
		$this->swimmer_selector( $user_id, 'swimlog-manual-workout' );
		$workout = $source['workout'] ?? array(); $segments = $source['segments'] ?? array(); $start_value = (string) ( $workout['workout_start'] ?? '' );
		$date = sanitize_text_field( wp_unslash( $_POST['workout_date'] ?? ( $start_value ? substr( $start_value, 0, 10 ) : wp_date( 'Y-m-d' ) ) ) );
		$time = sanitize_text_field( wp_unslash( $_POST['workout_time'] ?? ( $start_value && '12:00:00' !== substr( $start_value, 11, 8 ) ? substr( $start_value, 11, 5 ) : '' ) ) );
		$ms_to_time = static function( $ms ){ if(null===$ms||$ms<=0)return'';$seconds=$ms/1000;$minutes=(int)floor($seconds/60);$seconds-=$minutes*60;$formatted=number_format($seconds,3,'.','');$formatted=rtrim(rtrim($formatted,'0'),'.');return $minutes>0?$minutes.':'.str_pad($formatted,2,'0',STR_PAD_LEFT):$formatted; };
		if ( empty( $_POST ) && $editing ) { $_POST['location_id']=$workout['location_id']??0; $_POST['pool_length']=$workout['original_pool_length']??25; $_POST['course_unit']=$workout['pool_length_unit']??'yd'; $_POST['total_distance']=$workout['original_distance']??''; $_POST['duration_minutes']=null!==($workout['elapsed_time_ms']??null)?$workout['elapsed_time_ms']/60000:''; $_POST['notes']=$workout['notes']??''; foreach($segments as$segment){$_POST['segment_repetitions'][]=$segment['repetitions']??'';$_POST['segment_distance'][]=$segment['distance_per_rep']??'';$_POST['segment_stroke'][]=$segment['stroke']??'';$_POST['segment_timing_type'][]=$segment['timing_type']??'unknown';$_POST['segment_rep_times'][]=implode(', ',array_map($ms_to_time,$segment['rep_times_ms']??array()));$_POST['segment_set_time'][]=$ms_to_time($segment['set_time_ms']??null);$_POST['segment_interval'][]=$ms_to_time($segment['interval_ms']??null);$_POST['segment_rest'][]=$ms_to_time($segment['rest_time_ms']??null);$_POST['segment_notes'][]=$segment['notes']??'';} }
		$posted_reps=(array)wp_unslash($_POST['segment_repetitions']??array());$posted_distance=(array)wp_unslash($_POST['segment_distance']??array());$posted_stroke=(array)wp_unslash($_POST['segment_stroke']??array());$posted_notes=(array)wp_unslash($_POST['segment_notes']??array());$posted_timing=(array)wp_unslash($_POST['segment_timing_type']??array());$posted_rep_times=(array)wp_unslash($_POST['segment_rep_times']??array());$posted_set_time=(array)wp_unslash($_POST['segment_set_time']??array());$posted_interval=(array)wp_unslash($_POST['segment_interval']??array());$posted_rest=(array)wp_unslash($_POST['segment_rest']??array()); $row_count=max(12,count($posted_reps));
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



	public function training_trends() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::training_trends();
	}
	public function pool_analytics() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-analytics.php';
		Admin_Analytics::pool_analytics();
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
			else{$items=array();foreach((array)$files['name'] as $i=>$name){$items[]=array('name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0);}usort($items,function($x,$y){$xf=(bool)preg_match('/\\.fit(?:\\.gz)?$/i',(string)$x['name']);$yf=(bool)preg_match('/\\.fit(?:\\.gz)?$/i',(string)$y['name']);return$xf===$yf?0:($xf?-1:1);});
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
		<form method="post" enctype="multipart/form-data"><?php wp_nonce_field('swimlog_upload_workout'); ?><input type="hidden" name="swimlog_upload_action" value="import"><table class="form-table" role="presentation"><tr><th><label for="swimlog-workout-files"><?php esc_html_e('Workout files','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required id="swimlog-workout-files" type="file" name="workout_files[]" accept=".fit,.fit.gz,.zip,.csv,.csv.gz,.gpx,.gpx.gz,.tcx,.tcx.gz" multiple><p class="description"><?php esc_html_e('FIT, FIT.GZ, ZIP, CSV, GPX, and TCX are supported. Gzip-compressed GPX/TCX/CSV are also accepted. Maximum 10 MB per uploaded and expanded workout file. ZIP archives must contain exactly one supported workout file; CSV is limited to FORM swim-export files; GPX/TCX are imported as open-water GPS tracks.','chriss-swim-training-progress-evaluation'); ?></p></td></tr>
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

	public function handle_workout_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_workouts' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-workouts' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_workout_action'] ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['swimlog_workout_action'] ) );
		if ( ! in_array( $action, array( 'save_metadata', 'rebuild', 'delete', 'create_post' ), true ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';

		$user_id = $this->selected_user_id();
		$detail = isset( $_GET['workout_id'] ) ? absint( wp_unslash( $_GET['workout_id'] ) ) : 0;
		if ( ! $detail || ! Workout::get_for_user( $detail, $user_id ) ) {
			wp_die( esc_html__( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
		}

		if ( 'create_post' === $action ) {
			check_admin_referer( 'swimlog_create_workout_post_' . $detail );
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to create posts.', 'chriss-swim-training-progress-evaluation' ) );
			}
			$workout = Workout::get_for_user( $detail, $user_id );
			$existing = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'future', 'publish', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_swimlog_workout_id',
				'meta_value'     => $detail,
			) );
			if ( $existing ) {
				wp_safe_redirect( get_edit_post_link( (int) $existing[0], 'raw' ) );
				exit;
			}
			$date = mysql2date( 'F j, Y', $workout->workout_start, false );
			$type = 'open_water' === ( $workout->workout_type ?? 'pool' ) ? __( 'Open Water Swim', 'chriss-swim-training-progress-evaluation' ) : __( 'Pool Swim', 'chriss-swim-training-progress-evaluation' );
			$descriptor = trim( (string) ( $workout->location_name ?? '' ) );
			if ( '' === $descriptor ) $descriptor = $type;
			$title = sprintf( /* translators: 1: workout date, 2: location or workout type. */ __( 'Swim — %1$s — %2$s', 'chriss-swim-training-progress-evaluation' ), $date, $descriptor );
			$content = '<!-- wp:swimlog/workout {"workoutId":' . (int) $detail . '} /-->';
			$post_id = wp_insert_post( array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_author'  => $user_id,
				'post_title'   => $title,
				'post_content' => $content,
			), true );
			if ( is_wp_error( $post_id ) ) {
				$this->workout_action_error = $post_id->get_error_message();
				return;
			}
			update_post_meta( $post_id, '_swimlog_workout_id', $detail );
			wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
			exit;
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

		if ( ! is_wp_error( $result ) ) {
			$photo_result = $this->save_workout_photos( $detail, $user_id );
			if ( is_wp_error( $photo_result ) ) $result = $photo_result;
		}

		if ( is_wp_error( $result ) ) {
			$this->workout_action_error = $result->get_error_message();
			return;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => $detail, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}


	private function save_workout_photos( $workout_id, $user_id ) {
		$photos = Workout::photos( $workout_id, $user_id );
		$delete_slots = isset( $_POST['delete_photo'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['delete_photo'] ) ) : array();
		for ( $slot = 0; $slot < 3; $slot++ ) {
			$old_id = $photos[$slot] ?? 0;
			$field = 'workout_photo_' . $slot;
			$has_upload = ! empty( $_FILES[$field]['name'] ) && UPLOAD_ERR_NO_FILE !== (int) ( $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE );
			if ( ! $has_upload && ! in_array( $slot, $delete_slots, true ) ) continue;
			if ( $has_upload ) {
				if ( UPLOAD_ERR_OK !== (int) $_FILES[$field]['error'] ) return new \WP_Error( 'swimlog_workout_photo_upload', __( 'A workout photo upload failed.', 'chriss-swim-training-progress-evaluation' ) );
				$check = wp_check_filetype_and_ext( $_FILES[$field]['tmp_name'], sanitize_file_name( $_FILES[$field]['name'] ), array( 'jpg|jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp' ) );
				if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) return new \WP_Error( 'swimlog_workout_photo_type', __( 'Workout photos must be JPEG, PNG, GIF, or WebP images.', 'chriss-swim-training-progress-evaluation' ) );
				require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
				$new_id = media_handle_upload( $field, 0, array( 'post_title' => sprintf( /* translators: %d: workout ID. */ __( 'Workout %d photo', 'chriss-swim-training-progress-evaluation' ), $workout_id ) ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp' ) ) );
				if ( is_wp_error( $new_id ) ) return $new_id;
				if ( $old_id ) { $photos[$slot] = $new_id; wp_delete_attachment( $old_id, true ); } else $photos[] = $new_id;
			} elseif ( $old_id ) { wp_delete_attachment( $old_id, true ); unset( $photos[$slot] ); }
		}
		$photos = array_values( array_filter( array_map( 'absint', $photos ) ) );
		return Workout::set_photos( $workout_id, $user_id, $photos );
	}

	public function handle_location_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::handle_location_actions();
	}


	public function handle_event_actions() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::handle_event_actions();
	}

	public function events() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::events();
	}
	public function locations() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-events-locations.php';
		Admin_Events_Locations::locations();
	}
	public function shortcodes() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcodes();
	}

	public function shortcode_latest() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_latest();
	}

	public function shortcode_upcoming_events() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_upcoming_events();
	}

	public function shortcode_workout() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_workout();
	}

	public function shortcode_log() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_log();
	}

	public function shortcode_progress() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_progress();
	}

	public function shortcode_bests() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-shortcodes.php';
		Admin_Shortcodes::shortcode_bests();
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
			$rest_api = isset( $_POST['rest_api_access'] ) ? '1' : '0';
			$birthdate = isset( $_POST['birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['birthdate'] ) ) : '';
			$training_volume_months = isset( $_POST['training_volume_months'] ) ? absint( wp_unslash( $_POST['training_volume_months'] ) ) : 3;
			$training_volume_months = max( 1, min( 24, $training_volume_months ) );
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
				update_user_meta( $uid, 'swimlog_rest_api_access', $rest_api );
				update_user_meta( $uid, 'swimlog_training_volume_months', $training_volume_months );
				$saved = true;
			}
		}
		$value = get_user_meta( $uid, 'swimlog_public_results', true );
		if ( '' === $value ) {
			$value = get_option( 'swimlog_public_results_default', '0' );
		}
		$birthdate = (string) get_user_meta( $uid, 'swimlog_birthdate', true );
		$public_routes = (string) get_user_meta( $uid, 'swimlog_public_ows_routes', true );
		$rest_api = (string) get_user_meta( $uid, 'swimlog_rest_api_access', true );
		$training_volume_months = (int) get_user_meta( $uid, 'swimlog_training_volume_months', true );
		if ( $training_volume_months < 1 || $training_volume_months > 24 ) {
			$training_volume_months = 3;
		}
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
					<tr>
						<th scope="row"><label for="swimlog-training-volume-months"><?php esc_html_e( 'Training Volume Period', 'chriss-swim-training-progress-evaluation' ); ?></label></th>
						<td><input id="swimlog-training-volume-months" type="number" name="training_volume_months" min="1" max="24" step="1" value="<?php echo esc_attr( $training_volume_months ); ?>"> <?php esc_html_e( 'months', 'chriss-swim-training-progress-evaluation' ); ?><p class="description"><?php esc_html_e( 'Pool Training Volume by Stroke uses this rolling period. Choose from 1 to 24 months; the default is 3 months.', 'chriss-swim-training-progress-evaluation' ); ?></p></td>
					</tr>
				</table>
				<hr>
				<h2><?php esc_html_e( 'Privacy', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<p><?php esc_html_e( 'Your swim results are private unless you explicitly enable public results. This controls the public swim shortcodes; it does not change who can manage your workouts in WordPress administration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Public Results', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_results" value="1" <?php checked( $value, '1' ); ?>> <?php esc_html_e( 'Allow my swim results to be displayed by public swim shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><?php esc_html_e( 'Default is OFF. When OFF, you can still preview your own shortcode output while logged in. Administrators can also preview private results, but private results are not made public.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Public Open-Water Routes', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="public_ows_routes" value="1" <?php checked( $public_routes, '1' ); ?>> <?php esc_html_e( 'Allow GPS route shapes from my open-water workouts to appear in public workout shortcodes.', 'chriss-swim-training-progress-evaluation' ); ?></label><p class="description"><strong><?php esc_html_e( 'Default is OFF.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'GPS routes can reveal where you swim, including start and finish locations. This setting is separate from Public Results and must be enabled explicitly.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'REST API Access', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label><input type="checkbox" name="rest_api_access" value="1" <?php checked( $rest_api, '1' ); ?>> <?php esc_html_e( 'Allow authenticated read-only access to my swim workout data through the swimlog/v1 REST API.', 'chriss-swim-training-progress-evaluation' ); ?></label> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-api-help' ) ); ?>"><?php esc_html_e( 'API Help / Documentation', 'chriss-swim-training-progress-evaluation' ); ?></a><p class="description"><strong><?php esc_html_e( 'Default is OFF.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'This permission is separate from Public Results and Public Open-Water Routes. API requests are limited to the authenticated swimmer and never expose preserved source files or open-water GPS coordinates/trackpoints.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ) ); ?>
			</form>
		</div>
		<?php
	}
	public function api_help() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) wp_die( esc_html__( 'You do not have permission to view the REST API documentation.', 'chriss-swim-training-progress-evaluation' ) );
		$base = rest_url( 'swimlog/v1' );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'SwimLog REST API Documentation', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'The swimlog/v1 API provides authenticated, read-only access to the currently authenticated swimmer’s normalized workout data. REST API Access must first be enabled on Swimmer Configuration.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-privacy' ) ); ?>">&larr; <?php esc_html_e( 'Return to Swimmer Configuration', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
			<h2><?php esc_html_e( 'Authentication and permission', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Requests must authenticate as a WordPress user who has permission to view their own swim results and who has explicitly enabled REST API Access. The API always uses the authenticated WordPress user as the swimmer; a request cannot select another swimmer.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<p><?php esc_html_e( 'For external clients, use a WordPress-supported authentication method appropriate to your site, such as an Application Password over HTTPS. Do not place credentials in a public URL.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Routes', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Method', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Endpoint', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Purpose', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><tr><td><code>GET</code></td><td><code><?php echo esc_html( $base . '/workouts' ); ?></code></td><td><?php esc_html_e( 'List the authenticated swimmer’s workouts.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>GET</code></td><td><code><?php echo esc_html( $base . '/workouts/{id}' ); ?></code></td><td><?php esc_html_e( 'Return one owned workout with normalized detail.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr></tbody></table>
			<h2><?php esc_html_e( 'Workout list parameters', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Parameter', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Meaning', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><tr><td><code>page</code></td><td><?php esc_html_e( 'Page number; minimum 1.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>per_page</code></td><td><?php esc_html_e( 'Results per page; default 20, maximum 100.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>workout_type</code></td><td><code>pool</code> / <code>open_water</code></td></tr><tr><td><code>date_from</code></td><td><?php esc_html_e( 'Beginning of the requested date range.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr><tr><td><code>date_to</code></td><td><?php esc_html_e( 'End of the requested date range.', 'chriss-swim-training-progress-evaluation' ); ?></td></tr></tbody></table>
			<h2><?php esc_html_e( 'Returned data', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Workout summaries include the workout ID/type/timestamps, saved location name, distance and timing values, pool information when applicable, primary stroke, heart-rate/cadence summaries, and recording-device manufacturer/model. A single pool workout also includes normalized laps, lengths, and derived performances. A single open-water workout includes normalized laps, derived performances, and non-geographic analytics/splits.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Privacy exclusions', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'The API does not expose preserved source files or paths, import records, workout notes, open-water latitude/longitude, or GPS trackpoints. Enabling Public Open-Water Routes does not change the REST API: geographic route data remains excluded.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<h2><?php esc_html_e( 'Examples', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><code>GET <?php echo esc_html( $base . '/workouts?per_page=10&workout_type=pool' ); ?></code></p><p><code>GET <?php echo esc_html( $base . '/workouts/123' ); ?></code></p>
			<h2><?php esc_html_e( 'Errors', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<p><?php esc_html_e( 'Unauthenticated requests, users without the required own-results capability, and swimmers who have not enabled REST API Access are denied by WordPress REST permissions. Requesting a workout that does not belong to the authenticated swimmer returns Workout not found (404).', 'chriss-swim-training-progress-evaluation' ); ?></p>
		</div><?php
	}

	public function handle_garmin_fit_download() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';
		Admin_Garmin::handle_garmin_fit_download();
	}

	public function garmin() {
		require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin.php';
		Admin_Garmin::garmin();
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
		<tr><th><label for="swimlog-private-storage"><?php esc_html_e('Private Workout Storage Directory','chriss-swim-training-progress-evaluation'); ?></label></th><td><input id="swimlog-private-storage" class="regular-text code" type="text" name="private_storage_dir" value="<?php echo esc_attr($storage_dir); ?>" <?php disabled($storage_locked); ?>><p class="description"><?php esc_html_e('Absolute server filesystem path outside the public web root. WordPress must be able to create or write to this directory. Original FIT, FIT.GZ, ZIP, CSV, GPX, and TCX uploads are preserved here unchanged.','chriss-swim-training-progress-evaluation'); ?></p><?php if($storage_locked):?><p class="description"><strong><?php esc_html_e('This path is locked by SWIMLOG_PRIVATE_STORAGE_DIR in server configuration.','chriss-swim-training-progress-evaluation'); ?></strong></p><?php elseif($storage_dir===''):?><p class="description"><?php esc_html_e('If left blank, the plugin will try to derive a private directory one level above the web document root.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?></td></tr></table>
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
