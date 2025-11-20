<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

use Catlaq\Expo\Modules\Agreements\Room_Model;
use Catlaq\Expo\Modules\AI\AI_Service;
use WP_Error;
use function Catlaq\Expo\Helpers\render;

class RFQ_Controller {
    private $table;
    private $companies;
    private $escrow;
    private $ai;
    private $orders;
    private $booths;

    public function __construct( ?Company_Model $companies = null, ?Escrow_Service $escrow = null, ?AI_Service $ai = null, ?Order_Service $orders = null, ?Booth_Model $booths = null ) {
        global $wpdb;
        $this->table     = $wpdb->prefix . 'catlaq_rfq';
        $this->companies = $companies ?: new Company_Model();
        $this->escrow    = $escrow ?: new Escrow_Service();
        $this->ai        = $ai ?: new AI_Service();
        $this->orders    = $orders ?: new Order_Service();
        $this->booths    = $booths ?: new Booth_Model();
    }

    public function boot(): void {
        add_action( 'init', [ $this, 'register_product_post_type' ] );
        add_action( 'init', [ $this, 'register_product_taxonomy' ] );
        add_shortcode( 'catlaq_digital_expo_showcase', [ $this, 'render_digital_expo_catalog_shortcode' ] );
        add_shortcode( 'catlaq_expo_price', [ $this, 'render_product_price_shortcode' ] );
        add_shortcode( 'catlaq_expo_category_filter', [ $this, 'render_category_filter_shortcode' ] );
        add_shortcode( 'catlaq_expo_booths', [ $this, 'render_booth_grid_shortcode' ] );
        add_shortcode( 'catlaq_expo_sessions', [ $this, 'render_booth_sessions_shortcode' ] );
        $this->ai->boot();
    }

    public function create( array $payload ) {
        global $wpdb;

        $buyer_company_id = (int) ( $payload['buyer_company_id'] ?? 0 );

        if ( ! $buyer_company_id && ! empty( $payload['company_name'] ) ) {
            $buyer_company_id = $this->companies->create(
                array(
                    'name'            => $payload['company_name'],
                    'country'         => $payload['country'] ?? '',
                    'membership_tier' => $payload['membership_tier'] ?? 'standard',
                )
            );
        }

        if ( ! $buyer_company_id ) {
            return new WP_Error( 'catlaq_missing_company', __( 'A buyer company is required.', 'catlaq-online-expo' ) );
        }

        $membership_tier = sanitize_key( $payload['membership_tier'] ?? $this->companies->get_company_membership_slug( $buyer_company_id ) );
        $can_create      = $this->companies->company_can_create_rfq( $buyer_company_id, $membership_tier );
        if ( is_wp_error( $can_create ) ) {
            return $can_create;
        }

        $title   = sanitize_text_field( $payload['title'] ?? '' );
        $details = sanitize_textarea_field( $payload['details'] ?? '' );

        if ( '' === $title ) {
            return new WP_Error( 'catlaq_missing_title', __( 'RFQ title is required.', 'catlaq-online-expo' ) );
        }

        $moderation      = $this->ai->analyze( array( 'content' => $details ) );
        $moderation_state = $moderation['status'] ?? 'pending';

        $budget   = (float) ( $payload['budget'] ?? 0 );
        $currency = strtoupper( substr( (string) ( $payload['currency'] ?? 'USD' ), 0, 3 ) );

        $wpdb->insert(
            $this->table,
            array(
                'buyer_company_id' => $buyer_company_id,
                'status'           => $payload['status'] ?? 'open',
                'title'            => $title,
                'details'          => $details,
                'moderation_state' => $moderation_state,
                'budget'           => $budget,
                'currency'         => $currency,
                'membership_tier'  => $membership_tier,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
        );

        $rfq_id = (int) $wpdb->insert_id;

        if ( $rfq_id && ! empty( $payload['open_room'] ) ) {
            ( new Room_Model() )->create( $rfq_id );
        }

        $membership = $this->companies->get_membership( $membership_tier );
        $requires_escrow = (bool) ( $membership['escrow_required'] ?? false );
        if ( $rfq_id && $budget > 0 && ( ! empty( $payload['hold_escrow'] ) || $requires_escrow ) ) {
            $this->escrow->hold_funds(
                array(
                    'rfq_id'   => $rfq_id,
                    'amount'   => $budget,
                    'currency' => $currency,
                )
            );
        }

        return array(
            'id'          => $rfq_id,
            'moderation'  => $moderation,
            'membership'  => $membership_tier,
            'budget'      => $budget,
            'currency'    => $currency,
        );
    }

    public function all( int $limit = 50 ): array {
        global $wpdb;

        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, status, moderation_state, budget, currency, membership_tier FROM {$this->table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get( int $rfq_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $rfq_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function mark_status( int $rfq_id, string $status ): void {
        global $wpdb;
        $wpdb->update(
            $this->table,
            array(
                'status'     => sanitize_key( $status ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $rfq_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    public function convert_to_order( int $rfq_id, array $payload ) {
        $rfq = $this->get( $rfq_id );
        if ( ! $rfq ) {
            return new WP_Error( 'catlaq_rfq_missing', __( 'RFQ not found.', 'catlaq-online-expo' ), array( 'status' => 404 ) );
        }

        $order = $this->orders->create_from_rfq( $rfq, $payload );
        if ( ! is_wp_error( $order ) ) {
            $this->mark_status( $rfq_id, 'converted' );
        }

        return $order;
    }

    public function register_product_post_type(): void {
        if ( post_type_exists( 'catlaq_product' ) ) {
            return;
        }

        $labels = [
            'name'               => _x( 'Products', 'Post Type General Name', 'catlaq-online-expo' ),
            'singular_name'      => _x( 'Product', 'Post Type Singular Name', 'catlaq-online-expo' ),
            'menu_name'          => __( 'Products', 'catlaq-online-expo' ),
            'name_admin_bar'     => __( 'Product', 'catlaq-online-expo' ),
            'add_new'            => __( 'Add New', 'catlaq-online-expo' ),
            'add_new_item'       => __( 'Add New Product', 'catlaq-online-expo' ),
            'new_item'           => __( 'New Product', 'catlaq-online-expo' ),
            'edit_item'          => __( 'Edit Product', 'catlaq-online-expo' ),
            'view_item'          => __( 'View Product', 'catlaq-online-expo' ),
            'all_items'          => __( 'All Products', 'catlaq-online-expo' ),
            'search_items'       => __( 'Search Products', 'catlaq-online-expo' ),
            'not_found'          => __( 'No products found.', 'catlaq-online-expo' ),
            'not_found_in_trash' => __( 'No products found in Trash.', 'catlaq-online-expo' ),
        ];

        register_post_type(
            'catlaq_product',
            [
                'label'               => __( 'Product', 'catlaq-online-expo' ),
                'description'         => __( 'Catlaq Digital Expo catalog', 'catlaq-online-expo' ),
                'labels'              => $labels,
                'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'menu_icon'           => 'dashicons-cart',
                'has_archive'         => true,
                'rewrite'             => [ 'slug' => 'digital-expo' ],
                'show_in_rest'        => true,
                'publicly_queryable'  => true,
                'exclude_from_search' => false,
            ]
        );
    }

    public function register_product_taxonomy(): void {
        if ( taxonomy_exists( 'catlaq_product_category' ) ) {
            return;
        }

        $labels = [
            'name'          => _x( 'Product Categories', 'taxonomy general name', 'catlaq-online-expo' ),
            'singular_name' => _x( 'Product Category', 'taxonomy singular name', 'catlaq-online-expo' ),
            'search_items'  => __( 'Search Categories', 'catlaq-online-expo' ),
            'all_items'     => __( 'All Categories', 'catlaq-online-expo' ),
            'edit_item'     => __( 'Edit Category', 'catlaq-online-expo' ),
            'update_item'   => __( 'Update Category', 'catlaq-online-expo' ),
            'add_new_item'  => __( 'Add New Category', 'catlaq-online-expo' ),
            'new_item_name' => __( 'New Category Name', 'catlaq-online-expo' ),
            'menu_name'     => __( 'Categories', 'catlaq-online-expo' ),
        ];

        register_taxonomy(
            'catlaq_product_category',
            [ 'catlaq_product' ],
            [
                'hierarchical'      => true,
                'labels'            => $labels,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => [ 'slug' => 'digital-expo-category' ],
                'show_in_rest'      => true,
            ]
        );
    }

    public function render_digital_expo_catalog_shortcode(): string {
        do_action( 'catlaq_enqueue_digital_expo_assets' );

        $query = new \WP_Query(
            [
                'post_type'      => 'catlaq_product',
                'posts_per_page' => 6,
                'post_status'    => 'publish',
            ]
        );

        if ( ! $query->have_posts() ) {
            return '<div class="catlaq-digital-expo-grid catlaq-digital-expo-grid--empty">' . esc_html__( 'No products yet.', 'catlaq-online-expo' ) . '</div>';
        }

        ob_start();
        echo '<div class="catlaq-digital-expo-grid">';
        while ( $query->have_posts() ) {
            $query->the_post();
            echo '<article class="catlaq-digital-expo-card">';
            if ( has_post_thumbnail() ) {
                echo '<div class="catlaq-digital-expo-card__media">';
                the_post_thumbnail( 'medium_large' );
                echo '</div>';
            }
            echo '<div class="catlaq-digital-expo-card__body">';
            echo '<h3 class="catlaq-digital-expo-card__title"><a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a></h3>';
            echo '<p class="catlaq-digital-expo-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), 20 ) ) . '</p>';
            echo do_shortcode( '[catlaq_expo_price]' );
            echo '</div></article>';
        }
        wp_reset_postdata();
        echo '</div>';

        return ob_get_clean();
    }

    public function render_product_price_shortcode(): string {
        $post_id = get_the_ID();
        if ( ! $post_id || 'catlaq_product' !== get_post_type( $post_id ) ) {
            return '';
        }

        $price = get_post_meta( $post_id, '_catlaq_product_price', true );
        if ( '' === $price ) {
            global $wpdb;
            $table = $wpdb->prefix . 'catlaq_products';
            $row   = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT base_price, currency FROM {$table} WHERE wp_post_id = %d LIMIT 1",
                    $post_id
                ),
                ARRAY_A
            );

            if ( $row && (float) $row['base_price'] > 0 ) {
                $price = sprintf(
                    '%s %s',
                    number_format_i18n( (float) $row['base_price'], 2 ),
                    strtoupper( $row['currency'] ?? 'USD' )
                );
            }
        }

        if ( '' === $price ) {
            return '';
        }

        return sprintf(
            '<span class="catlaq-product-price">%s</span>',
            esc_html( wp_strip_all_tags( $price ) )
        );
    }

    public function render_category_filter_shortcode(): string {
        do_action( 'catlaq_enqueue_digital_expo_assets' );

        $terms = get_terms(
            [
                'taxonomy'   => 'catlaq_product_category',
                'hide_empty' => true,
            ]
        );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        $output = '<ul class="catlaq-product-category-filter">';
        $output .= sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url( get_post_type_archive_link( 'catlaq_product' ) ),
            esc_html__( 'All products', 'catlaq-online-expo' )
        );

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                continue;
            }
            $output .= sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url( $link ),
                esc_html( $term->name )
            );
        }

        $output .= '</ul>';

        return $output;
    }

    public function render_booth_grid_shortcode(): string {
        do_action( 'catlaq_enqueue_digital_expo_assets' );

        $booths = $this->booths->list_booths( 12 );

        if ( empty( $booths ) ) {
            return '<div class="catlaq-expo-booths catlaq-expo-booths--empty">' . esc_html__( 'No booths are published yet.', 'catlaq-online-expo' ) . '</div>';
        }

        ob_start();
        render(
            'frontend/shortcode-expo',
            [
                'booths' => $booths,
            ]
        );

        return ob_get_clean() ?: '';
    }

    public function render_booth_sessions_shortcode( array $atts ): string {
        $booth_id = isset( $atts['booth_id'] ) ? absint( $atts['booth_id'] ) : 0;
        if ( ! $booth_id ) {
            return '';
        }

        $sessions = $this->booths->sessions( $booth_id );
        if ( empty( $sessions ) ) {
            return '';
        }

        ob_start();
        echo '<ul class="catlaq-expo-sessions">';
        foreach ( $sessions as $session ) {
            echo '<li>';
            echo '<strong>' . esc_html( $session['topic'] ) . '</strong><br />';
            echo esc_html( $session['starts_at'] ) . ' – ' . esc_html( $session['ends_at'] );
            echo ' · ' . esc_html( $session['host'] );
            echo '</li>';
        }
        echo '</ul>';

        return ob_get_clean() ?: '';
    }
}
