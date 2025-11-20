<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Payments\Payment_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Payments_Controller {
    private Payment_Service $service;

    public function __construct( Payment_Service $service ) {
        $this->service = $service;
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/payments',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'list_transactions' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/payments/(?P<transaction_id>\\d+)',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_transaction' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/payments/(?P<transaction_id>\\d+)/status',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'update_status' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/payments/webhook',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'webhook' ],
                        'permission_callback' => '__return_true',
                    ]
                );
            }
        );
    }

    public function list_transactions( WP_REST_Request $request ): WP_REST_Response {
        $limit    = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        $order_id = absint( $request->get_param( 'order_id' ) ?: 0 );

        return new WP_REST_Response( $this->service->list( $limit, $order_id ?: null ) );
    }

    public function get_transaction( WP_REST_Request $request ) {
        $transaction = $this->service->get( absint( $request->get_param( 'transaction_id' ) ) );
        if ( ! $transaction ) {
            return new WP_Error( 'catlaq_payment_missing', __( 'Payment transaction not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $transaction );
    }

    public function update_status( WP_REST_Request $request ) {
        $transaction_id = absint( $request->get_param( 'transaction_id' ) );
        $status         = sanitize_key( $request->get_param( 'status' ) );
        $metadata       = $request->get_param( 'metadata' );
        if ( '' === $status ) {
            return new WP_Error( 'catlaq_payment_status', __( 'Status is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $metadata ) ) {
            $metadata = [];
        }

        $updated = $this->service->update_status( $transaction_id, $status, $metadata );
        if ( ! $updated ) {
            return new WP_Error( 'catlaq_payment_missing', __( 'Payment transaction not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $updated );
    }

    public function webhook( WP_REST_Request $request ) {
        $body      = $request->get_body();
        $signature = $request->get_header( 'x-catlaq-signature' );

        $result = $this->service->handle_webhook( $body, (string) $signature );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result ?: [ 'status' => 'ack' ] );
    }
}

