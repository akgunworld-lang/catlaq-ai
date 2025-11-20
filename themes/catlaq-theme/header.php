<?php
/**
 * Theme global header.
 *
 * @package CatlaqAI\Theme
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'catlaq-theme-shell' ); ?>>
<?php wp_body_open(); ?>
<button class="catlaq-theme-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'catlaq-ai' ); ?>">🌙</button>
<header class="catlaq-header" role="banner">
	<div class="catlaq-header__inner">
		<div class="site-branding">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} elseif ( display_header_text() ) {
				?>
				<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php bloginfo( 'name' ); ?>
				</a>
				<p class="site-tagline"><?php bloginfo( 'description' ); ?></p>
				<?php
			}
			?>
		</div>
		<nav class="site-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Primary menu', 'catlaq-ai' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_class'     => 'catlaq-menu',
						'container'      => false,
						'depth'          => 2,
					)
				);
			} else {
				wp_page_menu(
					array(
						'menu_class' => 'catlaq-menu catlaq-menu--fallback',
					)
				);
			}
			?>
		</nav>
		<div class="catlaq-header__cta">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="catlaq-cta catlaq-cta--ghost" href="<?php echo esc_url( home_url( '/catlaq-dashboard' ) ); ?>">
					<?php esc_html_e( 'Dashboard', 'catlaq-ai' ); ?>
				</a>
				<a class="catlaq-cta catlaq-cta--solid" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
					<?php esc_html_e( 'Logout', 'catlaq-ai' ); ?>
				</a>
			<?php else : ?>
				<a class="catlaq-cta catlaq-cta--ghost" href="<?php echo esc_url( wp_login_url() ); ?>">
					<?php esc_html_e( 'Sign In', 'catlaq-ai' ); ?>
				</a>
				<a class="catlaq-cta catlaq-cta--solid" href="<?php echo esc_url( home_url( '/catlaq-access' ) ); ?>">
					<?php esc_html_e( 'Sign Up', 'catlaq-ai' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>
