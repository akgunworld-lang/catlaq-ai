<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Memberships;
use Catlaq\Expo\Modules\Payments\Payment_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Membership_Controller {
    private Memberships $memberships;
    private Payment_Service $payments;

    public function __construct( ?Memberships $memberships = null, ?Payment_Service $payments = null ) {
        $this->memberships = $memberships ?: new Memberships();
        $this->payments    = $payments ?: new Payment_Service();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/membership',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_membership' ],
                        'permission_callback' => function () {
                            return is_user_logged_in();
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/membership/(?P<user_id>\d+)',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_membership' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/membership/checkout',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'checkout' ],
                        'permission_callback' => function () {
                            return is_user_logged_in();
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/membership/invoices',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'list_invoices' ],
                        'permission_callback' => function () {
                            return is_user_logged_in();
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/membership/invoices/(?P<invoice_id>\d+)/status',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'update_invoice_status' ],
                        'permission_callback' => function () {
                            return current_user_can( 'manage_options' );
                        },
                    ]
                );
            }
        );
    }

    public function get_membership( WP_REST_Request $request ) {
        $user_id = $request->get_param( 'user_id' );
        if ( $user_id ) {
            $user_id = absint( $user_id );
        } else {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return new WP_Error( 'catlaq_membership_auth', __( 'User must be logged in.', 'catlaq-online-expo' ), [ 'status' => 401 ] );
        }

        $plan = $this->memberships->user_membership( $user_id );
        if ( ! $plan ) {
            return new WP_Error( 'catlaq_membership_missing', __( 'No membership plan assigned.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        $quotas = [];
        $quota_defs = (array) ( $plan['quotas'] ?? [] );
        foreach ( $quota_defs as $metric => $limit ) {
            $quotas[ $metric ] = $this->memberships->quota_status( $user_id, $metric, (int) $limit );
        }

        $response = [
            'user_id'   => $user_id,
            'plan'      => [
                'slug'     => $plan['slug'] ?? '',
                'label'    => $plan['label'] ?? '',
                'features' => $plan['features'] ?? [],
                'quotas'   => $quotas,
            ],
        ];

        return new WP_REST_Response( $response );
    }

    public function checkout( WP_REST_Request $request ) {
        $plan_slug = sanitize_key( (string) $request->get_param( 'plan_slug' ) );
        if ( '' === $plan_slug ) {
            return new WP_Error( 'catlaq_membership_plan', __( 'plan_slug is required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $result = $this->payments->create_membership_invoice( get_current_user_id(), $plan_slug );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result );
    }

    public function list_invoices( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $limit = min( max( 1, absint( $request->get_param( 'per_page' ) ?: 20 ) ), 200 );
        $user_id_param = absint( $request->get_param( 'user_id' ) ?: 0 );

        if ( current_user_can( 'manage_options' ) ) {
            $user_id = $user_id_param;
        } else {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return new WP_Error( 'catlaq_membership_auth', __( 'User must be logged in.', 'catlaq-online-expo' ), [ 'status' => 401 ] );
        }

        if ( ! current_user_can( 'manage_options' ) && $user_id_param && $user_id_param !== $user_id ) {
            return new WP_Error( 'catlaq_membership_scope', __( 'You cannot view invoices for other users.', 'catlaq-online-expo' ), [ 'status' => 403 ] );
        }

        $target_user = current_user_can( 'manage_options' ) ? $user_id_param : $user_id;
        $invoices    = $this->payments->list_membership_invoices( $target_user, $limit );

        return new WP_REST_Response( $invoices );
    }

    public function update_invoice_status( WP_REST_Request $request ) {
        $invoice_id = absint( $request->get_param( 'invoice_id' ) );
        $status     = sanitize_key( $request->get_param( 'status' ) );
        $metadata   = $request->get_param( 'metadata' );

        if ( ! $invoice_id || '' === $status ) {
            return new WP_Error( 'catlaq_membership_invoice', __( 'Invoice ID and status are required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $metadata ) ) {
            $metadata = [];
        }

        $updated = $this->payments->update_membership_invoice_status( $invoice_id, $status, $metadata );
        if ( ! $updated ) {
            return new WP_Error( 'catlaq_membership_invoice_missing', __( 'Membership invoice not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $updated );
    }
}
