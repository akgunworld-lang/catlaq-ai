<?php
namespace Catlaq\Expo;

class Shortcodes {
    public static function register(): void {
        add_shortcode( 'catlaq_membership_overview', [ __CLASS__, 'membership_overview' ] );
        add_shortcode( 'catlaq_membership_plans', [ __CLASS__, 'membership_plans' ] );
        add_shortcode( 'catlaq_auth_portal', [ __CLASS__, 'auth_portal' ] );
        add_shortcode( 'catlaq_policy', [ __CLASS__, 'policy' ] );
    }

    public static function membership_overview( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'layout' => 'card',
                'title'  => __( 'Membership Overview', 'catlaq-online-expo' ),
            ],
            $atts,
            'catlaq_membership_overview'
        );

        if ( ! is_user_logged_in() ) {
            return self::wrap_card(
                sprintf(
                    '<p>%s</p>',
                    esc_html__( 'Please sign in to view your Catlaq membership details.', 'catlaq-online-expo' )
                ),
                $atts['title']
            );
        }

        $memberships  = new Memberships();
        $plan         = $memberships->user_membership( get_current_user_id() );
        $plan_catalog = self::plan_catalog();

        if ( ! $plan ) {
            return self::wrap_card(
                sprintf(
                    '<p>%s</p>',
                    esc_html__( 'No membership assigned yet. Choose your plan to unlock the Digital Expo.', 'catlaq-online-expo' )
                ),
                $atts['title']
            );
        }

        $segment = $plan_catalog[ $plan['slug'] ]['segment'] ?? '';
        $quota_list = '';
        $quotas     = (array) ( $plan['quotas'] ?? [] );
        foreach ( $quotas as $metric => $limit ) {
            $status     = $memberships->quota_status( get_current_user_id(), $metric, (int) $limit );
            $metric_key = esc_html( ucwords( str_replace( '_', ' ', $metric ) ) );
            $remaining  = isset( $status['remaining'] ) ? (int) $status['remaining'] : __( 'Unlimited', 'catlaq-online-expo' );
            $quota_list .= sprintf(
                '<li><strong>%s</strong><span>%s</span></li>',
                $metric_key,
                is_numeric( $remaining ) ? sprintf( '%d / %d', (int) $status['used'], (int) $status['limit'] ) : esc_html( $remaining )
            );
        }

        $features_markup = '';
        if ( ! empty( $plan['features'] ) && is_array( $plan['features'] ) ) {
            $features = array_map(
                fn( $value, $key ) => sprintf(
                    '<li><strong>%s:</strong> %s</li>',
                    esc_html( ucwords( str_replace( '_', ' ', $key ) ) ),
                    esc_html( $value )
                ),
                $plan['features'],
                array_keys( $plan['features'] )
            );
            $features_markup = '<ul class="catlaq-membership__features">' . implode( '', $features ) . '</ul>';
        }

        $html  = '<div class="catlaq-membership">';
        $html .= sprintf(
            '<p class="catlaq-membership__plan"><span>%s</span> <strong>%s</strong></p>',
            esc_html__( 'Current Plan', 'catlaq-online-expo' ),
            esc_html( $plan['label'] ?? '' )
        );
        if ( $segment ) {
            $html .= '<span class="catlaq-membership__segment-badge">' . esc_html( $segment ) . '</span>';
        }
        $html .= $features_markup;
        if ( $quota_list ) {
            $html .= '<ul class="catlaq-membership__quotas">' . $quota_list . '</ul>';
        }
        $html .= '</div>';

        return self::wrap_card( $html, $atts['title'] );
    }

    public static function membership_plans(): string {
        $memberships  = new Memberships();
        $plans        = $memberships->all();
        $plan_catalog = self::plan_catalog();

        if ( empty( $plans ) ) {
            return '<p>' . esc_html__( 'Membership plans will be announced soon.', 'catlaq-online-expo' ) . '</p>';
        }

        $cards = array_map(
            function ( $plan ) use ( $plan_catalog ) {
                $catalog      = $plan_catalog[ $plan['slug'] ] ?? [];
                $segment      = $catalog['segment'] ?? '';
                $tagline      = $catalog['tagline'] ?? '';
                $cta          = $catalog['cta'] ?? __( 'Select Plan', 'catlaq-online-expo' );
                $anchor_id    = sprintf( 'catlaq-plan-%s', sanitize_html_class( $plan['slug'] ?? '' ) );
                $segment_html = $segment ? '<span class="catlaq-plan-card__segment">' . esc_html( $segment ) . '</span>' : '';
                $tagline_html = $tagline ? '<p class="catlaq-plan-card__tagline">' . esc_html( $tagline ) . '</p>' : '';

                $features = '';
                if ( ! empty( $plan['features'] ) && is_array( $plan['features'] ) ) {
                    $items = array_map(
                        fn( $value, $key ) => sprintf(
                            '<li><strong>%s</strong> %s</li>',
                            esc_html( ucwords( str_replace( '_', ' ', $key ) ) ),
                            esc_html( $value )
                        ),
                        $plan['features'],
                        array_keys( $plan['features'] )
                    );
                    $features = '<ul class="catlaq-plan__features">' . implode( '', $items ) . '</ul>';
                }

                return sprintf(
                    '<article class="catlaq-plan-card" id="%1$s">
                        <header>
                            %2$s
                            <h3>%3$s</h3>
                            <p class="catlaq-plan-card__price">%4$s %5$s</p>
                        </header>
                        %6$s
                        %7$s
                        <button class="catlaq-plan-card__cta" data-plan="%8$s">%9$s</button>
                    </article>',
                    esc_attr( $anchor_id ),
                    $segment_html,
                    esc_html( $plan['label'] ?? '' ),
                    esc_html( number_format_i18n( (float) ( $plan['price'] ?? 0 ), 2 ) ),
                    esc_html( strtoupper( $plan['currency'] ?? 'USD' ) ),
                    $features,
                    $tagline_html,
                    esc_attr( $plan['slug'] ?? '' ),
                    esc_html( $cta )
                );
            },
            $plans
        );

        return '<div class="catlaq-plan-grid">' . implode( '', $cards ) . '</div>';
    }

    public static function auth_portal( $atts = [] ): string {
        if ( is_user_logged_in() ) {
            return '<p>' . esc_html__( 'You are already signed in to Catlaq. Visit your dashboard to manage Digital Expo access.', 'catlaq-online-expo' ) . '</p>';
        }

        $atts = shortcode_atts(
            [
                'show_register' => 'true',
                'show_login'    => 'true',
                'redirect'      => home_url( '/catlaq-dashboard' ),
            ],
            $atts,
            'catlaq_auth_portal'
        );

        $plan_catalog = self::plan_catalog();
        $segments     = [];
        foreach ( $plan_catalog as $slug => $meta ) {
            $segment = $meta['segment'] ?? '';
            if ( '' === $segment ) {
                continue;
            }

            $segments[ $segment ] = [
                'slug'    => $slug,
                'tagline' => $meta['tagline'] ?? '',
                'cta'     => $meta['cta'] ?? __( 'Learn more', 'catlaq-online-expo' ),
            ];
        }

        $output = '<div class="catlaq-auth-portal">';

        if ( filter_var( $atts['show_login'], FILTER_VALIDATE_BOOLEAN ) ) {
            $output .= '<div class="catlaq-auth-portal__block catlaq-auth-portal__login">';
            $output .= '<h3>' . esc_html__( 'Sign In', 'catlaq-online-expo' ) . '</h3>';
            $output .= wp_login_form(
                [
                    'echo'     => false,
                    'redirect' => esc_url( $atts['redirect'] ),
                ]
            );
            $output .= '</div>';
        }

        if ( filter_var( $atts['show_register'], FILTER_VALIDATE_BOOLEAN ) ) {
            $output .= '<div class="catlaq-auth-portal__block catlaq-auth-portal__register">';
            $output .= '<h3>' . esc_html__( 'Request Expo Access', 'catlaq-online-expo' ) . '</h3>';

            if ( '1' !== get_option( 'users_can_register' ) ) {
                $output .= '<p>' . esc_html__( 'Registration is currently disabled. Please contact Catlaq support.', 'catlaq-online-expo' ) . '</p>';
            } else {
                $registration_url = wp_registration_url();
                $output          .= '<form class="catlaq-register-form" action="' . esc_url( $registration_url ) . '" method="post">';
                $output          .= '<p><label>' . esc_html__( 'Username', 'catlaq-online-expo' ) . '<input type="text" name="user_login" required></label></p>';
                $output          .= '<p><label>' . esc_html__( 'Email', 'catlaq-online-expo' ) . '<input type="email" name="user_email" required></label></p>';
                if ( ! empty( $segments ) ) {
                    $output .= '<p><label>' . esc_html__( 'Business role', 'catlaq-online-expo' ) . '<select name="catlaq_role_segment">';
                    foreach ( $segments as $segment_name => $meta ) {
                        $output .= sprintf(
                            '<option value="%1$s">%1$s</option>',
                            esc_html( $segment_name )
                        );
                    }
                    $output .= '</select></label></p>';
                }
                $output          .= '<p><button type="submit">' . esc_html__( 'Create Account', 'catlaq-online-expo' ) . '</button></p>';
                $output          .= wp_nonce_field( 'register', 'wpnonce', true, false );
                $output          .= '<input type="hidden" name="redirect_to" value="' . esc_attr( $atts['redirect'] ) . '">';
                $output          .= '</form>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';

        if ( ! empty( $segments ) ) {
            $output .= '<div class="catlaq-auth-portal__roles">';
            $output .= '<h4>' . esc_html__( 'Membership tracks at a glance', 'catlaq-online-expo' ) . '</h4>';
            $output .= '<ul>';
            foreach ( $segments as $segment_name => $meta ) {
                $slug    = $meta['slug'] ?? '';
                $tagline = $meta['tagline'] ?? '';
                $output .= sprintf(
                    '<li><strong>%1$s</strong><span>%2$s</span><a href="%3$s">%4$s</a></li>',
                    esc_html( $segment_name ),
                    esc_html( $tagline ),
                    esc_url( home_url( '/#catlaq-plan-' . $slug ) ),
                    esc_html__( 'Preview plans', 'catlaq-online-expo' )
                );
            }
            $output .= '</ul></div>';
        }

        return $output;
    }

    public static function policy( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'type'  => 'privacy',
                'title' => '',
            ],
            $atts,
            'catlaq_policy'
        );

        $type     = sanitize_key( $atts['type'] );
        $policies = [
            'privacy' => [
                'title'   => __( 'Privacy Policy', 'catlaq-online-expo' ),
                'content' => [
                    __( 'Catlaq records member data solely to operate the Digital Expo and brokerless negotiation spaces.', 'catlaq-online-expo' ),
                    __( 'Analytics, booth statistics, and RFQ activity are encrypted and never sold to third parties.', 'catlaq-online-expo' ),
                    __( 'Members can request export or deletion of their data by emailing compliance@catlaq.com.', 'catlaq-online-expo' ),
                ],
            ],
            'terms'   => [
                'title'   => __( 'Terms of Service', 'catlaq-online-expo' ),
                'content' => [
                    __( 'Catlaq provides a membership platform only; financial transactions remain between buyer and seller.', 'catlaq-online-expo' ),
                    __( 'Misuse of AI agents, harassment, or off-platform solicitation results in immediate suspension.', 'catlaq-online-expo' ),
                    __( 'By publishing booth content you confirm you own the rights and grant Catlaq showcase permissions.', 'catlaq-online-expo' ),
                ],
            ],
            'refund'  => [
                'title'   => __( 'Refund & Cancellation Policy', 'catlaq-online-expo' ),
                'content' => [
                    __( 'Membership fees are billed in advance on a monthly cycle.', 'catlaq-online-expo' ),
                    __( 'You may cancel anytime; future renewals will be halted immediately.', 'catlaq-online-expo' ),
                    __( 'Partial refunds are not issued, but Catlaq can grant credits for prolonged outages.', 'catlaq-online-expo' ),
                ],
            ],
        ];

        $policy = $policies[ $type ] ?? $policies['privacy'];
        $title  = $atts['title'] ? $atts['title'] : $policy['title'];

        $items = array_map(
            fn( $paragraph ) => '<li>' . esc_html( $paragraph ) . '</li>',
            $policy['content']
        );

        return self::wrap_card(
            '<ul class="catlaq-policy">' . implode( '', $items ) . '</ul>',
            $title
        );
    }

    private static function wrap_card( string $content, string $title = '' ): string {
        $html = '<div class="catlaq-card">';
        if ( $title ) {
            $html .= '<h3>' . esc_html( $title ) . '</h3>';
        }
        $html .= $content . '</div>';
        return $html;
    }

    private static function plan_catalog(): array {
        return [
            'buyer_core'       => [
                'segment' => __( 'Buyer', 'catlaq-online-expo' ),
                'tagline' => __( 'Import teams needing Expo scouting and RFQ visibility.', 'catlaq-online-expo' ),
                'cta'     => __( 'Choose Buyer Plan', 'catlaq-online-expo' ),
            ],
            'buyer_enterprise' => [
                'segment' => __( 'Buyer', 'catlaq-online-expo' ),
                'tagline' => __( 'High-volume buyers with logistics + agreement workflows.', 'catlaq-online-expo' ),
                'cta'     => __( 'Deploy Buyer Fleet', 'catlaq-online-expo' ),
            ],
            'seller_showcase'  => [
                'segment' => __( 'Seller', 'catlaq-online-expo' ),
                'tagline' => __( 'Suppliers showcasing SKUs and answering global RFQs.', 'catlaq-online-expo' ),
                'cta'     => __( 'Open Seller Booth', 'catlaq-online-expo' ),
            ],
            'seller_enterprise'=> [
                'segment' => __( 'Seller', 'catlaq-online-expo' ),
                'tagline' => __( 'Enterprise exporters with multiple booths and escrow automation.', 'catlaq-online-expo' ),
                'cta'     => __( 'Launch Seller Hub', 'catlaq-online-expo' ),
            ],
            'broker_ops'       => [
                'segment' => __( 'Brokerage', 'catlaq-online-expo' ),
                'tagline' => __( 'Brokers and agents managing deals, commissions, and disputes.', 'catlaq-online-expo' ),
                'cta'     => __( 'Enable Broker Ops', 'catlaq-online-expo' ),
            ],
            'logistics_partner'=> [
                'segment' => __( 'Logistics', 'catlaq-online-expo' ),
                'tagline' => __( 'Forwarders and carriers publishing corridors + tracking data.', 'catlaq-online-expo' ),
                'cta'     => __( 'Join Logistics Network', 'catlaq-online-expo' ),
            ],
            'support_vendor'   => [
                'segment' => __( 'Support Vendor', 'catlaq-online-expo' ),
                'tagline' => __( 'Finance, QC, insurance, and auxiliary service firms.', 'catlaq-online-expo' ),
                'cta'     => __( 'List Support Services', 'catlaq-online-expo' ),
            ],
        ];
    }
}
