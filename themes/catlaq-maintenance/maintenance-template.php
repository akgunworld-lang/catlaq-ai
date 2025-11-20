<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<div class="catlaq-maintenance">
		<div class="catlaq-maintenance__badge"><?php esc_html_e( 'Catlaq', 'catlaq-maintenance' ); ?></div>
		<h1><?php esc_html_e( 'We are preparing the Digital Expo.', 'catlaq-maintenance' ); ?></h1>
		<p><?php esc_html_e( 'Our engineers are configuring the Catlaq AI and Expo modules. We will be back shortly with the full experience.', 'catlaq-maintenance' ); ?></p>
		<p><?php esc_html_e( 'If you are the site owner, sign in to preview the latest build.', 'catlaq-maintenance' ); ?></p>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
