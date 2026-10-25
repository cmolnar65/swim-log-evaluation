<?php
use PHPUnit\Framework\TestCase;

final class WorkoutAdminIdTest extends TestCase {
	public function test_workout_admin_list_displays_workout_number() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "esc_html_e('Workout #'", $admin );
		$this->assertStringContainsString( "esc_html((int)\$w->id)", $admin );
		$list_start = strpos( $admin, "if(!\$result['rows'])" );
		$list_end = strpos( $admin, "public function training_volume()", $list_start );
		$list = substr( $admin, $list_start, $list_end - $list_start );
		$this->assertSame( 1, substr_count( $list, "esc_html_e('Workout #'") );
		$this->assertStringContainsString( "Workout #','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Date'", $list );
	}

	public function test_public_workout_renderer_does_not_gain_workout_number_label() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$this->assertStringNotContainsString( "esc_html__('Workout #'", $shortcodes );
	}
}
