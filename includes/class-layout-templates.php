<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Opt-in page/post layouts. Existing content and shortcode/block renderers
 * remain untouched; no schema or theme files are modified.
 */
final class Layout_Templates {
    const WIDE = 'swimlog-wide';
    const FULL = 'swimlog-full';
    const PREFIX = 'chriss-swim-training-progress-evaluation//';

    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_block_templates' ) );
        add_filter( 'theme_page_templates', array( __CLASS__, 'choices' ), 10, 4 );
        add_filter( 'theme_post_templates', array( __CLASS__, 'choices' ), 10, 4 );
        add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
        add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
        add_action( 'save_post', array( __CLASS__, 'save_preferences' ), 10, 2 );
    }

    /** Native block-theme templates, available in the Page and Post editor. */
    public static function register_block_templates() {
        if ( ! function_exists( 'register_block_template' ) ) return;
        foreach ( array( self::WIDE, self::FULL ) as $slug ) {
            register_block_template(
                self::PREFIX . $slug,
                array(
                    'title' => self::WIDE === $slug ? __( 'SwimLog Wide Content', 'chriss-swim-training-progress-evaluation' ) : __( 'SwimLog Full Width', 'chriss-swim-training-progress-evaluation' ),
                    'description' => __( 'A SwimLog layout with the theme header, footer, and editable post content.', 'chriss-swim-training-progress-evaluation' ),
                    'post_types' => array( 'page', 'post' ),
                    'content' => '<!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->'
                        . '<!-- wp:group {"tagName":"main","className":"swimlog-layout-main","layout":{"type":"constrained"}} -->'
                        . '<main class="wp-block-group swimlog-layout-main is-layout-constrained wp-block-group-is-layout-constrained">'
                        . '<!-- wp:post-title {"level":1} /-->'
                        . '<!-- wp:post-content {"className":"swimlog-layout-content"} /-->'
                        . '</main><!-- /wp:group -->'
                        . '<!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->',
                )
            );
        }
    }

    public static function choices( $templates ) {
        $templates[ self::WIDE ] = __( 'SwimLog Wide Content', 'chriss-swim-training-progress-evaluation' );
        $templates[ self::FULL ] = __( 'SwimLog Full Width', 'chriss-swim-training-progress-evaluation' );
        return $templates;
    }

    private static function selected( $post_id = 0 ) {
        if ( ! $post_id ) $post_id = get_queried_object_id();
        if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'page', 'post' ), true ) ) return '';
        $template = get_page_template_slug( $post_id );
        if ( in_array( $template, array( self::WIDE, self::FULL ), true ) ) return $template;
        if ( in_array( $template, array( self::PREFIX . self::WIDE, self::PREFIX . self::FULL ), true ) ) return substr( $template, strlen( self::PREFIX ) );
        return '';
    }

    public static function template_include( $template ) {
        if ( is_singular( array( 'post', 'page' ) ) && self::selected() && ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() || ! function_exists( 'register_block_template' ) ) ) {
            return SWIMLOG_EVALUATION_DIR . 'templates/swimlog-content.php';
        }
        return $template;
    }

    public static function body_classes( $classes ) {
        if ( ! is_singular( array( 'post', 'page' ) ) || ! self::selected() ) return $classes;
        $classes[] = 'swimlog-layout';
        $classes[] = 'swimlog-layout-' . ( self::FULL === self::selected() ? 'full' : 'wide' );
        $density = get_post_meta( get_queried_object_id(), '_swimlog_table_density', true );
        $font = get_post_meta( get_queried_object_id(), '_swimlog_table_font', true );
        $classes[] = 'swimlog-density-' . ( 'compact' === $density ? 'compact' : 'comfortable' );
        $classes[] = 'swimlog-table-font-' . ( 'small' === $font ? 'small' : 'normal' );
        return $classes;
    }

    public static function enqueue() {
        if ( ! is_singular( array( 'post', 'page' ) ) || ! self::selected() ) return;
        wp_enqueue_style( 'swimlog-layout', SWIMLOG_EVALUATION_URL . 'assets/css/layout-templates.css', array(), SWIMLOG_EVALUATION_VERSION );
    }

    public static function meta_boxes() {
        foreach ( array( 'page', 'post' ) as $type ) {
            add_meta_box( 'swimlog-layout-options', __( 'SwimLog Table Layout', 'chriss-swim-training-progress-evaluation' ), array( __CLASS__, 'options_box' ), $type, 'side' );
        }
    }

    public static function options_box( $post ) {
        wp_nonce_field( 'swimlog_layout_options', 'swimlog_layout_nonce' );
        $density = get_post_meta( $post->ID, '_swimlog_table_density', true );
        $font = get_post_meta( $post->ID, '_swimlog_table_font', true );
        echo '<p><label for="swimlog-table-density">' . esc_html__( 'Table density', 'chriss-swim-training-progress-evaluation' ) . '</label></p><select id="swimlog-table-density" name="swimlog_table_density">';
        foreach ( array( 'comfortable' => 'Comfortable', 'compact' => 'Compact' ) as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $density ?: 'comfortable', $key, false ) . '>' . esc_html( $label ) . '</option>';
        echo '</select><p><label for="swimlog-table-font">' . esc_html__( 'Table font size', 'chriss-swim-training-progress-evaluation' ) . '</label></p><select id="swimlog-table-font" name="swimlog_table_font">';
        foreach ( array( 'normal' => 'Normal', 'small' => 'Small' ) as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $font ?: 'normal', $key, false ) . '>' . esc_html( $label ) . '</option>';
        echo '</select><p>' . esc_html__( 'Choose SwimLog Wide Content or SwimLog Full Width in the Page/Post Template selector to apply these options.', 'chriss-swim-training-progress-evaluation' ) . '</p>';
    }

    public static function save_preferences( $post_id, $post ) {
        if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST['swimlog_layout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swimlog_layout_nonce'] ) ), 'swimlog_layout_options' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $options = array( 'swimlog_table_density' => array( '_swimlog_table_density', array( 'comfortable', 'compact' ) ), 'swimlog_table_font' => array( '_swimlog_table_font', array( 'normal', 'small' ) ) );
        foreach ( $options as $name => $config ) {
            if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) continue;
            $value = sanitize_key( wp_unslash( $_POST[ $name ] ) );
            if ( in_array( $value, $config[1], true ) ) update_post_meta( $post_id, $config[0], $value );
        }
    }
}
