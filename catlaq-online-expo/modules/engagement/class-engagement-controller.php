<?php
namespace Catlaq\Expo\Modules\Engagement;

use function Catlaq\Expo\Helpers\render;

class Engagement_Controller {
    /**
     * Bootstrap Engagement module hooks.
     */
    public function boot(): void {
        add_shortcode( 'catlaq_engagement_feed', [ $this, 'render_engagement_feed' ] );
        add_action( 'catlaq_enqueue_engagement_assets', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Render frontend shortcode container.
     */
    public function render_engagement_feed(): string {
        do_action( 'catlaq_enqueue_engagement_assets' );

        ob_start();
        render( 'frontend/shortcode-engagement' );
        return ob_get_clean() ?: '';
    }

    /**
     * Enqueue scripts/styles + REST config for the engagement feed widget.
     */
    public function enqueue_assets(): void {
        wp_enqueue_style( 'catlaq-frontend' );
        wp_enqueue_script( 'catlaq-engagement-feed' );

        if ( wp_script_is( 'catlaq-engagement-feed', 'enqueued' ) ) {
            $permissions     = new Permissions();
            $user_id         = get_current_user_id();
            $allowed_vis     = $permissions->visibility_options( $user_id );
            $post_gate       = $permissions->assert_can_post( $user_id );
            $can_post        = true === $post_gate;
            $blocked_message = is_wp_error( $post_gate ) ? $post_gate->get_error_message() : '';

            wp_localize_script(
                'catlaq-engagement-feed',
                'catlaqEngagementConfig',
                [
                    'rest'        => [
                        'root'  => untrailingslashit( rest_url( 'catlaq/v1' ) ),
                        'nonce' => wp_create_nonce( 'wp_rest' ),
                    ],
                    'permissions' => [
                        'can_post'        => $can_post,
                        'blocked_message' => $blocked_message,
                        'visibility'      => $allowed_vis,
                    ],
                    'i18n'        => [
                        'postPlaceholder' => __( 'Write an update...', 'catlaq-online-expo' ),
                        'postButton'      => __( 'Share update', 'catlaq-online-expo' ),
                        'loginPrompt'     => __( 'Please sign in to publish updates.', 'catlaq-online-expo' ),
                        'emptyState'      => __( 'No activity yet. Start the conversation!', 'catlaq-online-expo' ),
                        'refresh'         => __( 'Refresh', 'catlaq-online-expo' ),
                        'visibilityLabel' => __( 'Audience', 'catlaq-online-expo' ),
                    ],
                ]
            );
        }
    }
}
