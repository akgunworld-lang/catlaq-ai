<?php
get_header();
?>
<main id="primary" class="site-main catlaq-theme">
    <?php
    if ( have_posts() ) {
        while ( have_posts() ) {
            the_post();
            the_content();
        }
    } else {
        echo '<section class="catlaq-empty">' . esc_html__( 'No content found. Please publish a page and assign the Catlaq layout.', 'catlaq-online-expo' ) . '</section>';
    }
    ?>
</main>
<?php
get_footer();
?>
