<?php
namespace Catlaq\Expo;

class Settings {
    const OPTION_KEY = 'catlaq_settings';

    public static function get(): array {
        $defaults = [
            'environment'            => 'development',
            'ai_provider'            => 'local',
            'escrow_api_key'         => '',
            'payment_provider'       => 'mock',
            'payment_webhook_secret' => '',
            'worldfirst_partner_id'  => '',
            'worldfirst_api_key'     => '',
        ];

        $stored = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $stored, $defaults );
    }

    public static function update( array $data ): void {
        update_option(
            self::OPTION_KEY,
            array(
                'environment'            => sanitize_text_field( $data['environment'] ?? 'development' ),
                'ai_provider'            => sanitize_text_field( $data['ai_provider'] ?? 'local' ),
                'escrow_api_key'         => sanitize_text_field( $data['escrow_api_key'] ?? '' ),
                'payment_provider'       => sanitize_text_field( $data['payment_provider'] ?? 'mock' ),
                'payment_webhook_secret' => sanitize_text_field( $data['payment_webhook_secret'] ?? '' ),
                'worldfirst_partner_id'  => sanitize_text_field( $data['worldfirst_partner_id'] ?? '' ),
                'worldfirst_api_key'     => sanitize_text_field( $data['worldfirst_api_key'] ?? '' ),
            )
        );
    }
}
