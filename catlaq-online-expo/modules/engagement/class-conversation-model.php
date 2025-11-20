<?php
namespace Catlaq\Expo\Modules\Engagement;

class Conversation_Model {
    private $conversations;
    private $participants;
    private $messages;

    public function __construct() {
        global $wpdb;
        $this->conversations = $wpdb->prefix . 'catlaq_engagement_conversations';
        $this->participants  = $wpdb->prefix . 'catlaq_engagement_conversation_participants';
        $this->messages      = $wpdb->prefix . 'catlaq_engagement_messages';
    }

    public function create( array $user_ids, string $subject = '' ): int {
        global $wpdb;

        $user_ids = array_unique( array_map( 'absint', $user_ids ) );
        $user_ids = array_filter( $user_ids );
        if ( empty( $user_ids ) ) {
            return 0;
        }

        $wpdb->insert(
            $this->conversations,
            [
                'subject'    => $subject,
                'is_group'   => count( $user_ids ) > 2 ? 1 : 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%s', '%s' ]
        );

        $conversation_id = (int) $wpdb->insert_id;
        if ( ! $conversation_id ) {
            return 0;
        }

        foreach ( $user_ids as $id ) {
            $wpdb->insert(
                $this->participants,
                [
                    'conversation_id' => $conversation_id,
                    'user_id'         => $id,
                    'last_read_at'    => null,
                ],
                [ '%d', '%d', '%s' ]
            );
        }

        return $conversation_id;
    }

    public function add_message( int $conversation_id, int $sender_user_id, string $message, array $attachments = [] ): int {
        global $wpdb;

        $wpdb->insert(
            $this->messages,
            [
                'conversation_id' => $conversation_id,
                'sender_user_id'  => $sender_user_id,
                'message'         => $message,
                'attachments'     => ! empty( $attachments ) ? wp_json_encode( $attachments ) : null,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );

        $wpdb->update(
            $this->conversations,
            [
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $conversation_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function conversations_for( int $user_id, int $limit = 50 ): array {
        global $wpdb;

        $limit = max( 1, min( 200, $limit ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.* FROM {$this->conversations} c\n                INNER JOIN {$this->participants} p ON p.conversation_id = c.id\n                WHERE p.user_id = %d\n                ORDER BY c.updated_at DESC\n                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map( [ $this, 'format_conversation' ], $rows );
    }

    public function messages_for( int $conversation_id, int $limit = 100 ): array {
        global $wpdb;

        $limit = max( 1, min( 500, $limit ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->messages} WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
                $conversation_id,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        $rows = array_reverse( $rows );

        return array_map( [ $this, 'format_message' ], $rows );
    }

    public function participant_ids( int $conversation_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id FROM {$this->participants} WHERE conversation_id = %d",
                $conversation_id
            ),
            ARRAY_A
        ) ?: [];

        return array_map( 'intval', wp_list_pluck( $rows, 'user_id' ) );
    }

    private function format_conversation( array $row ): array {
        $row['id']        = (int) $row['id'];
        $row['is_group']  = (bool) $row['is_group'];
        return $row;
    }

    private function format_message( array $row ): array {
        $row['id']               = (int) $row['id'];
        $row['conversation_id']  = (int) $row['conversation_id'];
        $row['sender_user_id']   = (int) $row['sender_user_id'];
        if ( ! empty( $row['attachments'] ) ) {
            $decoded = json_decode( $row['attachments'], true );
            $row['attachments'] = is_array( $decoded ) ? $decoded : [];
        } else {
            $row['attachments'] = [];
        }
        return $row;
    }
}

