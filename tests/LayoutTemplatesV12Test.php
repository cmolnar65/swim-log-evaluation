<?php
use PHPUnit\Framework\TestCase;

/** Static regression guards for the WordPress 6.0+ compatible template path. */
final class LayoutTemplatesV12Test extends TestCase {
    private function source( $file ) {
        return file_get_contents( dirname( __DIR__ ) . '/' . $file );
    }

    public function test_both_layouts_are_opt_in_for_posts_and_pages() {
        $code = $this->source( 'includes/class-layout-templates.php' );
        $this->assertStringContainsString( "'theme_page_templates'", $code );
        $this->assertStringContainsString( "'theme_post_templates'", $code );
        $this->assertStringContainsString( 'SwimLog Wide Content', $code );
        $this->assertStringContainsString( 'SwimLog Full Width', $code );
        $this->assertStringContainsString( 'get_page_template_slug( $post_id )', $code );
        $this->assertStringContainsString( "is_singular( array( 'post', 'page' ) )", $code );
        $this->assertStringContainsString( 'return $template;', $code );
    }

    public function test_native_block_theme_templates_are_registered_for_pages_and_posts() {
        $code = $this->source( 'includes/class-layout-templates.php' );
        $this->assertStringContainsString( "add_action( 'init', array( __CLASS__, 'register_block_templates' ) )", $code );
        $this->assertStringContainsString( "function_exists( 'register_block_template' )", $code );
        $this->assertStringContainsString( 'register_block_template(', $code );
        $this->assertStringContainsString( "'post_types' => array( 'page', 'post' )", $code );
        $this->assertStringContainsString( 'wp:post-content', $code );
        $this->assertStringContainsString( 'wp:template-part', $code );
        $this->assertStringContainsString( "self::PREFIX . self::WIDE", $code );
        $this->assertStringContainsString( "self::PREFIX . self::FULL", $code );
        $this->assertStringContainsString( "'swimlog-layout-' . ( self::FULL === self::selected() ? 'full' : 'wide' )", $code );
    }

    public function test_preferences_are_nonce_and_capability_protected() {
        $code = $this->source( 'includes/class-layout-templates.php' );
        $this->assertStringContainsString( "wp_verify_nonce(", $code );
        $this->assertStringContainsString(  'current_user_can( \'edit_post\', $post_id )' , $code );
        $this->assertStringContainsString( 'wp_is_post_revision( $post_id )', $code );
        $this->assertStringContainsString( "array( 'comfortable', 'compact' )", $code );
        $this->assertStringContainsString( "array( 'normal', 'small' )", $code );
    }

    public function test_existing_content_and_theme_parts_are_preserved() {
        $template = $this->source( 'templates/swimlog-content.php' );
        $this->assertStringContainsString( 'the_content()', $template );
        $this->assertStringContainsString( 'get_header()', $template );
        $this->assertStringContainsString( 'get_footer()', $template );
        $this->assertStringContainsString( 'wp_head()', $template );
        $this->assertStringContainsString( 'wp_footer()', $template );
        $this->assertStringContainsString( 'wp_body_open()', $template );
        $this->assertStringContainsString( 'wp:template-part', $template );
    }

    public function test_css_is_scoped_and_mobile_tables_scroll() {
        $css = $this->source( 'assets/css/layout-templates.css' );
        $this->assertStringContainsString( 'body.swimlog-layout', $css );
        $this->assertStringContainsString( 'overflow-x:auto', $css );
        $this->assertStringContainsString( '.wp-block-columns', $css );
        $this->assertStringContainsString( 'swimlog-table-font-small', $css );
        $this->assertStringContainsString( 'swimlog-density-compact', $css );
    }

    public function test_plugin_support_and_database_version_remain_unchanged() {
        $main = $this->source( 'swim-log-evaluation.php' );
        $this->assertStringContainsString( 'Requires at least: 6.0', $main );
        $this->assertStringContainsString( "SWIMLOG_EVALUATION_DB_VERSION', '1.2'", $main );
        $this->assertStringContainsString( 'Layout_Templates::register()', $this->source( 'includes/class-plugin.php' ) );
    }
}
