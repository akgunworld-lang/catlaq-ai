<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Logistics\Logistics_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Logistics_Controller {
    private Logistics_Service $service;

    public function __construct( ?Logistics_Service $service = null ) {
        $this->service = $service ?: new Logistics_Service();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/shipments',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'list_shipments' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/shipments/(?P<shipment_id>\d+)',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_shipment' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/shipments/(?P<shipment_id>\d+)/tracking',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'update_tracking' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );
            }
        );
    }

    public function list_shipments( WP_REST_Request $request ): WP_REST_Response {
        $limit    = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        $order_id = absint( $request->get_param( 'order_id' ) ?: 0 );

        return new WP_REST_Response( $this->service->all( $limit, $order_id ?: null ) );
    }

    public function get_shipment( WP_REST_Request $request ) {
        $shipment = $this->service->get( absint( $request->get_param( 'shipment_id' ) ) );
        if ( ! $shipment ) {
            return new WP_Error( 'catlaq_shipment_missing', __( 'Shipment not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $shipment );
    }

    public function update_tracking( WP_REST_Request $request ) {
        $shipment_id = absint( $request->get_param( 'shipment_id' ) );
        $status      = $request->get_param( 'status' );
        $tracking    = $request->get_param( 'tracking_number' );
        $carrier     = $request->get_param( 'carrier' );
        $note        = sanitize_textarea_field( (string) $request->get_param( 'note' ) );

        if ( empty( $status ) && empty( $tracking ) && empty( $carrier ) ) {
            return new WP_Error( 'catlaq_tracking_required', __( 'Provide at least one of status, tracking number or carrier.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $payload = array_filter(
            [
                'status'          => $status,
                'tracking_number' => $tracking,
                'carrier'         => $carrier,
            ],
            fn( $value ) => '' !== $value && null !== $value
        );

        $updated = $this->service->update_tracking( $shipment_id, $payload, $note );
        if ( ! $updated ) {
            return new WP_Error( 'catlaq_shipment_missing', __( 'Shipment not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $updated );
    }
}
