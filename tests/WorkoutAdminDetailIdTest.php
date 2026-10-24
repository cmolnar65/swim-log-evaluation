<?php
use PHPUnit\Framework\TestCase;

final class WorkoutAdminDetailIdTest extends TestCase {
	public function test_workout_admin_detail_heading_displays_workout_number() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "Workout Details — Workout #%d", $admin );
		$this->assertStringContainsString( "(int)\$detail", $admin );
	}

	public function test_public_workout_renderer_still_does_not_gain_admin_workout_number_heading() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$this->assertStringNotContainsString( 'Workout Details — Workout #%d', $shortcodes );
	}
}
