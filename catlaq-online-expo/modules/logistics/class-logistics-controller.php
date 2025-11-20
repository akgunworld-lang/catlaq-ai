<?php
namespace Catlaq\Expo\Modules\Logistics;

use Catlaq\Expo\Modules\Digital_Expo\Order_Service;

class Logistics_Controller {
    private Logistics_Service $service;
    private Order_Service $orders;

    public function __construct( ?Logistics_Service $service = null, ?Order_Service $orders = null ) {
        $this->service = $service ?: new Logistics_Service( $orders );
        $this->orders  = $orders ?: new Order_Service();
    }

    public function boot(): void {
        add_action( 'catlaq_logistics_booking_required', [ $this, 'auto_create_shipment' ], 10, 1 );
        add_action( 'catlaq_logistics_tracking_update', [ $this, 'auto_tracking_update' ], 10, 1 );
        add_action( 'catlaq_order_closed', [ $this, 'mark_shipment_delivered' ], 10, 1 );
    }

    public function auto_create_shipment( array $order ): void {
        $result = $this->service->ensure_shipment( $order );
        if ( is_wp_error( $result ) ) {
            do_action( 'catlaq_logistics_error', 'booking', $result->get_error_message(), $order );
        }
    }

    public function auto_tracking_update( array $order ): void {
        $order_id = (int) ( $order['id'] ?? 0 );
        if ( ! $order_id ) {
            return;
        }

        $shipment = $this->service->get_by_order( $order_id ) ?: $this->service->ensure_shipment( $order );
        if ( is_wp_error( $shipment ) || ! $shipment ) {
            return;
        }

        $tracking = $order['metadata']['tracking_number'] ?? '';
        $note     = __( 'Order marked as shipped.', 'catlaq-online-expo' );
        $this->service->update_tracking(
            (int) $shipment['id'],
            [
                'status'          => 'in_transit',
                'tracking_number' => $tracking,
            ],
            $note
        );
    }

    public function mark_shipment_delivered( array $order ): void {
        $shipment = $this->service->get_by_order( (int) $order['id'] );
        if ( ! $shipment ) {
            return;
        }

        $this->service->update_tracking(
            (int) $shipment['id'],
            [
                'status' => 'delivered',
            ],
            __( 'Order closed – shipment delivered.', 'catlaq-online-expo' )
        );
    }
}
