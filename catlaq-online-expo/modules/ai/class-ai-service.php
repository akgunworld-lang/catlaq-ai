<?php
namespace Catlaq\Expo\Modules\AI;

use Catlaq\Expo\AI_Kernel;

class AI_Service {
    public function boot(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function analyze( array $input ): array {
        return AI_Kernel::instance()->moderate( $input );
    }

    public function score( array $signals ): array {
        return AI_Kernel::instance()->score( $signals );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'catlaq/v1',
            '/ai/moderate',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_moderate' ),
                'permission_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    public function rest_moderate( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = (array) $request->get_json_params();
        $result  = $this->analyze( $payload );

        return new \WP_REST_Response( $result );
    }
}
