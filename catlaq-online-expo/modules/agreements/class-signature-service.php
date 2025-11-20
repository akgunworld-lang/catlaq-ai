<?php
namespace Catlaq\Expo\Modules\Agreements;

use WP_Error;

class Signature_Service {
    /**
     * @var Document_Factory
     */
    private $documents;

    public function __construct( ?Document_Factory $documents = null ) {
        $this->documents = $documents ?: new Document_Factory();
    }

    public function request_signature( array $document, array $participants ): bool {
        if ( empty( $document['id'] ) ) {
            return false;
        }

        $entry = array(
            'document_id'  => (int) $document['id'],
            'room_id'      => (int) $document['room_id'],
            'requested_by' => get_current_user_id() ?: 0,
            'participants' => $participants,
            'requested_at' => current_time( 'mysql' ),
            'status'       => 'pending',
        );

        $this->documents->update_signature_status( $entry['document_id'], 'pending' );
        $this->append_log( $entry );

        do_action( 'catlaq_signature_requested', $entry );

        return true;
    }

    public function mark_signed( int $document_id, int $actor_id = 0 ): array|WP_Error {
        $document = $this->documents->get_document( $document_id );
        if ( ! $document ) {
            return new WP_Error( 'catlaq_document_missing', __( 'Document not found.', 'catlaq-online-expo' ), array( 'status' => 404 ) );
        }

        $this->documents->update_signature_status( $document_id, 'signed' );

        $entry = array(
            'document_id' => $document_id,
            'room_id'     => (int) $document['room_id'],
            'actor_id'    => $actor_id ?: get_current_user_id() ?: 0,
            'signed_at'   => current_time( 'mysql' ),
            'status'      => 'signed',
        );

        $this->append_log( $entry );
        do_action( 'catlaq_signature_completed', $entry );

        return $document;
    }

    public function log(): array {
        return array_reverse( get_option( 'catlaq_signature_log', array() ) );
    }

    private function append_log( array $entry ): void {
        $log   = get_option( 'catlaq_signature_log', array() );
        $log[] = $entry;
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }
        update_option( 'catlaq_signature_log', $log );
    }
}
