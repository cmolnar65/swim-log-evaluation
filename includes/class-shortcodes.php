<?php
namespace SwimLogEvaluation;
require_once __DIR__ . '/class-workout-visibility.php';
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
        Workout_Visibility::no_cache();
        return $uid>0 && '1' === (string)get_user_meta($uid,'swimlog_public_results',true);
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
        $r=Workout_Visibility::query_public($uid,array(),1,max(1,min(20,(int)($a['count']?:1))));
        return self::public_listing($r['workouts'],__('Latest Activities','chriss-swim-training-progress-evaluation'),$a['title']);
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
		$pbs=$best==='AG'?Workout_Visibility::personal_bests($uid,$course,$age_group):Workout_Visibility::personal_bests($uid,$course);$o=self::styles().self::open(__('Personal Bests','chriss-swim-training-progress-evaluation'),$a['title']);$distances=$a['distance']?array($a['distance']):Evaluator::DISTANCES;
		if($a['stroke']){$strokes=array($a['stroke']);}else{$strokes=array('OVERALL');foreach(array('FR','BR','BACK','FLY','MIXED') as$s){foreach($pbs as$distance_pbs){if(!empty($distance_pbs[$s])){$strokes[]=$s;break;}}}}
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th>';foreach($strokes as $s)$o.='<th scope="col">'.esc_html($s==='OVERALL'?__('Overall','chriss-swim-training-progress-evaluation'):self::stroke_name($s)).'</th>';$o.='</tr></thead><tbody>';
		$any=false;foreach($distances as $d){$o.='<tr><th scope="row">'.esc_html(self::distance_label($d,$course)).'</th>';foreach($strokes as $s){$p=$pbs[$d][$s]??null;$any=$any||!!$p;$o.='<td>'.($p?esc_html(Workout::format_duration($p->duration_ms)).'<br><span class="swimlog-muted">'.esc_html(self::local_date('M j, Y',$p->achieved_at)).'</span>':'&mdash;').'</td>';}$o.='</tr>';}$o.='</tbody></table></div>';return$o.(!$any?'<p>'.esc_html__('No qualifying personal bests are available for this selection.','chriss-swim-training-progress-evaluation').'</p>':'').self::close();
	}
	public static function progress($atts){return self::render_progress($atts);}
	public static function render_progress($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$course=in_array($a['course'],array('m','yd'),true)?$a['course']:get_option('swimlog_default_course_unit','m');if(!in_array($course,array('m','yd'),true))$course='m';
		$valid_strokes=array('FR','BR','BACK','FLY','MIXED');if(!$a['distance']||!in_array($a['distance'],Evaluator::DISTANCES,true)||!in_array($a['stroke'],$valid_strokes,true))return self::unavailable();
		$rows=Workout_Visibility::progression($uid,$a['distance'],$course,$a['stroke']);$event=self::distance_label($a['distance'],$course).' '.self::stroke_name($a['stroke']);
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
		foreach($events as $e){$public_bests=Workout_Visibility::personal_bests($uid,$e->course_unit);$pb=$public_bests[(int)$e->distance_value][$e->stroke]??null;$o.='<tr><td>'.esc_html(self::local_date('F j, Y',$e->event_date.' 12:00:00')).($e->event_time?'<br><span class="swimlog-muted">'.esc_html(self::local_date(get_option('time_format','g:i a'),$e->event_date.' '.$e->event_time)).'</span>':'').'</td><td>'.esc_html($e->event_name).'</td><td>'.esc_html(self::distance_label((int)$e->distance_value,$e->course_unit)).'</td><td>'.esc_html(self::stroke_name($e->stroke)).'</td><td>'.($pb?esc_html(Workout::format_duration($pb->duration_ms)):'&mdash;').'</td></tr>';}$o.='</tbody></table></div>';return$o.self::close();
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
	public static function log($atts){return self::render_log($atts);}
	public static function render_log($atts){
        self::load();$raw=(array)$atts;$a=self::attrs($atts,array('month'=>'','year'=>''));$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
        $year=absint($a['year']??0);if(!$year)$year=(int)current_time('Y');if($year<1900||$year>2100)return self::unavailable();
        $annual=array_key_exists('year',$raw)&&!array_key_exists('month',$raw);$month=$annual?1:self::month_number($a['month']??'');if(!$month)return self::unavailable();
        $start=sprintf('%04d-%02d-01',$year,$month);$end=$annual?sprintf('%04d-12-31',$year):gmdate('Y-m-t',strtotime($start.' UTC'));
        $rows=array();$page=1;do{$r=Workout_Visibility::query_public($uid,array('date_from'=>$start,'date_to'=>$end),$page,100);if(is_wp_error($r))return self::unavailable();$rows=array_merge($rows,$r['workouts']);++$page;}while($page<=$r['pages']);
        return self::public_listing($rows,__('Training Log','chriss-swim-training-progress-evaluation').' — '.($annual?$year:self::local_date('F Y',$start.' 12:00:00')),$a['title']);
	}

	public static function workout($atts){return self::render_workout($atts);}
	public static function render_workout($atts){
        self::load();$a=self::attrs($atts,array('id'=>''));$w=Workout_Visibility::get_public(absint($atts['id']??0));if(is_wp_error($w))return self::unavailable();
        $o=self::public_listing(array($w),__('Recorded Workout','chriss-swim-training-progress-evaluation'),$a['title'],false);
        if(null!==$w['moving_time_ms'])$o.='<p>'.esc_html__('Moving time','chriss-swim-training-progress-evaluation').': '.esc_html(Workout::format_duration($w['moving_time_ms'])).'</p>';
        if((float)$w['total_distance_m']>0&&(int)$w['elapsed_time_ms']>0)$o.='<p>'.esc_html__('Average pace / 100 m','chriss-swim-training-progress-evaluation').': '.esc_html(Workout::format_duration((int)round($w['elapsed_time_ms']/($w['total_distance_m']/100)))).'</p>';
        if($w['lengths'])$o.=self::public_details($w['lengths'],__('Recorded Pool Lengths','chriss-swim-training-progress-evaluation'));
        if($w['splits'])$o.=self::public_details($w['splits'],__('Recorded laps / splits','chriss-swim-training-progress-evaluation'));
        if($w['performances']){$o.='<h3>'.esc_html__('Workout Bests','chriss-swim-training-progress-evaluation').'</h3><ul>';foreach($w['performances']as$p)$o.='<li>'.esc_html($p->distance_value.' '.$p->course_unit.' '.self::stroke_name($p->stroke).' — '.Workout::format_duration($p->duration_ms)).'</li>';$o.='</ul>';}
        if(null!==$w['avg_swolf'])$o.='<p>'.esc_html__('Average SWOLF','chriss-swim-training-progress-evaluation').': '.esc_html(number_format_i18n($w['avg_swolf'],2)).'</p>';
        if($w['route'])$o.=self::public_route_svg($w['route']);return$o.self::close();
	}
    private static function public_listing($rows,$title,$show,$close=true){
        $o=self::styles().self::open($title,$show);if(!$rows)return$o.'<p>'.esc_html__('No public workouts are available for this selection.','chriss-swim-training-progress-evaluation').'</p>'.($close?self::close():'');
        $o.='<div class="swimlog-table-wrap"><table><thead><tr><th>'.esc_html__('Date','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Type','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Course / Stroke','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Elapsed Time','chriss-swim-training-progress-evaluation').'</th></tr></thead><tbody>';
        $totals=array();foreach($rows as$w){$type=$w['workout_type'];$label=array('pool'=>__('Pool','chriss-swim-training-progress-evaluation'),'open_water'=>__('Open Water','chriss-swim-training-progress-evaluation'),'running'=>__('Running','chriss-swim-training-progress-evaluation'),'walking'=>__('Walking','chriss-swim-training-progress-evaluation'))[$type];
            $unit=in_array($w['original_distance_unit'],array('m','yd'),true)?$w['original_distance_unit']:'m';$distance=null!==$w['original_distance']&&in_array($w['original_distance_unit'],array('m','yd'),true)?$w['original_distance']:$w['total_distance_m'];
            $o.='<tr data-workout-id="'.esc_attr($w['id']).'"><td>'.esc_html(self::local_date('F j, Y',$w['date'].' 12:00:00')).'</td><td>'.esc_html($label).'</td><td>'.esc_html(null===$distance?'—':number_format_i18n($distance,2).' '.$unit).'</td><td>'.esc_html('pool'===$type?($w['original_pool_length'].' '.$w['pool_length_unit'].' / '.self::stroke_name($w['primary_stroke'])):'—').'</td><td>'.esc_html(Workout::format_duration($w['elapsed_time_ms'])).'</td></tr>';
            if(!isset($totals[$type]))$totals[$type]=array('label'=>$label,'count'=>0,'m'=>0,'yd'=>0,'elapsed'=>0);$totals[$type]['count']++;if(null!==$distance)$totals[$type][$unit]+=$distance;$totals[$type]['elapsed']+=(int)$w['elapsed_time_ms'];
        }$o.='</tbody></table></div><h3>'.esc_html__('Separate Activity Totals','chriss-swim-training-progress-evaluation').'</h3><ul>';
        foreach($totals as$t)$o.='<li>'.esc_html($t['label'].' — '.$t['count'].' · '.number_format_i18n($t['m'],2).' m · '.number_format_i18n($t['yd'],2).' yd · '.Workout::format_duration($t['elapsed'])).'</li>';
        return$o.'</ul>'.($close?self::close():'');
    }
    private static function public_details($rows,$title){
        $o='<h3>'.esc_html($title).'</h3><div class="swimlog-table-wrap"><table><thead><tr><th>#</th><th>'.esc_html__('Distance','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Stroke','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Time','chriss-swim-training-progress-evaluation').'</th><th>'.esc_html__('Strokes','chriss-swim-training-progress-evaluation').'</th><th>SWOLF</th></tr></thead><tbody>';
        foreach($rows as$r)$o.='<tr><td>'.esc_html($r->sequence_no).'</td><td>'.esc_html(null!==$r->distance_m?number_format_i18n($r->distance_m,2).' m':'—').'</td><td>'.esc_html(isset($r->stroke)?self::stroke_name($r->stroke):'—').'</td><td>'.esc_html(Workout::format_duration($r->elapsed_time_ms)).'</td><td>'.esc_html($r->stroke_count??'—').'</td><td>'.esc_html($r->swolf_score??'—').'</td></tr>';
        return$o.'</tbody></table></div>';
    }
    private static function public_route_svg($route){
        $points=array();foreach($route['segments']as$segment)$points=array_merge($points,$segment);if(count($points)<2)return'';
        $xs=array_column($points,'longitude');$ys=array_column($points,'latitude');$minx=min($xs);$miny=min($ys);$dx=max(.000001,max($xs)-$minx);$dy=max(.000001,max($ys)-$miny);
        $scale=min(760/$dx,360/$dy);$xoff=(800-$dx*$scale)/2;$yoff=(400-$dy*$scale)/2;
        $svg='<h3>'.esc_html__('Route','chriss-swim-training-progress-evaluation').'</h3><svg class="swimlog-public-route" viewBox="0 0 800 400" role="img" aria-label="'.esc_attr__('Recorded GPS route','chriss-swim-training-progress-evaluation').'">';
        foreach($route['segments']as$segment){if(count($segment)<2)continue;$coords=array();foreach($segment as$p)$coords[]=round($xoff+($p['longitude']-$minx)*$scale,2).','.round(400-$yoff-($p['latitude']-$miny)*$scale,2);$svg.='<polyline fill="none" stroke="currentColor" stroke-width="3" points="'.esc_attr(implode(' ',$coords)).'" />';}
        $first=$points[0];$last=$points[count($points)-1];
        foreach(array(array($first,__('Start','chriss-swim-training-progress-evaluation')),array($last,__('Finish','chriss-swim-training-progress-evaluation')))as$mark){$x=round($xoff+($mark[0]['longitude']-$minx)*$scale,2);$y=round(400-$yoff-($mark[0]['latitude']-$miny)*$scale,2);$svg.='<circle cx="'.esc_attr($x).'" cy="'.esc_attr($y).'" r="4" fill="currentColor"/><text x="'.esc_attr($x+7).'" y="'.esc_attr($y).'">'.esc_html($mark[1]).'</text>';}
        return$svg.'</svg>';
    }

}

