<?php
use PHPUnit\Framework\TestCase;

final class WorkoutAdminDetailIdTest extends TestCase {
	public function test_workout_admin_detail_heading_displays_workout_number() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "Workout Details — Workout #%d", $admin );
		$this->assertStringContainsString( "(int)\$detail", $admin );
	}

	public function test_workout_detail_can_create_one_linked_draft_post() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
		$this->assertStringContainsString( "'create_post'", $admin );
		$this->assertStringContainsString( "swimlog_create_workout_post_", $admin );
		$this->assertStringContainsString( "current_user_can( 'edit_posts' )", $admin );
		$this->assertStringContainsString( "'post_status'  => 'draft'", $admin );
		$this->assertStringContainsString( "'post_author'  => \$user_id", $admin );
		$this->assertStringContainsString( "Swim — %1\$s — %2\$s", $admin );
		$this->assertStringContainsString( "Open Water Swim", $admin );
		$this->assertStringContainsString( "Pool Swim", $admin );
		$this->assertStringContainsString( "wp:swimlog/workout", $admin );
		$this->assertStringContainsString( "_swimlog_workout_id", $admin );
		$this->assertStringContainsString( "get_edit_post_link", $admin );
		$this->assertStringContainsString( "Create Post", $admin );
		$this->assertStringContainsString( "Edit Post", $admin );
		$this->assertStringContainsString( 'style="display:inline-block;margin-left:4px"', $admin );
	}

	public function test_public_workout_renderer_still_does_not_gain_admin_workout_number_heading() {
		$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$this->assertStringNotContainsString( 'Workout Details — Workout #%d', $shortcodes );
	}
}
