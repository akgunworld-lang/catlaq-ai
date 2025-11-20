<?php
namespace Catlaq\Expo\Helpers;

// Register bridge hooks immediately when the helper is loaded.
add_action( 'after_setup_theme', __NAMESPACE__ . '\\announce_theme_support' );
add_filter( 'catlaq_view_path', __NAMESPACE__ . '\\prefer_theme_view', 10, 2 );

/**
 * Allow themes to declare optional support for Catlaq layouts.
 */
function announce_theme_support(): void {
    add_theme_support(
        'catlaq-online-expo',
        [
            'templates' => true,
            'styles'    => true,
        ]
    );
}

/**
 * Let themes override plugin view fragments by placing files inside `catlaq/`.
 */
function prefer_theme_view( string $path, string $view ): string {
    $view       = trim( $view, '/' );
    $candidates = [
        'catlaq/' . $view . '.php',
        'catlaq/' . $view . '/index.php',
    ];

    $override = locate_template( $candidates, false, false );
    if ( $override ) {
        return $override;
    }

    return $path;
}
