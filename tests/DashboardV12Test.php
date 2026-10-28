<?php
use PHPUnit\Framework\TestCase;

final class DashboardV12Test extends TestCase {
    public function test_dashboard_is_a_separate_owner_scoped_read_only_component() {
        $source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-dashboard.php' );
        $this->assertStringContainsString( "Admin_UI::selected_user_id()", $source );
        $this->assertStringContainsString( 'w.user_id = %d', $source );
        $this->assertStringContainsString( 'user_id = %d', $source );
        $this->assertStringContainsString( 'swimlog_view_own_results', $source );
        $this->assertStringContainsString( 'swimlog_manage_all_users', $source );
        $this->assertStringContainsString( "array( '1', '3', '6', '12', 'all' )", $source );
    }

    public function test_swolf_is_measured_and_course_and_pool_length_aware() {
        $source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-dashboard.php' );
        $this->assertStringContainsString( 'n.swolf_score IS NOT NULL', $source );
        $this->assertStringContainsString( 'w.pool_length_unit = %s', $source );
        $this->assertStringContainsString( 'w.original_pool_length', $source );
        $this->assertStringContainsString( "n.length_type = 'active'", $source );
        $this->assertStringContainsString( 'n.stroke = %s', $source );
    }

    public function test_dashboard_does_not_require_schema_change() {
        $main = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
        $this->assertStringContainsString( "SWIMLOG_EVALUATION_DB_VERSION', '1.2'", $main );
    }

    public function test_carousel_is_accessible_and_responsive() {
        $php = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-dashboard.php' );
        $js = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );
        $css = file_get_contents( dirname( __DIR__ ) . '/assets/css/admin.css' );
        $this->assertStringContainsString( 'data-swimlog-chart-next', $php );
        $this->assertStringContainsString( 'tabindex="0"', $php );
        $this->assertStringContainsString( 'ArrowRight', $js );
        $this->assertStringContainsString( 'scroll-snap-type:x mandatory', $css );
        $this->assertStringContainsString( 'prefers-reduced-motion', $css );
    }
}
