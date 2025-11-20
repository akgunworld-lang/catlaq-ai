<?php
namespace Catlaq\Expo\Modules\Agreements;

use WP_Error;
use function __;

class Document_Service {
    private Document_Factory $factory;
    private Document_Signature_Service $signatures;

    public function __construct( ?Document_Factory $factory = null, ?Document_Signature_Service $signatures = null ) {
        $this->factory    = $factory ?: new Document_Factory();
        $this->signatures = $signatures ?: new Document_Signature_Service();
    }

    public function templates_for_user( array $filters = [] ): array {
        return Document_Registry::for_user( get_current_user_id(), $filters );
    }

    public function template( string $key ): ?array {
        return Document_Registry::find( $key );
    }

    public function generate( int $room_id, string $template_key, array $payload = [] ) {
        $template = Document_Registry::find( $template_key );
        if ( ! $template ) {
            return new WP_Error( 'catlaq_document_template_missing', __( 'Template not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        if ( ! Document_Registry::user_can_use( get_current_user_id(), $template ) ) {
            return new WP_Error( 'catlaq_document_forbidden', __( 'You do not have access to this template.', 'catlaq-online-expo' ), [ 'status' => 403 ] );
        }

        $context = $this->normalize_payload( $template, $payload );

        return $this->factory->generate_for_room( $room_id, $template_key, $context );
    }

    public function list_room_documents( int $room_id ): array {
        return $this->factory->list_for_room( $room_id );
    }

    public function get_document( int $document_id ): ?array {
        return $this->factory->get_document( $document_id );
    }

    public function recent_documents( int $limit = 20 ): array {
        return $this->factory->recent( $limit );
    }

    public function request_signature( int $document_id, array $payload ): int {
        if ( empty( $payload['signer_id'] ) && empty( $payload['signer_email'] ) ) {
            return 0;
        }

        return $this->signatures->request_signature( $document_id, $payload );
    }

    public function signatures_for_document( int $document_id ): array {
        return $this->signatures->list_for_document( $document_id );
    }

    public function recent_signatures( int $limit = 20 ): array {
        return $this->signatures->recent( $limit );
    }

    public function complete_signature( int $signature_id, array $payload = [] ): ?array {
        return $this->signatures->complete( $signature_id, $payload );
    }

    public function complete_signature_by_token( string $token, array $payload = [] ): ?array {
        return $this->signatures->complete_by_token( $token, $payload );
    }

    private function normalize_payload( array $template, array $payload ): array {
        $auto_fields = $template['auto_fields'] ?? [];

        $context = [
            'template'  => [
                'key'         => $template['key'] ?? array_search( $template, Document_Registry::all(), true ),
                'label'       => $template['label'] ?? '',
                'category'    => $template['category'] ?? '',
                'description' => $template['description'] ?? '',
            ],
            'generated' => [
                'by'   => get_current_user_id(),
                'name' => wp_get_current_user()->display_name ?? '',
                'at'   => current_time( 'mysql' ),
            ],
            'auto_fields' => [],
            'payload'     => $payload,
        ];

        foreach ( $auto_fields as $field ) {
            if ( isset( $payload[ $field ] ) ) {
                $context['auto_fields'][ $field ] = $payload[ $field ];
            } else {
                $context['auto_fields'][ $field ] = null;
            }
        }

        return $context;
    }
}
