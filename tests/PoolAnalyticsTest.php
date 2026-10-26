<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Pool_Analytics;

final class PoolAnalyticsTest extends TestCase {
	private function row( $overrides = array() ) {
		return (object) array_merge(
			array(
				'workout_id' => 1,
				'workout_start' => '2026-10-01 06:00:00',
				'pool_length_unit' => 'm',
				'original_pool_length' => 25,
				'pool_length_m' => 25,
				'sequence_no' => 1,
				'distance_m' => 25,
				'elapsed_time_ms' => 20000,
				'stroke' => 'BR',
				'stroke_count' => 10,
				'length_type' => 'active',
			),
			$overrides
		);
	}

	public function test_analytics_keep_pool_lengths_courses_and_strokes_separate() {
		$rows = array(
			$this->row(),
			$this->row( array( 'workout_id' => 2, 'workout_start' => '2026-10-02 06:00:00', 'elapsed_time_ms' => 21000, 'stroke_count' => 11 ) ),
			$this->row( array( 'workout_id' => 3, 'original_pool_length' => 50, 'pool_length_m' => 50, 'distance_m' => 50, 'elapsed_time_ms' => 40000, 'stroke_count' => 20 ) ),
			$this->row( array( 'workout_id' => 4, 'pool_length_unit' => 'yd', 'original_pool_length' => 25, 'pool_length_m' => 22.86, 'distance_m' => 22.86, 'elapsed_time_ms' => 19000, 'stroke' => 'FR', 'stroke_count' => 9 ) ),
		);
		$groups = Pool_Analytics::aggregate_rows( $rows );
		$this->assertCount( 3, $groups );

		$meter25 = null;
		foreach ( $groups as $group ) {
			if ( 'm' === $group['course_unit'] && 25.0 === $group['pool_length'] && 'BR' === $group['stroke'] ) $meter25 = $group;
		}
		$this->assertNotNull( $meter25 );
		$this->assertSame( 2, $meter25['workout_count'] );
		$this->assertSame( 2, $meter25['active_lengths'] );
		$this->assertSame( 82000, $meter25['avg_pace_100_ms'] );
		$this->assertSame( 80000, $meter25['best_pace_100_ms'] );
		$this->assertEqualsWithDelta( 10.5, $meter25['avg_stroke_count'], 0.001 );
		$this->assertEqualsWithDelta( 10.5, $meter25['normalized_strokes_per_25'], 0.001 );
		$this->assertEqualsWithDelta( 31.0, $meter25['avg_swolf'], 0.001 );
		$this->assertEqualsWithDelta( 100.0, $meter25['stroke_count_coverage_percent'], 0.001 );
		$this->assertEqualsWithDelta( 2.439, $meter25['pace_consistency_cv'], 0.01 );
	}

	public function test_missing_stroke_counts_remain_missing_and_are_not_invented() {
		$groups = Pool_Analytics::aggregate_rows(
			array(
				$this->row( array( 'stroke_count' => null ) ),
				$this->row( array( 'sequence_no' => 2, 'elapsed_time_ms' => 22000, 'stroke_count' => null ) ),
			)
		);
		$this->assertCount( 1, $groups );
		$group = $groups[0];
		$this->assertSame( 2, $group['timed_lengths'] );
		$this->assertSame( 0, $group['stroke_count_lengths'] );
		$this->assertNull( $group['avg_stroke_count'] );
		$this->assertNull( $group['normalized_strokes_per_25'] );
		$this->assertNull( $group['avg_swolf'] );
		$this->assertSame( 0.0, $group['stroke_count_coverage_percent'] );
	}

	public function test_rest_lengths_are_excluded_and_untimed_active_distance_is_preserved() {
		$groups = Pool_Analytics::aggregate_rows(
			array(
				$this->row( array( 'length_type' => 'rest', 'distance_m' => 0, 'elapsed_time_ms' => 30000, 'stroke_count' => null ) ),
				$this->row( array( 'sequence_no' => 2, 'elapsed_time_ms' => null, 'stroke_count' => null ) ),
				$this->row( array( 'sequence_no' => 3, 'elapsed_time_ms' => 20000, 'stroke_count' => null ) ),
			)
		);
		$this->assertCount( 1, $groups );
		$group = $groups[0];
		$this->assertSame( 2, $group['active_lengths'] );
		$this->assertSame( 50.0, $group['distance_native'] );
		$this->assertSame( 1, $group['timed_lengths'] );
		$this->assertSame( 80000, $group['avg_pace_100_ms'] );
		$this->assertNull( $group['pace_consistency_cv'] );
	}

	public function test_monthly_trends_use_the_same_metric_rules_as_summary() {
		$groups = Pool_Analytics::aggregate_rows(
			array(
				$this->row( array( 'workout_start' => '2026-09-15 06:00:00' ) ),
				$this->row( array( 'workout_start' => '2026-10-15 06:00:00', 'workout_id' => 2, 'elapsed_time_ms' => 22500, 'stroke_count' => 12 ) ),
			)
		);
		$this->assertCount( 1, $groups );
		$this->assertCount( 2, $groups[0]['months'] );
		$this->assertSame( '2026-09', $groups[0]['months'][0]['month'] );
		$this->assertSame( '2026-10', $groups[0]['months'][1]['month'] );
		$this->assertSame( 90000, $groups[0]['months'][1]['avg_pace_100_ms'] );
		$this->assertEqualsWithDelta( 34.5, $groups[0]['months'][1]['avg_swolf'], 0.001 );
	}

	public function test_database_query_is_user_scoped_pool_only_and_source_agnostic() {
		$code = file_get_contents( dirname( __DIR__ ) . '/includes/class-pool-analytics.php' );
		$this->assertStringContainsString( 'WHERE w.user_id=%d', $code );
		$this->assertStringContainsString( "w.workout_type='pool'", $code );
		$this->assertStringContainsString( "n.length_type='active'", $code );
		$this->assertStringContainsString( 'w.pool_length_unit=%s', $code );
		$this->assertStringContainsString( 'w.workout_start >= %s', $code );
		$this->assertStringContainsString( 'w.workout_start <= %s', $code );
		$this->assertStringNotContainsString( "source_type='import'", $code );
	}

	public function test_admin_page_documents_metric_provenance_and_comparison_limits() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "'swimlog-pool-analytics', 'pool_analytics'", $admin );
		$this->assertStringContainsString( 'public function pool_analytics()', $admin );
		$this->assertStringContainsString( 'Missing timing and stroke-count data are never estimated.', $admin );
		$this->assertStringContainsString( 'SWOLF is elapsed seconds plus recorded strokes for the same length and is pool-length dependent', $admin );
	}
}
