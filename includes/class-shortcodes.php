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
	}
	private static function load(){
		if(self::$loaded)return;
		foreach(array('class-database.php','class-workout.php','class-event.php','class-evaluator.php','class-records.php') as $f)require_once SWIMLOG_EVALUATION_DIR.'includes/'.$f;
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
	private static function attrs($atts,$defaults=array()){
		$a=shortcode_atts(array_merge(array('user'=>'','course'=>'','stroke'=>'','distance'=>'','count'=>'','title'=>'yes'),$defaults),$atts);
		$a['course']=strtolower(sanitize_key($a['course']));$a['stroke']=strtoupper(sanitize_key($a['stroke']));$a['distance']=absint($a['distance']);$a['count']=absint($a['count']);$a['title']=strtolower($a['title'])==='no'?'no':'yes';return$a;
	}
	private static function stroke_name($s){return array('FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'))[$s]??__('Unknown','swim-log-evaluation');}
	private static function unavailable(){return '<div class="swimlog-public"><p>'.esc_html__('Swim results are unavailable.','swim-log-evaluation').'</p></div>';}
	private static function open($title,$show){return '<div class="swimlog-public">'.($show==='yes'?'<h2>'.esc_html($title).'</h2>':'');}
	private static function close(){return '</div>';}
	private static function distance_label($d,$c){if($c==='yd'&&$d===1650)return __('1650 yd / 1 Mile','swim-log-evaluation');if($c==='yd'&&$d===3300)return __('3300 yd / 2 Miles','swim-log-evaluation');if($c==='m'&&$d===5000)return __('5000 m / 5K','swim-log-evaluation');return $d.' '.$c;}
	private static function styles(){return '<style>.swimlog-public{margin:1.5em 0}.swimlog-table-wrap{overflow-x:auto}.swimlog-public table{width:100%;border-collapse:collapse}.swimlog-public th,.swimlog-public td{padding:.55em .7em;border:1px solid currentColor;text-align:left;vertical-align:top}.swimlog-public th{font-weight:600}.swimlog-public .swimlog-muted{opacity:.75}</style>';}

	public static function latest($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$r=Workout::recent_for_user($uid,1);$w=$r[0]??null;$o=self::styles().self::open(__('Latest Swim','swim-log-evaluation'),$a['title']);
		if(!$w)return$o.'<p>'.esc_html__('No swim workouts are available yet.','swim-log-evaluation').'</p>'.self::close();
		$o.='<dl><dt>'.esc_html__('Date','swim-log-evaluation').'</dt><dd>'.esc_html(wp_date('F j, Y',strtotime($w->workout_start))).'</dd>';
		if($w->location_name)$o.='<dt>'.esc_html__('Location','swim-log-evaluation').'</dt><dd>'.esc_html($w->location_name).'</dd>';
		$dist=null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—');
		$o.='<dt>'.esc_html__('Distance','swim-log-evaluation').'</dt><dd>'.esc_html($dist).'</dd><dt>'.esc_html__('Pool','swim-log-evaluation').'</dt><dd>'.esc_html(null!==$w->original_pool_length?$w->original_pool_length.' '.$w->pool_length_unit:'—').'</dd><dt>'.esc_html__('Elapsed','swim-log-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</dd>';
		if($w->primary_stroke)$o.='<dt>'.esc_html__('Primary stroke','swim-log-evaluation').'</dt><dd>'.esc_html(self::stroke_name($w->primary_stroke)).'</dd>';
		return$o.'</dl>'.self::close();
	}
	public static function bests($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$course=in_array($a['course'],array('m','yd'),true)?$a['course']:get_option('swimlog_default_course_unit','m');if(!in_array($course,array('m','yd'),true))$course='m';
		$valid_strokes=array('FR','BR','BACK','FLY','MIXED');if($a['stroke']&&!in_array($a['stroke'],$valid_strokes,true))return self::unavailable();if($a['distance']&&!in_array($a['distance'],Evaluator::DISTANCES,true))return self::unavailable();
		$pbs=Records::personal_bests($uid,$course);$o=self::styles().self::open(__('Personal Bests','swim-log-evaluation'),$a['title']);$strokes=$a['stroke']?array($a['stroke']):array('OVERALL','FR','BR','BACK','FLY','MIXED');$distances=$a['distance']?array($a['distance']):Evaluator::DISTANCES;
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','swim-log-evaluation').'</th>';foreach($strokes as $s)$o.='<th scope="col">'.esc_html($s==='OVERALL'?__('Overall','swim-log-evaluation'):self::stroke_name($s)).'</th>';$o.='</tr></thead><tbody>';
		$any=false;foreach($distances as $d){$o.='<tr><th scope="row">'.esc_html(self::distance_label($d,$course)).'</th>';foreach($strokes as $s){$p=$pbs[$d][$s]??null;$any=$any||!!$p;$o.='<td>'.($p?esc_html(Workout::format_duration($p->duration_ms)).'<br><span class="swimlog-muted">'.esc_html(wp_date('M j, Y',strtotime($p->achieved_at))).'</span>':'&mdash;').'</td>';}$o.='</tr>';}$o.='</tbody></table></div>';return$o.(!$any?'<p>'.esc_html__('No qualifying personal bests are available for this selection.','swim-log-evaluation').'</p>':'').self::close();
	}
	public static function events($atts){
		self::load();$a=self::attrs($atts);$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();$count=$a['count']?:max(1,min(50,(int)get_option('swimlog_default_event_count',5)));$count=max(1,min(50,$count));$events=Event::upcoming_for_user($uid,$count);$o=self::styles().self::open(__('Upcoming Events','swim-log-evaluation'),$a['title']);if(!$events)return$o.'<p>'.esc_html__('No upcoming events are scheduled.','swim-log-evaluation').'</p>'.self::close();
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Date','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Event','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Distance','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Stroke','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Current PB','swim-log-evaluation').'</th></tr></thead><tbody>';
		foreach($events as $e){$pb=Records::exact_personal_best($uid,(int)$e->distance_value,$e->course_unit,$e->stroke);$o.='<tr><td>'.esc_html(wp_date('F j, Y',strtotime($e->event_date.' 12:00:00'))).($e->event_time?'<br><span class="swimlog-muted">'.esc_html(wp_date(get_option('time_format','g:i a'),strtotime($e->event_date.' '.$e->event_time))).'</span>':'').'</td><td>'.esc_html($e->event_name).'</td><td>'.esc_html(self::distance_label((int)$e->distance_value,$e->course_unit)).'</td><td>'.esc_html(self::stroke_name($e->stroke)).'</td><td>'.($pb?esc_html(Workout::format_duration($pb->duration_ms)):'&mdash;').'</td></tr>';}$o.='</tbody></table></div>';return$o.self::close();
	}
	private static function month_number($value){
		$value=trim((string)$value);if($value==='')return(int)current_time('n');
		if(ctype_digit($value)){ $n=(int)$value; return($n>=1&&$n<=12)?$n:0; }
		$months=array('jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,'apr'=>4,'april'=>4,'may'=>5,'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,'aug'=>8,'august'=>8,'sep'=>9,'sept'=>9,'september'=>9,'oct'=>10,'october'=>10,'nov'=>11,'november'=>11,'dec'=>12,'december'=>12);
		return$months[strtolower($value)]??0;
	}
	private static function distance_totals($rows){
		$t=array('m'=>0.0,'yd'=>0.0);foreach($rows as$w){$u=$w->original_distance_unit;if(in_array($u,array('m','yd'),true)&&null!==$w->original_distance)$t[$u]+=(float)$w->original_distance;}return$t;
	}
	private static function total_label($m,$y){
		$p=array();if($m>0)$p[]=number_format_i18n($m,0).' m';if($y>0)$p[]=number_format_i18n($y,0).' yd';return$p?implode(' + ',$p):'—';
	}
	public static function log($atts){
		self::load();$raw=(array)$atts;$a=self::attrs($atts,array('month'=>'','year'=>''));$uid=self::owner($a);if(!self::allowed($uid))return self::unavailable();
		$has_year=array_key_exists('year',$raw);$has_month=array_key_exists('month',$raw);$year=absint($a['year']??0);if(!$year)$year=(int)current_time('Y');
		$annual=$has_year&&!$has_month;$o=self::styles();
		if($annual){
			if($year<1900||$year>2100)return self::unavailable();$rows=Workout::totals_by_month_for_year($uid,$year);$o.=self::open(sprintf(__('Swim Log — %d','swim-log-evaluation'),$year),$a['title']);
			if(!$rows)return$o.'<p>'.esc_html__('No swim workouts are available for this year.','swim-log-evaluation').'</p>'.self::close();
			$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Month','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Workouts','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Total Distance','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Total Elapsed Time','swim-log-evaluation').'</th></tr></thead><tbody>';$wc=0;$tm=0;$ty=0;$te=0;
			foreach($rows as$r){$wc+=(int)$r->workout_count;$tm+=(float)$r->meters;$ty+=(float)$r->yards;$te+=(int)$r->elapsed_ms;$month=wp_date('F',mktime(12,0,0,(int)$r->month_num,1,$year));$o.='<tr><th scope="row">'.esc_html($month.' '.$year).'</th><td>'.esc_html($r->workout_count).'</td><td>'.esc_html(self::total_label((float)$r->meters,(float)$r->yards)).'</td><td>'.esc_html(Workout::format_duration((int)$r->elapsed_ms)).'</td></tr>';}
			$o.='<tr><th scope="row">'.esc_html(sprintf(__('%d Total','swim-log-evaluation'),$year)).'</th><td>'.esc_html($wc).'</td><td>'.esc_html(self::total_label($tm,$ty)).'</td><td>'.esc_html(Workout::format_duration($te)).'</td></tr></tbody></table></div>';return$o.self::close();
		}
		$month=self::month_number($a['month']??'');if(!$month||$year<1900||$year>2100)return self::unavailable();$rows=Workout::for_month($uid,$year,$month);$label=wp_date('F Y',mktime(12,0,0,$month,1,$year));$o.=self::open(sprintf(__('Swim Log — %s','swim-log-evaluation'),$label),$a['title']);
		if(!$rows)return$o.'<p>'.esc_html__('No swim workouts are available for this month.','swim-log-evaluation').'</p>'.self::close();
		$o.='<div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Date','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Location','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Distance','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Course','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Elapsed Time','swim-log-evaluation').'</th></tr></thead><tbody>';$elapsed=0;
		foreach($rows as$w){$elapsed+=(int)$w->elapsed_time_ms;$dist=null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—');$o.='<tr><td>'.esc_html(wp_date('F j, Y',strtotime($w->workout_start))).'</td><td>'.esc_html($w->location_name?:'—').'</td><td>'.esc_html($dist).'</td><td>'.esc_html($w->pool_length_unit?:'—').'</td><td>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</td></tr>';}
		$t=self::distance_totals($rows);$o.='<tr><th scope="row">'.esc_html__('Month Total','swim-log-evaluation').'</th><td>'.esc_html(sprintf(_n('%d workout','%d workouts',count($rows),'swim-log-evaluation'),count($rows))).'</td><td>'.esc_html(self::total_label($t['m'],$t['yd'])).'</td><td>—</td><td>'.esc_html(Workout::format_duration($elapsed)).'</td></tr></tbody></table></div>';return$o.self::close();
	}

	public static function workout($atts){
		self::load();$a=self::attrs($atts,array('id'=>''));$id=absint($atts['id']??0);if(!$id)return self::unavailable();global$wpdb;$t=Database::table('workouts');$base=$wpdb->get_row($wpdb->prepare("SELECT id,user_id FROM $t WHERE id=%d",$id));if(!$base)return self::unavailable();$uid=self::owner($a,$base);if(!self::allowed($uid))return self::unavailable();$w=Workout::get_for_user($id,$uid);if(!$w)return self::unavailable();$perfs=Workout::performances($id,$uid);$o=self::styles().self::open(__('Swim Workout','swim-log-evaluation'),$a['title']);
		$o.='<dl><dt>'.esc_html__('Date','swim-log-evaluation').'</dt><dd>'.esc_html(wp_date('F j, Y',strtotime($w->workout_start))).'</dd>';if($w->location_name)$o.='<dt>'.esc_html__('Location','swim-log-evaluation').'</dt><dd>'.esc_html($w->location_name).'</dd>';$o.='<dt>'.esc_html__('Distance','swim-log-evaluation').'</dt><dd>'.esc_html(null!==$w->original_distance?$w->original_distance.' '.$w->original_distance_unit:($w->total_distance_m?$w->total_distance_m.' m':'—')).'</dd><dt>'.esc_html__('Pool','swim-log-evaluation').'</dt><dd>'.esc_html(null!==$w->original_pool_length?$w->original_pool_length.' '.$w->pool_length_unit:'—').'</dd><dt>'.esc_html__('Elapsed','swim-log-evaluation').'</dt><dd>'.esc_html(Workout::format_duration($w->elapsed_time_ms)).'</dd></dl>';
		if($perfs){$o.='<h3>'.esc_html__('Workout Bests','swim-log-evaluation').'</h3><div class="swimlog-table-wrap"><table><thead><tr><th scope="col">'.esc_html__('Distance','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Stroke','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Time','swim-log-evaluation').'</th><th scope="col">'.esc_html__('Current PB','swim-log-evaluation').'</th></tr></thead><tbody>';foreach($perfs as$p){if($p->stroke==='UNKNOWN')continue;$o.='<tr><td>'.esc_html(self::distance_label((int)$p->distance_value,$p->course_unit)).'</td><td>'.esc_html(self::stroke_name($p->stroke)).'</td><td>'.esc_html(Workout::format_duration($p->duration_ms)).'</td><td>'.($p->is_personal_best?esc_html__('Yes','swim-log-evaluation'):'&mdash;').'</td></tr>';}$o.='</tbody></table></div>';}return$o.self::close();
	}
}
