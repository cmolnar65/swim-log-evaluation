<?php
use PHPUnit\Framework\TestCase;

final class ManualWorkoutIntegrationTest extends TestCase {
 public function test_manual_workouts_use_canonical_workout_queries_for_history_latest_and_training_totals(){
  $workout=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');$shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString('public static function recent_for_user',$workout);$this->assertStringContainsString('public static function totals_by_month_for_year',$workout);
  $this->assertStringContainsString('Workout::recent_for_user($uid,$count)',$shortcodes);$this->assertStringContainsString('Workout::totals_by_month_for_year($uid,$year', $shortcodes);
  $this->assertStringNotContainsString("source_type='import'",$shortcodes);
 }
 public function test_manual_performances_feed_personal_bests_and_progress_through_shared_records(){
  $evaluator=file_get_contents(dirname(__DIR__).'/includes/class-evaluator.php');$shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');
  $this->assertStringContainsString('evaluate_manual_measured_repetitions',$evaluator);$this->assertStringContainsString('self::recalculate_personal_bests( $user_id )',$evaluator);
  $this->assertStringContainsString('Records::personal_bests(', $shortcodes);$this->assertStringContainsString('Records::progression(', $shortcodes);
 }
 public function test_manual_workout_public_display_and_blocks_share_canonical_renderer(){
  $shortcodes=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$blocks=file_get_contents(dirname(__DIR__).'/includes/class-blocks.php');
  $this->assertStringContainsString('Workout::get_for_user($id,$uid)',$shortcodes);$this->assertStringContainsString('Shortcodes::render_workout(', $blocks);
  $this->assertStringContainsString('Shortcodes::render_latest(', $blocks);$this->assertStringContainsString('Shortcodes::render_progress(', $blocks);
 }
 public function test_manual_workout_rest_api_remains_read_only_and_source_agnostic(){
  $rest=file_get_contents(dirname(__DIR__).'/includes/class-rest-api.php');
  $this->assertStringContainsString("'methods'=>\\WP_REST_Server::READABLE",$rest);$this->assertStringContainsString('Workout::get_for_user($id,$uid)',$rest);
  $this->assertStringNotContainsString('CREATABLE',$rest);$this->assertStringNotContainsString('EDITABLE',$rest);$this->assertStringNotContainsString('DELETABLE',$rest);$this->assertStringNotContainsString("source_type='import'",$rest);
 }
 public function test_create_edit_post_workflow_embeds_shared_workout_block_for_manual_workouts(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin-workouts.php');
  $this->assertStringContainsString("'create_post'",$admin);$this->assertStringContainsString('wp:swimlog/workout',$admin);$this->assertStringContainsString('_swimlog_workout_id',$admin);$this->assertStringContainsString('get_edit_post_link',$admin);
 }
 public function test_dashboard_recent_workouts_keeps_workout_number_aligned_with_header(){
  $admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');$start=strpos($admin,"<h2><?php esc_html_e('Recent Workouts'");$end=strpos($admin,'</div><?php',$start);$section=substr($admin,$start,$end-$start);
  $this->assertStringContainsString("esc_html_e('Workout #'",$section);$this->assertStringContainsString('esc_html((int)$w->id)',$section);$this->assertStringContainsString("wp_date('M j, Y'",$section);
 }
}
