<?php
/**
 * Plugin Name: Catlaq Online Expo
 * Description: B2B Digital Expo platform with AI-managed agreements and logistics workflows.
 * Version: 0.2.0
 * Requires PHP: 8.1
 * Requires at least: 6.6
 * Author: Catlaq Platform
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CATLAQ_PLUGIN_FILE', __FILE__ );
define( 'CATLAQ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CATLAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CATLAQ_PLUGIN_VERSION', '0.2.0' );

if ( ! function_exists( 'add_action' ) ) {
    $catlaq_stub = CATLAQ_PLUGIN_PATH . 'dev/wp-stubs.php';
    if ( file_exists( $catlaq_stub ) ) {
        require_once $catlaq_stub;
    }
}

require_once CATLAQ_PLUGIN_PATH . 'includes/class-autoloader.php';
\Catlaq\Expo\Autoloader::register();

// Helpers expose global functions, so we require them manually.
require_once CATLAQ_PLUGIN_PATH . 'includes/helpers/template-loader.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/helpers/capability-map.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/helpers/theme-bridge.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-settings.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-onboarding.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-activator.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-deactivator.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-cli.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-reminder-service.php';
require_once CATLAQ_PLUGIN_PATH . 'includes/class-shortcodes.php';

if ( ! function_exists( 'catlaq_online_expo' ) ) {
    /**
     * Retrieve the main plugin instance.
     */
    function catlaq_online_expo() {
        static $plugin = null;
        if ( null === $plugin ) {
            $plugin = new \Catlaq\Expo\Plugin();
        }
        return $plugin;
    }
}

catlaq_online_expo()->boot();
\Catlaq\Expo\Shortcodes::register();

register_activation_hook( CATLAQ_PLUGIN_FILE, [ \Catlaq\Expo\Activator::class, 'activate' ] );
register_deactivation_hook( CATLAQ_PLUGIN_FILE, [ \Catlaq\Expo\Deactivator::class, 'deactivate' ] );
\Catlaq\Expo\CLI::register();
\Catlaq\Expo\Reminder_Service::boot();
