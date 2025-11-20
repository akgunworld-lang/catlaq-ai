<?php
/**
 * Catlaq AI Theme Functions
 * 
 * @package CatlaqAI\Theme
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup
 */
function catlaq_ai_theme_setup() {
	// Add theme support
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script'
	) );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'catlaq-ai' ),
			'footer'  => __( 'Footer Menu', 'catlaq-ai' ),
		)
	);
	
	// Add editor style
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'catlaq_ai_theme_setup' );

/**
 * Enqueue theme styles and scripts
 */
function catlaq_ai_theme_scripts() {
	// Enqueue theme style
	wp_enqueue_style( 
		'catlaq-ai-theme-style', 
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
	
	// Enqueue Google Fonts
	wp_enqueue_style(
		'catlaq-ai-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);
	
	// Enqueue theme script
	wp_enqueue_script(
		'catlaq-ai-theme-script',
		get_template_directory_uri() . '/assets/js/theme.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
	
	// Localize script with theme data
	wp_localize_script( 'catlaq-ai-theme-script', 'catlaqTheme', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'catlaq_theme_nonce' ),
		'siteUrl' => home_url(),
		'themePath' => get_template_directory_uri(),
	) );
}
add_action( 'wp_enqueue_scripts', 'catlaq_ai_theme_scripts' );

/**
 * Helper to enqueue a theme asset only once.
 */
function catlaq_ai_theme_enqueue_style_once( string $handle, string $relative_path, array $deps = array() ): void {
	static $enqueued = array();

	if ( isset( $enqueued[ $handle ] ) ) {
		return;
	}

	$stylesheet_uri = trailingslashit( get_template_directory_uri() ) . ltrim( $relative_path, '/' );

	wp_enqueue_style(
		$handle,
		$stylesheet_uri,
		$deps,
		wp_get_theme()->get( 'Version' )
	);

	$enqueued[ $handle ] = true;
}

/**
 * Attach Catlaq-specific styles when plugin shortcodes request them.
 */
function catlaq_ai_theme_digital_expo_assets(): void {
	catlaq_ai_theme_enqueue_style_once( 'catlaq-ai-frontend', 'assets/css/frontend.css', array( 'catlaq-ai-theme-style' ) );
	catlaq_ai_theme_enqueue_style_once( 'catlaq-ai-digital-expo', 'assets/css/manager.css', array( 'catlaq-ai-frontend' ) );
	catlaq_ai_theme_enqueue_style_once( 'catlaq-ai-dashboard', 'assets/css/dashboard-layout.css', array( 'catlaq-ai-theme-style' ) );
	catlaq_ai_theme_enqueue_style_once( 'catlaq-ai-modern', 'assets/css/modern-futuristic-theme.css', array( 'catlaq-ai-dashboard' ) );
}
add_action( 'catlaq_enqueue_digital_expo_assets', 'catlaq_ai_theme_digital_expo_assets' );

function catlaq_ai_theme_engagement_assets(): void {
	catlaq_ai_theme_enqueue_style_once( 'catlaq-ai-engagement', 'assets/css/dark-light-mode.css', array( 'catlaq-ai-theme-style' ) );
}
add_action( 'catlaq_enqueue_engagement_assets', 'catlaq_ai_theme_engagement_assets' );

/**
 * Add custom CSS classes to body
 */
function catlaq_ai_theme_body_classes( $classes ) {
	// Add theme version class
	$classes[] = 'catlaq-ai-theme';
	
	// Add page template class
	if ( is_page_template() ) {
		$template = get_page_template_slug();
		$classes[] = 'page-template-' . sanitize_html_class( str_replace( '.php', '', $template ) );
	}
	
	// Add Digital Expo indicator classes
	if ( function_exists( 'catlaq_is_digital_expo_page' ) && catlaq_is_digital_expo_page() ) {
		$classes[] = 'catlaq-digital-expo';
	}
	
	return $classes;
}
add_filter( 'body_class', 'catlaq_ai_theme_body_classes' );

/**
 * Detect whether current request displays a Digital Expo layout.
 */
function catlaq_is_digital_expo_page(): bool {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_post();
	if ( ! $post ) {
		return false;
	}

	$shortcodes = array(
		'catlaq_expo_booths',
		'catlaq_expo_sessions',
		'catlaq_digital_expo_showcase',
		'catlaq_expo_category_filter',
	);

	foreach ( $shortcodes as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Add support for Catlaq AI plugin features
 */
function catlaq_ai_theme_plugin_support() {
	// Check if Catlaq AI plugin is active
	if ( ! function_exists( 'catlaq_online_expo' ) ) {
		return;
	}
	
	// Add theme support for Digital Expo features
	add_theme_support( 'catlaq-digital-expo' );
	add_theme_support( 'catlaq-chatbot' );
	add_theme_support( 'catlaq-messaging' );
	
	// Hook into plugin actions
	add_action( 'catlaq_ai_ready', 'catlaq_ai_theme_plugin_ready' );
}
add_action( 'after_setup_theme', 'catlaq_ai_theme_plugin_support' );

/**
 * Plugin ready callback
 */
function catlaq_ai_theme_plugin_ready() {
	// Add custom styles for plugin components
	add_action( 'wp_head', function() {
		echo '<style id="catlaq-plugin-integration">';
		echo '.catlaq-chatbot-widget { z-index: 9999; }';
		echo '.catlaq-digital-expo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }';
		echo '</style>';
	} );
}

/**
 * Theme activation hook
 */
function catlaq_ai_theme_activation() {
	flush_rewrite_rules();
	catlaq_ai_theme_ensure_structure();
}
add_action( 'after_switch_theme', 'catlaq_ai_theme_activation' );

/**
 * Add theme-specific post meta
 */
function catlaq_ai_theme_register_block_styles() {
    if ( function_exists( 'register_block_style' ) ) {
        register_block_style(
            'core/button', // Stili eklemek istediğimiz blok (core/button = Düğme)
            array(
                'name'  => 'underlined', // Benzersiz stil adı (CSS sınıfı için: is-style-underlined)
                'label' => __( 'Underlined', 'catlaq-ai' ), // Düzenleyicide görünecek etiket
            )
        );
    }
}
add_action( 'init', 'catlaq_ai_theme_register_block_styles' );

/**
 * Allow SVG uploads for administrators.
 *
 * @param array $mime_types Allowed mime types.
 * @return array Modified mime types.
 */
function catlaq_ai_theme_add_svg_mime_type( $mime_types ) {
    if ( current_user_can( 'manage_options' ) ) {
        $mime_types['svg'] = 'image/svg+xml';
    }
    return $mime_types;
}
add_filter( 'upload_mimes', 'catlaq_ai_theme_add_svg_mime_type' );

/**
 * Modify the default excerpt length.
 *
 * @param int $length Default excerpt length.
 * @return int New excerpt length.
 */
function catlaq_ai_theme_custom_excerpt_length( $length ) {
    // Change the number below to your desired word count.
    return 25;
}
add_filter( 'excerpt_length', 'catlaq_ai_theme_custom_excerpt_length', 999 );

/**
 * Ensure Catlaq pages and menus exist so shortcode blocks render immediately.
 */
function catlaq_ai_theme_ensure_structure(): void {
	if ( 'yes' === get_option( 'catlaq_ai_structure_ready' ) ) {
		return;
	}

	if ( ! function_exists( 'wp_insert_post' ) ) {
		return;
	}

	$pages = array(
		'catlaq-dashboard' => array(
			'title'    => __( 'Catlaq Dashboard', 'catlaq-ai' ),
			'content'  => "[catlaq_membership_overview]\n\n[catlaq_membership_plans]\n\n[catlaq_policy type=\"privacy\"]",
			'template' => 'page-catlaq-dashboard.php',
		),
		'digital-expo'      => array(
			'title'   => __( 'Digital Expo Showcase', 'catlaq-ai' ),
			'content' => '[catlaq_digital_expo_showcase]',
		),
		'expo-booths'       => array(
			'title'   => __( 'Expo Booth Directory', 'catlaq-ai' ),
			'content' => '[catlaq_expo_booths]',
		),
		'engagement-hub'    => array(
			'title'   => __( 'Engagement Hub', 'catlaq-ai' ),
			'content' => '[catlaq_engagement_feed]',
		),
		'membership-plans'  => array(
			'title'   => __( 'Membership Plans', 'catlaq-ai' ),
			'content' => '[catlaq_membership_plans]',
		),
		'catlaq-access'     => array(
			'title'   => __( 'Expo Access Portal', 'catlaq-ai' ),
			'content' => '[catlaq_auth_portal show_login="true" show_register="true"]',
		),
		'privacy-policy'    => array(
			'title'   => __( 'Privacy Policy', 'catlaq-ai' ),
			'content' => '[catlaq_policy type="privacy"]',
		),
		'terms-of-service'  => array(
			'title'   => __( 'Terms of Service', 'catlaq-ai' ),
			'content' => '[catlaq_policy type="terms"]',
		),
		'refund-policy'     => array(
			'title'   => __( 'Refund Policy', 'catlaq-ai' ),
			'content' => '[catlaq_policy type="refund"]',
		),
	);

	$created_ids = array();

	foreach ( $pages as $slug => $config ) {
		$existing = get_page_by_path( $slug );

		if ( $existing ) {
			$created_ids[ $slug ] = $existing->ID;
			if ( ! empty( $config['template'] ) ) {
				update_post_meta( $existing->ID, '_wp_page_template', $config['template'] );
			}
			continue;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $config['title'],
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $config['content'],
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$created_ids[ $slug ] = $page_id;
			if ( ! empty( $config['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $config['template'] );
			}
		}
	}

	catlaq_ai_theme_assign_primary_menu( $created_ids );
	update_option( 'catlaq_ai_structure_ready', 'yes' );
}

/**
 * Assign primary navigation with the essential Catlaq pages.
 *
 * @param array $page_ids Map of slug => page ID.
 */
function catlaq_ai_theme_assign_primary_menu( array $page_ids ): void {
	$menu_name = 'Catlaq Primary';
	$menu      = wp_get_nav_menu_object( $menu_name );

	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );
	} else {
		$menu_id = $menu->term_id;
	}

	if ( is_wp_error( $menu_id ) || ! $menu_id ) {
		return;
	}

	$menu_items = array(
		'catlaq-dashboard',
		'digital-expo',
		'expo-booths',
		'engagement-hub',
		'membership-plans',
		'catlaq-access',
		'privacy-policy',
		'terms-of-service',
		'refund-policy',
	);

	$existing_items = wp_get_nav_menu_items( $menu_id );
	if ( ! empty( $existing_items ) ) {
		foreach ( $existing_items as $item ) {
			wp_delete_nav_menu_item( $menu_id, $item->ID );
		}
	}

	foreach ( $menu_items as $slug ) {
		if ( empty( $page_ids[ $slug ] ) ) {
			continue;
		}

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => get_the_title( $page_ids[ $slug ] ),
				'menu-item-object' => 'page',
				'menu-item-object-id' => $page_ids[ $slug ],
				'menu-item-type'   => 'post_type',
				'menu-item-status' => 'publish',
			)
		);
	}

	$locations              = get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary']   = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Secondary safety net in case the site was already using the theme before this upgrade.
 */
function catlaq_ai_theme_run_structure_on_init(): void {
	if ( 'yes' !== get_option( 'catlaq_ai_structure_ready' ) ) {
		catlaq_ai_theme_ensure_structure();
	}
}
add_action( 'init', 'catlaq_ai_theme_run_structure_on_init' );
