<?php
namespace Catlaq\Expo;

class Logger {
    public static function log( string $level, string $message, array $context = [] ): void {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf( '[catlaq:%s] %s %s', $level, $message, wp_json_encode( $context ) ) );
        }
    }
}
