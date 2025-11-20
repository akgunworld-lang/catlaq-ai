<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Digital_Expo\Order_Service;
use Catlaq\Expo\Modules\Digital_Expo\RFQ_Controller as Expo_RFQ;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Orders_Controller {
    private $orders;
    private $rfq;

    public function __construct() {
        $this->orders = new Order_Service();
        $this->rfq    = new Expo_RFQ();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/orders',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_orders' ],
                            'permission_callback' => function () {
                                return current_user_can( 'read' );
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_order' ],
                            'permission_callback' => function () {
                                return current_user_can( 'manage_options' );
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/orders/(?P<order_id>\d+)',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_order' ],
                        'permission_callback' => function () {
                            return current_user_can( 'read' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/orders/(?P<order_id>\d+)/status',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'update_status' ],
                        'permission_callback' => function () {
                            return current_user_can( 'edit_posts' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/orders/(?P<order_id>\d+)/disputes',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'open_dispute' ],
                        'permission_callback' => function () {
                            return current_user_can( 'edit_posts' );
                        },
                    ]
                );
            }
        );
    }

    public function list_orders( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $limit = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );

        $table = $wpdb->prefix . 'catlaq_orders';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, rfq_id, buyer_company_id, seller_company_id, status, total_amount, currency, escrow_state, payment_state, logistics_state, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return new WP_REST_Response( $rows );
    }

    public function create_order( WP_REST_Request $request ) {
        $rfq_id = absint( $request->get_param( 'rfq_id' ) );
        if ( ! $rfq_id ) {
            return new WP_Error( 'catlaq_missing_rfq', __( 'RFQ ID required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $payload = [
            'seller_company_id' => absint( $request->get_param( 'seller_company_id' ) ),
            'currency'          => sanitize_text_field( $request->get_param( 'currency' ) ),
            'items'             => $this->sanitize_items( $request->get_param( 'items' ) ),
        ];

        $order = $this->rfq->convert_to_order( $rfq_id, $payload );
        if ( is_wp_error( $order ) ) {
            return $order;
        }

        return new WP_REST_Response( $order, 201 );
    }

    public function get_order( WP_REST_Request $request ) {
        $order = $this->orders->get_order( absint( $request->get_param( 'order_id' ) ) );
        if ( ! $order ) {
            return new WP_Error( 'catlaq_order_missing', __( 'Order not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $order );
    }

    public function update_status( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $status   = sanitize_key( $request->get_param( 'status' ) );
        $note     = sanitize_textarea_field( (string) $request->get_param( 'note' ) );

        if ( '' === $status ) {
            return new WP_Error( 'catlaq_status_required', __( 'Status is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $result = $this->orders->transition_status(
            $order_id,
            $status,
            $note,
            [
                'source' => 'rest',
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result );
    }

    public function open_dispute( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $reason   = sanitize_text_field( $request->get_param( 'reason' ) );
        $role     = sanitize_key( $request->get_param( 'role' ) );
        $evidence = $request->get_param( 'evidence' );

        if ( '' === $reason ) {
            return new WP_Error( 'catlaq_dispute_reason', __( 'Reason is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $context = [
            'role'     => $role ?: 'buyer',
            'evidence' => is_array( $evidence ) ? $evidence : [],
        ];

        $dispute_id = $this->orders->open_dispute( $order_id, $reason, $context );
        if ( is_wp_error( $dispute_id ) ) {
            return $dispute_id;
        }

        return new WP_REST_Response(
            [
                'dispute_id' => $dispute_id,
                'order'      => $this->orders->get_order( $order_id ),
            ],
            201
        );
    }

    private function sanitize_items( $items ): array {
        if ( empty( $items ) || ! is_array( $items ) ) {
            return [];
        }

        $clean = [];
        foreach ( $items as $item ) {
            $clean[] = [
                'product_id'   => isset( $item['product_id'] ) ? absint( $item['product_id'] ) : null,
                'description'  => sanitize_text_field( $item['description'] ?? '' ),
                'quantity'     => (float) ( $item['quantity'] ?? 0 ),
                'unit_price'   => (float) ( $item['unit_price'] ?? 0 ),
                'total_weight' => (float) ( $item['total_weight'] ?? 0 ),
                'metadata'     => is_array( $item['metadata'] ?? null ) ? $item['metadata'] : [],
            ];
        }

        return $clean;
    }
}
