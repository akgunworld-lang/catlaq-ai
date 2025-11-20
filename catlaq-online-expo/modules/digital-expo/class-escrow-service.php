<?php
namespace Catlaq\Expo\Modules\Digital_Expo;

class Escrow_Service {
    public function hold_funds( array $order ): bool {
        $entry = array(
            'rfq_id'    => (int) ( $order['rfq_id'] ?? 0 ),
            'amount'    => (float) ( $order['amount'] ?? 0 ),
            'currency'  => strtoupper( (string) ( $order['currency'] ?? 'USD' ) ),
            'timestamp' => current_time( 'mysql' ),
            'status'    => 'held',
        );

        $this->append_log( $entry );
        do_action( 'catlaq_escrow_held', $entry );

        return true;
    }

    public function release_funds( int $rfq_id ): void {
        $entry = array(
            'rfq_id'    => $rfq_id,
            'timestamp' => current_time( 'mysql' ),
            'status'    => 'released',
        );

        $this->append_log( $entry );
        do_action( 'catlaq_escrow_released', $entry );
    }

    public function latest( int $limit = 10 ): array {
        $log = get_option( 'catlaq_escrow_log', array() );
        return array_slice( array_reverse( $log ), 0, $limit );
    }

    private function append_log( array $entry ): void {
        $log   = get_option( 'catlaq_escrow_log', array() );
        $log[] = $entry;
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }
        update_option( 'catlaq_escrow_log', $log );
    }
}
