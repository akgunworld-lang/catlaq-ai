<?php
namespace Catlaq\Expo\Modules\Agreements;

use Catlaq\Expo\Logger;

class Document_Factory {
    /**
     * @var string
     */
    private $documents_table;
    /**
     * @var Document_Renderer
     */
    private $renderer;
    private Document_Pdf_Generator $pdf;

    public function __construct( ?Document_Renderer $renderer = null, ?Document_Pdf_Generator $pdf = null ) {
        global $wpdb;
        $this->documents_table = $wpdb->prefix . 'catlaq_documents';
        $this->renderer        = $renderer ?: new Document_Renderer();
        $this->pdf             = $pdf ?: new Document_Pdf_Generator();
    }

    /**
     * Generate a JSON document for a room and record it in the documents table.
     */
    public function generate_for_room( int $room_id, string $template, array $context ): array {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'catlaq-docs/';

        if ( ! wp_mkdir_p( $dir ) ) {
            Logger::log( 'error', 'Unable to create catlaq-docs directory', array( 'dir' => $dir ) );
            return array();
        }

        $filename_html = sprintf( '%s-%s.html', sanitize_key( $template ), time() );
        $path_html     = $dir . $filename_html;

        $html = $this->renderer->render( $template, $context );
        if ( '' === trim( $html ) ) {
            $html = $this->fallback_html( $template, $context );
        }

        $written = file_put_contents( $path_html, $html );

        if ( false === $written ) {
            Logger::log( 'error', 'Failed to write document', array( 'path' => $path ) );
            return array();
        }

        $url_html = trailingslashit( $uploads['baseurl'] ) . 'catlaq-docs/' . $filename_html;

        $pdf_binary = $this->pdf->render( $html );
        $filename_pdf = sprintf( '%s-%s.pdf', sanitize_key( $template ), time() );
        $path_pdf     = $dir . $filename_pdf;
        $url_pdf      = null;

        if ( false !== file_put_contents( $path_pdf, $pdf_binary ) ) {
            $url_pdf = trailingslashit( $uploads['baseurl'] ) . 'catlaq-docs/' . $filename_pdf;
        }

        $primary_url = $url_pdf ?: $url_html;

        global $wpdb;
        $wpdb->insert(
            $this->documents_table,
            array(
                'room_id'         => $room_id,
                'template_key'    => $template,
                'file_path'       => $primary_url,
                'html_path'       => $url_html,
                'pdf_path'        => $url_pdf,
                'signature_status'=> 'draft',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        $document_id = (int) $wpdb->insert_id;

        $document = array(
            'id'               => $document_id,
            'room_id'          => $room_id,
            'template_key'     => $template,
            'file_url'         => $primary_url,
            'html_url'         => $url_html,
            'pdf_url'          => $url_pdf,
            'signature_status' => 'draft',
        );

        do_action( 'catlaq_document_generated', $document );

        return $document;
    }

    public function list_for_room( int $room_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, room_id, template_key, file_path as file_url, html_path, pdf_path, signature_status, created_at FROM {$this->documents_table} WHERE room_id = %d ORDER BY id DESC",
                $room_id
            ),
            ARRAY_A
        ) ?: array();
    }

    public function recent( int $limit = 20 ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, room_id, template_key, file_path as file_url, html_path, pdf_path, signature_status, created_at FROM {$this->documents_table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    public function get_document( int $document_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, room_id, template_key, file_path as file_url, html_path, pdf_path, signature_status, created_at FROM {$this->documents_table} WHERE id = %d",
                $document_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function update_signature_status( int $document_id, string $status ): void {
        global $wpdb;
        $wpdb->update(
            $this->documents_table,
            array(
                'signature_status' => $status,
            ),
            array( 'id' => $document_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    private function fallback_html( string $template_key, array $context ): string {
        $title = sprintf( '%s - %s', strtoupper( str_replace( '_', ' ', $template_key ) ), current_time( 'mysql' ) );
        $auto  = '';
        foreach ( (array) ( $context['auto_fields'] ?? [] ) as $field => $value ) {
            $auto .= sprintf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html( ucwords( str_replace( '_', ' ', $field ) ) ),
                esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) )
            );
        }

        return sprintf(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>%1$s</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:40px;}table{width:100%%;border-collapse:collapse;margin-top:20px;}th,td{text-align:left;padding:8px;border-bottom:1px solid #ddd;}th{width:30%%;text-transform:capitalize;color:#555;}</style></head><body><h1>%1$s</h1><p><strong>%2$s</strong></p><table>%3$s</table></body></html>',
            esc_html( $title ),
            esc_html__( 'Auto Fields Snapshot', 'catlaq-online-expo' ),
            $auto
        );
    }
}
