<?php
namespace Catlaq\Expo;

class Migrator {
    const OPTION_KEY = 'catlaq_db_version';
    const PLUGIN_DB_VERSION = '2024.11.30';

    public static function maybe_upgrade(): void {
        $current = get_option( self::OPTION_KEY, '0' );
        if ( version_compare( $current, self::PLUGIN_DB_VERSION, '>=' ) ) {
            return;
        }

        self::run_migrations( $current );
        update_option( self::OPTION_KEY, self::PLUGIN_DB_VERSION );
    }

    private static function run_migrations( string $from ): void {
        Schema::install_tables();

        if ( version_compare( $from, '2024.11.20', '<' ) ) {
            self::ensure_ai_secret();
            self::seed_memberships();
        }

        if ( version_compare( $from, '2024.11.30', '<' ) ) {
            self::rename_legacy_engagement_tables();
        }
    }

    private static function ensure_ai_secret(): void {
        $secret = get_option( 'catlaq_ai_secret' );
        if ( ! empty( $secret ) ) {
            return;
        }

        $bytes  = random_bytes( 32 );
        $secret = bin2hex( $bytes );
        update_option( 'catlaq_ai_secret', $secret );
    }

    private static function seed_memberships(): void {
        global $wpdb;

        $table = "{$wpdb->prefix}catlaq_memberships";
        $plans = [
            [
                'slug'     => 'buyer_core',
                'label'    => 'Buyer Core Pass',
                'price'    => 0,
                'currency' => 'USD',
                'features' => [
                    'rfq_access' => 'Browse booths + 20 RFQ submissions / month',
                    'agreements' => 'Join up to 3 agreement rooms',
                    'visibility' => 'Expo analytics snapshots',
                ],
                'quotas'   => [
                    'rfq_requests'    => 20,
                    'agreement_rooms' => 3,
                ],
            ],
            [
                'slug'     => 'buyer_enterprise',
                'label'    => 'Buyer Enterprise Fleet',
                'price'    => 189,
                'currency' => 'USD',
                'features' => [
                    'rfq_access' => 'Unlimited booth browsing + 120 RFQs / month',
                    'agreements' => 'Full agreement + signature workflow',
                    'logistics'  => 'Shipment status dashboard',
                ],
                'quotas'   => [
                    'rfq_requests'    => 120,
                    'agreement_rooms' => 15,
                ],
            ],
            [
                'slug'     => 'seller_showcase',
                'label'    => 'Seller Showcase',
                'price'    => 149,
                'currency' => 'USD',
                'features' => [
                    'booths'     => 'Up to 5 digital booths with media',
                    'rfq'        => 'Respond to global RFQs',
                    'compliance' => 'AI document pre-checks',
                ],
                'quotas'   => [
                    'product_publish' => 50,
                    'rfq_responses'   => 150,
                ],
            ],
            [
                'slug'     => 'seller_enterprise',
                'label'    => 'Seller Enterprise Hub',
                'price'    => 329,
                'currency' => 'USD',
                'features' => [
                    'booths'      => 'Unlimited booths + team analytics',
                    'agreements'  => 'Escrow + milestone automation',
                    'marketing'   => 'Featured placement on Expo',
                ],
                'quotas'   => [
                    'product_publish' => 200,
                    'rfq_responses'   => 500,
                ],
            ],
            [
                'slug'     => 'broker_ops',
                'label'    => 'Broker Ops Suite',
                'price'    => 229,
                'currency' => 'USD',
                'features' => [
                    'broker'    => 'Deal rooms + commission ledger',
                    'compliance'=> 'Dispute & audit automation',
                    'network'   => 'Access to buyer/seller leads',
                ],
                'quotas'   => [
                    'broker_rooms' => 80,
                ],
            ],
            [
                'slug'     => 'logistics_partner',
                'label'    => 'Logistics Partner',
                'price'    => 129,
                'currency' => 'USD',
                'features' => [
                    'shipments' => 'Publish service corridors + quotes',
                    'tracking'  => 'Integrate tracking + alerts',
                    'support'   => 'AI anomaly detection',
                ],
                'quotas'   => [
                    'shipment_tracks' => 300,
                ],
            ],
            [
                'slug'     => 'support_vendor',
                'label'    => 'Support Vendor Access',
                'price'    => 79,
                'currency' => 'USD',
                'features' => [
                    'services'  => 'Offer QC, finance, insurance support',
                    'directory' => 'Listed in Catlaq vendor hub',
                    'engagement'=> 'Post expertise updates',
                ],
                'quotas'   => [
                    'service_posts' => 40,
                ],
            ],
        ];

        foreach ( $plans as $plan ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                    $plan['slug']
                )
            );

            if ( $exists ) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'slug'       => $plan['slug'],
                    'label'      => $plan['label'],
                    'price'      => $plan['price'],
                    'currency'   => $plan['currency'],
                    'features'   => wp_json_encode( $plan['features'] ),
                    'quotas'     => wp_json_encode( $plan['quotas'] ),
                    'status'     => 'active',
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [
                    '%s',
                    '%s',
                    '%f',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    private static function rename_legacy_engagement_tables(): void {
        global $wpdb;

        $map = [
            'catlaq_social_posts'                    => 'catlaq_engagement_posts',
            'catlaq_social_groups'                   => 'catlaq_engagement_groups',
            'catlaq_social_group_members'            => 'catlaq_engagement_group_members',
            'catlaq_social_conversations'            => 'catlaq_engagement_conversations',
            'catlaq_social_conversation_participants'=> 'catlaq_engagement_conversation_participants',
            'catlaq_social_messages'                 => 'catlaq_engagement_messages',
        ];

        foreach ( $map as $legacy => $modern ) {
            $legacy_table = $wpdb->prefix . $legacy;
            $modern_table = $wpdb->prefix . $modern;

            $legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table;
            $modern_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modern_table ) ) === $modern_table;

            if ( $legacy_exists && ! $modern_exists ) {
                $wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$modern_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }
    }
}

