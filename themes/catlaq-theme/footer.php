<?php
/**
 * Theme global footer.
 *
 * @package CatlaqAI\Theme
 */
?>
<footer class="catlaq-footer" role="contentinfo">
	<div class="catlaq-footer__inner">
		<div class="site-info">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
			<span class="separator">•</span>
			<a href="<?php echo esc_url( __( 'https://wordpress.org/', 'catlaq-ai' ) ); ?>">
				<?php esc_html_e( 'Powered by WordPress', 'catlaq-ai' ); ?>
			</a>
		</div>
		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'menu_class'     => 'catlaq-footer-menu',
					'container'      => false,
					'depth'          => 1,
				)
			);
		}
		?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
