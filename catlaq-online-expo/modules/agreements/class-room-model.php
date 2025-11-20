<?php
namespace Catlaq\Expo\Modules\Agreements;

use WP_Error;

class Room_Model {
    private $rooms_table;
    private $participants_table;
    private $documents;

    public function __construct( ?Document_Factory $documents = null ) {
        global $wpdb;
        $this->rooms_table        = $wpdb->prefix . 'catlaq_agreement_rooms';
        $this->participants_table = $wpdb->prefix . 'catlaq_agreement_participants';
        $this->documents          = $documents ?: new Document_Factory();
    }

    public function boot(): void {
        // Reserved for future hooks.
    }

    public function create( int $rfq_id, array $args = [] ): int {
        global $wpdb;

        $status = $args['room_status'] ?? 'draft';

        $wpdb->insert(
            $this->rooms_table,
            [
                'rfq_id'      => $rfq_id,
                'room_status' => $status,
                'opened_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s' ]
        );

        $room_id = (int) $wpdb->insert_id;

        if ( $room_id && ! empty( $args['participants'] ) && is_array( $args['participants'] ) ) {
            foreach ( $args['participants'] as $participant ) {
                $this->add_participant( $room_id, $participant );
            }
        }

        return $room_id;
    }

    public function close( int $room_id, string $status = 'closed' ): bool {
        global $wpdb;
        $updated = $wpdb->update(
            $this->rooms_table,
            [
                'room_status' => $status,
                'closed_at'   => current_time( 'mysql' ),
            ],
            [ 'id' => $room_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return (bool) $updated;
    }

    public function add_participant( int $room_id, array $participant ): void {
        global $wpdb;

        $wpdb->insert(
            $this->participants_table,
            [
                'room_id'      => $room_id,
                'user_id'      => $participant['user_id'] ?? null,
                'email'        => $participant['email'] ?? null,
                'role'         => $participant['role'] ?? 'viewer',
                'invite_token' => $participant['invite_token'] ?? null,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    public function list( int $limit = 50 ): array {
        global $wpdb;

        $limit = max( 1, $limit );

        $rooms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, rfq_id, room_status, opened_at, closed_at FROM {$this->rooms_table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        foreach ( $rooms as &$room ) {
            $room['participants'] = $this->participants( (int) $room['id'] );
            $room['documents']    = $this->documents->list_for_room( (int) $room['id'] );
        }

        return $rooms;
    }

    public function get( int $room_id ): ?array {
        global $wpdb;

        $room = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, rfq_id, room_status, opened_at, closed_at FROM {$this->rooms_table} WHERE id = %d",
                $room_id
            ),
            ARRAY_A
        );

        if ( ! $room ) {
            return null;
        }

        $room['participants'] = $this->participants( $room_id );
        $room['documents']    = $this->documents->list_for_room( $room_id );

        return $room;
    }

    private function participants( int $room_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, room_id, user_id, email, role, invite_token, created_at FROM {$this->participants_table} WHERE room_id = %d ORDER BY id ASC",
                $room_id
            ),
            ARRAY_A
        ) ?: [];
    }
}
