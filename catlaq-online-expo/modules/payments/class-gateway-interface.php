<?php
namespace Catlaq\Expo\Modules\Payments;

interface Gateway_Interface {
    public function hold( array $order, float $amount, string $currency = 'USD' ): array;

    public function release( array $order, float $amount, string $currency = 'USD' ): array;

    public function refund( array $order, float $amount, string $currency = 'USD' ): array;

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array;

    /**
     * Normalize webhook payloads into provider_ref/status pairs or return WP_Error on failure.
     *
     * @param string $body      Raw webhook body.
     * @param string $signature Header signature or token.
     * @param array  $settings  Global Catlaq settings (provider secrets, etc.)
     *
     * @return array|\WP_Error  ['provider_ref' => string, 'status' => string, 'payload' => array]
     */
    public function parse_webhook_payload( string $body, string $signature, array $settings );

    public function get_name(): string;
}
