<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

use WP_Error;

class Booth_Model {
    private string $table;
    private string $sessions_table;

    public function __construct() {
        global $wpdb;
        $this->table          = $wpdb->prefix . 'catlaq_expo_booths';
        $this->sessions_table = $wpdb->prefix . 'catlaq_expo_sessions';
    }

    public function create( array $data ): int {
        global $wpdb;
        $defaults = [
            'company_id'        => 0,
            'title'             => '',
            'description'       => '',
            'sponsorship_level' => 'standard',
            'analytics'         => [],
            'status'            => 'draft',
        ];

        $payload = wp_parse_args( $data, $defaults );
        if ( empty( $payload['title'] ) ) {
            return 0;
        }

        $wpdb->insert(
            $this->table,
            [
                'company_id'        => (int) $payload['company_id'],
                'title'             => sanitize_text_field( $payload['title'] ),
                'description'       => wp_kses_post( $payload['description'] ),
                'sponsorship_level' => sanitize_key( $payload['sponsorship_level'] ),
                'analytics'         => wp_json_encode( (array) $payload['analytics'] ),
                'status'            => sanitize_key( $payload['status'] ),
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function update( int $booth_id, array $data ): bool {
        global $wpdb;
        $fields = [];

        foreach ( [ 'title', 'description', 'sponsorship_level', 'status' ] as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $fields[ $key ] = 'description' === $key ? wp_kses_post( $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
            }
        }

        if ( isset( $data['analytics'] ) ) {
            $fields['analytics'] = wp_json_encode( (array) $data['analytics'] );
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $fields['updated_at'] = current_time( 'mysql' );

        $result = $wpdb->update(
            $this->table,
            $fields,
            [ 'id' => $booth_id ],
            null,
            [ '%d' ]
        );

        return (bool) $result;
    }

    public function list_booths( int $limit = 20 ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, company_id, title, description, sponsorship_level, analytics, status, created_at, updated_at FROM {$this->table} ORDER BY sponsorship_level DESC, updated_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        foreach ( $rows as &$row ) {
            $row['analytics'] = json_decode( $row['analytics'] ?: '[]', true );
            $row['sessions']  = $this->sessions( (int) $row['id'] );
        }

        return $rows;
    }

    public function sessions( int $booth_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, booth_id, starts_at, ends_at, host, topic FROM {$this->sessions_table} WHERE booth_id = %d ORDER BY starts_at ASC",
                $booth_id
            ),
            ARRAY_A
        ) ?: [];
    }

    public function schedule_session( int $booth_id, array $data ): WP_Error|int {
        global $wpdb;

        $starts_at = $data['starts_at'] ?? '';
        $ends_at   = $data['ends_at'] ?? '';

        if ( empty( $starts_at ) || empty( $ends_at ) ) {
            return new WP_Error( 'catlaq_session_time', __( 'Session start/end required.', 'catlaq-online-expo' ) );
        }

        $wpdb->insert(
            $this->sessions_table,
            [
                'booth_id'  => $booth_id,
                'starts_at' => $starts_at,
                'ends_at'   => $ends_at,
                'host'      => sanitize_text_field( $data['host'] ?? '' ),
                'topic'     => sanitize_text_field( $data['topic'] ?? '' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }
}

