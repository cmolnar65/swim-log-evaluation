<?php
use PHPUnit\Framework\TestCase;
final class StructuredEditorLayoutV12Test extends TestCase {
    public function test_editor_loads_responsive_stylesheet() {
        $admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString( 'assets/css/structured-workout-editor.css', $admin );
        $css = file_get_contents( dirname( __DIR__ ) . '/assets/css/structured-workout-editor.css' );
        $this->assertStringContainsString( 'grid-template-columns', $css );
        $this->assertStringContainsString( '@media (max-width: 782px)', $css );
    }
    public function test_equipment_checklist_preserves_custom_equipment() {
        $js = file_get_contents( dirname( __DIR__ ) . '/assets/js/structured-workout-editor.js' );
        $this->assertStringContainsString( "check.type = 'checkbox'", $js );
        $this->assertStringContainsString( 'standard.concat(extra)', $js );
        $this->assertStringContainsString( "equipmentField(panel, node)", $js );
        $this->assertStringContainsString( 'swimlog-structure-fields', $js );
    }
}
