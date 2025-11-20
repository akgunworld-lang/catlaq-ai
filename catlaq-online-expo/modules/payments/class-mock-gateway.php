<?php
namespace Catlaq\Expo\Modules\Payments;

class Mock_Gateway implements Gateway_Interface {
    public function hold( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'held', $order['id'] ?? 0, $amount, $currency );
    }

    public function release( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'released', $order['id'] ?? 0, $amount, $currency );
    }

    public function refund( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->response( 'refunded', $order['id'] ?? 0, $amount, $currency );
    }

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array {
        $response = $this->response( 'requires_action', $invoice['id'] ?? 0, $amount, $currency );
        $response['checkout_url'] = add_query_arg(
            [
                'mock-membership' => $response['provider_ref'],
            ],
            home_url( '/' )
        );
        return $response;
    }

    public function parse_webhook_payload( string $body, string $signature, array $settings ) {
        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'catlaq_payment_mock_payload', __( 'Invalid mock payload', 'catlaq-online-expo' ) );
        }

        return [
            'provider_ref' => sanitize_text_field( $payload['provider_ref'] ?? '' ),
            'status'       => sanitize_key( $payload['status'] ?? 'pending' ),
            'payload'      => $payload,
        ];
    }

    public function get_name(): string {
        return 'mock';
    }

    private function response( string $status, int $order_id, float $amount, string $currency ): array {
        return [
            'status'       => $status,
            'provider_ref' => sprintf( 'MOCK-%d-%s', $order_id, strtoupper( substr( wp_hash( microtime() ), 0, 8 ) ) ),
            'amount'       => round( $amount, 2 ),
            'currency'     => strtoupper( $currency ),
        ];
    }
}
