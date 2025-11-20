<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Audit;
use Catlaq\Expo\Modules\Digital_Expo\RFQ_Controller as Expo_RFQ;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RFQ_Controller {
    private $rfq;

    public function __construct() {
        $this->rfq = new Expo_RFQ();
    }

    public function register(): void {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'catlaq/v1', '/rfq', [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'list_rfq' ],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'create_rfq' ],
                    'permission_callback' => function () {
                        return current_user_can( 'manage_options' );
                    },
                ],
            ] );
        } );
    }

    public function list_rfq( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        $rows  = $this->rfq->all( $limit );
        return new WP_REST_Response( $rows );
    }

    public function create_rfq( WP_REST_Request $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        if ( empty( $title ) ) {
            return new WP_Error( 'catlaq_missing_title', __( 'Title required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $payload = [
            'title'            => $title,
            'details'          => sanitize_textarea_field( (string) $request->get_param( 'details' ) ),
            'open_room'        => rest_sanitize_boolean( $request->get_param( 'open_room' ) ),
            'hold_escrow'      => rest_sanitize_boolean( $request->get_param( 'hold_escrow' ) ),
            'buyer_company_id' => absint( $request->get_param( 'buyer_company_id' ) ),
            'company_name'     => sanitize_text_field( (string) $request->get_param( 'company_name' ) ),
            'country'          => sanitize_text_field( (string) $request->get_param( 'country' ) ),
            'membership_tier'  => sanitize_key( (string) $request->get_param( 'membership_tier' ) ),
            'budget'           => floatval( $request->get_param( 'budget' ) ),
            'currency'         => sanitize_text_field( (string) $request->get_param( 'currency' ) ),
        ];

        $result = $this->rfq->create( $payload );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( empty( $result['id'] ) ) {
            return new WP_Error( 'catlaq_rfq_error', __( 'Could not create RFQ.', 'catlaq-online-expo' ), [ 'status' => 500 ] );
        }

        Audit::log( 'rfq_created', [ 'rfq_id' => $result['id'], 'open_room' => $payload['open_room'] ] );

        return new WP_REST_Response( $result, 201 );
    }
}
