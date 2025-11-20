<?php
namespace Catlaq\Expo;

class Schema {
    public static function install_tables(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( self::get_table_sql() as $sql ) {
            dbDelta( $sql );
        }
    }

    private static function get_table_sql(): array {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        return [
            'catlaq_profiles' => "CREATE TABLE {$wpdb->prefix}catlaq_profiles (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                holistic_score float DEFAULT 0,
                onboarding_state varchar(32) DEFAULT 'pending',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_id (user_id)
            ) $charset;",
            'catlaq_companies' => "CREATE TABLE {$wpdb->prefix}catlaq_companies (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                owner_user_id bigint(20) unsigned NOT NULL,
                name varchar(255) NOT NULL,
                country char(2) DEFAULT '',
                kyc_status varchar(32) DEFAULT 'pending',
                trust_score float DEFAULT 0,
                membership_tier varchar(32) DEFAULT 'standard',
                membership_renewal datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
            'catlaq_products' => "CREATE TABLE {$wpdb->prefix}catlaq_products (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                company_id bigint(20) unsigned NOT NULL,
                wp_post_id bigint(20) unsigned DEFAULT NULL,
                name varchar(255) NOT NULL,
                sku varchar(64) DEFAULT '',
                unit varchar(32) DEFAULT '',
                min_order_qty decimal(14,4) DEFAULT 0,
                lead_time_days smallint DEFAULT 0,
                base_price decimal(14,4) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                status varchar(32) DEFAULT 'draft',
                visibility varchar(32) DEFAULT 'private',
                highlights longtext NULL,
                notes longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY company_id (company_id),
                KEY wp_post_id (wp_post_id)
            ) $charset;",
            'catlaq_product_variants' => "CREATE TABLE {$wpdb->prefix}catlaq_product_variants (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                product_id bigint(20) unsigned NOT NULL,
                label varchar(120) NOT NULL,
                sku varchar(64) DEFAULT '',
                attributes longtext NULL,
                stock_qty decimal(14,4) DEFAULT 0,
                stock_unit varchar(32) DEFAULT '',
                unit_price decimal(14,4) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                weight_kg decimal(14,4) DEFAULT 0,
                volume_cbm decimal(14,4) DEFAULT 0,
                status varchar(32) DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY product_id (product_id)
            ) $charset;",
            'catlaq_product_prices' => "CREATE TABLE {$wpdb->prefix}catlaq_product_prices (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                product_id bigint(20) unsigned NOT NULL,
                variant_id bigint(20) unsigned DEFAULT NULL,
                min_qty decimal(14,4) DEFAULT 0,
                max_qty decimal(14,4) DEFAULT 0,
                unit_price decimal(14,4) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                notes varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY variant_id (variant_id)
            ) $charset;",
            'catlaq_company_members' => "CREATE TABLE {$wpdb->prefix}catlaq_company_members (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                company_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                role varchar(32) NOT NULL,
                status varchar(32) DEFAULT 'active',
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY company_user (company_id,user_id)
            ) $charset;",
            'catlaq_rfq' => "CREATE TABLE {$wpdb->prefix}catlaq_rfq (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                buyer_company_id bigint(20) unsigned NOT NULL,
                status varchar(32) DEFAULT 'open',
                title varchar(255) NOT NULL,
                details longtext NULL,
                moderation_state varchar(32) DEFAULT 'pending',
                budget decimal(12,2) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                membership_tier varchar(32) DEFAULT 'standard',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
            'catlaq_agreement_rooms' => "CREATE TABLE {$wpdb->prefix}catlaq_agreement_rooms (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                rfq_id bigint(20) unsigned NOT NULL,
                room_status varchar(32) DEFAULT 'draft',
                opened_at datetime DEFAULT NULL,
                closed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY rfq (rfq_id)
            ) $charset;",
            'catlaq_agreement_participants' => "CREATE TABLE {$wpdb->prefix}catlaq_agreement_participants (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                email varchar(255) DEFAULT NULL,
                role varchar(32) NOT NULL,
                invite_token varchar(64) DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY room (room_id)
            ) $charset;",
            'catlaq_documents' => "CREATE TABLE {$wpdb->prefix}catlaq_documents (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_id bigint(20) unsigned NOT NULL,
                template_key varchar(64) NOT NULL,
                file_path varchar(255) NOT NULL,
                html_path varchar(255) DEFAULT NULL,
                pdf_path varchar(255) DEFAULT NULL,
                signature_status varchar(32) DEFAULT 'draft',
                created_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
            'catlaq_document_signatures' => "CREATE TABLE {$wpdb->prefix}catlaq_document_signatures (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                document_id bigint(20) unsigned NOT NULL,
                signer_id bigint(20) unsigned DEFAULT NULL,
                signer_email varchar(255) DEFAULT NULL,
                role varchar(32) DEFAULT 'participant',
                status varchar(32) DEFAULT 'pending',
                token varchar(64) DEFAULT NULL,
                metadata longtext NULL,
                signed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY document (document_id),
                KEY token (token)
            ) $charset;",
            'catlaq_orders' => "CREATE TABLE {$wpdb->prefix}catlaq_orders (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                rfq_id bigint(20) unsigned NOT NULL,
                buyer_company_id bigint(20) unsigned NOT NULL,
                seller_company_id bigint(20) unsigned NOT NULL,
                status varchar(32) DEFAULT 'draft',
                total_amount decimal(14,2) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                escrow_state varchar(32) DEFAULT 'pending',
                payment_state varchar(32) DEFAULT 'pending',
                logistics_state varchar(32) DEFAULT 'unassigned',
                milestones longtext NULL,
                metadata longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY rfq_id (rfq_id)
            ) $charset;",
            'catlaq_order_items' => "CREATE TABLE {$wpdb->prefix}catlaq_order_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned DEFAULT NULL,
                description varchar(255) NOT NULL,
                quantity decimal(14,4) DEFAULT 0,
                unit_price decimal(14,4) DEFAULT 0,
                total_weight decimal(14,4) DEFAULT 0,
                metadata longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY order_id (order_id)
            ) $charset;",
            'catlaq_order_status_log' => "CREATE TABLE {$wpdb->prefix}catlaq_order_status_log (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                status varchar(32) NOT NULL,
                note longtext NULL,
                actor bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY order_id (order_id)
            ) $charset;",
            'catlaq_disputes' => "CREATE TABLE {$wpdb->prefix}catlaq_disputes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                opened_by bigint(20) unsigned NOT NULL,
                role varchar(32) NOT NULL,
                reason varchar(64) NOT NULL,
                state varchar(32) DEFAULT 'open',
                evidence longtext NULL,
                resolution_note longtext NULL,
                opened_at datetime NOT NULL,
                closed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY order_id (order_id)
            ) $charset;",
            'catlaq_shipments' => "CREATE TABLE {$wpdb->prefix}catlaq_shipments (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                booking_ref varchar(64) NOT NULL,
                status varchar(32) DEFAULT 'draft',
                carrier varchar(64) DEFAULT '',
                incoterm varchar(16) DEFAULT 'FOB',
                pickup_location varchar(255) DEFAULT '',
                delivery_location varchar(255) DEFAULT '',
                packages int DEFAULT 0,
                total_weight decimal(14,4) DEFAULT 0,
                volume_cbm decimal(14,4) DEFAULT 0,
                tracking_number varchar(64) DEFAULT '',
                metadata longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY order_ref (order_id),
                KEY booking_ref (booking_ref)
            ) $charset;",
            'catlaq_shipment_events' => "CREATE TABLE {$wpdb->prefix}catlaq_shipment_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                shipment_id bigint(20) unsigned NOT NULL,
                event varchar(32) NOT NULL,
                note longtext NULL,
                actor bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY shipment (shipment_id)
            ) $charset;",
            'catlaq_payment_transactions' => "CREATE TABLE {$wpdb->prefix}catlaq_payment_transactions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                type varchar(32) NOT NULL,
                status varchar(32) DEFAULT 'pending',
                amount decimal(14,2) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                provider varchar(64) DEFAULT '',
                provider_ref varchar(128) DEFAULT '',
                metadata longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY order_id (order_id),
                KEY provider_ref (provider_ref)
            ) $charset;",
            'catlaq_score_events' => "CREATE TABLE {$wpdb->prefix}catlaq_score_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                entity_type varchar(16) NOT NULL,
                entity_id bigint(20) unsigned NOT NULL,
                delta float NOT NULL DEFAULT 0,
                score_after float NOT NULL DEFAULT 0,
                activity float DEFAULT 0,
                compliance float DEFAULT 0,
                trade float DEFAULT 0,
                flags longtext NULL,
                source varchar(64) DEFAULT '',
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY entity (entity_type, entity_id)
            ) $charset;",
            'catlaq_engagement_posts' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_posts (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                profile_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                group_id bigint(20) unsigned DEFAULT NULL,
                content longtext NOT NULL,
                attachments longtext NULL,
                visibility varchar(16) DEFAULT 'public',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY profile (profile_id),
                KEY group_idx (group_id)
            ) $charset;",
            'catlaq_engagement_groups' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_groups (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(120) NOT NULL,
                slug varchar(150) NOT NULL,
                description longtext NULL,
                visibility varchar(16) DEFAULT 'public',
                owner_user_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) $charset;",
            'catlaq_engagement_group_members' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_group_members (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                group_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                role varchar(32) DEFAULT 'member',
                status varchar(32) DEFAULT 'active',
                joined_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY membership (group_id,user_id)
            ) $charset;",
            'catlaq_engagement_conversations' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_conversations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                subject varchar(255) DEFAULT '',
                is_group tinyint(1) DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
            'catlaq_engagement_conversation_participants' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_conversation_participants (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                last_read_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY participant (conversation_id,user_id)
            ) $charset;",
            'catlaq_engagement_messages' => "CREATE TABLE {$wpdb->prefix}catlaq_engagement_messages (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) unsigned NOT NULL,
                sender_user_id bigint(20) unsigned NOT NULL,
                message longtext NOT NULL,
                attachments longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY conversation (conversation_id)
            ) $charset;",
            'catlaq_expo_booths' => "CREATE TABLE {$wpdb->prefix}catlaq_expo_booths (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                company_id bigint(20) unsigned NOT NULL,
                title varchar(255) NOT NULL,
                description longtext NULL,
                sponsorship_level varchar(32) DEFAULT 'standard',
                analytics longtext NULL,
                status varchar(32) DEFAULT 'draft',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
            'catlaq_expo_sessions' => "CREATE TABLE {$wpdb->prefix}catlaq_expo_sessions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booth_id bigint(20) unsigned NOT NULL,
                starts_at datetime NOT NULL,
                ends_at datetime NOT NULL,
                host varchar(255) NOT NULL,
                topic varchar(255) NOT NULL,
                PRIMARY KEY (id),
                KEY booth_id (booth_id)
            ) $charset;",
            'catlaq_memberships' => "CREATE TABLE {$wpdb->prefix}catlaq_memberships (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                slug varchar(64) NOT NULL,
                label varchar(120) NOT NULL,
                price decimal(10,2) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                features longtext NULL,
                quotas longtext NULL,
                status varchar(20) DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) $charset;",
            'catlaq_usage' => "CREATE TABLE {$wpdb->prefix}catlaq_usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                membership_id bigint(20) unsigned NOT NULL,
                metric varchar(64) NOT NULL,
                used int unsigned DEFAULT 0,
                limit_value int unsigned DEFAULT 0,
                reset_at datetime DEFAULT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY user_metric (user_id, metric)
            ) $charset;",
            'catlaq_membership_invoices' => "CREATE TABLE {$wpdb->prefix}catlaq_membership_invoices (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                plan_slug varchar(64) NOT NULL,
                plan_label varchar(120) DEFAULT '',
                amount decimal(10,2) DEFAULT 0,
                currency char(3) DEFAULT 'USD',
                status varchar(32) DEFAULT 'pending',
                provider varchar(64) DEFAULT '',
                provider_ref varchar(128) DEFAULT '',
                checkout_url varchar(255) DEFAULT '',
                metadata longtext NULL,
                paid_at datetime DEFAULT NULL,
                renewal_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY user_plan (user_id, plan_slug),
                KEY provider_ref (provider_ref)
            ) $charset;",
            'catlaq_ai_logs' => "CREATE TABLE {$wpdb->prefix}catlaq_ai_logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned DEFAULT NULL,
                agent varchar(60) NOT NULL,
                context varchar(255) DEFAULT '',
                encrypted_payload longtext NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY user_agent (user_id, agent)
            ) $charset;",
            'catlaq_file_hashes' => "CREATE TABLE {$wpdb->prefix}catlaq_file_hashes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                path varchar(255) NOT NULL,
                hash char(64) NOT NULL,
                last_scanned datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY path (path)
            ) $charset;",
            'catlaq_audit_log' => "CREATE TABLE {$wpdb->prefix}catlaq_audit_log (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                actor bigint(20) unsigned DEFAULT NULL,
                action varchar(64) NOT NULL,
                context longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id)
            ) $charset;",
        ];
    }

    public static function table_names(): array {
        return array_keys( self::get_table_sql() );
    }
}
