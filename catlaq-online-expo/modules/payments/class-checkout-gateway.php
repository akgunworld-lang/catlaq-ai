<?php
namespace Catlaq\Expo\Modules\Payments;

class Checkout_Gateway implements Gateway_Interface {
    public function hold( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'held', $order, $amount, $currency );
    }

    public function release( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'released', $order, $amount, $currency );
    }

    public function refund( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'refunded', $order, $amount, $currency );
    }

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array {
        $response                = $this->response( 'requires_action', $invoice, $amount, $currency );
        $response['checkout_url'] = 'https://portal.checkout.com/payments/' . strtolower( $response['provider_ref'] );
        return $response;
    }

    public function parse_webhook_payload( string $body, string $signature, array $settings ) {
        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'catlaq_payment_checkout_payload', __( 'Invalid Checkout.com payload.', 'catlaq-online-expo' ) );
        }

        $token = $settings['payment_webhook_secret'] ?? '';
        if ( $token && $token !== ( $payload['auth_token'] ?? '' ) ) {
            return new \WP_Error( 'catlaq_payment_checkout_signature', __( 'Checkout.com token mismatch.', 'catlaq-online-expo' ) );
        }

        return [
            'provider_ref' => sanitize_text_field( $payload['id'] ?? '' ),
            'status'       => sanitize_key( $payload['status'] ?? 'pending' ),
            'payload'      => $payload,
        ];
    }

    public function get_name(): string {
        return 'checkout';
    }

    private function response( string $status, array $order, float $amount, string $currency ): array {
        return [
            'status'       => $status,
            'provider_ref' => sprintf( 'CHK-%d-%s', $order['id'] ?? 0, strtoupper( substr( wp_hash( microtime() ), 0, 8 ) ) ),
            'amount'       => round( $amount, 2 ),
            'currency'     => strtoupper( $currency ),
        ];
    }
}
