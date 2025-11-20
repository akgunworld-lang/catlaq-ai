<?php
/**
 * Catlaq Maintenance Child Theme.
 *
 * @package CatlaqMaintenance
 */

add_action(
	'template_redirect',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			status_header( 503 );
			nocache_headers();
			include get_stylesheet_directory() . '/maintenance-template.php';
			exit;
		}
	}
);

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
	}
);
