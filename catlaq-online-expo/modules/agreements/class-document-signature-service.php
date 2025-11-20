<?php
namespace Catlaq\Expo\Modules\Agreements;

class Document_Signature_Service {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'catlaq_document_signatures';
    }

    public function request_signature( int $document_id, array $args ): int {
        global $wpdb;
        $token = wp_generate_password( 24, false, false );
        $wpdb->insert(
            $this->table,
            [
                'document_id'  => $document_id,
                'signer_id'    => isset( $args['signer_id'] ) ? (int) $args['signer_id'] : null,
                'signer_email' => sanitize_email( $args['signer_email'] ?? '' ),
                'role'         => sanitize_key( $args['role'] ?? 'participant' ),
                'status'       => 'pending',
                'token'        => $token,
                'metadata'     => wp_json_encode( $args['metadata'] ?? [] ),
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function list_for_document( int $document_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE document_id = %d ORDER BY id ASC",
                $document_id
            ),
            ARRAY_A
        ) ?: [];

        return array_map( [ $this, 'hydrate' ], $rows );
    }

    public function recent( int $limit = 20 ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map( [ $this, 'hydrate' ], $rows );
    }

    public function complete( int $signature_id, array $args = [] ): ?array {
        global $wpdb;
        $update = [
            'status'    => sanitize_key( $args['status'] ?? 'signed' ),
            'metadata'  => wp_json_encode( $args['metadata'] ?? [] ),
            'signed_at' => current_time( 'mysql' ),
        ];

        if ( isset( $args['signer_id'] ) && $args['signer_id'] ) {
            $update['signer_id'] = (int) $args['signer_id'];
        }

        $formats = [];
        foreach ( array_keys( $update ) as $key ) {
            $formats[] = 'signer_id' === $key ? '%d' : '%s';
        }

        $wpdb->update(
            $this->table,
            $update,
            [ 'id' => $signature_id ],
            $formats,
            [ '%d' ]
        );

        return $this->get( $signature_id );
    }

    public function complete_by_token( string $token, array $args = [] ): ?array {
        $signature = $this->find_by_token( $token );
        if ( ! $signature ) {
            return null;
        }

        return $this->complete( (int) $signature['id'], $args );
    }

    public function get( int $signature_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $signature_id
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function find_by_token( string $token ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE token = %s",
                $token
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    private function hydrate( array $row ): array {
        $row['metadata'] = json_decode( $row['metadata'] ?? '{}', true );
        return $row;
    }
}
