<?php
namespace Catlaq\Expo\Modules\Engagement;

use WP_Error;

/**
 * Centralizes engagement visibility and posting rules.
 */
class Permissions {
    /**
     * Visibility tiers a user may access.
     *
     * @var array<string,string[]>
     */
    private array $visibility_map = [
        'administrator' => [ 'public', 'members', 'premium', 'private' ],
        'editor'        => [ 'public', 'members', 'premium' ],
        'author'        => [ 'public', 'members' ],
        'subscriber'    => [ 'public', 'members' ],
        'guest'         => [ 'public' ],
    ];

    /**
     * Determine which visibilities are visible to a user.
     */
    public function visible_visibilities( int $user_id ): array {
        return $this->visibility_map[ $this->resolve_tier( $user_id ) ];
    }

    /**
     * Determine the allowed visibility values when posting.
     */
    public function visibility_options( int $user_id ): array {
        return $this->visible_visibilities( $user_id );
    }

    /**
     * Ensure a user can post at all.
     */
    public function assert_can_post( int $user_id ) {
        if ( ! $user_id ) {
            return new WP_Error(
                'catlaq_engagement_auth',
                __( 'Only signed-in users can post to the network.', 'catlaq-online-expo' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    /**
     * Ensure the requested visibility is one of the user's allowed options.
     */
    public function enforce_visibility( int $user_id, string $requested ): string {
        $allowed   = $this->visibility_options( $user_id );
        $requested = $requested ?: 'public';
        return in_array( $requested, $allowed, true ) ? $requested : $allowed[0];
    }

    /**
     * Check if a user can view/join a target visibility.
     */
    public function visibility_allowed( int $user_id, string $visibility ): bool {
        return in_array( $visibility, $this->visible_visibilities( $user_id ), true );
    }

    /**
     * Translate the user ID into a tier key.
     */
    private function resolve_tier( int $user_id ): string {
        if ( $user_id && function_exists( 'user_can' ) ) {
            if ( user_can( $user_id, 'manage_options' ) ) {
                return 'administrator';
            }

            if ( user_can( $user_id, 'edit_others_posts' ) ) {
                return 'editor';
            }

            if ( user_can( $user_id, 'publish_posts' ) ) {
                return 'author';
            }

            if ( user_can( $user_id, 'read' ) ) {
                return 'subscriber';
            }
        }

        return 'guest';
    }
}

