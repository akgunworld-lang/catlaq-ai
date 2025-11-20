<?php
namespace Catlaq\Expo;

class Token {
    public static function generate( int $length = 32 ): string {
        return wp_generate_password( $length, false, false );
    }
}
