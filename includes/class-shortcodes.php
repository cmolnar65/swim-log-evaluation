<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Privacy-safe, display-only public shortcodes. */
final class Shortcodes {
	private static $loaded=false;
	public static function register(){
		add_shortcode('swimlog_latest',array(__CLASS__,'latest'));
		add_shortcode('swimlog_bests',array(__CLASS__,'bests'));
		add_shortcode('swimlog_upcoming_events',array(__CLASS__,'events'));
		add_shortcode('swimlog_workout',array(__CLASS__,'workout'));
		add_shortcode('swimlog_log',array(__CLASS__,'log'));
		add_shortcode('swimlog_progress',array(__CLASS__,'progress'));
	}
	private static function load(){
		if(self::$loaded)return;
		foreach(array('class-database.php','class-workout.php','class-event.php','class-evaluator.php','class-age-group.php','class-records.php') as $f)require_once SWIMLOG_EVALUATION_DIR.'includes/'.$f;
		self::$loaded=true;
	}
	private static function owner($atts,$workout=null){
		if($workout)return(int)$workout->user_id;
		if(!empty($atts['user']))return absint($atts['user']);
		$post=get_post();return($post&&$post->post_author)?(int)$post->post_author:0;
	}
	private static function allowed($uid){
		if(!$uid)return false;
		if(is_user_logged_in()&&get_current_user_id()===$uid)return true;
		if(current_user_can('swimlog_manage_all_users'))return true;
		$v=get_user_meta($uid,'swimlog_public_results',true);
		if($v==='')$v=get_option('swimlog_public_results_default','0');
		return(string)$v==='1';
	}
	private static function public_routes_allowed($uid){return $uid>0&&(string)get_user_meta($uid,'swimlog_public_ows_routes',true)==='1';}
	private static function open_water_route_svg($trackpoints){
		$points=array();foreach((array)$trackpoints as$p){if(null===$p->latitude||null===$p->longitude)continue;$points[]=array((float)$p->latitude,(float)$p->longitude);}
		if(count($points)<2)return '';
		$lats=array_column($points,0);$lons=array_column($points,1);$min_lat=min($lats);$max_lat=max($lats);$min_lon=min($lons);$max_lon=max($lons);$lat_span=max(0.000001,$max_lat-$min_lat);$lon_span=max(0.000001,$max_lon-$min_lon);$width=800;$height=400;$padding=35;$scale=min(($width-2*$padding)/$lon_span,($height-2*$padding)/$lat_span);$route_width=$lon_span*$scale;$route_height=$lat_span*$scale;$x_offset=($width-$route_width)/2;$y_offset=($height-$route_height)/2;$svg_points=array();
		foreach($points as$p){$x=$x_offset+($p[1]-$min_lon)*$scale;$y=$height-($y_offset+($p[0]-$min_lat)*$scale);$svg_points[]=round($x,1).','.round($y,1);}
		$start=explode(',',$svg_points[0]);$finish=explode(',',$svg_points[count($svg_points)-1]);$grid='';foreach(array(0.25,0.5,0.75) as$ratio){$gx=round($padding+($width-2*$padding)*$ratio,1);$gy=round($padding+($height-2*$padding)*$ratio,1);$grid.='<line x1="'.$gx.'" y1="'.$padding.'" x2="'.$gx.'" y2="'.($height-$padding).'"/><line x1="'.$padding.'" y1="'.$gy.'" x2="'.($width-$padding).'" y2="'.$gy.'"/>';}
		return '<svg class="swimlog-ows-route swimlog-ows-route-map" viewBox="0 0 800 400" role="img" aria-label="'.esc_attr__('Open-water GPS route map','chriss-swim-training-progress-evaluation').'"><g class="swimlog-ows-map-grid">'.$grid.'</g><text class="swimlog-ows-map-north" x="20" y="28">N</text><polyline class="swimlog-ows-map-line" points="'.esc_attr(implode(' ',$svg_points)).'"/><circle class="swimlog-ows-map-start" cx="'.esc_attr($start[0]).'" cy="'.esc_attr($start[1]).'" r="6"/><circle class="swimlog-ows-map-finish" cx="'.esc_attr($finish[0]).'" cy="'.esc_attr($finish[1]).'" r="6"/><text class="swimlog-ows-map-label" x="'.esc_attr((float)$start[0]+9).'" y="'.esc_attr((float)$start[1]-9).'">'.esc_html__('Start','chriss-swim-training-progress-evaluation').'</text><text class="swimlog-ows-map-label" x="'.esc_attr((float)$finish[0]+9).'" y="'.esc_attr((float)$finish[1]+16).'">'.esc_html__('Finish','chriss-swim-training-progress-evaluation').'</text></svg>';
	}
	private static function attrs($atts,$defaults=array()){
		$a=shortcode_atts(array_merge(array('user'=>'','course'=>'','stroke'=>'','distance'=>'','count'=>'','title'=>'yes','best'=>'LB','ag'=>''),$defaults),$atts);
		$a['course']=strtolower(sanitize_key($a['course']));$a['stroke']=strtoupper(sanitize_key($a['stroke']));$a['distance']=absint($a['distance']);$a['count']=absint($a['count']);$a['best']=strtoupper(sanitize_key($a['best']));$a['ag']=sanitize_text_field((string)$a['ag']);$title=sanitize_text_field((string)$a['title']);$a['title']=strtolower($title)==='no'?'no':(strtolower($title)==='yes'?'yes':$title);return$a;
	}
	private static function stroke_name($s){return array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'))[$s]??__('Unknown','chriss-swim-training-progress-evaluation');}
	private static function unavailable(){return '<div class="swimlog-public"><p>'.esc_html__('Swim results are unavailable.','chriss-swim-training-progress-evaluation').'</p></div>';}
	private static function open($title,$show){$heading=$show==='yes'?$title:($show==='no'?'':$show);return '<div class="swimlog-public">'.($heading!==''?'<h2>'.esc_html($heading).'</h2>':'');}
	private static function local_timestamp($value){if(!$value)return false;$dt=date_create_immutable((string)$value,wp_timezone());return$dt?$dt->getTimestamp():false;}
	private static function local_date($format,$value){$ts=self::local_timestamp($value);return false===$ts?'':wp_date($format,$ts,wp_timezone());}
	private static function close(){return '</div>';}
	private static function distance_label($d,$c){if($c==='yd'&&$d===1650)return __('1650 yd / 1 Mile','chriss-swim-training-progress-evaluation');if($c==='yd'&&$d===3300)return __('3300 yd / 2 Miles','chriss-swim-training-progress-evaluation');if($c==='m'&&$d===5000)return __('5000 m / 5K','chriss-swim-training-progress-evaluation');return $d.' '.$c;}
	private static function workout_type($w){return (string)($w->workout_type??'pool');}
	private static function workout_type_label($w){$labels=Land_Activity::labels();if(isset($labels[self::workout_type($w)]))return $labels[self::workout_type($w)];return self::workout_type($w)==='open_water'?__('Open Water','chriss-swim-training-progress-evaluation'):__('Pool','chriss-swim-training-progress-evaluation');}
	private static function workout_distance($w){return null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—');}
	private static function styles(){
		wp_enqueue_style(
			'swimlog-public',
			SWIMLOG_EVALUATION_URL.'assets/css/public.css',
			array(),
			SWIMLOG_EVALUATION_VERSION
		);
		return '';
	}

	public static function latest($atts){return self::render_latest($atts);}
	public static function render_latest($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$count=$a['count']?max(1,min(20,$a['count'])):1;$r=Workout::recent_for_user($uid,$count);$o=self::styles().self::open($count===1?__('Latest Swim','chriss-swim-training-progress-evaluation'):__('Latest Swims','chriss-swim-training-progress-evaluation'),$a['title']);
		if(!$r)return$o.'<p>'.esc_html__('No swim workouts are available yet.','chriss-swim-training-progress-evaluation').'</p>'.self::close();
		if($count>1)$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Type','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Location','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Course','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Elapsed','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';
		foreach($r as$w){$dist=self::workout_distance($w);$type=self::workout_type($w);$course=$type==='open_water'?__('Open Water','chriss-swim-training-progress-evaluation'):(null!==$w->original_pool_length?$w->original_pool_length.' '.$w->pool_length_unit:'—');if($count>1){$o.='<tr><td>'.esc_html(self::local_date('F j, Y',$w->workout_start)).'</td><td>'.esc_html(self::workout_type_label($w)).'</td><td>'.esc_html($w->location_name?:'—').'</td><td>'.esc_html($dist).'</td><td>'.esc_html($course).'</td><td>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</td></tr>';continue;}
		$o.='<dl><dt>'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::local_date('F j, Y',$w->workout_start)).'</dd><dt>'.esc_html__('Type','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::workout_type_label($w)).'</dd>';
		if($w->location_name)$o.='<dt>'.esc_html__('Location','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html($w->location_name).'</dd>';
		$o.='<dt>'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html($dist).'</dd>';
		if($type==='pool')$o.='<dt>'.esc_html__('Pool','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html($course).'</dd>';
		$o.='<dt>'.esc_html__('Elapsed','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</dd>';
		if($type==='open_water'&&isset($w->moving_time_ms)&&null!==$w->moving_time_ms)$o.='<dt>'.esc_html__('Moving','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->moving_time_ms)).'</dd>';
		if($type==='pool'&&$w->primary_stroke)$o.='<dt>'.esc_html__('Primary stroke','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::stroke_name($w->primary_stroke)).'</dd>';$o.='</dl>';}
		if($count>1)$o.='</tbody></table></div>';return$o.self::close();
	}
	public static function bests($atts){return self::render_bests($atts);}
	public static function render_bests($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$course=in_array($a['course'],array('m','yd'),true)?$a['course']:get_option('swimlog_default_course_unit','m');if(!in_array($course,array('m','yd'),true))$course='m';
		$valid_strokes=array('FR','BR','BACK','FLY','MIXED');if($a['stroke']&&!in_array($a['stroke'],$valid_strokes,true))return self::unavailable();if($a['distance']&&!in_array($a['distance'],Evaluator::DISTANCES,true))return self::unavailable();
		$best=$a['best']?:'LB';if($a['ag']!=='')$best='AG';if(!in_array($best,array('LB','AG'),true))return self::unavailable();
		$age_group='';
		if($best==='AG'){
			if($a['ag']==='' ){
				$post=get_post();if(!$post||empty($post->post_date))return self::unavailable();
				$group=Age_Group::for_user($uid,substr((string)$post->post_date,0,10));if(!$group)return self::unavailable();$age_group=$group['label'];
			}elseif(strtolower($a['ag'])==='current'){
				$group=Age_Group::for_user($uid);if(!$group)return self::unavailable();$age_group=$group['label'];
			}else{
				if(!preg_match('/^(\\d{1,3})\\s*[-–]\\s*(\\d{1,3})$/u',$a['ag'],$m))return self::unavailable();
				$age_group=$m[1].'–'.$m[2];
				$start=(int)$m[1];$end=(int)$m[2];if($start>$end||Age_Group::label_for_age($start)!==$age_group||Age_Group::label_for_age($end)!==$age_group)return self::unavailable();
			}
		}
		$pbs=$best==='AG'?Records::age_group_personal_bests($uid,$course,$age_group):Records::personal_bests($uid,$course);$o=self::styles().self::open(__('Personal Bests','chriss-swim-training-progress-evaluation'),$a['title']);$distances=$a['distance']?array($a['distance']):Evaluator::DISTANCES;
		if($a['stroke']){$strokes=array($a['stroke']);}else{$strokes=array('OVERALL');foreach(array('FR','BR','BACK','FLY','MIXED') as$s){foreach($pbs as$distance_pbs){if(!empty($distance_pbs[$s])){$strokes[]=$s;break;}}}}
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th>';foreach($strokes as $s)$o.='<th scope="col">'.esc_html($s==='OVERALL'?__('Overall','chriss-swim-training-progress-evaluation'):self::stroke_name($s)).'</th>';$o.='</tr></thead><tbody>';
		$any=false;foreach($distances as $d){$o.='<tr><th scope="row">'.esc_html(self::distance_label($d,$course)).'</th>';foreach($strokes as $s){$p=$pbs[$d][$s]??null;$any=$any||!!$p;$o.='<td>'.($p?esc_html(Workout::format_duration($p->duration_ms)).'<br><span class="swimlog-muted">'.esc_html(self::local_date('M j, Y',$p->achieved_at)).'</span>':'&mdash;').'</td>';}$o.='</tr>';}$o.='</tbody></table></div>';return$o.(!$any?'<p>'.esc_html__('No qualifying personal bests are available for this selection.','chriss-swim-training-progress-evaluation').'</p>':'').self::close();
	}
	public static function progress($atts){return self::render_progress($atts);}
	public static function render_progress($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$course=in_array($a['course'],array('m','yd'),true)?$a['course']:get_option('swimlog_default_course_unit','m');if(!in_array($course,array('m','yd'),true))$course='m';
		$valid_strokes=array('FR','BR','BACK','FLY','MIXED');if(!$a['distance']||!in_array($a['distance'],Evaluator::DISTANCES,true)||!in_array($a['stroke'],$valid_strokes,true))return self::unavailable();
		$rows=Records::progression($uid,$a['distance'],$course,$a['stroke']);$event=self::distance_label($a['distance'],$course).' '.self::stroke_name($a['stroke']);
		/* translators: %s: swim event, for example 100 m Breaststroke. */
		$title=sprintf(__('%s — Personal Best Progression','chriss-swim-training-progress-evaluation'),$event);$o=self::styles().self::open($title,$a['title']);
		if(!$rows)return$o.'<p>'.esc_html__('No personal best history is available for this event.','chriss-swim-training-progress-evaluation').'</p>'.self::close();
		$points=array();foreach($rows as$r)$points[]=array('date'=>self::local_date('F j, Y',$r->achieved_at),'shortDate'=>self::local_date('M j, Y',$r->achieved_at),'duration'=>(int)$r->duration_ms,'time'=>Workout::format_duration((int)$r->duration_ms));
		$first=$points[0];$current=$points[count($points)-1];$improvement=max(0,$first['duration']-$current['duration']);
		$o.='<div class="swimlog-progress-summary"><strong>'.esc_html__('Current PB:','chriss-swim-training-progress-evaluation').'</strong> '.esc_html($current['time']).' <span aria-hidden="true">·</span> <strong>'.esc_html__('First recorded PB:','chriss-swim-training-progress-evaluation').'</strong> '.esc_html($first['time']).' <span aria-hidden="true">·</span> <strong>'.esc_html__('Improvement:','chriss-swim-training-progress-evaluation').'</strong> '.esc_html(number_format_i18n($improvement/1000,2).' '.__('seconds','chriss-swim-training-progress-evaluation')).'</div>';
		if(count($points)>1){wp_enqueue_style('swimlog-progress',SWIMLOG_EVALUATION_URL.'assets/css/progress.css',array(),SWIMLOG_EVALUATION_VERSION);wp_enqueue_script('swimlog-progress',SWIMLOG_EVALUATION_URL.'assets/js/progress.js',array(),SWIMLOG_EVALUATION_VERSION,true);$json=wp_json_encode($points);/* translators: %s: swim event, for example 100 m Breaststroke. */ $o.='<div class="swimlog-progress-chart" data-swimlog-progress="'.esc_attr($json).'" data-swimlog-label="'.esc_attr($event).'" role="img" aria-label="'.esc_attr(sprintf(__('%s personal best progression chart. Faster times appear higher.','chriss-swim-training-progress-evaluation'),$event)).'"><canvas aria-hidden="true"></canvas><div class="swimlog-progress-tooltip" hidden></div></div>';}
		$o.='<div class="swimlog-table-wrap swimlog-progress-data"><table><thead><tr><th scope="col">'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Personal Best','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';foreach($points as$p)$o.='<tr><td>'.esc_html($p['date']).'</td><td>'.esc_html($p['time']).'</td></tr>';$o.='</tbody></table></div>';return$o.self::close();
	}

	public static function events($atts){return self::render_events($atts);}
	public static function render_events($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();$count=$a['count']?:max(1,min(50,(int)get_option('swimlog_default_event_count',5)));$count=max(1,min(50,$count));$events=Event::upcoming_for_user($uid,$count);$o=self::styles().self::open(__('Upcoming Events','chriss-swim-training-progress-evaluation'),$a['title']);if(!$events)return$o.'<p>'.esc_html__('No upcoming events are scheduled.','chriss-swim-training-progress-evaluation').'</p>'.self::close();
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Event','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Stroke','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Current PB','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';
		foreach($events as $e){$pb=Records::exact_personal_best($uid,(int)$e->distance_value,$e->course_unit,$e->stroke);$o.='<tr><td>'.esc_html(self::local_date('F j, Y',$e->event_date.' 12:00:00')).($e->event_time?'<br><span class="swimlog-muted">'.esc_html(self::local_date(get_option('time_format','g:i a'),$e->event_date.' '.$e->event_time)).'</span>':'').'</td><td>'.esc_html($e->event_name).'</td><td>'.esc_html(self::distance_label((int)$e->distance_value,$e->course_unit)).'</td><td>'.esc_html(self::stroke_name($e->stroke)).'</td><td>'.($pb?esc_html(Workout::format_duration($pb->duration_ms)):'&mdash;').'</td></tr>';}$o.='</tbody></table></div>';return$o.self::close();
	}
	private static function month_number($value){
		$value=trim((string)$value);if($value==='')return(int)current_time('n');
		if(ctype_digit($value)){ $n=(int)$value; return($n>=1&&$n<=12)?$n:0; }
		$months=array('jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,'apr'=>4,'april'=>4,'may'=>5,'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,'aug'=>8,'august'=>8,'sep'=>9,'sept'=>9,'september'=>9,'oct'=>10,'october'=>10,'nov'=>11,'november'=>11,'dec'=>12,'december'=>12);
		return$months[strtolower($value)]??0;
	}
	private static function distance_totals($rows){
		$t=array('m'=>0.0,'yd'=>0.0);foreach($rows as$w){if(!in_array(self::workout_type($w),array('pool','open_water'),true))continue;$u=$w->original_distance_unit;if(in_array($u,array('m','yd'),true)&&null!==$w->original_distance)$t[$u]+=(float)$w->original_distance;}return$t;
	}
	private static function total_label($m,$y){
		$p=array();if($m>0)$p[]=number_format_i18n($m,0).' m';if($y>0)$p[]=number_format_i18n($y,0).' yd';return$p?implode(' + ',$p):'—';
	}
    private static function land_month_html( $rows ) {
        $html='';
        foreach ( Land_Activity::labels() as $type=>$label ) {
            $items=array_values(array_filter($rows,static function($row)use($type){return $type===($row->workout_type??'');}));
            if(!$items)continue;
            $distance=0.0;$known=0;$elapsed=0;
            foreach($items as $row){if(null!==$row->total_distance_m){$distance+=(float)$row->total_distance_m;$known++;}$elapsed+=(int)$row->elapsed_time_ms;}
            $html.='<p><strong>'.esc_html($label).'</strong>: '.esc_html($known?number_format_i18n($distance,0).' m':'—').' · '.esc_html(Workout::format_duration($elapsed)).'</p>';
        }
        return $html;
    }
    private static function land_year_html( $uid, $year ) {
        if(!Land_Activity::authorized($uid))return '';
        $html='';
        foreach(Land_Activity::labels() as $type=>$label){
            $rows=Workout::totals_by_month_for_year($uid,$year,$type);if(!$rows)continue;
            $html.='<h3>'.esc_html($label).'</h3><div class="swimlog-table-wrap"><table><thead><tr><th>'.esc_html__('Month','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Elapsed','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';$distance=0.0;$elapsed=0;$known=0;
            foreach($rows as $row){$meters=(float)$row->meters+(float)$row->yards*0.9144;$distance+=$meters;$elapsed+=(int)$row->elapsed_ms;$samples=(int)($row->distance_samples??$row->workout_count);$known+=$samples;$html.='<tr><th>'.esc_html(sprintf('%04d-%02d',$year,(int)$row->month_num)).'</th><td>'.esc_html($samples?number_format_i18n($meters,0).' m':'—').'</td><td>'.esc_html(Workout::format_duration((int)$row->elapsed_ms)).'</td></tr>';}
            $html.='<tr><th>'.esc_html__('Total','chriss-swim-training-progress-evaluation').'</th><td>'.esc_html($known?number_format_i18n($distance,0).' m':'—').'</td><td>'.esc_html(Workout::format_duration($elapsed)).'</td></tr></tbody></table></div>';
        }
        return $html;
    }
	public static function log($atts){return self::render_log($atts);}
	public static function render_log($atts){
		self::load();$raw=(array)$atts;$a=self::attrs($atts,array('month'=>'','year'=>''));$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$has_year=array_key_exists('year',$raw);$has_month=array_key_exists('month',$raw);$year=absint($a['year']??0);if(!$year)$year=(int)current_time('Y');
		$annual=$has_year&&!$has_month;$o=self::styles();if(Land_Activity::authorized($uid))Land_Activity::no_cache();
		if($annual){
			if($year<1900||$year>2100)return self::unavailable();
			$pool_rows=Workout::totals_by_month_for_year($uid,$year,'pool');
			$open_water_rows=Workout::totals_by_month_for_year($uid,$year,'open_water');
            $land_year=self::land_year_html($uid,$year);
			/* translators: %d: four-digit year. */
			$o.=self::open(sprintf(__('Training Log — %d','chriss-swim-training-progress-evaluation'),$year),$a['title']);
			if(!$pool_rows&&!$open_water_rows)return$o.'<p>'.esc_html__('No swim workouts are available for this year.','chriss-swim-training-progress-evaluation').'</p>'.$land_year.self::close();
			$months=array();
			foreach($pool_rows as$r)$months[(int)$r->month_num]['pool']=$r;
			foreach($open_water_rows as$r)$months[(int)$r->month_num]['open_water']=$r;
			ksort($months);
			$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Month','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Pool Workouts','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Pool Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Open Water Workouts','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Open Water Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Total Elapsed Time','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';
			$pool_count=0;$pool_m=0;$pool_yd=0;$ows_count=0;$ows_m=0;$elapsed=0;
			foreach($months as$month_num=>$types){
				$p=$types['pool']??null;$ow=$types['open_water']??null;
				$pc=$p?(int)$p->workout_count:0;$pm=$p?(float)$p->meters:0;$py=$p?(float)$p->yards:0;
				$oc=$ow?(int)$ow->workout_count:0;$om=$ow?(float)$ow->meters:0;
				$month_elapsed=($p?(int)$p->elapsed_ms:0)+($ow?(int)$ow->elapsed_ms:0);
				$pool_count+=$pc;$pool_m+=$pm;$pool_yd+=$py;$ows_count+=$oc;$ows_m+=$om;$elapsed+=$month_elapsed;
				$month=wp_date('F',(new \DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00',$year,$month_num),wp_timezone()))->getTimestamp(),wp_timezone());
				$o.='<tr><th scope="row">'.esc_html($month.' '.$year).'</th><td>'.esc_html($pc).'</td><td>'.esc_html($pc?self::total_label($pm,$py):'—').'</td><td>'.esc_html($oc).'</td><td>'.esc_html($oc?self::total_label($om,0):'—').'</td><td>'.esc_html(Workout::format_duration($month_elapsed)).'</td></tr>';
			}
			/* translators: %d: four-digit year. */
			$o.='<tr><th scope="row">'.esc_html(sprintf(__('%d Total','chriss-swim-training-progress-evaluation'),$year)).'</th><td>'.esc_html($pool_count).'</td><td>'.esc_html($pool_count?self::total_label($pool_m,$pool_yd):'—').'</td><td>'.esc_html($ows_count).'</td><td>'.esc_html($ows_count?self::total_label($ows_m,0):'—').'</td><td>'.esc_html(Workout::format_duration($elapsed)).'</td></tr></tbody></table></div>';return$o.$land_year.self::close();
		}
		$month=self::month_number($a['month']??'');if(!$month||$year<1900||$year>2100)return self::unavailable();$rows=Workout::for_month($uid,$year,$month,Land_Activity::authorized($uid)?'all':null);$label=wp_date('F Y',(new \DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00',$year,$month),wp_timezone()))->getTimestamp(),wp_timezone());
		/* translators: %s: localized month and year label. */
		$o.=self::open(sprintf(__('Training Log — %s','chriss-swim-training-progress-evaluation'),$label),$a['title']);
		if(!$rows)return$o.'<p>'.esc_html__('No swim workouts are available for this month.','chriss-swim-training-progress-evaluation').'</p>'.self::close();
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Type','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Location','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Course','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Elapsed Time','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';$elapsed=0;
		foreach($rows as$w){if(in_array(self::workout_type($w),array('pool','open_water'),true))$elapsed+=(int)$w->elapsed_time_ms;$type=self::workout_type($w);$course=$type==='open_water'?__('Open Water','chriss-swim-training-progress-evaluation'):($w->pool_length_unit?:'—');$o.='<tr><td>'.esc_html(self::local_date('F j, Y',$w->workout_start)).'</td><td>'.esc_html(self::workout_type_label($w)).'</td><td>'.esc_html($w->location_name?:'—').'</td><td>'.esc_html(self::workout_distance($w)).'</td><td>'.esc_html($course).'</td><td>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</td></tr>';}
		$t=self::distance_totals($rows);
        $swims=array_values(array_filter($rows,static function($w){return in_array($w->workout_type??'pool',array('pool','open_water'),true);}));
		/* translators: %d: number of swim workouts in the month. */
		$workout_count=sprintf(_n('%d workout','%d workouts',count($swims),'chriss-swim-training-progress-evaluation'),count($swims));
		$o.='<tr><th scope="row">'.esc_html(count($swims)!==count($rows)?__('Swimming Total','chriss-swim-training-progress-evaluation'):__('Month Total','chriss-swim-training-progress-evaluation')).'</th><td>—</td><td>'.esc_html($workout_count).'</td><td>'.esc_html(self::total_label($t['m'],$t['yd'])).'</td><td>—</td><td>'.esc_html(Workout::format_duration($elapsed)).'</td></tr></tbody></table></div>';return$o.self::land_month_html($rows).self::close();
	}

	public static function workout($atts){return self::render_workout($atts);}
	public static function render_workout($atts){
		self::load();$a=self::attrs($atts,array('id'=>''));$id=absint($atts['id']??0);if(!$id)return self::unavailable();global$wpdb;$t=Database::table('workouts');$base=Database::read( 'get_row', $wpdb->prepare("SELECT id,user_id FROM %i WHERE id=%d",$t, $id));if(!$base)return self::unavailable();$uid=self::owner($a,$base);if(!self::allowed($uid))return self::unavailable();$w=Workout::get_for_user($id,$uid);if(!$w||!in_array($w->workout_type??'pool',array('pool','open_water'),true))return self::unavailable();$perfs=Workout::performances($id,$uid);$o=self::styles().self::open(__('Swim Workout','chriss-swim-training-progress-evaluation'),$a['title']);
		$type=self::workout_type($w);$o.='<dl><dt>'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::local_date('F j, Y',$w->workout_start)).'</dd><dt>'.esc_html__('Type','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::workout_type_label($w)).'</dd>';if($w->location_name)$o.='<dt>'.esc_html__('Location','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html($w->location_name).'</dd>';$o.='<dt>'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(self::workout_distance($w)).'</dd>';if($type==='pool'){$o.='<dt>'.esc_html__('Pool','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(null!==$w->original_pool_length?$w->original_pool_length.' '.$w->pool_length_unit:'—').'</dd>';}else{$analytics=Workout::open_water_analytics($id,$uid);if(null!==($w->moving_time_ms??null))$o.='<dt>'.esc_html__('Moving','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->moving_time_ms)).'</dd>';if(!empty($analytics['elapsed_pace_100m_ms']))$o.='<dt>'.esc_html__('Average pace / 100 m','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($analytics['elapsed_pace_100m_ms'])).'</dd>';if(!empty($analytics['moving_pace_100m_ms']))$o.='<dt>'.esc_html__('Moving pace / 100 m','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($analytics['moving_pace_100m_ms'])).'</dd>';if(null!==($analytics['non_moving_time_ms']??null))$o.='<dt>'.esc_html__('Non-moving time','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($analytics['non_moving_time_ms'])).'</dd>';if(null!==($analytics['active_percent']??null))$o.='<dt>'.esc_html__('Active time','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(number_format_i18n($analytics['active_percent'],1).'%').'</dd>';if(null!==($w->average_heart_rate??null))$o.='<dt>'.esc_html__('Heart rate','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(sprintf(/* translators: 1: average heart rate, 2: maximum heart rate. */ __('%1$d bpm average / %2$d bpm max','chriss-swim-training-progress-evaluation'),(int)$w->average_heart_rate,(int)$w->max_heart_rate)).'</dd>';if(null!==($w->average_cadence??null))$o.='<dt>'.esc_html__('Cadence','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(sprintf(/* translators: 1: average cadence, 2: maximum cadence. */ __('%1$s average / %2$s max','chriss-swim-training-progress-evaluation'),number_format_i18n((float)$w->average_cadence,1),number_format_i18n((float)$w->max_cadence,1))).'</dd>';} $o.='<dt>'.esc_html__('Elapsed','chriss-swim-training-progress-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</dd></dl>';if($type==='open_water'&&!empty($analytics['splits'])){$o.='<h3>'.esc_html__('1 km Splits','chriss-swim-training-progress-evaluation').'</h3><div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Split','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Cumulative','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Pace / 100 m','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';foreach($analytics['splits'] as$split){$o.='<tr><td>'.esc_html(number_format_i18n($split['distance_m']/1000,0).' km').'</td><td>'.esc_html(Workout::format_duration($split['split_time_ms'])).'</td><td>'.esc_html(Workout::format_duration($split['cumulative_time_ms'])).'</td><td>'.esc_html(Workout::format_duration((int)round($split['split_time_ms']/10)).' / 100 m').'</td></tr>';}$o.='</tbody></table></div>';}
		$o .= '<dl><dt>' . esc_html__( 'Equivalent Calculated Steps', 'chriss-swim-training-progress-evaluation' ) . '</dt><dd>' . esc_html( null !== ( $w->equivalent_steps ?? null ) ? number_format_i18n( (int) $w->equivalent_steps ) : '—' ) . '</dd>';
		$o .= '<dt>' . esc_html__( 'Calculated active swimming time', 'chriss-swim-training-progress-evaluation' ) . '</dt><dd>' . esc_html( null !== ( $w->steps_active_ms ?? null ) ? Workout::format_duration( (int) $w->steps_active_ms ) : '—' ) . '</dd>';
		$o .= '<dt>' . esc_html__( 'Equivalent steps rate', 'chriss-swim-training-progress-evaluation' ) . '</dt><dd>' . esc_html( null !== ( $w->steps_rate ?? null ) ? number_format_i18n( (int) $w->steps_rate ) . ' ' . __( 'steps/min', 'chriss-swim-training-progress-evaluation' ) : '—' ) . '</dd>';
		$o .= '<dt>' . esc_html__( 'Calculation source', 'chriss-swim-training-progress-evaluation' ) . '</dt><dd>' . esc_html( 'pool_active_lengths' === ( $w->steps_method ?? '' ) ? __( 'Active pool lengths', 'chriss-swim-training-progress-evaluation' ) : ( 'recorded_moving_time' === ( $w->steps_method ?? '' ) ? __( 'Recorded moving time', 'chriss-swim-training-progress-evaluation' ) : '—' ) ) . '</dd></dl>';
		if ( $type === 'pool' ) {
			$o .= '<dl><dt>' . esc_html__( 'Average SWOLF', 'chriss-swim-training-progress-evaluation' ) . '</dt><dd>' . esc_html( null !== ( $w->avg_swolf ?? null ) ? number_format_i18n( (float) $w->avg_swolf, 2 ) : '-' ) . '</dd></dl>';
			$lengths = Workout::lengths( $id, $uid );
			if ( $lengths ) {
				$o .= '<h3>' . esc_html__( 'Recorded Pool Lengths', 'chriss-swim-training-progress-evaluation' ) . '</h3><div class="swimlog-table-wrap"><table><thead><tr><th>#</th><th>' . esc_html__( 'Stroke', 'chriss-swim-training-progress-evaluation' ) . '</th><th>' . esc_html__( 'Time', 'chriss-swim-training-progress-evaluation' ) . '</th><th>' . esc_html__( 'Strokes', 'chriss-swim-training-progress-evaluation' ) . '</th><th>SWOLF</th></tr></thead><tbody>';
				foreach ( $lengths as $length ) {
					$o .= '<tr><td>' . esc_html( $length->sequence_no ) . '</td><td>' . esc_html( self::stroke_name( $length->stroke ) ) . '</td><td>' . esc_html( Workout::format_duration( $length->elapsed_time_ms ) ) . '</td><td>' . esc_html( null !== ( $length->stroke_count ?? null ) ? (int) $length->stroke_count : '-' ) . '</td><td>' . esc_html( null !== ( $length->swolf_score ?? null ) ? (int) $length->swolf_score : '-' ) . '</td></tr>';
				}
				$o .= '</tbody></table></div>';
			}
		}
		if($type==='open_water'&&self::public_routes_allowed($uid)){$route=self::open_water_route_svg(Workout::trackpoints($id,$uid));if($route!=='')$o.='<h3>'.esc_html__('Route','chriss-swim-training-progress-evaluation').'</h3>'.$route;}
		if($perfs){$o.='<h3>'.esc_html__('Workout Bests','chriss-swim-training-progress-evaluation').'</h3><div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Stroke','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Time','chriss-swim-training-progress-evaluation').'</th><th scope="col">'.esc_html__('Current PB','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';foreach($perfs as$p){if($p->stroke==='UNKNOWN')continue;$o.='<tr><td>'.esc_html(self::distance_label((int)$p->distance_value,$p->course_unit)).'</td><td>'.esc_html(self::stroke_name($p->stroke)).'</td><td>'.esc_html(Workout::format_duration($p->duration_ms)).'</td><td>'.($p->is_personal_best?esc_html__('Yes','chriss-swim-training-progress-evaluation'):'&mdash;').'</td></tr>';}$o.='</tbody></table></div>';}return$o.self::close();
	}
}

