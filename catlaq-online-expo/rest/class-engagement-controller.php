<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Engagement\Conversation_Model;
use Catlaq\Expo\Modules\Engagement\Group_Model;
use Catlaq\Expo\Modules\Engagement\Post_Model;
use Catlaq\Expo\Modules\Engagement\Profile_Model;
use Catlaq\Expo\Modules\Engagement\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Engagement_Controller {
    private $profiles;
    private $posts;
    private $groups;
    private $conversations;
    private $permissions;

    public function __construct() {
        $this->profiles      = new Profile_Model();
        $this->posts         = new Post_Model();
        $this->groups        = new Group_Model();
        $this->conversations = new Conversation_Model();
        $this->permissions   = new Permissions();
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/engagement/feed',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_feed' ],
                            'permission_callback' => '__return_true',
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_post' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/engagement/groups',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_groups' ],
                            'permission_callback' => '__return_true',
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_group' ],
                            'permission_callback' => function () {
                                return current_user_can( 'read' );
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/engagement/groups/(?P<id>\d+)/join',
                    [
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'join_group' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/engagement/conversations',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_conversations' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'create_conversation' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                    ]
                );

                register_rest_route(
                    'catlaq/v1',
                    '/engagement/conversations/(?P<id>\d+)/messages',
                    [
                        [
                            'methods'             => 'GET',
                            'callback'            => [ $this, 'list_messages' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                        [
                            'methods'             => 'POST',
                            'callback'            => [ $this, 'send_message' ],
                            'permission_callback' => function () {
                                return is_user_logged_in();
                            },
                        ],
                    ]
                );
            }
        );
    }

    public function list_feed( WP_REST_Request $request ): WP_REST_Response {
        $group_id = $request->get_param( 'group_id' ) ? absint( $request->get_param( 'group_id' ) ) : null;
        $limit    = absint( $request->get_param( 'per_page' ) ?: 50 );
        $user_id  = get_current_user_id();
        $allowed  = $this->permissions->visible_visibilities( $user_id );

        $posts = $this->posts->feed( $limit, $group_id, $allowed );

        return new WP_REST_Response( $posts );
    }

    public function create_post( WP_REST_Request $request ) {
        $content = wp_kses_post( (string) $request->get_param( 'content' ) );
        if ( '' === trim( $content ) ) {
            return new WP_Error( 'catlaq_engagement_content', __( 'Post content required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $user_id     = get_current_user_id();
        $eligibility = $this->permissions->assert_can_post( $user_id );
        if ( is_wp_error( $eligibility ) ) {
            return $eligibility;
        }

        $profile    = $this->profiles->ensure( $user_id );
        $profile_id = (int) ( $profile['id'] ?? 0 );

        if ( ! $profile_id ) {
            return new WP_Error( 'catlaq_engagement_profile', __( 'Profile missing.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $allowed_visibility = $this->permissions->visibility_options( $user_id );
        $visibility         = sanitize_key( (string) $request->get_param( 'visibility' ) );
        if ( ! in_array( $visibility, $allowed_visibility, true ) ) {
            $visibility = $allowed_visibility[0] ?? 'public';
        }

        $this->posts->create(
            $profile_id,
            $user_id,
            $content,
            [
                'group_id'    => $request->get_param( 'group_id' ),
                'visibility'  => $visibility,
                'attachments' => $request->get_param( 'attachments' ),
            ]
        );

        return $this->list_feed( $request );
    }

    public function list_groups( WP_REST_Request $request ): WP_REST_Response {
        $limit   = absint( $request->get_param( 'per_page' ) ?: 50 );
        $allowed = $this->permissions->visible_visibilities( get_current_user_id() );
        return new WP_REST_Response( $this->groups->list( $limit, $allowed ) );
    }


    public function create_group( WP_REST_Request $request ) {
        $name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
        if ( '' === $name ) {
            return new WP_Error( 'catlaq_group_name', __( 'Group name required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $user_id     = get_current_user_id();
        $eligibility = $this->permissions->assert_can_post( $user_id );
        if ( is_wp_error( $eligibility ) ) {
            return $eligibility;
        }

        $desc       = wp_kses_post( (string) $request->get_param( 'description' ) );
        $visibility = $this->permissions->enforce_visibility( $user_id, (string) $request->get_param( 'visibility' ) );
        $group_id   = $this->groups->create( $user_id, $name, $desc, $visibility );

        return new WP_REST_Response( [ 'id' => $group_id ], 201 );
    }


    public function join_group( WP_REST_Request $request ) {
        $group_id = absint( $request->get_param( 'id' ) );
        $user_id  = get_current_user_id();
        $group    = $this->groups->find( $group_id );

        if ( ! $group ) {
            return new WP_Error( 'catlaq_group_missing', __( 'Group not found.', 'catlaq-online-expo' ), [ 'status' => 404 ] );
        }

        if ( ! $this->permissions->visibility_allowed( $user_id, $group['visibility'] ?? 'public' ) ) {
            return new WP_Error( 'catlaq_group_restricted', __( 'Your membership tier cannot access this group.', 'catlaq-online-expo' ), [ 'status' => 403 ] );
        }

        $this->groups->assign_member( $group_id, $user_id );
        return new WP_REST_Response( [ 'group_id' => $group_id ] );
    }


    public function list_conversations( WP_REST_Request $request ): WP_REST_Response {
        $limit = absint( $request->get_param( 'per_page' ) ?: 50 );
        $rows  = $this->conversations->conversations_for( get_current_user_id(), $limit );
        return new WP_REST_Response( $rows );
    }

    public function create_conversation( WP_REST_Request $request ) {
        $participants = (array) $request->get_param( 'participants' );
        $subject      = sanitize_text_field( (string) $request->get_param( 'subject' ) );
        $current      = get_current_user_id();

        $user_ids = array_map( 'absint', $participants );
        $user_ids[] = $current;

        $conversation_id = $this->conversations->create( $user_ids, $subject );
        if ( ! $conversation_id ) {
            return new WP_Error( 'catlaq_conversation', __( 'Could not create conversation.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        return new WP_REST_Response( [ 'id' => $conversation_id ], 201 );
    }

    public function list_messages( WP_REST_Request $request ) {
        $conversation_id = absint( $request->get_param( 'id' ) );
        if ( ! $this->user_in_conversation( $conversation_id, get_current_user_id() ) ) {
            return new WP_Error( 'catlaq_conversation_access', __( 'Access denied.', 'catlaq-online-expo' ), [ 'status' => 403 ] );
        }

        $limit = absint( $request->get_param( 'per_page' ) ?: 100 );
        $messages = $this->conversations->messages_for( $conversation_id, $limit );

        return new WP_REST_Response( $messages );
    }

    public function send_message( WP_REST_Request $request ) {
        $conversation_id = absint( $request->get_param( 'id' ) );
        $user_id         = get_current_user_id();

        if ( ! $this->user_in_conversation( $conversation_id, $user_id ) ) {
            return new WP_Error( 'catlaq_conversation_access', __( 'Access denied.', 'catlaq-online-expo' ), [ 'status' => 403 ] );
        }

        $message = wp_kses_post( (string) $request->get_param( 'message' ) );
        if ( '' === trim( $message ) ) {
            return new WP_Error( 'catlaq_message_empty', __( 'Message required.', 'catlaq-online-expo' ), [ 'status' => 400 ] );
        }

        $this->conversations->add_message(
            $conversation_id,
            $user_id,
            $message,
            (array) $request->get_param( 'attachments' )
        );

        return $this->list_messages( $request );
    }

    private function user_in_conversation( int $conversation_id, int $user_id ): bool {
        $participants = $this->conversations->participant_ids( $conversation_id );
        return in_array( $user_id, $participants, true );
    }
}








