<?php
namespace Catlaq\Expo\Modules\Agreements;

class Document_Renderer {
    private string $template_dir;

    public function __construct( ?string $template_dir = null ) {
        $this->template_dir = $template_dir ?: CATLAQ_PLUGIN_PATH . 'views/documents/templates/';
    }

    public function render( string $template_key, array $context ): string {
        $template_key = sanitize_key( $template_key );
        $file         = $this->locate_template( $template_key );
        $data         = $this->prepare_context( $context );
        $template     = $data['template'];
        $auto_fields  = $data['auto_fields'];
        $payload      = $data['payload'];
        $generated    = $data['generated'];

        ob_start();
        include $file;
        return ob_get_clean() ?: '';
    }

    private function locate_template( string $template_key ): string {
        $candidate = $this->template_dir . $template_key . '.php';
        if ( is_readable( $candidate ) ) {
            return $candidate;
        }

        return $this->template_dir . 'default.php';
    }

    private function prepare_context( array $context ): array {
        $defaults = [
            'template'    => [
                'key'   => '',
                'label' => '',
                'category' => '',
            ],
            'generated'   => [
                'by'   => get_current_user_id(),
                'name' => wp_get_current_user()->display_name ?? '',
                'at'   => current_time( 'mysql' ),
            ],
            'auto_fields' => [],
            'payload'     => [],
        ];

        return wp_parse_args( $context, $defaults );
    }
}
