<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Engagement\Profile_Model;
use Catlaq\Expo\Audit;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Profiles_Controller {
    private $profiles;

    public function __construct() {
        $this->profiles = new Profile_Model();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/profiles',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_profiles' ],
                            'permission_callback' => function () {
                                return current_user_can( 'manage_options' );
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_profile' ],
                            'permission_callback' => function () {
                                return current_user_can( 'manage_options' );
                            },
                        ],
                    ]
                );
            }
        );
    }

    public function list_profiles( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        $rows  = $this->profiles->list( $limit );
        return new WP_REST_Response( $rows );
    }

    public function create_profile( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user_id' ) );
        if ( ! $user_id ) {
            return new WP_Error( 'catlaq_invalid_user', __( 'User ID required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $profile_id = $this->profiles->create( $user_id );
        if ( ! $profile_id ) {
            return new WP_Error( 'catlaq_profile_error', __( 'Could not create profile.', 'catlaq-online-expo' ), [ 'status' => 500 ] );
        }

        Audit::log( 'profile_created', [ 'profile_id' => $profile_id, 'user_id' => $user_id ] );

        return new WP_REST_Response( [ 'id' => $profile_id ], 201 );
    }
}

