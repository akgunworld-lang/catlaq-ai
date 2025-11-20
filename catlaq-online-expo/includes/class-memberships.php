<?php
namespace Catlaq\Expo;

class Memberships {
    private string $table;
    private string $usage_table;

    public function __construct() {
        global $wpdb;
        $this->table       = "{$wpdb->prefix}catlaq_memberships";
        $this->usage_table = "{$wpdb->prefix}catlaq_usage";
    }

    public function all(): array {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY price ASC", ARRAY_A );
        foreach ( $rows as &$row ) {
            $row['features'] = json_decode( $row['features'] ?? '[]', true );
            $row['quotas']   = json_decode( $row['quotas'] ?? '[]', true );
        }
        return $rows;
    }

    public function find_by_slug( string $slug ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1", $slug ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['features'] = json_decode( $row['features'] ?? '[]', true );
        $row['quotas']   = json_decode( $row['quotas'] ?? '[]', true );
        return $row;
    }

    public function user_membership( int $user_id ): ?array {
        $profile = get_user_meta( $user_id, 'catlaq_membership_slug', true );
        if ( ! $profile ) {
            return null;
        }

        return $this->find_by_slug( $profile );
    }

    public function get_usage( int $user_id, string $metric ): array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->usage_table} WHERE user_id = %d AND metric = %s LIMIT 1",
                $user_id,
                $metric
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return [
                'used'        => 0,
                'limit_value' => 0,
                'reset_at'    => null,
            ];
        }

        return $row;
    }

    public function track_usage( int $user_id, string $metric, int $delta, int $limit_value = 0 ): void {
        global $wpdb;
        $existing = $this->get_usage( $user_id, $metric );
        $used     = (int) $existing['used'] + $delta;
        $limit    = $limit_value ?: (int) $existing['limit_value'];

        if ( isset( $existing['used'] ) ) {
            $wpdb->update(
                $this->usage_table,
                [
                    'used'       => max( 0, $used ),
                    'limit_value'=> max( 0, $limit ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [
                    'user_id' => $user_id,
                    'metric'  => $metric,
                ],
                [ '%d', '%d', '%s' ],
                [ '%d', '%s' ]
            );
        } else {
            $wpdb->insert(
                $this->usage_table,
                [
                    'user_id'     => $user_id,
                    'membership_id'=> 0,
                    'metric'      => $metric,
                    'used'        => max( 0, $used ),
                    'limit_value' => max( 0, $limit ),
                    'reset_at'    => null,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%s', '%d', '%d', '%s', '%s' ]
            );
        }
    }

    public function quota_status( int $user_id, string $metric, int $default_limit = 0 ): array {
        $usage = $this->get_usage( $user_id, $metric );
        $limit = (int) ( $usage['limit_value'] ?: $default_limit );
        $used  = (int) $usage['used'];

        return [
            'metric'   => $metric,
            'used'     => $used,
            'limit'    => $limit,
            'remaining'=> $limit > 0 ? max( 0, $limit - $used ) : null,
            'reset_at' => $usage['reset_at'] ?? null,
        ];
    }

    public function assign_user_plan( int $user_id, string $plan_slug ): bool {
        $plan_slug = sanitize_key( $plan_slug );
        if ( '' === $plan_slug ) {
            return false;
        }

        $plan = $this->find_by_slug( $plan_slug );
        if ( ! $plan ) {
            return false;
        }

        update_user_meta( $user_id, 'catlaq_membership_slug', $plan_slug );
        update_user_meta( $user_id, 'catlaq_membership_assigned_at', current_time( 'mysql' ) );

        return true;
    }
}
