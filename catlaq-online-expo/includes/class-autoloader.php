<?php
namespace Catlaq\Expo;

/**
 * Autoload plugin classes using WordPress class-file naming.
 */
class Autoloader {
    /**
     * Namespace prefixes mapped to base directories.
     *
     * @var array<string,string>
     */
    private static $prefixes = [
        'Catlaq\\Expo\\Modules\\' => 'modules/',
        'Catlaq\\Expo\\REST\\'    => 'rest/',
        'Catlaq\\Expo\\Helpers\\' => 'includes/helpers/',
        'Catlaq\\Expo\\'          => 'includes/',
    ];

    /**
     * Register the autoloader with SPL.
     */
    public static function register(): void {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Handle class loading.
     */
    private static function autoload( string $class ): void {
        foreach ( self::$prefixes as $prefix => $base_dir ) {
            if ( 0 !== strpos( $class, $prefix ) ) {
                continue;
            }

            $relative = substr( $class, strlen( $prefix ) );
            $path     = self::build_path( $base_dir, $relative );

            if ( $path && file_exists( $path ) ) {
                require_once $path;
            }

            return;
        }
    }

    /**
     * Convert namespace to WordPress-style class file path.
     */
    private static function build_path( string $base_dir, string $relative ): string {
        $parts = explode( '\\', $relative );
        $class = array_pop( $parts );

        $subdir = '';
        if ( ! empty( $parts ) ) {
            $normalized = array_map(
                fn( $segment ) => strtolower( str_replace( '_', '-', $segment ) ),
                $parts
            );
            $subdir = implode( '/', $normalized ) . '/';
        }

        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

        return CATLAQ_PLUGIN_PATH . $base_dir . $subdir . $file;
    }
}
