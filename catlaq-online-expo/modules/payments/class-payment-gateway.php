<?php
namespace Catlaq\Expo\Modules\Payments;

class Payment_Gateway {
    private Gateway_Interface $provider;
    private array $settings;

    public function __construct( string $provider_slug = 'mock', array $settings = [] ) {
        $this->settings = $settings;
        $this->provider = $this->make_provider( $provider_slug );
    }

    public function hold( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->provider->hold( $order, $amount, $currency );
    }

    public function release( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->provider->release( $order, $amount, $currency );
    }

    public function refund( array $order, float $amount, string $currency = 'USD' ): array {
        return $this->provider->refund( $order, $amount, $currency );
    }

    public function checkout_membership( array $invoice, float $amount, string $currency = 'USD' ): array {
        return $this->provider->checkout_membership( $invoice, $amount, $currency );
    }

    public function parse_webhook_payload( string $body, string $signature = '' ) {
        return $this->provider->parse_webhook_payload( $body, $signature, $this->settings );
    }

    public function get_provider_name(): string {
        return $this->provider->get_name();
    }

    private function make_provider( string $slug ): Gateway_Interface {
        $slug = sanitize_key( $slug );

        switch ( $slug ) {
            case 'stripe':
                $gateway = new Stripe_Gateway();
                break;
            case 'checkout':
                $gateway = new Checkout_Gateway();
                break;
            case 'worldfirst':
                $gateway = new WorldFirst_Gateway( $this->settings );
                break;
            case 'mock':
            default:
                $gateway = new Mock_Gateway();
                break;
        }

        /**
         * Allow third parties to supply a custom provider implementation.
         *
         * @param Gateway_Interface $gateway  Default gateway instance.
         * @param string            $slug     Requested provider slug.
         * @param array             $settings Catlaq payment settings.
         */
        $gateway = apply_filters( 'catlaq_payment_gateway', $gateway, $slug, $this->settings );

        if ( ! $gateway instanceof Gateway_Interface ) {
            $gateway = new Mock_Gateway();
        }

        return $gateway;
    }
}
