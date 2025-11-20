<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

use WP_Error;

/**
 * Handles persistent product catalog records (products, variants, price tiers).
 */
class Product_Catalog {
    private string $products_table;
    private string $variants_table;
    private string $prices_table;

    private Company_Model $companies;

    public function __construct( ?Company_Model $companies = null ) {
        global $wpdb;

        $prefix               = $wpdb->prefix;
        $this->products_table = $prefix . 'catlaq_products';
        $this->variants_table = $prefix . 'catlaq_product_variants';
        $this->prices_table   = $prefix . 'catlaq_product_prices';
        $this->companies      = $companies ?: new Company_Model();
    }

    /**
     * Create a product with optional variants and price tiers.
     */
    public function create_product( array $product, array $variants = [], array $price_tiers = [] ): WP_Error|array {
        global $wpdb;

        $company_id = (int) ( $product['company_id'] ?? 0 );
        if ( ! $company_id || ! $this->companies->find( $company_id ) ) {
            return new WP_Error( 'catlaq_product_company', __( 'Valid company is required for catalog products.', 'catlaq-online-expo' ) );
        }

        $name = sanitize_text_field( $product['name'] ?? '' );
        if ( '' === $name ) {
            return new WP_Error( 'catlaq_product_name', __( 'Product name cannot be empty.', 'catlaq-online-expo' ) );
        }

        $now             = current_time( 'mysql' );
        $base_price      = $this->format_decimal( $product['base_price'] ?? 0 );
        $currency        = strtoupper( substr( sanitize_text_field( $product['currency'] ?? 'USD' ), 0, 3 ) );
        $min_order_qty   = $this->format_decimal( $product['min_order_qty'] ?? 0 );
        $lead_time_days  = (int) ( $product['lead_time_days'] ?? 0 );
        $status          = sanitize_key( $product['status'] ?? 'draft' );
        $visibility      = sanitize_key( $product['visibility'] ?? 'private' );
        $unit            = sanitize_text_field( $product['unit'] ?? '' );
        $sku             = sanitize_text_field( $product['sku'] ?? '' );
        $highlights      = wp_kses_post( $product['highlights'] ?? '' );
        $notes           = wp_kses_post( $product['notes'] ?? '' );
        $create_wp_post  = ! empty( $product['create_wp_post'] );
        $linked_wp_post  = absint( $product['wp_post_id'] ?? 0 );

        $inserted = $wpdb->insert(
            $this->products_table,
            [
                'company_id'    => $company_id,
                'wp_post_id'    => $linked_wp_post ?: null,
                'name'          => $name,
                'sku'           => $sku,
                'unit'          => $unit,
                'min_order_qty' => $min_order_qty,
                'lead_time_days'=> $lead_time_days,
                'base_price'    => $base_price,
                'currency'      => $currency,
                'status'        => $status,
                'visibility'    => $visibility,
                'highlights'    => $highlights,
                'notes'         => $notes,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%f', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'catlaq_product_insert', __( 'Product could not be saved.', 'catlaq-online-expo' ) );
        }

        $product_id = (int) $wpdb->insert_id;

        if ( $create_wp_post && ! $linked_wp_post ) {
            $linked_wp_post = $this->maybe_create_wp_post(
                [
                    'name'       => $name,
                    'highlights' => $highlights,
                    'base_price' => $base_price,
                    'currency'   => $currency,
                ]
            );

            if ( $linked_wp_post ) {
                $wpdb->update(
                    $this->products_table,
                    [
                        'wp_post_id' => $linked_wp_post,
                        'updated_at' => current_time( 'mysql' ),
                    ],
                    [ 'id' => $product_id ],
                    [ '%d', '%s' ],
                    [ '%d' ]
                );
            }
        }

        $variant_lookup = $this->persist_variants( $product_id, $variants );
        $this->persist_price_tiers( $product_id, $variant_lookup, $price_tiers );

        if ( $linked_wp_post && $base_price > 0 ) {
            $this->sync_post_price_meta( $linked_wp_post, $base_price, $currency );
        }

        return [
            'id'          => $product_id,
            'wp_post_id'  => $linked_wp_post,
            'name'        => $name,
            'base_price'  => $base_price,
            'currency'    => $currency,
            'variants'    => array_values( $variant_lookup ),
        ];
    }

    /**
     * Lightweight admin listing data.
     */
    public function list_products( int $limit = 25 ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.name, p.sku, p.base_price, p.currency, p.status, p.updated_at, c.name AS company_name,
                        (SELECT COUNT(*) FROM {$this->variants_table} v WHERE v.product_id = p.id) AS variant_count
                 FROM {$this->products_table} p
                 LEFT JOIN {$wpdb->prefix}catlaq_companies c ON c.id = p.company_id
                 ORDER BY p.updated_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Fetch companies for dropdowns.
     */
    public function companies_for_select( int $limit = 100 ): array {
        $items = $this->companies->all( $limit );
        $result = [];

        foreach ( $items as $item ) {
            $result[ (int) $item['id'] ] = $item['name'];
        }

        return $result;
    }

    /**
     * Provide variant label => ID map for admin display.
     *
     * @return array<string,int>
     */
    private function persist_variants( int $product_id, array $variants ): array {
        global $wpdb;
        $map = [];

        foreach ( $variants as $variant ) {
            $label = sanitize_text_field( $variant['label'] ?? '' );
            if ( '' === $label ) {
                continue;
            }

            $data = [
                'product_id'  => $product_id,
                'label'       => $label,
                'sku'         => sanitize_text_field( $variant['sku'] ?? '' ),
                'attributes'  => wp_json_encode( $variant['attributes'] ?? [] ),
                'stock_qty'   => $this->format_decimal( $variant['stock_qty'] ?? 0 ),
                'stock_unit'  => sanitize_text_field( $variant['stock_unit'] ?? '' ),
                'unit_price'  => $this->format_decimal( $variant['unit_price'] ?? 0 ),
                'currency'    => strtoupper( substr( sanitize_text_field( $variant['currency'] ?? 'USD' ), 0, 3 ) ),
                'weight_kg'   => $this->format_decimal( $variant['weight_kg'] ?? 0 ),
                'volume_cbm'  => $this->format_decimal( $variant['volume_cbm'] ?? 0 ),
                'status'      => sanitize_key( $variant['status'] ?? 'active' ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ];

            $inserted = $wpdb->insert(
                $this->variants_table,
                $data,
                [ '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%f', '%s', '%s', '%s' ]
            );

            if ( $inserted ) {
                $map[ $label ] = (int) $wpdb->insert_id;
            }
        }

        return $map;
    }

    /**
     * Persist tiered pricing records.
     *
     * @param array<string,int> $variant_lookup
     */
    private function persist_price_tiers( int $product_id, array $variant_lookup, array $price_tiers ): void {
        global $wpdb;

        foreach ( $price_tiers as $tier ) {
            $min_qty = $this->format_decimal( $tier['min_qty'] ?? 0 );
            $max_qty = $this->format_decimal( $tier['max_qty'] ?? 0 );
            $price   = $this->format_decimal( $tier['unit_price'] ?? 0 );

            if ( 0 >= $price ) {
                continue;
            }

            $currency = strtoupper( substr( sanitize_text_field( $tier['currency'] ?? 'USD' ), 0, 3 ) );
            $label    = sanitize_text_field( $tier['variant_label'] ?? '' );
            $variant  = $label && isset( $variant_lookup[ $label ] ) ? (int) $variant_lookup[ $label ] : null;

            $wpdb->insert(
                $this->prices_table,
                [
                    'product_id' => $product_id,
                    'variant_id' => $variant,
                    'min_qty'    => $min_qty,
                    'max_qty'    => $max_qty,
                    'unit_price' => $price,
                    'currency'   => $currency,
                    'notes'      => sanitize_text_field( $tier['notes'] ?? '' ),
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s' ]
            );
        }
    }

    private function maybe_create_wp_post( array $product ): int {
        $post_id = wp_insert_post(
            [
                'post_title'   => $product['name'],
                'post_content' => $product['highlights'] ?? '',
                'post_type'    => 'catlaq_product',
                'post_status'  => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return 0;
        }

        return (int) $post_id;
    }

    private function sync_post_price_meta( int $post_id, float $price, string $currency ): void {
        $display = sprintf(
            '%s %s',
            number_format_i18n( $price, 2 ),
            sanitize_text_field( $currency )
        );

        update_post_meta( $post_id, '_catlaq_product_price', $display );
    }

    private function format_decimal( $value ): float {
        return round( (float) $value, 4 );
    }
}

