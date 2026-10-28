<?php
use PHPUnit\Framework\TestCase;

final class EventReadinessTest extends TestCase {
 public function test_readiness_query_is_course_and_stroke_specific_and_uses_recent_pool_training(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-race-readiness.php');
  $this->assertStringContainsString('public static function for_event( $user_id, $event, $lookback_days = 42 )',$code);
  $this->assertStringContainsString("w.workout_type='pool'",$code);
  $this->assertStringContainsString('w.pool_length_unit=%s',$code);
  $this->assertStringContainsString("n.length_type='active'",$code);
  $this->assertStringContainsString('n.stroke=%s',$code);
  $this->assertStringContainsString('ORDER BY p.distance_value DESC',$code);
  $this->assertStringContainsString("'event_distance_or_longer'",$code);
 }
 public function test_dashboard_labels_readiness_as_descriptive_not_predictive(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin-dashboard.php');
  $this->assertStringContainsString('Race Readiness 2.0 summarizes recorded evidence only.',$code);
  $this->assertSame(42,\SwimLogEvaluation\Workout::EVENT_READINESS_LOOKBACK_DAYS);
  $this->assertStringContainsString('Workout::event_readiness( $user_id, $event, Workout::EVENT_READINESS_LOOKBACK_DAYS )',$code);
  $this->assertStringContainsString('Admin_Race_Readiness::render(', $code);
  $this->assertStringContainsString('Matching training:',$code=file_get_contents(dirname(__DIR__).'/admin/class-admin-race-readiness.php'));
  $this->assertStringContainsString('Recent distance evidence:',$code);
  $this->assertStringContainsString('event distance or longer recorded',$code);
 }
}
