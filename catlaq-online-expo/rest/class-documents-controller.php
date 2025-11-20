<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Agreements\Document_Factory;
use Catlaq\Expo\Modules\Agreements\Document_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function __;

class Documents_Controller {
    private Document_Service $service;
    private Document_Factory $factory;

    public function __construct( ?Document_Service $service = null, ?Document_Factory $factory = null ) {
        $this->service = $service ?: new Document_Service();
        $this->factory = $factory ?: new Document_Factory();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/document-templates',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'list_templates' ],
                        'permission_callback' => function () {
                            return current_user_can( 'read' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/documents',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'create_document' ],
                        'permission_callback' => function () {
                            return current_user_can( 'read' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/rooms/(?P<room_id>\d+)/documents',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'list_room_documents' ],
                        'permission_callback' => function () {
                            return current_user_can( 'read' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/documents/(?P<document_id>\d+)',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'get_document' ],
                        'permission_callback' => function () {
                            return current_user_can( 'read' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/documents/(?P<document_id>\d+)/signatures',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_signatures' ],
                            'permission_callback' => function () {
                                return current_user_can( 'read' );
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_signature' ],
                            'permission_callback' => function () {
                                return current_user_can( 'edit_pages' );
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/documents/signatures/(?P<signature_id>\d+)/complete',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'complete_signature' ],
                        'permission_callback' => function () {
                            return current_user_can( 'edit_pages' );
                        },
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/documents/signatures/complete',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'complete_signature_token' ],
                        'permission_callback' => '__return_true',
                    ]
                );
            }
        );
    }

    public function list_templates( WP_REST_Request $request ): WP_REST_Response {
        $category = sanitize_text_field( (string) $request->get_param( 'category' ) );
        $filters  = [];
        if ( '' !== $category ) {
            $filters['category'] = $category;
        }

        $templates = $this->service->templates_for_user( $filters );
        return new WP_REST_Response( array_values( $templates ) );
    }

    public function create_document( WP_REST_Request $request ) {
        $room_id      = absint( $request->get_param( 'room_id' ) );
        $template_key = sanitize_key( $request->get_param( 'template_key' ) );
        if ( ! $room_id || '' === $template_key ) {
            return new WP_Error( 'catlaq_document_params', __( 'Room ID and template key are required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $payload = $request->get_param( 'context' );
        if ( ! is_array( $payload ) ) {
            $payload = [];
        }

        $document = $this->service->generate( $room_id, $template_key, $payload );
        if ( is_wp_error( $document ) ) {
            return $document;
        }

        return new WP_REST_Response( $document, 201 );
    }

    public function list_room_documents( WP_REST_Request $request ): WP_REST_Response {
        $room_id = absint( $request->get_param( 'room_id' ) );
        return new WP_REST_Response( $this->service->list_room_documents( $room_id ) );
    }

    public function get_document( WP_REST_Request $request ) {
        $document_id = absint( $request->get_param( 'document_id' ) );
        $document    = $this->factory->get_document( $document_id );
        if ( ! $document ) {
            return new WP_Error( 'catlaq_document_missing', __( 'Document not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $document );
    }

    public function list_signatures( WP_REST_Request $request ): WP_REST_Response {
        $document_id = absint( $request->get_param( 'document_id' ) );
        return new WP_REST_Response( $this->service->signatures_for_document( $document_id ) );
    }

    public function create_signature( WP_REST_Request $request ) {
        $document_id = absint( $request->get_param( 'document_id' ) );
        $payload     = [
            'signer_id'    => absint( $request->get_param( 'signer_id' ) ),
            'signer_email' => sanitize_email( $request->get_param( 'signer_email' ) ),
            'role'         => sanitize_key( $request->get_param( 'role' ) ?: 'participant' ),
        ];

        if ( ! $document_id || ( ! $payload['signer_id'] && ! $payload['signer_email'] ) ) {
            return new WP_Error( 'catlaq_signature_payload', __( 'Signer information required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $signature_id = $this->service->request_signature( $document_id, $payload );
        if ( ! $signature_id ) {
            return new WP_Error( 'catlaq_signature_error', __( 'Unable to create signature request.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        return new WP_REST_Response( [ 'signature_id' => $signature_id ], 201 );
    }

    public function complete_signature( WP_REST_Request $request ) {
        $signature_id = absint( $request->get_param( 'signature_id' ) );
        $payload      = [
            'status'    => sanitize_key( $request->get_param( 'status' ) ?: 'signed' ),
            'metadata'  => (array) $request->get_param( 'metadata' ),
            'signer_id' => get_current_user_id(),
        ];

        $result = $this->service->complete_signature( $signature_id, $payload );
        if ( ! $result ) {
            return new WP_Error( 'catlaq_signature_missing', __( 'Signature not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $result );
    }

    public function complete_signature_token( WP_REST_Request $request ) {
        $token   = sanitize_text_field( $request->get_param( 'token' ) );
        $payload = [
            'status'   => sanitize_key( $request->get_param( 'status' ) ?: 'signed' ),
            'metadata' => (array) $request->get_param( 'metadata' ),
        ];

        if ( '' === $token ) {
            return new WP_Error( 'catlaq_signature_token', __( 'Token required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $result = $this->service->complete_signature_by_token( $token, $payload );
        if ( ! $result ) {
            return new WP_Error( 'catlaq_signature_missing', __( 'Signature not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $result );
    }
}
