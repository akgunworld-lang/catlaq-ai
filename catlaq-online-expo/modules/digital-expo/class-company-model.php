<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

use WP_Error;

class Company_Model {
    /**
     * @var string
     */
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'catlaq_companies';
    }

    public function all( int $limit = 25 ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, country, kyc_status, trust_score, membership_tier, membership_renewal FROM {$this->table} ORDER BY updated_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    public function find( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }


    public function find_by_owner( int $user_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE owner_user_id = %d ORDER BY id ASC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function create( array $data ): int {
        global $wpdb;
        $defaults = array(
            'owner_user_id'    => get_current_user_id() ?: 0,
            'name'             => '',
            'country'          => '',
            'kyc_status'       => 'pending',
            'trust_score'      => 0,
            'membership_tier'  => 'standard',
            'membership_renewal' => null,
        );

        $payload = wp_parse_args( $data, $defaults );
        if ( empty( $payload['name'] ) ) {
            return 0;
        }

        $wpdb->insert(
            $this->table,
            array(
                'owner_user_id'     => (int) $payload['owner_user_id'],
                'name'              => sanitize_text_field( $payload['name'] ),
                'country'           => strtoupper( substr( sanitize_text_field( $payload['country'] ), 0, 2 ) ),
                'kyc_status'        => sanitize_text_field( $payload['kyc_status'] ),
                'trust_score'       => (float) $payload['trust_score'],
                'membership_tier'   => sanitize_key( $payload['membership_tier'] ),
                'membership_renewal'=> $payload['membership_renewal'],
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function update_membership( int $company_id, string $tier ): bool {
        global $wpdb;
        $tier = sanitize_key( $tier );
        $result = $wpdb->update(
            $this->table,
            array(
                'membership_tier' => $tier,
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( 'id' => $company_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return (bool) $result;
    }

    public function membership_tiers(): array {
        $stored = get_option( 'catlaq_membership_tiers', array() );
        if ( empty( $stored ) || ! is_array( $stored ) ) {
            $stored = array(
                'standard'   => array(
                    'label'          => __( 'Standard', 'catlaq-online-expo' ),
                    'max_open_rfq'   => 3,
                    'escrow_required'=> false,
                ),
                'pro'        => array(
                    'label'          => __( 'Pro', 'catlaq-online-expo' ),
                    'max_open_rfq'   => 10,
                    'escrow_required'=> true,
                ),
                'enterprise' => array(
                    'label'          => __( 'Enterprise', 'catlaq-online-expo' ),
                    'max_open_rfq'   => 50,
                    'escrow_required'=> true,
                ),
            );
        }

        return apply_filters( 'catlaq_membership_tiers', $stored );
    }

    public function get_membership( string $tier ): array {
        $tiers = $this->membership_tiers();
        if ( isset( $tiers[ $tier ] ) ) {
            return $tiers[ $tier ];
        }

        return $tiers['standard'];
    }

    public function get_company_membership_slug( int $company_id ): string {
        $company = $this->find( $company_id );
        return $company['membership_tier'] ?? 'standard';
    }

    public function company_can_create_rfq( int $company_id, string $tier ): WP_Error|bool {
        $limits = $this->get_membership( $tier );
        $max    = (int) ( $limits['max_open_rfq'] ?? 3 );

        if ( $max <= 0 ) {
            return new WP_Error( 'catlaq_membership_blocked', __( 'Membership tier cannot create RFQs.', 'catlaq-online-expo' ) );
        }

        if ( $this->count_open_rfq( $company_id ) >= $max ) {
            return new WP_Error(
                'catlaq_membership_limit',
                sprintf(
                    /* translators: %d max RFQ count */
                    __( 'This membership tier allows %d open RFQs. Please close or upgrade to continue.', 'catlaq-online-expo' ),
                    $max
                )
            );
        }

        return true;
    }

    private function count_open_rfq( int $company_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'catlaq_rfq';
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE buyer_company_id = %d AND status IN ('open','negotiating')",
                $company_id
            )
        );
    }
}
