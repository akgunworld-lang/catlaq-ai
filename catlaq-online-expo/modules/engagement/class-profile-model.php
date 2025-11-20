<?php
namespace Catlaq\Expo\Modules\Engagement;

class Profile_Model {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'catlaq_profiles';
    }

    public function create( int $user_id ): int {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'user_id'          => $user_id,
                'holistic_score'   => 0,
                'onboarding_state' => 'pending',
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ '%d', '%f', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function get( int $profile_id ): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $profile_id
            ),
            ARRAY_A
        );

        return $row ? $this->format_profile( $row ) : [];
    }

    public function get_by_user( int $user_id ): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        return $row ? $this->format_profile( $row ) : [];
    }

    public function ensure( int $user_id ): array {
        $profile = $this->get_by_user( $user_id );
        if ( ! empty( $profile ) ) {
            return $profile;
        }

        $profile_id = $this->create( $user_id );
        if ( ! $profile_id ) {
            return [];
        }

        return $this->get( $profile_id );
    }

    public function list( int $limit = 50 ): array {
        global $wpdb;

        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, holistic_score, onboarding_state FROM {$this->table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    private function format_profile( array $row ): array {
        $row['id']             = (int) $row['id'];
        $row['user_id']        = (int) $row['user_id'];
        $row['holistic_score'] = (float) $row['holistic_score'];
        return $row;
    }
}
