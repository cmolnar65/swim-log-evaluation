<?php
use PHPUnit\Framework\TestCase;

final class EventReadinessTest extends TestCase {
 public function test_readiness_query_is_course_and_stroke_specific_and_uses_recent_pool_training(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString('public static function event_readiness',$code);
  $this->assertStringContainsString("w.workout_type='pool'",$code);
  $this->assertStringContainsString('w.pool_length_unit=%s',$code);
  $this->assertStringContainsString("n.length_type='active'",$code);
  $this->assertStringContainsString('n.stroke=%s',$code);
  $this->assertStringContainsString('ORDER BY p.distance_value DESC',$code);
  $this->assertStringContainsString("'event_distance_or_longer'",$code);
 }
 public function test_dashboard_labels_readiness_as_descriptive_not_predictive(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Readiness is descriptive, not predictive.',$code);
  $this->assertSame(42,\SwimLogEvaluation\Workout::EVENT_READINESS_LOOKBACK_DAYS);
  $this->assertStringContainsString('Workout::event_readiness($user_id,$e,Workout::EVENT_READINESS_LOOKBACK_DAYS)',$code);
  $this->assertStringContainsString('Recent matching volume',$code);
  $this->assertStringContainsString('Recent distance evidence',$code);
  $this->assertStringContainsString('Event distance or longer recorded',$code);
 }
}
