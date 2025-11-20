<?php
namespace Catlaq\Expo;

use Catlaq\Expo\AI_Kernel;
use Catlaq\Expo\AI\Logger as AI_Logger;
use Catlaq\Expo\AI\Manifest as AI_Manifest;
use Catlaq\Expo\AI\Coach;
use Catlaq\Expo\Memberships;
use Catlaq\Expo\Modules\Agreements\Document_Factory;
use Catlaq\Expo\Modules\Agreements\Document_Service;
use Catlaq\Expo\Modules\Digital_Expo\Product_Catalog;
use function Catlaq\Expo\Helpers\render;

/**
 * Handles WordPress admin menus and pages.
 */
class Admin {
    private ?Product_Catalog $catalog = null;
    private ?Document_Service $documents = null;

    private const PRODUCT_SETTINGS_SLUG  = 'catlaq_product_catalog';
    private const DOCUMENT_SETTINGS_SLUG = 'catlaq_documents_portal';

    /**
     * Register Catlaq menu pages.
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'Catlaq Dashboard', 'catlaq-online-expo' ),
            __( 'Catlaq', 'catlaq-online-expo' ),
            'manage_options',
            'catlaq-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-admin-site',
            3
        );

        add_submenu_page(
            'catlaq-dashboard',
            __( 'Catlaq Settings', 'catlaq-online-expo' ),
            __( 'Settings', 'catlaq-online-expo' ),
            'manage_options',
            'catlaq-settings',
            [ $this, 'render_settings' ]
        );

        add_submenu_page(
            'catlaq-dashboard',
            __( 'Onboarding Wizard', 'catlaq-online-expo' ),
            __( 'Onboarding', 'catlaq-online-expo' ),
            'read',
            'catlaq-onboarding',
            [ $this, 'render_onboarding' ]
        );

        add_submenu_page(
            'catlaq-dashboard',
            __( 'Digital Expo Booths', 'catlaq-online-expo' ),
            __( 'Digital Expo Booths', 'catlaq-online-expo' ),
            'manage_options',
            'catlaq-product-catalog',
            [ $this, 'render_product_catalog' ]
        );

        add_submenu_page(
            'catlaq-dashboard',
            __( 'Documents', 'catlaq-online-expo' ),
            __( 'Documents', 'catlaq-online-expo' ),
            'edit_pages',
            'catlaq-documents',
            [ $this, 'render_documents' ]
        );

        add_submenu_page(
            'catlaq-dashboard',
            __( 'AI Control Center', 'catlaq-online-expo' ),
            __( 'AI Control', 'catlaq-online-expo' ),
            'manage_options',
            'catlaq-ai-center',
            [ $this, 'render_ai_center' ]
        );
    }

    public function render_dashboard(): void {
        render( 'admin/dashboard' );
    }

    public function render_settings(): void {
        $settings = Settings::get();
        if ( isset( $_POST['catlaq_settings_nonce'] ) && wp_verify_nonce( $_POST['catlaq_settings_nonce'], 'catlaq_save_settings' ) ) {
            $data = [
                'environment'            => sanitize_text_field( wp_unslash( $_POST['environment'] ?? '' ) ),
                'ai_provider'            => sanitize_text_field( wp_unslash( $_POST['ai_provider'] ?? '' ) ),
                'escrow_api_key'         => sanitize_text_field( wp_unslash( $_POST['escrow_api_key'] ?? '' ) ),
                'payment_provider'       => sanitize_text_field( wp_unslash( $_POST['payment_provider'] ?? 'mock' ) ),
                'payment_webhook_secret' => sanitize_text_field( wp_unslash( $_POST['payment_webhook_secret'] ?? '' ) ),
            ];
            Settings::update( $data );
            $settings = Settings::get();
            add_settings_error( 'catlaq_settings', 'catlaq_settings_saved', __( 'Settings saved.', 'catlaq-online-expo' ), 'updated' );
        }

        settings_errors( 'catlaq_settings' );
        render( 'admin/settings', [ 'settings' => $settings ] );
    }

    public function render_onboarding(): void {
        $user_id = get_current_user_id();
        $step    = \Catlaq\Expo\Onboarding::current_step( $user_id );

        if ( isset( $_POST['catlaq_onboarding_nonce'] ) && wp_verify_nonce( $_POST['catlaq_onboarding_nonce'], 'catlaq_onboarding' ) ) {
            if ( isset( $_POST['advance_step'] ) ) {
                \Catlaq\Expo\Onboarding::advance( $user_id );
            } elseif ( isset( $_POST['reset_step'] ) ) {
                \Catlaq\Expo\Onboarding::reset( $user_id );
            }
            $step = \Catlaq\Expo\Onboarding::current_step( $user_id );
        }

        render(
            'admin/onboarding',
            [
                'step' => $step,
            ]
        );
    }

    public function render_product_catalog(): void {
        $catalog = $this->get_catalog();

        if ( isset( $_POST['catlaq_product_catalog_nonce'] ) && wp_verify_nonce( $_POST['catlaq_product_catalog_nonce'], 'catlaq_product_catalog' ) ) {
            $product    = $this->parse_product_payload();
            $variants   = $this->parse_variant_payload();
            $price_tier = $this->parse_price_tier_payload();

            $result = $catalog->create_product( $product, $variants, $price_tier );

            if ( is_wp_error( $result ) ) {
                add_settings_error( self::PRODUCT_SETTINGS_SLUG, $result->get_error_code(), $result->get_error_message(), 'error' );
            } else {
                add_settings_error( self::PRODUCT_SETTINGS_SLUG, 'catlaq_product_saved', __( 'Product saved to catalog.', 'catlaq-online-expo' ), 'updated' );
                $_POST = [];
            }
        }

        settings_errors( self::PRODUCT_SETTINGS_SLUG );

        render(
            'admin/product-catalog',
            [
                'products'  => $catalog->list_products(),
                'companies' => $catalog->companies_for_select(),
            ]
        );
    }

    private function get_catalog(): Product_Catalog {
        if ( null === $this->catalog ) {
            $this->catalog = new Product_Catalog();
        }

        return $this->catalog;
    }

    private function parse_product_payload(): array {
        return [
            'company_id'    => absint( $_POST['company_id'] ?? 0 ),
            'name'          => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
            'sku'           => sanitize_text_field( wp_unslash( $_POST['product_sku'] ?? '' ) ),
            'unit'          => sanitize_text_field( wp_unslash( $_POST['product_unit'] ?? '' ) ),
            'min_order_qty' => floatval( wp_unslash( $_POST['product_moq'] ?? 0 ) ),
            'lead_time_days'=> absint( wp_unslash( $_POST['product_lead_time'] ?? 0 ) ),
            'base_price'    => floatval( wp_unslash( $_POST['product_base_price'] ?? 0 ) ),
            'currency'      => sanitize_text_field( wp_unslash( $_POST['product_currency'] ?? 'USD' ) ),
            'status'        => sanitize_text_field( wp_unslash( $_POST['product_status'] ?? 'draft' ) ),
            'visibility'    => sanitize_text_field( wp_unslash( $_POST['product_visibility'] ?? 'private' ) ),
            'highlights'    => wp_kses_post( wp_unslash( $_POST['product_highlights'] ?? '' ) ),
            'notes'         => wp_kses_post( wp_unslash( $_POST['product_notes'] ?? '' ) ),
            'wp_post_id'    => absint( $_POST['linked_wp_post'] ?? 0 ),
            'create_wp_post'=> ! empty( $_POST['create_wp_post'] ),
        ];
    }

    private function parse_variant_payload(): array {
        $labels = $_POST['variant_label'] ?? [];
        if ( ! is_array( $labels ) ) {
            return [];
        }

        $variants = [];
        foreach ( $labels as $index => $label ) {
            $label = sanitize_text_field( wp_unslash( $label ) );
            if ( '' === $label ) {
                continue;
            }

            $raw_attributes = wp_unslash( $_POST['variant_attributes'][ $index ] ?? '' );
            $decoded        = json_decode( $raw_attributes, true );
            if ( ! is_array( $decoded ) ) {
                $decoded = $raw_attributes ? [ 'notes' => sanitize_text_field( $raw_attributes ) ] : [];
            }

            $variants[] = [
                'label'       => $label,
                'sku'         => sanitize_text_field( wp_unslash( $_POST['variant_sku'][ $index ] ?? '' ) ),
                'attributes'  => $decoded,
                'stock_qty'   => floatval( wp_unslash( $_POST['variant_stock_qty'][ $index ] ?? 0 ) ),
                'stock_unit'  => sanitize_text_field( wp_unslash( $_POST['variant_stock_unit'][ $index ] ?? '' ) ),
                'unit_price'  => floatval( wp_unslash( $_POST['variant_unit_price'][ $index ] ?? 0 ) ),
                'currency'    => sanitize_text_field( wp_unslash( $_POST['variant_currency'][ $index ] ?? 'USD' ) ),
                'weight_kg'   => floatval( wp_unslash( $_POST['variant_weight'][ $index ] ?? 0 ) ),
                'volume_cbm'  => floatval( wp_unslash( $_POST['variant_volume'][ $index ] ?? 0 ) ),
                'status'      => sanitize_text_field( wp_unslash( $_POST['variant_status'][ $index ] ?? 'active' ) ),
            ];
        }

        return $variants;
    }

    private function parse_price_tier_payload(): array {
        $labels = $_POST['tier_label'] ?? [];
        if ( ! is_array( $labels ) ) {
            return [];
        }

        $tiers = [];
        foreach ( $labels as $index => $label ) {
            $label = sanitize_text_field( wp_unslash( $label ) );
            $price = floatval( wp_unslash( $_POST['tier_unit_price'][ $index ] ?? 0 ) );
            if ( $price <= 0 ) {
                continue;
            }

            $tiers[] = [
                'variant_label' => $label,
                'min_qty'       => floatval( wp_unslash( $_POST['tier_min_qty'][ $index ] ?? 0 ) ),
                'max_qty'       => floatval( wp_unslash( $_POST['tier_max_qty'][ $index ] ?? 0 ) ),
                'unit_price'    => $price,
                'currency'      => sanitize_text_field( wp_unslash( $_POST['tier_currency'][ $index ] ?? 'USD' ) ),
                'notes'         => sanitize_text_field( wp_unslash( $_POST['tier_notes'][ $index ] ?? '' ) ),
            ];
        }

        return $tiers;
    }

    public function render_documents(): void {
        $service = $this->get_document_service();

        if ( isset( $_POST['catlaq_documents_nonce'] ) && wp_verify_nonce( $_POST['catlaq_documents_nonce'], 'catlaq_documents_portal' ) ) {
            $room_id      = absint( $_POST['room_id'] ?? 0 );
            $template_key = sanitize_key( $_POST['template_key'] ?? '' );
            $context_raw  = wp_unslash( $_POST['document_context'] ?? '' );
            $context      = [];

            if ( '' !== $context_raw ) {
                $decoded = json_decode( $context_raw, true );
                if ( is_array( $decoded ) ) {
                    $context = $decoded;
                } else {
                    add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_documents_context', __( 'Context must be valid JSON.', 'catlaq-online-expo' ), 'error' );
                }
            }

            if ( $room_id && $template_key ) {
                $result = $service->generate( $room_id, $template_key, $context );
                if ( is_wp_error( $result ) ) {
                    add_settings_error( self::DOCUMENT_SETTINGS_SLUG, $result->get_error_code(), $result->get_error_message(), 'error' );
                } else {
                    add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_document_generated', __( 'Document generated.', 'catlaq-online-expo' ), 'updated' );
                    $_POST = [];
                }
            } else {
                add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_documents_required', __( 'Room ID and template are required.', 'catlaq-online-expo' ), 'error' );
            }
        }

        if ( isset( $_POST['catlaq_documents_signature_nonce'] ) && wp_verify_nonce( $_POST['catlaq_documents_signature_nonce'], 'catlaq_documents_signature' ) ) {
            $document_id = absint( $_POST['signature_document_id'] ?? 0 );
            if ( $document_id ) {
                $payload = [
                    'signer_id'    => absint( $_POST['signature_user_id'] ?? 0 ),
                    'signer_email' => sanitize_email( wp_unslash( $_POST['signature_email'] ?? '' ) ),
                    'role'         => sanitize_key( $_POST['signature_role'] ?? 'participant' ),
                ];
                $result = $service->request_signature( $document_id, $payload );
                if ( $result ) {
                    add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_signature_requested', __( 'Signature requested.', 'catlaq-online-expo' ), 'updated' );
                } else {
                    add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_signature_invalid', __( 'Signer information is required.', 'catlaq-online-expo' ), 'error' );
                }
            }
        }

        if ( isset( $_POST['catlaq_documents_signature_complete_nonce'] ) && wp_verify_nonce( $_POST['catlaq_documents_signature_complete_nonce'], 'catlaq_documents_signature_complete' ) ) {
            $signature_id = absint( $_POST['complete_signature_id'] ?? 0 );
            if ( $signature_id ) {
                $service->complete_signature(
                    $signature_id,
                    [
                        'status'    => sanitize_key( $_POST['complete_signature_status'] ?? 'signed' ),
                        'metadata'  => [
                            'note' => sanitize_text_field( wp_unslash( $_POST['complete_signature_note'] ?? '' ) ),
                        ],
                        'signer_id' => get_current_user_id(),
                    ]
                );
                add_settings_error( self::DOCUMENT_SETTINGS_SLUG, 'catlaq_signature_completed', __( 'Signature updated.', 'catlaq-online-expo' ), 'updated' );
            }
        }

        $templates  = $service->templates_for_user();
        $recent     = $service->recent_documents();
        $signatures = $service->recent_signatures();

        settings_errors( self::DOCUMENT_SETTINGS_SLUG );

        render(
            'admin/documents',
            [
                'templates'   => $templates,
                'recent'      => $recent,
                'signatures'  => $signatures,
                'json_value'  => isset( $_POST['document_context'] ) ? wp_unslash( $_POST['document_context'] ) : '',
            ]
        );
    }

    public function render_ai_center(): void {
        $model_path = get_option( 'catlaq_ai_model_path', '' );
        $model_hash = get_option( 'catlaq_ai_model_hash', '' );
        $secret     = get_option( 'catlaq_ai_secret', '' );
        $provider   = get_option( 'catlaq_ai_provider', 'local_http' );
        $endpoint   = get_option( 'catlaq_ai_endpoint', 'http://127.0.0.1:11434/api/generate' );
        $model_name = get_option( 'catlaq_ai_model_name', 'mistral' );
        $message    = '';
        $membership_plan    = null;
        $membership_quotas  = [];
        $coach_question     = '';
        $coach_output       = '';
        $coach_error        = '';
        $bot_question       = '';
        $bot_output         = '';
        $bot_error          = '';

        if (
            isset( $_POST['catlaq_ai_model_nonce'] )
            && wp_verify_nonce( $_POST['catlaq_ai_model_nonce'], 'catlaq_ai_save_model' )
        ) {
            $model_path = sanitize_text_field( wp_unslash( $_POST['catlaq_ai_model_path'] ?? '' ) );
            $model_hash = sanitize_text_field( wp_unslash( $_POST['catlaq_ai_model_hash'] ?? '' ) );
            $provider   = sanitize_key( $_POST['catlaq_ai_provider'] ?? 'local_http' );
            $endpoint   = esc_url_raw( wp_unslash( $_POST['catlaq_ai_endpoint'] ?? '' ) );
            $model_name = sanitize_text_field( wp_unslash( $_POST['catlaq_ai_model_name'] ?? '' ) );
            update_option( 'catlaq_ai_model_path', $model_path );
            update_option( 'catlaq_ai_model_hash', $model_hash );
            update_option( 'catlaq_ai_provider', $provider );
            update_option( 'catlaq_ai_endpoint', $endpoint );
            update_option( 'catlaq_ai_model_name', $model_name );
            $message = __( 'AI model configuration saved.', 'catlaq-online-expo' );
        }

        global $wpdb;
        $table_name = "{$wpdb->prefix}catlaq_ai_logs";
        $logs       = [];
        $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( $exists === $table_name ) {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 10",
                ARRAY_A
            );
            foreach ( (array) $rows as $row ) {
                $logs[] = AI_Logger::decrypt_record( $row );
            }
        }

        $membership_service = new Memberships();
        $plan               = $membership_service->user_membership( get_current_user_id() );
        if ( $plan ) {
            $membership_plan = $plan;
            $quota_defs      = (array) ( $plan['quotas'] ?? [] );
            foreach ( $quota_defs as $metric => $limit ) {
                $membership_quotas[] = $membership_service->quota_status( get_current_user_id(), $metric, (int) $limit );
            }
        }

        if (
            isset( $_POST['catlaq_ai_membership_nonce'] )
            && wp_verify_nonce( $_POST['catlaq_ai_membership_nonce'], 'catlaq_ai_membership_coach' )
        ) {
            $coach_question = sanitize_textarea_field( wp_unslash( $_POST['catlaq_ai_membership_question'] ?? '' ) );
            if ( ! $membership_plan ) {
                $coach_error = __( 'Assign a membership plan before contacting the coach.', 'catlaq-online-expo' );
            } else {
                $prompt = Coach::membership_prompt( $membership_plan, $membership_quotas, $coach_question );
                $result = AI_Kernel::instance()->generate(
                    [
                        'prompt'      => $prompt,
                        'system'      => __( 'You are Catlaq Membership Coach. Provide concise, actionable guidance for B2B members. Mention relevant quotas and promote fairness. Never promise Catlaq commissions.', 'catlaq-online-expo' ),
                        'max_tokens'  => 400,
                        'temperature' => 0.3,
                    ]
                );
                if ( is_wp_error( $result ) ) {
                    $coach_error = $result->get_error_message();
                } else {
                    $coach_output = (string) ( $result['output'] ?? '' );
                    AI_Logger::log(
                        get_current_user_id(),
                        'membership_coach_manual',
                        'admin_ai_center',
                        [
                            'question' => $coach_question,
                            'response' => $coach_output,
                        ]
                    );
                }
            }
        }

        if (
            isset( $_POST['catlaq_ai_admin_bot_nonce'] )
            && wp_verify_nonce( $_POST['catlaq_ai_admin_bot_nonce'], 'catlaq_ai_admin_bot' )
        ) {
            $bot_question = sanitize_textarea_field( wp_unslash( $_POST['catlaq_ai_admin_bot_question'] ?? '' ) );
            if ( '' === $bot_question ) {
                $bot_error = __( 'Describe what you need help with.', 'catlaq-online-expo' );
            } else {
                $prompt   = Coach::admin_prompt( $bot_question, $status_data );
                $response = AI_Kernel::instance()->generate(
                    [
                        'prompt'      => $prompt,
                        'system'      => __( 'You are Catlaq Admin AI. Provide concise operational guidance about the Catlaq platform. Reference policies (no commissions, fairness) and list next actions. Respond in the admin’s language.', 'catlaq-online-expo' ),
                        'max_tokens'  => 400,
                        'temperature' => 0.4,
                    ]
                );

                if ( is_wp_error( $response ) ) {
                    $bot_error = $response->get_error_message();
                } else {
                    $bot_output = (string) ( $response['output'] ?? '' );
                    AI_Logger::log(
                        get_current_user_id(),
                        'admin_chatbot_manual',
                        'admin_ai_center',
                        [
                            'question' => $bot_question,
                            'response' => $bot_output,
                        ]
                    );
                }
            }
        }

        render(
            'admin/ai-center',
            [
                'manifest'    => AI_Manifest::get(),
                'model_path'  => $model_path,
                'model_hash'  => $model_hash,
                'secret_hash' => $secret ? substr( sha1( $secret ), 0, 12 ) : '',
                'logs'        => $logs,
                'message'     => $message,
                'provider'    => $provider,
                'endpoint'    => $endpoint,
                'model_name'  => $model_name,
                'membership_plan'    => $membership_plan,
                'membership_quotas'  => $membership_quotas,
                'coach_question'     => $coach_question,
                'coach_output'       => $coach_output,
                'coach_error'        => $coach_error,
                'bot_question'       => $bot_question,
                'bot_output'         => $bot_output,
                'bot_error'          => $bot_error,
            ]
        );
    }

    private function get_document_service(): Document_Service {
        if ( null === $this->documents ) {
            $this->documents = new Document_Service();
        }

        return $this->documents;
    }

    private function build_membership_prompt( array $plan, array $quotas, string $question ): string {
        $lines   = [];
        $lines[] = sprintf( 'Plan: %s (%s)', $plan['label'] ?? '', $plan['slug'] ?? '' );
        if ( ! empty( $plan['features'] ) ) {
            $lines[] = 'Features:';
            foreach ( $plan['features'] as $key => $feature ) {
                $lines[] = sprintf( '- %s: %s', $key, is_string( $feature ) ? $feature : wp_json_encode( $feature ) );
            }
        }
        if ( ! empty( $quotas ) ) {
            $lines[] = 'Quotas:';
            foreach ( $quotas as $quota ) {
                $lines[] = sprintf(
                    '- %s used %d of %s',
                    $quota['metric'] ?? '',
                    (int) ( $quota['used'] ?? 0 ),
                    isset( $quota['limit'] ) ? (string) $quota['limit'] : 'unlimited'
                );
            }
        }
        if ( '' !== $question ) {
            $lines[] = 'User question/goal: ' . $question;
        }
        $lines[] = 'Provide advice aligned with Catlaq policies (no commissions, fairness, encourage upgrades if necessary).';

        return implode( "\n", $lines );
    }

    private function build_admin_bot_prompt( string $question ): string {
        $settings = Settings::get();
        $env      = $settings['environment'] ?? 'development';
        $provider = $settings['ai_provider'] ?? 'local';

        $status = rest_do_request( '/catlaq/v1/status' );
        $missing = array();
        if ( ! $status->is_error() ) {
            $data     = $status->get_data();
            $missing  = (array) ( $data['missing_tables'] ?? array() );
        }

        $lines = array(
            sprintf( 'Environment: %s | AI Provider: %s', $env, $provider ),
            sprintf( 'Missing tables: %s', empty( $missing ) ? 'none' : implode( ', ', $missing ) ),
            'Task or question: ' . $question,
            'Provide concrete next steps and remind Catlaq policies (no commissions, fairness, human oversight).',
        );

        return implode( "\n", $lines );
    }
}
