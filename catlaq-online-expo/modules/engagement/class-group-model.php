<?php
namespace Catlaq\Expo\Modules\Engagement;

class Group_Model {
    private $groups_table;
    private $members_table;

    public function __construct() {
        global $wpdb;
        $this->groups_table  = $wpdb->prefix . 'catlaq_engagement_groups';
        $this->members_table = $wpdb->prefix . 'catlaq_engagement_group_members';
    }

    public function create( int $owner_user_id, string $name, string $description = '', string $visibility = 'public' ): int {
        global $wpdb;

        $slug = sanitize_title( $name . '-' . uniqid() );

        $wpdb->insert(
            $this->groups_table,
            [
                'name'          => sanitize_text_field( $name ),
                'slug'          => $slug,
                'description'   => wp_kses_post( $description ),
                'visibility'    => $this->sanitize_visibility( $visibility ),
                'owner_user_id' => $owner_user_id,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        $group_id = (int) $wpdb->insert_id;

        if ( $group_id ) {
            $this->assign_member( $group_id, $owner_user_id, 'owner' );
        }

        return $group_id;
    }

    public function find( int $group_id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->groups_table} WHERE id = %d",
                $group_id
            ),
            ARRAY_A
        );

        return $row ? $this->format_group( $row ) : null;
    }

    public function list( int $limit = 50, array $allowed_visibility = [] ): array {
        global $wpdb;

        $limit = max( 1, min( 200, $limit ) );

        $clauses = [];
        if ( ! empty( $allowed_visibility ) ) {
            $visibility_clause = $this->build_visibility_clause( $allowed_visibility );
            if ( '' === $visibility_clause ) {
                return [];
            }
            $clauses[] = $visibility_clause;
        }

        $where = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->groups_table} {$where} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map( [ $this, 'format_group' ], $rows );
    }

    public function assign_member( int $group_id, int $user_id, string $role = 'member' ): bool {
        global $wpdb;

        $wpdb->insert(
            $this->members_table,
            [
                'group_id' => $group_id,
                'user_id'  => $user_id,
                'role'     => sanitize_key( $role ),
                'status'   => 'active',
                'joined_at'=> current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );

        return (bool) $wpdb->insert_id;
    }

    public function members( int $group_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->members_table} WHERE group_id = %d",
                $group_id
            ),
            ARRAY_A
        ) ?: [];

        return array_map(
            function ( array $row ): array {
                $row['id'] = (int) $row['id'];
                return $row;
            },
            $rows
        );
    }

    private function format_group( array $row ): array {
        $row['id']            = (int) $row['id'];
        $row['owner_user_id'] = (int) $row['owner_user_id'];
        $row['visibility']    = $this->sanitize_visibility( $row['visibility'] ?? 'public' );
        return $row;
    }

    private function sanitize_visibility( string $visibility ): string {
        $visibility = sanitize_key( $visibility );
        $allowed    = [ 'public', 'premium', 'executive' ];

        if ( in_array( $visibility, $allowed, true ) ) {
            return $visibility;
        }

        return 'public';
    }

    private function build_visibility_clause( array $allowed ): string {
        $allowed = array_values( array_unique( array_filter( array_map( 'sanitize_key', $allowed ) ) ) );
        if ( empty( $allowed ) ) {
            return '';
        }

        $escaped = array_map( 'esc_sql', $allowed );
        $list    = "'" . implode( "','", $escaped ) . "'";


        return "visibility IN ({$list})";
    }
}




