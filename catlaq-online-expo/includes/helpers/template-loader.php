<?php
namespace Catlaq\Expo\Helpers;

function render( string $view, array $data = [] ): void {
    $path = CATLAQ_PLUGIN_PATH . 'views/' . $view . '.php';
    if ( ! file_exists( $path ) ) {
        return;
    }
    extract( $data, EXTR_SKIP );
    include $path;
}

