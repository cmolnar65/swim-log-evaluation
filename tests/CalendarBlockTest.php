<?php
use PHPUnit\Framework\TestCase;

final class CalendarBlockTest extends TestCase {
 public function test_calendar_is_native_dynamic_server_rendered_block(){
  $metadata=json_decode(file_get_contents(dirname(__DIR__).'/blocks/calendar/block.json'),true);
  $editor=file_get_contents(dirname(__DIR__).'/blocks/calendar/index.js');
  $this->assertSame('swimlog/calendar',$metadata['name']);
  $this->assertSame(3,$metadata['apiVersion']);
  $this->assertSame('swimlog-block-calendar',$metadata['editorScript']);
  $this->assertSame('swimlog-calendar',$metadata['style']);
  $this->assertFalse($metadata['supports']['html']);
  $this->assertStringContainsString("block: 'swimlog/calendar'",$editor);
  $this->assertStringContainsString('ServerSideRender',$editor);
  $this->assertStringContainsString('return null',$editor);
 }
 public function test_calendar_renderer_uses_calendar_service_and_initial_month(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $this->assertStringContainsString('Calendar::for_user( $user_id',$blocks);
  $this->assertStringContainsString("modify( 'last day of this month' )",$blocks);
  $this->assertStringContainsString('swimlog-calendar-grid',$blocks);
  $this->assertStringContainsString('swimlog-calendar-weekday',$blocks);
 }
 public function test_calendar_owner_resolution_matches_block_policy_without_viewer_fallback(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $start=strpos($blocks,'private static function calendar_owner');
  $end=strpos($blocks,'private static function calendar_allowed',$start);
  $body=substr($blocks,$start,$end-$start);
  $this->assertStringContainsString("attributes['user']",$body);
  $this->assertStringContainsString('get_post()',$body);
  $this->assertStringNotContainsString('get_current_user_id',$body);
  $editor=file_get_contents(dirname(__DIR__).'/blocks/calendar/index.js');
  $this->assertStringContainsString('page or post author',$editor);
  $this->assertStringContainsString('no logged-in-viewer fallback',$editor);
 }
 public function test_calendar_privacy_is_owner_or_manage_all_users_only(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $start=strpos($blocks,'private static function calendar_allowed');
  $end=strpos($blocks,'private static function calendar_month_html',$start);
  $body=substr($blocks,$start,$end-$start);
  $this->assertStringContainsString('! is_user_logged_in()',$body);
  $this->assertStringContainsString("'swimlog_view_own_results'",$body);
  $this->assertStringContainsString("'swimlog_manage_all_users'",$body);
  $this->assertStringNotContainsString('swimlog_public_results',$body);
  $this->assertStringNotContainsString('swimlog_public_ows_routes',$body);
  $this->assertStringNotContainsString('swimlog_rest_api_access',$body);
 }
 public function test_calendar_month_honors_service_first_day_and_keeps_events_distinct(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  foreach(array("'sunday' => array( 'Sun'","'monday' => array( 'Mon'","'saturday' => array( 'Sat'") as $needle)$this->assertStringContainsString($needle,$blocks);
  $this->assertStringContainsString('swimlog-calendar-workout',$blocks);
  $this->assertStringContainsString('swimlog-calendar-event',$blocks);
 }
 public function test_phase_five_adds_private_month_navigation_and_today(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  foreach(array('data-calendar-action="previous"','data-calendar-action="today"','data-calendar-action="next"') as $needle)$this->assertStringContainsString($needle,$blocks);
  $this->assertStringContainsString("url.searchParams.set( 'start', start )",$view);
  $this->assertStringContainsString("url.searchParams.set( 'end', end )",$view);
  $this->assertStringContainsString("url.searchParams.set( 'user', root.dataset.user )",$view);
  $this->assertStringContainsString("credentials: 'same-origin'",$view);
  $this->assertStringContainsString("'X-WP-Nonce'",$view);
 }
 public function test_phase_six_enables_month_week_switch_and_week_renderer(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('data-calendar-view="month"',$blocks);
  $this->assertStringContainsString('data-calendar-view="week"',$blocks);
  $this->assertStringNotContainsString('data-calendar-view="week" aria-pressed="false" disabled',$blocks);
  $this->assertStringContainsString('function renderWeek',$view);
  $this->assertStringContainsString('function loadWeek',$view);
  $this->assertStringContainsString("setViewButtons( root, viewName )",$view);
 }
 public function test_phase_six_week_boundaries_follow_swimmer_first_day(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('function weekStart( focus, firstDay )',$view);
  $this->assertStringContainsString('firstDayNumber( firstDay )',$view);
  $this->assertStringContainsString("value === 'monday' ? 1",$view);
  $this->assertStringContainsString("value === 'saturday' ? 6",$view);
  $this->assertStringContainsString('startDate.getDate() + 6',$view);
 }
 public function test_phase_six_week_navigation_moves_seven_days_and_today_uses_current_week(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString("currentView === 'week'",$view);
  $this->assertStringContainsString("button.dataset.calendarAction === 'previous' ? -7 : 7",$view);
  $this->assertStringContainsString("button.dataset.calendarAction === 'today'",$view);
  $this->assertStringContainsString("loadWeek( root, focus )",$view);
 }
 public function test_phase_six_week_view_uses_calendar_week_summary_only(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString("( data.weeks || [] )[0]",$view);
  foreach(array('workout_count','distance_m','active_time_ms') as $metric)$this->assertStringContainsString('summary.'.$metric,$view);
  foreach(array('calories','tss','fitness','recovery','injury') as $metric)$this->assertStringNotContainsString($metric,$view);
 }
 public function test_phase_seven_workout_cards_open_in_calendar_detail_panel(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('data-calendar-workout=',$blocks);
  $this->assertStringContainsString('data-calendar-detail="1"',$blocks);
  $this->assertStringContainsString('function showWorkoutDetail',$view);
  $this->assertStringContainsString("event.target.closest( '[data-calendar-workout]' )",$view);
  $this->assertStringContainsString('aria-expanded',$view);
  $this->assertStringContainsString("div.dataset.calendarWorkout = JSON.stringify( workout )",$view);
 }
 public function test_phase_seven_detail_uses_only_calendar_safe_workout_fields(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $start=strpos($blocks,'$detail = wp_json_encode');
  $end=strpos($blocks,'private static function calendar_event_html',$start);
  $body=substr($blocks,$start,$end-$start);
  foreach(array("'workout_type'","'workout_start'","'location'","'total_distance_m'","'elapsed_time_ms'","'moving_time_ms'","'pool_length_m'","'original_pool_length'","'pool_length_unit'","'primary_stroke'") as $field)$this->assertStringContainsString($field,$body);
  foreach(array('latitude','longitude','trackpoint','notes','device_model','device_manufacturer','source_type') as $field)$this->assertStringNotContainsString($field,$body);
 }
 public function test_phase_seven_detail_exposes_useful_recorded_fields_without_leaving_calendar(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  foreach(array("'Date'","'Location'","'Distance'","'Active time'","'Elapsed time'","'Pool length'","'Primary stroke'") as $label)$this->assertStringContainsString($label,$view);
  $this->assertStringContainsString("panel.scrollIntoView",$view);
  $this->assertStringContainsString("close.dataset.calendarDetailClose = '1'",$view);
  $this->assertStringNotContainsString('window.location.href',$view);
 }
 public function test_phase_eight_adds_narrow_screen_date_grouped_agenda(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $css=file_get_contents(dirname(__DIR__).'/assets/css/calendar.css');
  $this->assertStringContainsString('data-calendar-agenda="1"',$blocks);
  $this->assertStringContainsString('function renderAgenda',$view);
  $this->assertStringContainsString('swimlog-calendar-agenda-day',$view);
  $this->assertStringContainsString("weekday: 'long'",$view);
  $this->assertStringContainsString('@media(max-width:700px)',$css);
  $this->assertStringContainsString('.swimlog-calendar-grid{display:none}',$css);
  $this->assertStringContainsString('.swimlog-calendar-agenda{display:block}',$css);
 }
 public function test_phase_eight_agenda_is_used_by_month_and_week_without_new_data_source(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertSame(1,substr_count($view,'renderAgenda( root, data, first, last )'));
  $this->assertSame(2,substr_count($view,'renderAgenda( root, data, start, end )'));
  $this->assertStringContainsString('renderAgenda( root, data, first, last )',$view);
  $this->assertStringContainsString('renderAgenda( root, data, start, end )',$view);
  $this->assertStringContainsString('agendaItemsByDate( data )',$view);
  $this->assertStringNotContainsString('agendaUrl',$view);
  $this->assertStringNotContainsString('/agenda',$view);
 }
 public function test_phase_eight_agenda_keeps_workout_details_and_events(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $start=strpos($view,'function agendaItemsByDate');
  $end=strpos($view,'function renderMonth',$start);
  $body=substr($view,$start,$end-$start);
  $this->assertStringContainsString("item( 'swimlog-calendar-workout', parts, false, workout )",$body);
  $this->assertStringContainsString("item( 'swimlog-calendar-event'",$body);
  $this->assertStringContainsString('No workouts or events in this period.',$body);
 }
 public function test_phase_nine_adds_accessible_calendar_and_detail_relationships(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('aria-labelledby="swimlog-calendar-title-',$blocks);
  $this->assertStringContainsString('tabindex="-1"',$blocks);
  $this->assertStringContainsString('aria-controls="swimlog-calendar-detail-',$blocks);
  $this->assertStringContainsString('role="region"',$blocks);
  $this->assertStringContainsString("div.setAttribute( 'aria-controls'",$view);
 }
 public function test_phase_nine_detail_supports_escape_and_focus_return(){
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString("event.key === 'Escape'",$view);
  $this->assertStringContainsString('closeWorkoutDetail( root, true )',$view);
  $this->assertStringContainsString('if ( restoreFocus && trigger ) trigger.focus()',$view);
  $this->assertStringContainsString('close.focus()',$view);
 }
 public function test_phase_nine_announces_loading_and_busy_state(){
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $view=file_get_contents(dirname(__DIR__).'/blocks/calendar/view.js');
  $this->assertStringContainsString('role="status" aria-live="polite"',$blocks);
  $this->assertStringContainsString("root.setAttribute( 'aria-busy', 'true' )",$view);
  $this->assertStringContainsString("root.setAttribute( 'aria-busy', 'false' )",$view);
  $this->assertStringContainsString('Unable to load calendar.',$view);
 }
 public function test_phase_nine_has_visible_focus_reduced_motion_and_forced_colors(){
  $css=file_get_contents(dirname(__DIR__).'/assets/css/calendar.css');
  $this->assertStringContainsString(':focus-visible',$css);
  $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)',$css);
  $this->assertStringContainsString('@media(forced-colors:active)',$css);
 }
 public function test_phase_five_navigation_uses_existing_calendar_rest_endpoint(){
  $metadata=json_decode(file_get_contents(dirname(__DIR__).'/blocks/calendar/block.json'),true);
  $blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $this->assertSame('swimlog-calendar-view',$metadata['viewScript']);
  $this->assertStringContainsString("rest_url( 'swimlog/v1/calendar' )",$blocks);
  $this->assertStringContainsString("wp_enqueue_script( 'swimlog-calendar-view' )",$blocks);
 }
 public function test_calendar_block_does_not_add_shortcode_or_schema(){
  $shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $plugin=file_get_contents(dirname(__DIR__).'/swim-log-evaluation.php');
  $this->assertStringNotContainsString('swimlog_calendar',$shortcodes);
  $this->assertStringContainsString("define( 'SWIMLOG_EVALUATION_DB_VERSION', '0.9' );",$plugin);
 }
}
