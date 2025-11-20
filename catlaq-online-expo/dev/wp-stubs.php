<?php
/**
 * Development-only stubs for WordPress symbols.
 */

if ( defined( 'CATLAQ_WP_STUBS_LOADED' ) ) {
    return;
}

define( 'CATLAQ_WP_STUBS_LOADED', true );

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error extends Exception {
        public $errors = [];
        public $error_data = [];

        public function __construct( $code = '', $message = '', $data = [] ) {
            if ( $code ) {
                $this->add( $code, $message, $data );
            }
        }

        public function add( $code, $message, $data = [] ) {
            $this->errors[ $code ][]    = $message;
            $this->error_data[ $code ] = $data;
        }

        public function get_error_message( $code = '' ) {
            if ( $code && ! empty( $this->errors[ $code ] ) ) {
                return $this->errors[ $code ][0];
            }

            if ( ! $code && $this->errors ) {
                $first = reset( $this->errors );
                return $first[0] ?? '';
            }

            return '';
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        protected $data;
        protected $status;
        protected $headers;

        public function __construct( $data = null, $status = 200, array $headers = [] ) {
            $this->data    = $data;
            $this->status  = (int) $status;
            $this->headers = $headers;
        }

        public function set_data( $data ) {
            $this->data = $data;
            return $this;
        }

        public function get_data() {
            return $this->data;
        }

        public function set_status( $status ) {
            $this->status = (int) $status;
            return $this;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request implements ArrayAccess {
        protected $params = [];
        protected $headers = [];
        protected $body = '';

        public function __construct( array $params = [] ) {
            $this->params = $params;
        }

        public function get_param( $key ) {
            return $this->params[ $key ] ?? null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_headers( array $headers ): void {
            $this->headers = $headers;
        }

        public function get_header( $key ) {
            $key = strtolower( $key );
            foreach ( $this->headers as $header => $value ) {
                if ( strtolower( $header ) === $key ) {
                    return $value;
                }
            }

            return null;
        }

        public function set_body( string $body ): void {
            $this->body = $body;
        }

        public function get_body() {
            return $this->body;
        }

        public function offsetExists( $offset ): bool {
            return isset( $this->params[ $offset ] );
        }

        public function offsetGet( $offset ) {
            return $this->params[ $offset ] ?? null;
        }

        public function offsetSet( $offset, $value ): void {
            $this->params[ $offset ] = $value;
        }

        public function offsetUnset( $offset ): void {
            unset( $this->params[ $offset ] );
        }
    }
}

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( '__return_true' ) ) {
    function __return_true() {
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = [], $override = false ) {
        return true;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return true;
    }
}

if ( ! function_exists( 'user_can' ) ) {
    function user_can( $user_id, $capability ) {
        return true;
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        return true;
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $maybeint ) {
        return abs( intval( $maybeint ) );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return is_scalar( $text ) ? trim( (string) $text ) : '';
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $text ) {
        return sanitize_text_field( $text );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return trim( (string) $email );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_\-]/', '', $key );
    }
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
    function rest_sanitize_boolean( $value ) {
        return (bool) $value;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return is_scalar( $data ) ? (string) $data : '';
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) {
        return json_encode( $data );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return date( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $response ) {
        if ( $response instanceof WP_REST_Response ) {
            return $response;
        }

        return new WP_REST_Response( $response );
    }
}

