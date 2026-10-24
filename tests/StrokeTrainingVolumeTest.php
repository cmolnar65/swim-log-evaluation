<?php
use PHPUnit\Framework\TestCase;

final class StrokeTrainingVolumeTest extends TestCase {
 public function test_query_uses_configured_rolling_period_and_active_pool_lengths(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString('public static function stroke_volume_for_user( $user_id, $lookback_months=3 )',$code);
  $this->assertStringContainsString('$lookback_months=max(1,min(24,(int)$lookback_months));',$code);
  $this->assertStringContainsString("w.workout_type='pool'",$code);
  $this->assertStringContainsString("n.length_type='active'",$code);
  $this->assertStringContainsString("w.workout_start >= %s",$code);
  $this->assertStringContainsString("w.workout_start <= %s",$code);
  $this->assertStringContainsString("w.pool_length_unit course_unit",$code);
  $this->assertStringContainsString("GROUP BY w.pool_length_unit,COALESCE(NULLIF(n.stroke,''),'UNKNOWN')",$code);
  $this->assertStringContainsString("n.distance_m/0.9144",$code);
 }

 public function test_query_orders_meters_then_yards_and_distance_descending(){
  $code=file_get_contents(dirname(__DIR__).'/includes/class-workout.php');
  $this->assertStringContainsString("ORDER BY CASE WHEN w.pool_length_unit='m' THEN 0 ELSE 1 END ASC,distance_native DESC,stroke ASC",$code);
 }

 public function test_swimmer_configuration_stores_training_volume_period(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString("'swimlog_training_volume_months'",$code);
  $this->assertStringContainsString('name="training_volume_months" min="1" max="24" step="1"',$code);
  $this->assertStringContainsString('the default is 3 months.',$code);
 }

 public function test_admin_history_separates_courses_and_ignores_list_filters(){
  $code=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
  $this->assertStringContainsString('Pool Training Volume by Stroke — Last %d Months',$code);
  $this->assertStringContainsString("Workout::stroke_volume_for_user(\$user_id,\$training_volume_months)",$code);
  $this->assertStringNotContainsString("Workout::stroke_volume_for_user(\$user_id,\$filters)",$code);
  $this->assertStringContainsString("\$stroke_volume_by_course=array('m'=>array(),'yd'=>array())",$code);
  $this->assertStringContainsString("'open_water'!==\$filters['workout_type']",$code);
  $this->assertStringContainsString('Meter and yard courses are kept separate and strokes are ranked by total distance.',$code);
  $this->assertStringContainsString('Active swim time',$code);
 }
}
