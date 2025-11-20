<?php
namespace Catlaq\Expo\Modules\Agreements;

use function wp_strip_all_tags;

class Document_Pdf_Generator {
    public function render( string $html ): string {
        $text = $this->html_to_text( $html );
        $lines = $this->wrap_text( $text );
        return $this->build_pdf( $lines );
    }

    private function html_to_text( string $html ): string {
        $text = wp_strip_all_tags( $html );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        $text = preg_replace( '/\r?\n/', "\n", $text );
        return trim( $text );
    }

    private function wrap_text( string $text ): array {
        $words = preg_split( '/\s+/', $text );
        $lines = [];
        $current = '';
        foreach ( $words as $word ) {
            $candidate = '' === $current ? $word : $current . ' ' . $word;
            if ( strlen( $candidate ) > 90 ) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ( '' !== $current ) {
            $lines[] = $current;
        }
        return $lines ?: [ '' ];
    }

    private function build_pdf( array $lines ): string {
        $line_height = 14;
        $start_y     = 760;
        $content     = "BT\n/F1 12 Tf\n";
        $y           = $start_y;
        foreach ( $lines as $line ) {
            $safe = $this->escape_pdf_text( $line );
            $content .= sprintf( "72 %.2f Td (%s) Tj\n", $y, $safe );
            $y -= $line_height;
            $content .= "0 -14 Td\n";
        }
        $content .= "ET";

        $content_length = strlen( $content );
        $objects        = [];
        $objects[]      = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[]      = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[]      = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
        $objects[]      = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", $content_length, $content );
        $objects[]      = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pdf    = "%PDF-1.4\n";
        $offset = [];
        $pos    = strlen( $pdf );
        foreach ( $objects as $index => $object ) {
            $offset[ $index + 1 ] = $pos;
            $pdf                 .= sprintf( "%d 0 obj\n%s\nendobj\n", $index + 1, $object );
            $pos                  = strlen( $pdf );
        }
        $xref_pos = strlen( $pdf );
        $pdf     .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
        for ( $i = 1; $i <= count( $objects ); $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offset[ $i ] );
        }
        $pdf .= "trailer << /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF";

        return $pdf;
    }

    private function escape_pdf_text( string $text ): string {
        $text = str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $text );
        return $text;
    }
}
