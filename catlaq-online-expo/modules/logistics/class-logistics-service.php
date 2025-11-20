<?php
namespace Catlaq\Expo\Modules\Logistics;

use Catlaq\Expo\Modules\Digital_Expo\Order_Service;
use WP_Error;

class Logistics_Service {
    private $shipments_table;
    private $events_table;
    private Order_Service $orders;

    public function __construct( ?Order_Service $orders = null ) {
        global $wpdb;
        $prefix               = $wpdb->prefix;
        $this->shipments_table = $prefix . 'catlaq_shipments';
        $this->events_table    = $prefix . 'catlaq_shipment_events';
        $this->orders          = $orders ?: new Order_Service();
    }

    public function all( int $limit = 50, ?int $order_id = null ): array {
        global $wpdb;
        $limit = max( 1, $limit );

        $sql    = "SELECT * FROM {$this->shipments_table}";
        $params = [];
        if ( $order_id ) {
            $sql      .= " WHERE order_id = %d";
            $params[]  = $order_id;
        }
        $sql .= " ORDER BY updated_at DESC LIMIT %d";
        $params[] = $limit;

        $query = $wpdb->prepare( $sql, $params );
        $rows  = $wpdb->get_results( $query, ARRAY_A ) ?: [];

        return array_map( fn( $row ) => $this->hydrate_shipment( $row, false ), $rows );
    }

    public function get( int $shipment_id, bool $with_events = true ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->shipments_table} WHERE id = %d", $shipment_id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return $this->hydrate_shipment( $row, $with_events );
    }

    public function get_by_order( int $order_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->shipments_table} WHERE order_id = %d LIMIT 1", $order_id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return $this->hydrate_shipment( $row );
    }

    public function ensure_shipment( array $order, array $args = [] ) {
        $order_id = (int) ( $order['id'] ?? 0 );
        if ( ! $order_id ) {
            return new WP_Error( 'catlaq_logistics_order', __( 'Order reference missing for shipment.', 'catlaq-online-expo' ) );
        }

        $existing = $this->get_by_order( $order_id );
        if ( $existing ) {
            return $existing;
        }

        if ( empty( $order['items'] ) ) {
            $order = $this->orders->get_order( $order_id );
            if ( ! $order ) {
                return new WP_Error( 'catlaq_logistics_order_missing', __( 'Order could not be loaded.', 'catlaq-online-expo' ) );
            }
        }

        $totals = $this->compute_totals( $order );

        global $wpdb;
        $now      = current_time( 'mysql' );
        $booking  = $this->next_booking_ref( $order_id );
        $inserted = $wpdb->insert(
            $this->shipments_table,
            [
                'order_id'          => $order_id,
                'booking_ref'       => $booking,
                'status'            => 'draft',
                'carrier'           => sanitize_text_field( $args['carrier'] ?? '' ),
                'incoterm'          => strtoupper( substr( sanitize_text_field( $args['incoterm'] ?? ( $order['metadata']['incoterm'] ?? 'FOB' ) ), 0, 4 ) ),
                'pickup_location'   => sanitize_text_field( $args['pickup_location'] ?? ( $order['metadata']['pickup_location'] ?? '' ) ),
                'delivery_location' => sanitize_text_field( $args['delivery_location'] ?? ( $order['metadata']['delivery_location'] ?? '' ) ),
                'packages'          => $totals['packages'],
                'total_weight'      => $totals['total_weight'],
                'volume_cbm'        => $totals['volume_cbm'],
                'tracking_number'   => '',
                'metadata'          => wp_json_encode(
                    [
                        'contacts' => $args['contacts'] ?? [],
                        'notes'    => $args['notes'] ?? '',
                    ]
                ),
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'catlaq_logistics_insert', __( 'Shipment could not be created.', 'catlaq-online-expo' ) );
        }

        $shipment_id = (int) $wpdb->insert_id;
        $this->record_event( $shipment_id, 'created', __( 'Shipment draft created.', 'catlaq-online-expo' ) );

        return $this->get( $shipment_id );
    }

    public function update_tracking( int $shipment_id, array $data, string $note = '' ): ?array {
        $updates = [];
        if ( isset( $data['status'] ) ) {
            $updates['status'] = sanitize_key( $data['status'] );
        }
        if ( isset( $data['tracking_number'] ) ) {
            $updates['tracking_number'] = sanitize_text_field( $data['tracking_number'] );
        }
        if ( isset( $data['carrier'] ) ) {
            $updates['carrier'] = sanitize_text_field( $data['carrier'] );
        }
        if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
            $updates['metadata'] = wp_json_encode( $data['metadata'] );
        }

        if ( empty( $updates ) ) {
            return $this->get( $shipment_id );
        }

        global $wpdb;
        $updates['updated_at'] = current_time( 'mysql' );
        $formats               = array_fill( 0, count( $updates ), '%s' );

        $wpdb->update(
            $this->shipments_table,
            $updates,
            [ 'id' => $shipment_id ],
            $formats,
            [ '%d' ]
        );

        if ( '' !== $note || isset( $data['status'] ) ) {
            $event = isset( $data['status'] ) ? $data['status'] : 'update';
            $this->record_event( $shipment_id, $event, $note );
        }

        return $this->get( $shipment_id );
    }

    public function record_event( int $shipment_id, string $event, string $note = '' ): void {
        global $wpdb;
        $wpdb->insert(
            $this->events_table,
            [
                'shipment_id' => $shipment_id,
                'event'       => sanitize_key( $event ),
                'note'        => $note,
                'actor'       => get_current_user_id() ?: null,
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s' ]
        );
    }

    public function get_events( int $shipment_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event, note, actor, created_at FROM {$this->events_table} WHERE shipment_id = %d ORDER BY created_at ASC",
                $shipment_id
            ),
            ARRAY_A
        ) ?: [];

        return $rows;
    }

    private function compute_totals( array $order ): array {
        $total_weight = 0.0;
        $volume       = 0.0;
        $packages     = 0;

        $items = $order['items'] ?? $this->orders->get_items( (int) $order['id'] );
        foreach ( $items as $item ) {
            $packages++;
            $total_weight += (float) ( $item['total_weight'] ?? $item['quantity'] ?? 0 );
            $meta          = $item['metadata'] ?? [];
            if ( isset( $meta['volume_cbm'] ) ) {
                $volume += (float) $meta['volume_cbm'];
            }
        }

        return [
            'total_weight' => round( $total_weight, 4 ),
            'volume_cbm'   => round( $volume, 4 ),
            'packages'     => $packages,
        ];
    }

    private function next_booking_ref( int $order_id ): string {
        $hash = substr( wp_hash( $order_id . microtime() ), 0, 6 );
        return sprintf( 'BK-%d-%s', $order_id, strtoupper( $hash ) );
    }

    private function hydrate_shipment( array $row, bool $with_events = true ): array {
        $row['metadata'] = json_decode( $row['metadata'] ?? '{}', true );
        if ( $with_events ) {
            $row['events'] = $this->get_events( (int) $row['id'] );
        }
        return $row;
    }
}
