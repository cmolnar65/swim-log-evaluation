<?php
/**
 * SwimLog opt-in frontend content template.
 *
 * For block themes use their existing header/footer template parts. For classic
 * themes use their normal header/footer hooks. The post content is rendered by
 * WordPress without changing user-authored columns or SwimLog blocks.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$swimlog_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
if ( $swimlog_block_theme ) {
    ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?><div class="wp-site-blocks"><?php
    echo do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress block renderer.
} else {
    get_header();
}
?>
<main id="primary" class="swimlog-layout-main" role="main">
<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'swimlog-layout-article' ); ?>>
<header class="swimlog-layout-heading"><h1><?php the_title(); ?></h1></header>
<div class="entry-content swimlog-layout-content"><?php the_content(); ?></div>
</article>
<?php endwhile; ?>
</main>
<?php
if ( $swimlog_block_theme ) {
    echo do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress block renderer.
    echo '</div>';
    wp_footer();
    echo '</body></html>';
} else {
    get_footer();
}
