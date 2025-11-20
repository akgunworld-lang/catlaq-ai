<?php
namespace Catlaq\Expo\Modules\Payments;

class WorldFirst_Gateway implements Gateway_Interface {
    private array $settings;

    public function __construct( array $settings = [] ) {
        $this->settings = $settings;
    }

    public function hold( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->simulate( 'held', $order['id'] ?? 0, $amount, $currency );
    }

    public function release( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->simulate( 'released', $order['id'] ?? 0, $amount, $currency );
    }

    public function refund( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->simulate( 'refunded', $order['id'] ?? 0, $amount, $currency );
    }

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array {
        $response = $this->simulate( 'requires_action', $invoice['id'] ?? 0, $amount, $currency );

        $plan_slug = sanitize_key( $invoice['plan_slug'] ?? 'membership' );
        $response['checkout_url'] = sprintf(
            'https://online.worldfirst.com/pay/%s/%s',
            $plan_slug ?: 'membership',
            strtolower( $response['provider_ref'] )
        );

        $response['partner_id'] = $this->settings['worldfirst_partner_id'] ?? '';

        return $response;
    }

    public function parse_webhook_payload( string $body, string $signature, array $settings ) {
        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'catlaq_payment_worldfirst_payload', __( 'Invalid WorldFirst payload.', 'catlaq-online-expo' ) );
        }

        $secret = $settings['payment_webhook_secret'] ?? '';
        if ( $secret ) {
            $expected = hash_hmac( 'sha256', $body, $secret );
            if ( ! hash_equals( $expected, $signature ?: '' ) ) {
                return new \WP_Error( 'catlaq_payment_worldfirst_signature', __( 'WorldFirst signature mismatch.', 'catlaq-online-expo' ) );
            }
        }

        return [
            'provider_ref' => sanitize_text_field( $payload['reference'] ?? '' ),
            'status'       => sanitize_key( $payload['status'] ?? 'pending' ),
            'payload'      => $payload,
        ];
    }

    public function get_name(): string {
        return 'worldfirst';
    }

    private function simulate( string $status, int $entity_id, float $amount, string $currency ): array {
        $ref = sprintf( 'WF-%d-%s', $entity_id, strtoupper( substr( wp_hash( microtime() ), 0, 8 ) ) );

        return [
            'status'       => $status,
            'provider_ref' => $ref,
            'amount'       => round( $amount, 2 ),
            'currency'     => strtoupper( $currency ),
        ];
    }
}

