<?php
namespace Catlaq\Expo\Modules\Payments;

use Catlaq\Expo\Memberships;
use Catlaq\Expo\Modules\Digital_Expo\Order_Service;
use WP_Error;

class Payment_Service {
    private string $table;
    private string $membership_invoice_table;
    private Order_Service $orders;
    private Payment_Gateway $gateway;
    private array $settings;
    private Memberships $memberships;

    public function __construct( ?Order_Service $orders = null, ?Payment_Gateway $gateway = null, ?Memberships $memberships = null ) {
        global $wpdb;
        $this->table                    = $wpdb->prefix . 'catlaq_payment_transactions';
        $this->membership_invoice_table = $wpdb->prefix . 'catlaq_membership_invoices';
        $this->orders                   = $orders ?: new Order_Service();
        $this->settings                 = \Catlaq\Expo\Settings::get();
        $this->memberships              = $memberships ?: new Memberships();

        if ( $gateway ) {
            $this->gateway = $gateway;
        } else {
            $provider       = $this->settings['payment_provider'] ?? 'mock';
            $this->gateway  = new Payment_Gateway( $provider, $this->settings );
        }
    }

    public function boot(): void {
        add_action( 'catlaq_payment_deposit_requested', [ $this, 'maybe_hold_deposit' ] );
        add_action( 'catlaq_payment_escrow_funded', [ $this, 'mark_escrow_funded' ] );
        add_action( 'catlaq_payment_release_pending', [ $this, 'release_to_seller' ] );
        add_action( 'catlaq_order_closed', [ $this, 'complete_order_payments' ] );
        add_action( 'catlaq_dispute_required', [ $this, 'hold_for_dispute' ] );
    }

    public function list( int $limit = 50, ?int $order_id = null ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        $sql    = "SELECT * FROM {$this->table}";
        $params = [];
        if ( $order_id ) {
            $sql      .= " WHERE order_id = %d";
            $params[]  = $order_id;
        }
        $sql .= " ORDER BY id DESC LIMIT %d";
        $params[] = $limit;

        $query = $wpdb->prepare( $sql, $params );
        $rows  = $wpdb->get_results( $query, ARRAY_A ) ?: [];

        return array_map( [ $this, 'hydrate' ], $rows );
    }

    public function get( int $transaction_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $transaction_id ),
            ARRAY_A
        );
        return $row ? $this->hydrate( $row ) : null;
    }

    public function update_status( int $transaction_id, string $status, array $metadata = [] ): ?array {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'status'     => sanitize_key( $status ),
                'metadata'   => wp_json_encode( $metadata ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $transaction_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        return $this->get( $transaction_id );
    }

    public function handle_webhook( string $body, string $signature = '' ) {
        $parsed = $this->gateway->parse_webhook_payload( $body, $signature );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        $provider_ref = sanitize_text_field( $parsed['provider_ref'] ?? '' );
        if ( '' === $provider_ref ) {
            return new WP_Error( 'catlaq_payment_ref', __( 'provider_ref missing.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $transaction = $this->find_by_provider_ref( $provider_ref );
        $status      = sanitize_key( $parsed['status'] ?? 'pending' );
        $payload     = is_array( $parsed['payload'] ?? null ) ? $parsed['payload'] : [];

        if ( $transaction ) {
            return $this->update_status( (int) $transaction['id'], $status, $payload );
        }

        $invoice = $this->find_membership_invoice_by_provider_ref( $provider_ref );
        if ( $invoice ) {
            return $this->update_membership_invoice_status( (int) $invoice['id'], $status, $payload );
        }

        return new WP_Error( 'catlaq_payment_not_found', __( 'Transaction not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
    }

    public function create_membership_invoice( int $user_id, string $plan_slug ) {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return new WP_Error( 'catlaq_membership_user', __( 'Valid user required for membership checkout.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return new WP_Error( 'catlaq_membership_user', __( 'User not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        $plan_slug = sanitize_key( $plan_slug );
        if ( '' === $plan_slug ) {
            return new WP_Error( 'catlaq_membership_plan', __( 'Membership plan is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $plan = $this->memberships->find_by_slug( $plan_slug );
        if ( ! $plan ) {
            return new WP_Error( 'catlaq_membership_plan', __( 'Membership plan not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        $amount   = (float) ( $plan['price'] ?? 0 );
        $currency = strtoupper( $plan['currency'] ?? 'USD' );

        global $wpdb;
        $wpdb->insert(
            $this->membership_invoice_table,
            [
                'user_id'    => $user_id,
                'plan_slug'  => $plan_slug,
                'plan_label' => $plan['label'] ?? '',
                'amount'     => $amount,
                'currency'   => $currency,
                'status'     => $amount > 0 ? 'pending' : 'complimentary',
                'provider'   => $this->gateway->get_provider_name(),
                'metadata'   => wp_json_encode( [] ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );

        $invoice_id = (int) $wpdb->insert_id;
        if ( $amount <= 0 ) {
            $this->memberships->assign_user_plan( $user_id, $plan_slug );
            $this->update_membership_invoice_status(
                $invoice_id,
                'active',
                [
                    'auto'   => true,
                    'reason' => 'complimentary_membership',
                ]
            );

            return $this->get_membership_invoice( $invoice_id );
        }

        $context = [
            'id'         => $invoice_id,
            'user_id'    => $user_id,
            'plan_slug'  => $plan_slug,
            'plan_label' => $plan['label'] ?? '',
            'user_email' => $user->user_email,
            'user_login' => $user->user_login,
        ];

        $gateway_response = $this->gateway->checkout_membership( $context, $amount, $currency );
        $this->store_membership_gateway_response( $invoice_id, $gateway_response );

        return $this->get_membership_invoice( $invoice_id );
    }

    public function list_membership_invoices( int $user_id = 0, int $limit = 50 ): array {
        global $wpdb;
        $limit    = max( 1, $limit );
        $sql      = "SELECT * FROM {$this->membership_invoice_table}";
        $params   = [];
        if ( $user_id > 0 ) {
            $sql      .= " WHERE user_id = %d";
            $params[]  = absint( $user_id );
        }
        $sql     .= " ORDER BY id DESC LIMIT %d";
        $params[] = $limit;

        $query = $wpdb->prepare( $sql, $params );
        $rows  = $wpdb->get_results( $query, ARRAY_A ) ?: [];

        return array_map( [ $this, 'hydrate_membership_invoice' ], $rows );
    }

    public function get_membership_invoice( int $invoice_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->membership_invoice_table} WHERE id = %d", $invoice_id ),
            ARRAY_A
        );

        return $row ? $this->hydrate_membership_invoice( $row ) : null;
    }

    public function update_membership_invoice_status( int $invoice_id, string $status, array $metadata = [] ): ?array {
        global $wpdb;
        $invoice = $this->get_membership_invoice( $invoice_id );
        if ( ! $invoice ) {
            return null;
        }

        $wpdb->update(
            $this->membership_invoice_table,
            [
                'status'     => sanitize_key( $status ),
                'metadata'   => wp_json_encode( $metadata ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $invoice_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        $updated = $this->get_membership_invoice( $invoice_id );
        $this->maybe_activate_membership( $updated );

        return $updated;
    }

    public function create_transaction( int $order_id, string $type, float $amount, string $currency, array $metadata = [] ): int {
        global $wpdb;

        if ( empty( $metadata['provider'] ) ) {
            $metadata['provider'] = $this->gateway->get_provider_name();
        }

        $wpdb->insert(
            $this->table,
            [
                'order_id'    => $order_id,
                'type'        => sanitize_key( $type ),
                'status'      => 'pending',
                'amount'      => $amount,
                'currency'    => strtoupper( $currency ),
                'provider'    => $metadata['provider'] ?? 'mock',
                'provider_ref'=> $metadata['provider_ref'] ?? '',
                'metadata'    => wp_json_encode( $metadata ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function maybe_hold_deposit( array $order ): void {
        $order = $this->orders->get_order( (int) $order['id'] );
        if ( ! $order ) {
            return;
        }

        $amount = (float) ( $order['total_amount'] ?? 0 );
        if ( $amount <= 0 ) {
            return;
        }

        $gateway_response = $this->gateway->hold( $order, $amount, $order['currency'] ?? 'USD' );
        $transaction_id   = $this->create_transaction(
            (int) $order['id'],
            'escrow_hold',
            $amount,
            $order['currency'] ?? 'USD',
            $gateway_response
        );

        $this->update_status( $transaction_id, $gateway_response['status'], $gateway_response );
    }

    public function mark_escrow_funded( array $order ): void {
        $this->create_transaction(
            (int) $order['id'],
            'escrow_funded',
            (float) ( $order['total_amount'] ?? 0 ),
            $order['currency'] ?? 'USD',
            [ 'status' => 'funded' ]
        );
    }

    public function release_to_seller( array $order ): void {
        $order = $this->orders->get_order( (int) $order['id'] );
        if ( ! $order ) {
            return;
        }

        $amount           = (float) ( $order['total_amount'] ?? 0 );
        $gateway_response = $this->gateway->release( $order, $amount, $order['currency'] ?? 'USD' );
        $transaction_id   = $this->create_transaction(
            (int) $order['id'],
            'escrow_release',
            $amount,
            $order['currency'] ?? 'USD',
            $gateway_response
        );
        $this->update_status( $transaction_id, $gateway_response['status'], $gateway_response );
    }

    public function complete_order_payments( array $order ): void {
        $order = $this->orders->get_order( (int) $order['id'] );
        if ( ! $order ) {
            return;
        }

        $pending = $this->list( 100, (int) $order['id'] );
        foreach ( $pending as $transaction ) {
            if ( 'escrow_release' === $transaction['type'] && 'released' !== $transaction['status'] ) {
                $this->update_status( $transaction['id'], 'released' );
            }
        }
    }

    public function hold_for_dispute( array $order ): void {
        $order = $this->orders->get_order( (int) $order['id'] );
        if ( ! $order ) {
            return;
        }

        $this->create_transaction(
            (int) $order['id'],
            'escrow_hold_dispute',
            (float) ( $order['total_amount'] ?? 0 ),
            $order['currency'] ?? 'USD',
            [ 'status' => 'on_hold' ]
        );
    }

    private function hydrate( array $row ): array {
        $row['metadata'] = json_decode( $row['metadata'] ?? '{}', true );
        return $row;
    }

    private function hydrate_membership_invoice( array $row ): array {
        $row['metadata'] = json_decode( $row['metadata'] ?? '{}', true );
        return $row;
    }

    private function store_membership_gateway_response( int $invoice_id, array $response ): void {
        global $wpdb;
        $wpdb->update(
            $this->membership_invoice_table,
            [
                'provider_ref' => sanitize_text_field( $response['provider_ref'] ?? '' ),
                'status'       => sanitize_key( $response['status'] ?? 'pending' ),
                'checkout_url' => esc_url_raw( $response['checkout_url'] ?? '' ),
                'metadata'     => wp_json_encode( $response ),
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ 'id' => $invoice_id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    private function find_by_provider_ref( string $provider_ref ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE provider_ref = %s LIMIT 1", $provider_ref ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    private function find_membership_invoice_by_provider_ref( string $provider_ref ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->membership_invoice_table} WHERE provider_ref = %s LIMIT 1", $provider_ref ),
            ARRAY_A
        );

        return $row ? $this->hydrate_membership_invoice( $row ) : null;
    }

    private function maybe_activate_membership( ?array $invoice ): void {
        if ( ! $invoice ) {
            return;
        }

        if ( empty( $invoice['plan_slug'] ) || empty( $invoice['user_id'] ) ) {
            return;
        }

        $status = sanitize_key( $invoice['status'] ?? '' );
        if ( ! in_array( $status, [ 'paid', 'active', 'completed' ], true ) ) {
            return;
        }

        $this->memberships->assign_user_plan( (int) $invoice['user_id'], $invoice['plan_slug'] );

        if ( empty( $invoice['paid_at'] ) ) {
            global $wpdb;
            $wpdb->update(
                $this->membership_invoice_table,
                [
                    'paid_at'    => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => (int) $invoice['id'] ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }
    }
}



