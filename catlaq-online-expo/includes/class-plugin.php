<?php
namespace Catlaq\Expo;

use Catlaq\Expo\Modules\Agreements\Room_Model;
use Catlaq\Expo\Modules\AI\AI_Service;
use Catlaq\Expo\Modules\Logistics\Logistics_Controller;
use Catlaq\Expo\Modules\Digital_Expo\RFQ_Controller;
use Catlaq\Expo\Modules\Payments\Payment_Service;
use Catlaq\Expo\Modules\Engagement\Engagement_Controller;
use Catlaq\Expo\REST\Rest_Controller;

class Plugin {
    /**
     * REST controller instance.
     *
     * @var Rest_Controller|null
     */
    private $rest_controller;

    /**
     * Module registrar.
     *
     * @var Module_Registrar
     */
    private $registrar;

    /**
     * Admin handler.
     *
     * @var Admin
     */
    private $admin;

    /**
     * Boot plugin lifecycle.
     */
    public function boot(): void {
        $this->registrar = new Module_Registrar();
        $this->admin     = new Admin();
        $this->hooks();
    }

    /**
     * Register WordPress hooks.
     */
    private function hooks(): void {
        add_action( 'plugins_loaded', [ $this, 'init_modules' ] );
        add_action( 'init', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_assets' ] );
        add_action( 'admin_menu', [ $this->admin, 'register_menus' ] );
        add_action( 'catlaq_reminder_cron', [ $this, 'send_agreement_reminders' ] );
        add_filter( 'cron_schedules', [ $this, 'register_cron_schedule' ] );
        if ( ! wp_next_scheduled( 'catlaq_reminder_cron' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'catlaq_hourly', 'catlaq_reminder_cron' );
        }
    }

    /**
     * Initialize modules and REST endpoints.
     */
    public function init_modules(): void {
        $this->register_modules();

        $this->registrar->boot_all();

        $this->rest_controller = new Rest_Controller();
        $this->rest_controller->register();
    }

    /**
     * Register core modules with the registrar.
     */
    private function register_modules(): void {
        $this->registrar->register( 'memberships', fn() => new Memberships() );
        $this->registrar->register( 'ai', fn() => new AI_Service() );
        $this->registrar->register( 'engagement', fn() => new Engagement_Controller() );
        $this->registrar->register( 'digital_expo', fn() => new RFQ_Controller() );
        $this->registrar->register( 'agreements', fn() => new Room_Model() );
        $this->registrar->register( 'logistics', fn() => new Logistics_Controller() );
        $this->registrar->register( 'payments', fn() => new Payment_Service() );
    }

    /**
     * Register styles/scripts so they can be enqueued later.
     */
    public function register_assets(): void {
        wp_register_style(
            'catlaq-admin',
            CATLAQ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CATLAQ_PLUGIN_VERSION
        );

        wp_register_style(
            'catlaq-frontend',
            CATLAQ_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CATLAQ_PLUGIN_VERSION
        );

        wp_register_script(
            'catlaq-admin',
            CATLAQ_PLUGIN_URL . 'assets/js/admin.js',
            [ 'wp-element' ],
            CATLAQ_PLUGIN_VERSION,
            true
        );

        wp_register_script(
            'catlaq-chatbot',
            CATLAQ_PLUGIN_URL . 'assets/js/chatbot.js',
            [ 'wp-element' ],
            CATLAQ_PLUGIN_VERSION,
            true
        );

        wp_register_script(
            'catlaq-agreements',
            CATLAQ_PLUGIN_URL . 'assets/js/agreements-room.js',
            [ 'wp-element' ],
            CATLAQ_PLUGIN_VERSION,
            true
        );

        wp_register_script(
            'catlaq-frontend-extra',
            CATLAQ_PLUGIN_URL . 'assets/js/frontend-extra.js',
            [ 'wp-element' ],
            CATLAQ_PLUGIN_VERSION,
            true
        );

        wp_register_script(
            'catlaq-engagement-feed',
            CATLAQ_PLUGIN_URL . 'assets/js/engagement-feed.js',
            [],
            CATLAQ_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Enqueue admin assets when Catlaq screens load.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( false === strpos( $hook, 'catlaq' ) ) {
            return;
        }

        wp_enqueue_style( 'catlaq-admin' );
        wp_enqueue_script( 'catlaq-admin' );

        if ( str_contains( $hook, 'catlaq-onboarding' ) ) {
            $config = $this->get_rest_config();
            wp_enqueue_script(
                'catlaq-onboarding',
                CATLAQ_PLUGIN_URL . 'assets/js/onboarding.js',
                [ 'wp-element', 'wp-api-fetch' ],
                CATLAQ_PLUGIN_VERSION,
                true
            );
            wp_localize_script( 'catlaq-onboarding', 'catlaqREST', $config );
        }
    }

    /**
     * Enqueue frontend assets for shortcodes.
     */
    public function enqueue_front_assets(): void {
        if ( ! is_singular() ) {
            return;
        }

        wp_enqueue_style( 'catlaq-frontend' );
        wp_enqueue_script( 'catlaq-chatbot' );
        wp_enqueue_script( 'catlaq-agreements' );
        wp_enqueue_script( 'catlaq-frontend-extra' );

        $config = $this->get_rest_config();
        wp_localize_script( 'catlaq-chatbot', 'catlaqREST', $config );
        wp_localize_script( 'catlaq-frontend-extra', 'catlaqREST', $config );
    }

    public function send_agreement_reminders(): void {
        do_action( 'catlaq_send_agreement_reminders' );
    }

    public function register_cron_schedule( array $schedules ): array {
        $schedules['catlaq_hourly'] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => __( 'Every Hour (Catlaq)', 'catlaq-online-expo' ),
        ];

        return $schedules;
    }

    private function get_rest_config(): array {
        return [
            'root'  => untrailingslashit( rest_url( 'catlaq/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ];
    }
}
