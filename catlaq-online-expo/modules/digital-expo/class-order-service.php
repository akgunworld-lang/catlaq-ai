<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

use Catlaq\Expo\Audit;
use WP_Error;

class Order_Service {
    private $orders_table;
    private $items_table;
    private $status_table;
    private $disputes_table;
    private $rfq_table;
    /**
     * Graph of allowed status transitions.
     *
     * @var array<string,string[]>
     */
    private $status_flow = [
        'draft'        => [ 'proforma', 'cancelled' ],
        'proforma'     => [ 'confirmed', 'cancelled', 'dispute' ],
        'confirmed'    => [ 'financed', 'cancelled', 'dispute' ],
        'financed'     => [ 'production', 'ready_to_ship', 'cancelled', 'dispute' ],
        'production'   => [ 'ready_to_ship', 'cancelled', 'dispute' ],
        'ready_to_ship'=> [ 'shipped', 'cancelled', 'dispute' ],
        'shipped'      => [ 'delivered', 'dispute' ],
        'delivered'    => [ 'closed', 'dispute' ],
        'dispute'      => [ 'resolved', 'closed' ],
        'resolved'     => [ 'closed' ],
        'closed'       => [],
        'cancelled'    => [],
    ];

    public function __construct() {
        global $wpdb;
        $prefix               = $wpdb->prefix;
        $this->orders_table   = $prefix . 'catlaq_orders';
        $this->items_table    = $prefix . 'catlaq_order_items';
        $this->status_table   = $prefix . 'catlaq_order_status_log';
        $this->disputes_table = $prefix . 'catlaq_disputes';
        $this->rfq_table      = $prefix . 'catlaq_rfq';
    }

    /**
     * Create an order from an RFQ.
     *
     * @param array $rfq RFQ row.
     * @param array $payload Seller/line details.
     */
    public function create_from_rfq( array $rfq, array $payload ) {
        if ( empty( $payload['seller_company_id'] ) ) {
            return new WP_Error( 'catlaq_order_missing_seller', __( 'Seller company is required to create an order.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : [];
        if ( empty( $items ) ) {
            return new WP_Error( 'catlaq_order_missing_items', __( 'At least one order item is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $currency = strtoupper( substr( (string) ( $payload['currency'] ?? $rfq['currency'] ?? 'USD' ), 0, 3 ) );

        $total_amount = 0;
        foreach ( $items as &$item ) {
            $qty   = (float) ( $item['quantity'] ?? 0 );
            $price = (float) ( $item['unit_price'] ?? 0 );
            $item['line_total'] = $qty * $price;
            $total_amount      += $item['line_total'];
        }

        global $wpdb;
        $wpdb->insert(
            $this->orders_table,
            [
                'rfq_id'             => (int) $rfq['id'],
                'buyer_company_id'   => (int) $rfq['buyer_company_id'],
                'seller_company_id'  => (int) $payload['seller_company_id'],
                'status'             => 'proforma',
                'total_amount'       => $total_amount,
                'currency'           => $currency,
                'escrow_state'       => 'pending',
                'payment_state'      => 'pending',
                'logistics_state'    => 'unassigned',
                'milestones'         => wp_json_encode( $this->seed_milestones() ),
                'metadata'           => wp_json_encode( [
                    'membership_tier' => $rfq['membership_tier'] ?? 'standard',
                ] ),
                'created_at'         => current_time( 'mysql' ),
                'updated_at'         => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );

        $order_id = (int) $wpdb->insert_id;

        foreach ( $items as $item ) {
            $wpdb->insert(
                $this->items_table,
                [
                    'order_id'    => $order_id,
                    'product_id'  => isset( $item['product_id'] ) ? (int) $item['product_id'] : null,
                    'description' => sanitize_text_field( $item['description'] ?? '' ),
                    'quantity'    => (float) ( $item['quantity'] ?? 0 ),
                    'unit_price'  => (float) ( $item['unit_price'] ?? 0 ),
                    'total_weight'=> (float) ( $item['total_weight'] ?? 0 ),
                    'metadata'    => wp_json_encode( $item['metadata'] ?? [] ),
                    'created_at'  => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s' ]
            );
        }

        $this->log_status( $order_id, 'proforma', __( 'Order created from RFQ.', 'catlaq-online-expo' ), true );
        Audit::log( 'order_created', [
            'order_id' => $order_id,
            'rfq_id'   => (int) $rfq['id'],
        ] );

        return $this->get_order( $order_id );
    }

    public function get_order( int $order_id ): ?array {
        global $wpdb;
        $order = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->orders_table} WHERE id = %d", $order_id ),
            ARRAY_A
        );
        if ( ! $order ) {
            return null;
        }

        $order['metadata']   = json_decode( $order['metadata'] ?? '{}', true );
        $order['milestones'] = json_decode( $order['milestones'] ?? '[]', true );
        $order['items']      = $this->get_items( $order_id );
        $order['history']    = $this->get_status_log( $order_id );
        return $order;
    }

    public function get_items( int $order_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$this->items_table} WHERE order_id = %d", $order_id ),
            ARRAY_A
        ) ?: [];

        foreach ( $rows as &$row ) {
            $row['metadata'] = json_decode( $row['metadata'] ?? '[]', true );
        }

        return $rows;
    }

    public function log_status( int $order_id, string $status, string $note = '', bool $bypass_validation = false ): void {
        global $wpdb;
        $status = sanitize_key( $status );
        if ( ! $bypass_validation ) {
            $current   = $this->get_order( $order_id );
            $current_status = $current['status'] ?? '';
            if ( '' !== $current_status && ! in_array( $status, $this->allowed_transitions( $current_status ), true ) ) {
                return;
            }
        }

        $wpdb->insert(
            $this->status_table,
            [
                'order_id'  => $order_id,
                'status'    => sanitize_key( $status ),
                'note'      => sanitize_textarea_field( $note ),
                'actor'     => get_current_user_id() ?: null,
                'created_at'=> current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s' ]
        );

        $wpdb->update(
            $this->orders_table,
            [
                'status'     => sanitize_key( $status ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $order_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    public function transition_status( int $order_id, string $status, string $note = '', array $context = [] ) {
        $status = sanitize_key( $status );
        $order  = $this->get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'catlaq_order_missing', __( 'Order not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        if ( $order['status'] === $status ) {
            return $order;
        }

        if ( ! in_array( $status, $this->allowed_transitions( $order['status'] ), true ) ) {
            return new WP_Error(
                'catlaq_invalid_transition',
                sprintf(
                    /* translators: 1: current order status, 2: requested status */
                    __( 'Cannot move order from %1$s to %2$s.', 'catlaq-online-expo' ),
                    $order['status'],
                    $status
                ),
                [ 'status' => 409 ]
            );
        }

        $this->log_status( $order_id, $status, $note, true );
        $this->update_state_flags( $order_id, $status );
        $updated = $this->get_order( $order_id );

        Audit::log(
            'order_status',
            [
                'order_id' => $order_id,
                'from'     => $order['status'],
                'to'       => $status,
                'actor'    => get_current_user_id() ?: 0,
                'source'   => $context['source'] ?? 'system',
            ]
        );

        $this->trigger_status_events( $updated, $status, $context );

        return $updated;
    }

    public function allowed_transitions( string $current ): array {
        return $this->status_flow[ $current ] ?? [];
    }

    public function open_dispute( int $order_id, string $reason, array $context = [] ) {
        global $wpdb;
        $wpdb->insert(
            $this->disputes_table,
            [
                'order_id'  => $order_id,
                'opened_by' => get_current_user_id() ?: 0,
                'role'      => $context['role'] ?? 'buyer',
                'reason'    => sanitize_text_field( $reason ),
                'state'     => 'open',
                'evidence'  => wp_json_encode( $context['evidence'] ?? [] ),
                'opened_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        $dispute_id = (int) $wpdb->insert_id;
        Audit::log( 'dispute_opened', [ 'order_id' => $order_id, 'dispute_id' => $dispute_id ] );
        $transition = $this->transition_status( $order_id, 'dispute', __( 'Dispute opened.', 'catlaq-online-expo' ), $context );
        if ( is_wp_error( $transition ) ) {
            return $transition;
        }

        return $dispute_id;
    }

    public function get_rfqs_order_count( int $rfq_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->orders_table} WHERE rfq_id = %d",
                $rfq_id
            )
        );
    }

    private function seed_milestones(): array {
        $now = current_time( 'mysql' );
        return [
            'proforma'       => $now,
            'confirmed'      => null,
            'financed'       => null,
            'production'     => null,
            'ready_to_ship'  => null,
            'shipped'        => null,
            'delivered'      => null,
            'closed'         => null,
        ];
    }

    private function update_state_flags( int $order_id, string $status ): void {
        global $wpdb;
        $updates = [];

        $payment_updates = [
            'confirmed' => 'awaiting_funding',
            'financed'  => 'funded',
            'delivered' => 'release_pending',
            'closed'    => 'released',
            'cancelled' => 'cancelled',
            'dispute'   => 'on_hold',
        ];

        $logistics_updates = [
            'production'    => 'in_production',
            'ready_to_ship' => 'awaiting_pickup',
            'shipped'       => 'in_transit',
            'delivered'     => 'delivered',
            'cancelled'     => 'cancelled',
        ];

        if ( isset( $payment_updates[ $status ] ) ) {
            $updates['payment_state'] = $payment_updates[ $status ];
        }

        if ( isset( $logistics_updates[ $status ] ) ) {
            $updates['logistics_state'] = $logistics_updates[ $status ];
        }

        $order      = $this->get_order( $order_id );
        $milestones = is_array( $order['milestones'] ?? null ) ? $order['milestones'] : [];
        $milestones[ $status ] = current_time( 'mysql' );
        $updates['milestones'] = wp_json_encode( $milestones );

        if ( ! empty( $updates ) ) {
            $formats = array_fill( 0, count( $updates ), '%s' );
            $wpdb->update(
                $this->orders_table,
                $updates,
                [ 'id' => $order_id ],
                $formats,
                [ '%d' ]
            );
        }
    }

    private function trigger_status_events( array $order, string $status, array $context ): void {
        /**
         * Fires whenever an order status changes.
         *
         * @param array  $order  Updated order payload.
         * @param string $status New status.
         * @param array  $context Optional context.
         */
        do_action( 'catlaq_order_status_changed', $order, $status, $context );

        switch ( $status ) {
            case 'confirmed':
                do_action( 'catlaq_payment_deposit_requested', $order );
                break;
            case 'financed':
                do_action( 'catlaq_payment_escrow_funded', $order );
                break;
            case 'ready_to_ship':
                do_action( 'catlaq_logistics_booking_required', $order );
                break;
            case 'shipped':
                do_action( 'catlaq_logistics_tracking_update', $order );
                break;
            case 'delivered':
                do_action( 'catlaq_payment_release_pending', $order );
                break;
            case 'closed':
                do_action( 'catlaq_order_closed', $order );
                break;
            case 'dispute':
                do_action( 'catlaq_dispute_required', $order, $context );
                break;
        }
    }
}
