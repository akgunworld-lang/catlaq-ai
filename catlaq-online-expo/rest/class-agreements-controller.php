<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Audit;
use Catlaq\Expo\Logger;
use Catlaq\Expo\Modules\Agreements\Document_Factory;
use Catlaq\Expo\Modules\Agreements\Room_Model;
use Catlaq\Expo\Modules\Agreements\Signature_Service;
use Catlaq\Expo\Token;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Agreements_Controller {
    private $rooms;
    private $documents;
    private $signatures;

    public function __construct() {
        $this->rooms      = new Room_Model();
        $this->documents  = new Document_Factory();
        $this->signatures = new Signature_Service( $this->documents );
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/agreements',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_rooms' ],
                            'permission_callback' => function () {
                                return current_user_can( 'manage_options' );
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_room' ],
                            'permission_callback' => function () {
                                return current_user_can( 'manage_options' );
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/agreements/(?P<room_id>\d+)/sign',
                    [
                        'methods'             => 'POST',
                        'callback'            => [ $this, 'mark_signed' ],
                        'permission_callback' => function () {
                            return current_user_can( 'edit_posts' );
                        },
                    ]
                );
            }
        );
    }

    public function list_rooms( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        return new WP_REST_Response( $this->rooms->list( $limit ) );
    }

    public function create_room( WP_REST_Request $request ) {
        $rfq_id = absint( $request->get_param( 'rfq_id' ) );
        if ( ! $rfq_id ) {
            return new WP_Error( 'catlaq_missing_rfq', __( 'RFQ ID required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $participants = $this->sanitize_participants( $request->get_param( 'participants' ) );
        if ( is_wp_error( $participants ) ) {
            return $participants;
        }

        $room_id = $this->rooms->create(
            $rfq_id,
            [
                'participants' => $participants,
            ]
        );

        if ( ! $room_id ) {
            Logger::log( 'error', 'Agreement room creation failed', [ 'rfq_id' => $rfq_id ] );
            return new WP_Error( 'catlaq_room_error', __( 'Could not create room.', 'catlaq-online-expo' ), [ 'status' => 500 ] );
        }

        $document = $this->documents->generate_for_room(
            $room_id,
            'room-summary',
            [
                'room_id' => $room_id,
                'rfq_id'  => $rfq_id,
                'created' => current_time( 'mysql' ),
            ]
        );

        if ( ! empty( $document ) ) {
            $this->signatures->request_signature( $document, $participants );
        }

        Audit::log( 'agreement_created', [ 'room_id' => $room_id, 'rfq_id' => $rfq_id ] );

        $room = $this->rooms->get( $room_id );

        return new WP_REST_Response( $room, 201 );
    }

    public function mark_signed( WP_REST_Request $request ) {
        $room_id     = absint( $request->get_param( 'room_id' ) );
        $document_id = absint( $request->get_param( 'document_id' ) );

        if ( ! $document_id ) {
            return new WP_Error( 'catlaq_missing_document', __( 'Document ID required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $room = $this->rooms->get( $room_id );
        if ( ! $room ) {
            return new WP_Error( 'catlaq_room_missing', __( 'Agreement room not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        $document = $this->signatures->mark_signed( $document_id, get_current_user_id() ?: 0 );
        if ( is_wp_error( $document ) ) {
            return $document;
        }

        Audit::log( 'agreement_signed', [ 'room_id' => $room_id, 'document_id' => $document_id ] );

        $updated = $this->rooms->get( $room_id );
        return new WP_REST_Response( $updated );
    }

    private function sanitize_participants( $participants ) {
        if ( empty( $participants ) ) {
            return [];
        }

        if ( ! is_array( $participants ) ) {
            return new WP_Error( 'catlaq_invalid_participants', __( 'Participants must be an array.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $clean = [];
        foreach ( $participants as $participant ) {
            if ( ! is_array( $participant ) ) {
                continue;
            }

            $clean[] = [
                'user_id'      => isset( $participant['user_id'] ) ? absint( $participant['user_id'] ) : null,
                'email'        => isset( $participant['email'] ) ? sanitize_email( $participant['email'] ) : null,
                'role'         => isset( $participant['role'] ) ? sanitize_text_field( $participant['role'] ) : 'viewer',
                'invite_token' => isset( $participant['invite_token'] ) ? sanitize_text_field( $participant['invite_token'] ) : Token::generate( 24 ),
            ];
        }

        return $clean;
    }
}

