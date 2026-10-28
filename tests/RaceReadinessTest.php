<?php
use PHPUnit\Framework\TestCase;

final class RaceReadinessTest extends TestCase {
	public function test_service_uses_exact_event_and_matching_training_evidence() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-race-readiness.php' );
		$this->assertStringContainsString( 'final class Race_Readiness', $code );
		$this->assertStringContainsString( 'public static function for_event( $user_id, $event, $lookback_days = 42 )', $code );
		$this->assertStringContainsString( "w.workout_type='pool'", $code );
		$this->assertStringContainsString( 'w.pool_length_unit=%s', $code );
		$this->assertStringContainsString( 'n.stroke=%s', $code );
		$this->assertStringContainsString( 'p.user_id=%d AND p.course_unit=%s AND p.stroke=%s', $code );
		$this->assertStringContainsString( 'distance_value=%d AND course_unit=%s AND stroke=%s', $code );
		$this->assertStringContainsString( 'is_personal_best=1', $code );
		$this->assertStringContainsString( "'recent_performance_count' => \$recent_count", $code );
		$this->assertStringContainsString( "'recent_average_pace_100_ms'", $code );
		$this->assertStringContainsString( "'recent_change_ms'", $code );
		$this->assertStringContainsString( "'recent_best_vs_pb_ms'", $code );
		$this->assertStringContainsString( "'days_until_event'", $code );
	}

	public function test_readiness_remains_descriptive_not_predictive() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-race-readiness.php' );
		$this->assertStringContainsString( "'descriptive_only' => true", $code );
		$this->assertStringNotContainsString( 'readiness_score', $code );
		$this->assertStringNotContainsString( 'predicted_time', $code );
		$this->assertStringNotContainsString( 'fitness_score', $code );
		$this->assertStringNotContainsString( 'recovery_score', $code );
	}

	public function test_workout_keeps_existing_readiness_api_as_delegate() {
		$workout = file_get_contents( dirname( __DIR__ ) . '/includes/class-workout.php' );
		$this->assertStringContainsString( 'const EVENT_READINESS_LOOKBACK_DAYS = 42', $workout );
		$this->assertStringContainsString( "require_once SWIMLOG_EVALUATION_DIR . 'includes/class-race-readiness.php';", $workout );
		$this->assertStringContainsString( 'return Race_Readiness::for_event( $user_id, $event, $lookback_days );', $workout );
	}

	public function test_admin_surfaces_readiness_on_dashboard_and_events() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$dashboard = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-dashboard.php' );
		$this->assertStringContainsString( 'Admin_Dashboard::render()', $admin );
		$events = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-events-locations.php' );
		$view = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-race-readiness.php' );

		$this->assertStringContainsString( 'Race Readiness 2.0 summarizes recorded evidence only.', $dashboard );
		$this->assertStringContainsString( 'Admin_Race_Readiness::render( Workout::event_readiness( $user_id, $event, Workout::EVENT_READINESS_LOOKBACK_DAYS ), $event, $strokes )', $dashboard );
		$this->assertStringContainsString( 'Preparation Evidence', $events );
		$this->assertStringContainsString( 'Admin_Race_Readiness::render($ready,$event,$strokes)', $events );

		foreach ( array( 'Time to event:', 'Current PB:', 'Recent best:', 'Recent pace:', 'Matching training:', 'Recent distance evidence:', 'Recent change:', 'Recent best vs PB:' ) as $label ) {
			$this->assertStringContainsString( $label, $view );
		}
		$this->assertStringContainsString( 'does not predict race time, fitness, recovery, or whether the swimmer is ready to race.', $view );
	}

	public function test_readme_documents_v09_readiness_scope_and_no_schema_change() {
		$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' );
		$this->assertStringContainsString( 'Race Readiness 2.0', $readme );
		$this->assertStringContainsString( 'current personal best', $readme );
		$this->assertStringContainsString( 'exact-distance recent best', $readme );
		$this->assertStringContainsString( 'default evidence window remains 42 days', $readme );
		$this->assertStringContainsString( 'does not calculate a readiness score', $readme );
		$this->assertStringContainsString( 'requires no database schema change', $readme );
	}
}
