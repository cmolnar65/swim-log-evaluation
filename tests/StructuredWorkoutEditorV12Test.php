<?php
use PHPUnit\Framework\TestCase;

final class StructuredWorkoutEditorV12Test extends TestCase {
    private function source( $path ) { return file_get_contents( dirname( __DIR__ ) . '/' . $path ); }
    public function test_private_owner_scoped_persistence() {
        $s = $this->source( 'includes/class-structured-workout-store.php' );
        $this->assertStringContainsString( "'public' => false", $s );
        $this->assertStringContainsString( "'show_in_rest' => false", $s );
        $this->assertStringContainsString( 'post_author', $s );
        $this->assertStringContainsString( 'Structured_Workout::summarize', $s );
        $this->assertStringContainsString( 'wp_slash( $data )', $s );
        $this->assertStringContainsString( 'wp_update_post', $s );
        $this->assertStringContainsString( 'wp_insert_post', $s );
    }
    public function test_editor_has_nonce_and_owner_capability() {
        $s = $this->source( 'admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString( "check_admin_referer( 'swimlog_structure_save' )", $s );
        $this->assertStringContainsString( "'swimlog_manage_own_workouts'", $s );
        $this->assertStringContainsString( 'Structured_Workout_Store::get(', $s );
        $this->assertStringContainsString( 'Structured_Workout_Store::save(', $s );
        $this->assertStringContainsString( 'esc_attr( wp_json_encode( $document ) )', $s );
    }
    public function test_editor_supports_ladder_repeat_steps_and_preview() {
        $js = $this->source( 'assets/js/structured-workout-editor.js' );
        foreach ( array( "'ladder'", "'repeat'", "'step'", "'section'", "'pace'", "'rest'", "'equipment'", "'drill'", 'swimlog-structure-preview', 'JSON.stringify(doc)' ) as $term ) {
            $this->assertStringContainsString( $term, $js );
        }
    }
    public function test_admin_and_bootstrap_wiring() {
        $admin = $this->source( 'admin/class-admin.php' );
        $plugin = $this->source( 'includes/class-plugin.php' );
        $this->assertStringContainsString( 'swimlog-structured-workouts', $admin );
        $this->assertStringContainsString( 'handle_structured_workout_actions', $admin );
        $this->assertStringContainsString( 'Structured_Workout_Store', $plugin );
    }
}
