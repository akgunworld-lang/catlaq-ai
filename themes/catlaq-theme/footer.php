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
			<a href="<?php echo esc_url( home_url( '/privacy-policy' ) ); ?>">
				<?php esc_html_e( 'Privacy Policy', 'catlaq-ai' ); ?>
			</a>
			<span class="separator">•</span>
			<a href="<?php echo esc_url( home_url( '/terms-of-service' ) ); ?>">
				<?php esc_html_e( 'Terms of Service', 'catlaq-ai' ); ?>
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
	<div class="catlaq-footer__credit">
		<p><?php echo sprintf( 
			wp_kses_post( __( 'Built with <a href="%s">Catlaq AI</a> • <a href="%s">WordPress</a>', 'catlaq-ai' ) ),
			esc_url( 'https://catlaq.ai' ),
			esc_url( 'https://wordpress.org/' )
		); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
