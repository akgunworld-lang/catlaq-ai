<?php
namespace Catlaq\Expo\Modules\Engagement;

class Post_Model {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'catlaq_engagement_posts';
    }

    public function create( int $profile_id, int $user_id, string $content, array $args = [] ): int {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'profile_id'  => $profile_id,
                'user_id'     => $user_id,
                'group_id'    => isset( $args['group_id'] ) ? absint( $args['group_id'] ) : null,
                'content'     => $content,
                'attachments' => ! empty( $args['attachments'] ) ? wp_json_encode( $args['attachments'] ) : null,
                'visibility'  => $this->normalize_visibility( (string) ( $args['visibility'] ?? 'public' ) ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function feed( int $limit = 50, ?int $group_id = null, array $allowed_visibility = [] ): array {
        global $wpdb;

        $limit = max( 1, min( 200, $limit ) );
        if ( empty( $allowed_visibility ) ) {
            $allowed_visibility = [ 'public' ];
        }

        $clauses = [];
        if ( $group_id ) {
            $clauses[] = $wpdb->prepare( 'group_id = %d', $group_id );
        }

        $visibility_clause = $this->build_visibility_clause( $allowed_visibility );
        if ( $visibility_clause ) {
            $clauses[] = $visibility_clause;
        }

        $where = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';

        if ( empty( $visibility_clause ) ) {
            return [];
        }

        $results = $wpdb->get_results(
            "SELECT id, profile_id, user_id, group_id, content, attachments, visibility, created_at, updated_at
            FROM {$this->table}
            {$where}
            ORDER BY id DESC
            LIMIT {$limit}",
            ARRAY_A
        ) ?: [];

        return array_map( [ $this, 'format_post' ], $results );
    }

    private function format_post( array $row ): array {
        $row['id']         = (int) $row['id'];
        $row['profile_id'] = (int) $row['profile_id'];
        $row['user_id']    = (int) $row['user_id'];
        $row['group_id']   = $row['group_id'] ? (int) $row['group_id'] : null;

        if ( ! empty( $row['attachments'] ) ) {
            $decoded = json_decode( $row['attachments'], true );
            $row['attachments'] = is_array( $decoded ) ? $decoded : [];
        } else {
            $row['attachments'] = [];
        }

        $row['author'] = $this->resolve_author( $row['user_id'], $row['profile_id'] );

        return $row;
    }

    private function resolve_author( int $user_id, int $profile_id ): array {
        $author = [
            'user_id'    => $user_id,
            'profile_id' => $profile_id,
            'display'    => '',
        ];

        $user = get_userdata( $user_id );
        if ( $user ) {
            $author['display'] = $user->display_name;
        }

        return $author;
    }

    private function normalize_visibility( string $visibility ): string {
        $visibility = sanitize_key( $visibility );
        $allowed    = [ 'public', 'premium', 'executive' ];

        if ( in_array( $visibility, $allowed, true ) ) {
            return $visibility;
        }

        return 'public';
    }

    private function build_visibility_clause( array $allowed ): string {
        global $wpdb;

        $allowed = array_values( array_unique( array_filter( array_map( 'sanitize_key', $allowed ) ) ) );
        if ( empty( $allowed ) ) {
            return '';
        }

        $escaped = array_map( 'esc_sql', $allowed );
        $list    = "'" . implode( "','", $escaped ) . "'";

        return "visibility IN ({$list})";
    }
}
