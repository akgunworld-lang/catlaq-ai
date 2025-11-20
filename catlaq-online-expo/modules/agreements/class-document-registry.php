<?php
namespace Catlaq\Expo\Modules\Agreements;

use function __;

class Document_Registry {
    public static function all(): array {
        return self::templates();
    }

    public static function find( string $key ): ?array {
        $templates = self::templates();
        return $templates[ $key ] ?? null;
    }

    public static function for_user( int $user_id = 0, array $filters = [] ): array {
        $user_id = $user_id ?: get_current_user_id();
        $templates = self::templates();
        $results = [];
        foreach ( $templates as $key => $template ) {
            if ( ! self::user_can_use( $user_id, $template ) ) {
                continue;
            }
            if ( ! empty( $filters['category'] ) && $filters['category'] !== $template['category'] ) {
                continue;
            }
            $results[ $key ] = $template;
        }
        return $results;
    }

    public static function user_can_use( int $user_id, array $template ): bool {
        $cap = $template['capability'] ?? 'read';
        $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
        if ( ! $user || 0 === $user->ID ) {
            return false;
        }
        return user_can( $user, $cap );
    }

    private static function templates(): array {
        return [
            'nda_nca' => [
                'key'         => 'nda_nca',
                'label'       => __( 'NDA & NCA Agreement', 'catlaq-online-expo' ),
                'category'    => 'legal',
                'description' => __( 'Baseline confidentiality + non-circumvention agreement between parties and Catlaq.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'party_names', 'effective_date', 'duration', 'governing_law' ],
                'roles'       => [ 'buyer', 'seller', 'broker', 'admin' ],
                'capability'  => 'read',
            ],
            'brokerage_agreement' => [
                'key'         => 'brokerage_agreement',
                'label'       => __( 'Brokerage Agreement', 'catlaq-online-expo' ),
                'category'    => 'legal',
                'description' => __( 'Defines broker scope, commission and reporting duties.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'commission_rate', 'scope', 'visit_requirements' ],
                'roles'       => [ 'seller', 'broker', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'proforma_invoice' => [
                'key'         => 'proforma_invoice',
                'label'       => __( 'Proforma Invoice', 'catlaq-online-expo' ),
                'category'    => 'finance',
                'description' => __( 'Initial invoice outlining line totals, incoterms and payment milestones.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'line_totals', 'gross_weight', 'net_weight', 'incoterms', 'milestones' ],
                'roles'       => [ 'seller', 'buyer', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'purchase_order' => [
                'key'         => 'purchase_order',
                'label'       => __( 'Purchase Order', 'catlaq-online-expo' ),
                'category'    => 'finance',
                'description' => __( 'Buyer-issued commitment listing product lines, delivery schedule and payment plan.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'order_number', 'delivery_window', 'billing_address', 'shipping_address' ],
                'roles'       => [ 'buyer', 'seller', 'admin' ],
                'capability'  => 'read',
            ],
            'commercial_invoice' => [
                'key'         => 'commercial_invoice',
                'label'       => __( 'Commercial Invoice', 'catlaq-online-expo' ),
                'category'    => 'finance',
                'description' => __( 'Official invoice used for customs and settlements.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'hs_codes', 'tax_amount', 'total_value', 'payment_reference' ],
                'roles'       => [ 'seller', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'packing_list' => [
                'key'         => 'packing_list',
                'label'       => __( 'Packing List', 'catlaq-online-expo' ),
                'category'    => 'logistics',
                'description' => __( 'Details packages, dimensions and weight for the shipment.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'package_count', 'dimensions', 'weight', 'marks_numbers' ],
                'roles'       => [ 'seller', 'logistics', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'certificate_of_origin' => [
                'key'         => 'certificate_of_origin',
                'label'       => __( 'Certificate of Origin', 'catlaq-online-expo' ),
                'category'    => 'logistics',
                'description' => __( 'Declares manufacturing origin for customs authorities.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'manufacturer', 'country_of_origin', 'issuing_body' ],
                'roles'       => [ 'seller', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'inspection_visit_report' => [
                'key'         => 'inspection_visit_report',
                'label'       => __( 'Inspection / Visit Report', 'catlaq-online-expo' ),
                'category'    => 'logistics',
                'description' => __( 'Broker or QA agent report for factory visits.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'visit_date', 'inspector', 'findings', 'attachments' ],
                'roles'       => [ 'broker', 'admin' ],
                'capability'  => 'edit_pages',
            ],
            'logistics_booking' => [
                'key'         => 'logistics_booking',
                'label'       => __( 'Logistics Booking Form', 'catlaq-online-expo' ),
                'category'    => 'logistics',
                'description' => __( 'Captures forwarder selection, container type and loading windows.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'forwarder', 'container', 'loading_port', 'arrival_port' ],
                'roles'       => [ 'seller', 'logistics', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'payment_schedule' => [
                'key'         => 'payment_schedule',
                'label'       => __( 'Payment Schedule', 'catlaq-online-expo' ),
                'category'    => 'finance',
                'description' => __( 'Maps escrow releases to production milestones.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'milestones', 'percentages', 'due_dates' ],
                'roles'       => [ 'buyer', 'seller', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'return_policy' => [
                'key'         => 'return_policy',
                'label'       => __( 'Return & Dispute Policy', 'catlaq-online-expo' ),
                'category'    => 'legal',
                'description' => __( 'Guarantee and dispute handling document tailored to membership tier.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'membership_tier', 'return_window', 'warehouse_address' ],
                'roles'       => [ 'seller', 'admin' ],
                'capability'  => 'edit_posts',
            ],
            'escrow_release' => [
                'key'         => 'escrow_release',
                'label'       => __( 'Escrow Release Form', 'catlaq-online-expo' ),
                'category'    => 'finance',
                'description' => __( 'Authorization document to release escrow funds after delivery.', 'catlaq-online-expo' ),
                'auto_fields' => [ 'escrow_reference', 'delivery_proof', 'signatures' ],
                'roles'       => [ 'buyer', 'admin' ],
                'capability'  => 'edit_posts',
            ],
        ];
    }
}
