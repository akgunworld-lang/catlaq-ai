<?php
namespace Catlaq\Expo\Modules\Payments;

class Stripe_Gateway implements Gateway_Interface {
    public function hold( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->fake_api_response( 'held', $order, $amount, $currency );
    }

    public function release( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->fake_api_response( 'released', $order, $amount, $currency );
    }

    public function refund( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->fake_api_response( 'refunded', $order, $amount, $currency );
    }

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array {
        $response                = $this->fake_api_response( 'requires_action', $invoice, $amount, $currency );
        $response['checkout_url'] = sprintf( 'https://dashboard.stripe.com/test/checkout/sessions/%s', strtolower( $response['provider_ref'] ) );
        return $response;
    }

    public function parse_webhook_payload( string $body, string $signature, array $settings ) {
        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'catlaq_payment_stripe_payload', __( 'Invalid Stripe payload.', 'catlaq-online-expo' ) );
        }

        $secret = $settings['payment_webhook_secret'] ?? '';
        if ( $secret ) {
            $expected = hash_hmac( 'sha256', $body, $secret );
            if ( ! hash_equals( $expected, $signature ?: '' ) ) {
                return new \WP_Error( 'catlaq_payment_stripe_signature', __( 'Stripe signature mismatch.', 'catlaq-online-expo' ) );
            }
        }

        return [
            'provider_ref' => sanitize_text_field( $payload['data']['object']['id'] ?? '' ),
            'status'       => sanitize_key( $payload['data']['object']['status'] ?? 'pending' ),
            'payload'      => $payload,
        ];
    }

    public function get_name(): string {
        return 'stripe';
    }

    private function fake_api_response( string $status, array $order, float $amount, string $currency ): array {
        return [
            'status'       => $status,
            'provider_ref' => sprintf( 'STR-%d-%s', $order['id'] ?? 0, strtoupper( substr( wp_hash( microtime() ), 0, 8 ) ) ),
            'amount'       => round( $amount, 2 ),
            'currency'     => strtoupper( $currency ),
            'dashboard_url'=> 'https://dashboard.stripe.com/test/payments/' . ( $order['id'] ?? 0 ),
        ];
    }
}
