<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Administration documentation for public swim shortcodes.
 */
final class Admin_Shortcodes {
	public static function shortcodes() {
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to access this swim training page.','chriss-swim-training-progress-evaluation'));
		$items=array(
			'latest'=>array('[swimlog_latest]',__('Latest swim summary. Supports user, count, and title attributes.','chriss-swim-training-progress-evaluation')),
			'bests'=>array('[swimlog_bests]',__('Native-course personal best table. Supports user, course, stroke, distance, title, best, and ag attributes.','chriss-swim-training-progress-evaluation')),
			'upcoming-events'=>array('[swimlog_upcoming_events]',__('Upcoming events with exact matching current PB. Supports user, count, and title attributes.','chriss-swim-training-progress-evaluation')),
			'workout'=>array('[swimlog_workout id="123"]',__('One public workout summary. Requires id and supports title; ownership is derived from the workout.','chriss-swim-training-progress-evaluation')),
			'log'=>array('[swimlog_log]',__('Monthly training log or annual monthly totals. Supports user, month, year, and title attributes.','chriss-swim-training-progress-evaluation')),
			'progress'=>array('[swimlog_progress]',__('Personal-best progression for one event. Requires distance and stroke; supports user, course, and title.','chriss-swim-training-progress-evaluation')),
		);
		?><div class="wrap swimlog-admin"><h1><?php esc_html_e('Shortcodes','chriss-swim-training-progress-evaluation'); ?></h1><p><?php esc_html_e('Public swim output is display-only and private by default. Without a user attribute, the current page or post author is used. There is no logged-in-viewer fallback.','chriss-swim-training-progress-evaluation'); ?></p><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Shortcode','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Purpose','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($items as$slug=>$item):?><tr><td><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-shortcode-'.$slug)); ?>"><code><?php echo esc_html($item[0]); ?></code></a></td><td><?php echo esc_html($item[1]); ?></td></tr><?php endforeach;?></tbody></table></div><p class="description"><?php esc_html_e('Select any shortcode above for its complete attribute reference and examples.','chriss-swim-training-progress-evaluation'); ?></p></div><?php
	}

	private static function shortcode_help_page($shortcode,$summary,$attributes,$examples,$notes=array()){
		if(!current_user_can('swimlog_manage_settings'))wp_die(esc_html__('You do not have permission to access this swim training page.','chriss-swim-training-progress-evaluation'));
		?><div class="wrap swimlog-admin"><p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-shortcodes')); ?>">&larr; <?php esc_html_e('Return to Shortcodes','chriss-swim-training-progress-evaluation'); ?></a></p><h1><code><?php echo esc_html($shortcode); ?></code></h1><p><?php echo esc_html($summary); ?></p><h2><?php esc_html_e('Attributes','chriss-swim-training-progress-evaluation'); ?></h2><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Attribute','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Values / default','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Purpose','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody><?php foreach($attributes as$a):?><tr><td><code><?php echo esc_html($a[0]); ?></code></td><td><?php echo esc_html($a[1]); ?></td><td><?php echo esc_html($a[2]); ?></td></tr><?php endforeach;?></tbody></table></div><?php if($notes):?><h2><?php esc_html_e('Behavior','chriss-swim-training-progress-evaluation'); ?></h2><?php foreach($notes as$n):?><p><?php echo esc_html($n); ?></p><?php endforeach;?><?php endif;?><h2><?php esc_html_e('Examples','chriss-swim-training-progress-evaluation'); ?></h2><p><?php foreach($examples as$e):?><code><?php echo esc_html($e); ?></code><br><?php endforeach;?></p></div><?php
	}
	private static function common_shortcode_attributes(){return array(array('user',__('WordPress numeric user ID; omitted','chriss-swim-training-progress-evaluation'),__('Overrides page/post author resolution. When omitted, the current page/post author is used; there is no logged-in-viewer fallback.','chriss-swim-training-progress-evaluation')),array('title',__('yes (default), no, or custom text','chriss-swim-training-progress-evaluation'),__('Shows the default heading, hides it, or replaces it with custom heading text.','chriss-swim-training-progress-evaluation')));}
	public static function shortcode_latest(){$a=self::common_shortcode_attributes();array_splice($a,1,0,array(array('count',__('1 by default; 1–20','chriss-swim-training-progress-evaluation'),__('Number of most recent workouts to display. One workout uses a detail layout; multiple workouts use a table.','chriss-swim-training-progress-evaluation'))));self::shortcode_help_page('[swimlog_latest]',__('Displays the swimmer’s most recent pool or open-water workout(s).','chriss-swim-training-progress-evaluation'),$a,array('[swimlog_latest]','[swimlog_latest count="5"]','[swimlog_latest user="123" count="5" title="Recent Swims"]'));}
	public static function shortcode_upcoming_events(){$a=self::common_shortcode_attributes();array_splice($a,1,0,array(array('count',__('Site default event count; 1–50','chriss-swim-training-progress-evaluation'),__('Maximum upcoming events to display.','chriss-swim-training-progress-evaluation'))));self::shortcode_help_page('[swimlog_upcoming_events]',__('Displays upcoming configured events and an exact course/stroke/distance current personal best when available.','chriss-swim-training-progress-evaluation'),$a,array('[swimlog_upcoming_events]','[swimlog_upcoming_events count="3"]','[swimlog_upcoming_events count="3" title="no"]'));}
	public static function shortcode_workout(){self::shortcode_help_page('[swimlog_workout]',__('Displays one workout by its numeric workout ID. Workout ownership determines the swimmer; a user attribute does not override ownership.','chriss-swim-training-progress-evaluation'),array(array('id',__('Required numeric workout ID','chriss-swim-training-progress-evaluation'),__('Selects the workout to display.','chriss-swim-training-progress-evaluation')),array('title',__('yes (default), no, or custom text','chriss-swim-training-progress-evaluation'),__('Controls the Swim Workout heading.','chriss-swim-training-progress-evaluation'))),array('[swimlog_workout id="123"]','[swimlog_workout id="123" title="no"]','[swimlog_workout id="123" title="Race Day Swim"]'),array(__('Pool workouts include evaluated workout bests when available. Open-water workouts include stored summary analytics and 1 km splits; the GPS route appears only when the swimmer separately enables Public Open-Water Routes.','chriss-swim-training-progress-evaluation')));}
	public static function shortcode_log(){$a=self::common_shortcode_attributes();array_splice($a,1,0,array(array('month',__('1–12 or English month name/abbreviation; current month when omitted','chriss-swim-training-progress-evaluation'),__('Selects a monthly workout list.','chriss-swim-training-progress-evaluation')),array('year',__('1900–2100; current year when omitted','chriss-swim-training-progress-evaluation'),__('With month, selects that month/year. Supplied without month, switches to annual monthly totals.','chriss-swim-training-progress-evaluation'))));self::shortcode_help_page('[swimlog_log]',__('Displays either a monthly training log or monthly totals across a selected year. Pool meter/yard totals remain separate and open-water totals are reported separately.','chriss-swim-training-progress-evaluation'),$a,array('[swimlog_log]','[swimlog_log month="Sep"]','[swimlog_log month="Sep" year="2025"]','[swimlog_log year="2026"]'));}
	public static function shortcode_progress(){$a=self::common_shortcode_attributes();array_splice($a,1,0,array(array('course',__('m or yd; site default when omitted','chriss-swim-training-progress-evaluation'),__('Selects the native course for the event.','chriss-swim-training-progress-evaluation')),array('stroke',__('Required: FR, BR, BACK, FLY, or MIXED','chriss-swim-training-progress-evaluation'),__('Selects the stroke.','chriss-swim-training-progress-evaluation')),array('distance',__('Required supported evaluation distance','chriss-swim-training-progress-evaluation'),__('Selects the event distance.','chriss-swim-training-progress-evaluation'))));self::shortcode_help_page('[swimlog_progress]',__('Displays the complete recorded personal-best progression for one exact course, stroke, and distance, including a chart when more than one PB exists.','chriss-swim-training-progress-evaluation'),$a,array('[swimlog_progress course="m" stroke="BR" distance="100"]','[swimlog_progress course="yd" stroke="FR" distance="100" title="100 Free Progress"]','[swimlog_progress course="m" stroke="BR" distance="200" title="no"]'));}

	public static function shortcode_bests() {
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
			<div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped">
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
			</table></div>

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

}

