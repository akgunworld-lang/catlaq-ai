<?php
namespace Catlaq\Expo;

use Catlaq\Expo\AI\Logger as AI_Logger;
use Catlaq\Expo\AI\Manifest as AI_Manifest;

class CLI {
    public static function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        \WP_CLI::add_command( 'catlaq status', [ __CLASS__, 'status' ] );
        \WP_CLI::add_command( 'catlaq logs tail', [ __CLASS__, 'logs_tail' ] );
        \WP_CLI::add_command( 'catlaq logs prune', [ __CLASS__, 'logs_prune' ] );
        \WP_CLI::add_command( 'catlaq orders list', [ __CLASS__, 'orders_list' ] );
        \WP_CLI::add_command( 'catlaq orders status', [ __CLASS__, 'orders_status' ] );
        \WP_CLI::add_command( 'catlaq shipments list', [ __CLASS__, 'shipments_list' ] );
        \WP_CLI::add_command( 'catlaq shipments update', [ __CLASS__, 'shipments_update' ] );
        \WP_CLI::add_command( 'catlaq payments list', [ __CLASS__, 'payments_list' ] );
        \WP_CLI::add_command( 'catlaq payments update', [ __CLASS__, 'payments_update' ] );
        \WP_CLI::add_command( 'catlaq payments webhook', [ __CLASS__, 'payments_webhook' ] );
        \WP_CLI::add_command( 'catlaq ai manifest', [ __CLASS__, 'ai_manifest' ] );
        \WP_CLI::add_command( 'catlaq ai rotate-key', [ __CLASS__, 'ai_rotate_key' ] );
    }

    public static function status(): void {
        $response = rest_do_request( '/catlaq/v1/status' );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        $data           = $response->get_data();
        $missing_tables = empty( $data['missing_tables'] ) ? 'none' : implode( ', ', (array) $data['missing_tables'] );
        \WP_CLI::success( sprintf( 'Version %s, missing tables: %s', $data['version'], $missing_tables ) );
    }

    public static function logs_tail( $args, $assoc_args ): void {
        $limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
        $rows  = Audit::tail( $limit );

        if ( empty( $rows ) ) {
            \WP_CLI::log( 'No audit entries.' );
            return;
        }

        $items = array();
        foreach ( $rows as $row ) {
            $items[] = array(
                'id'      => $row['id'],
                'when'    => $row['created_at'],
                'action'  => $row['action'],
                'actor'   => $row['actor'],
                'context' => $row['context'],
            );
        }

        \WP_CLI\Utils\format_items( 'table', $items, array( 'id', 'when', 'action', 'actor', 'context' ) );
    }

    public static function logs_prune( $args, $assoc_args ): void {
        $days  = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : null;
        $count = Audit::prune( $days );
        \WP_CLI::success( sprintf( 'Pruned %d audit rows (retention %d days).', $count, Audit::retention_days() ) );
    }

    public static function orders_list( $args, $assoc_args ): void {
        $limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
        $response = rest_do_request( new \WP_REST_Request( 'GET', '/catlaq/v1/orders' ) );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        $rows = array_slice( (array) $response->get_data(), 0, $limit );
        if ( empty( $rows ) ) {
            \WP_CLI::log( 'No orders.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $rows,
            array(
                'id',
                'rfq_id',
                'buyer_company_id',
                'seller_company_id',
                'status',
                'total_amount',
                'currency',
                'escrow_state',
                'payment_state',
                'logistics_state',
                'created_at',
            )
        );
    }

    public static function orders_status( $args, $assoc_args ): void {
        list( $order_id, $status ) = $args;
        $request = new \WP_REST_Request( 'POST', sprintf( '/catlaq/v1/orders/%d/status', (int) $order_id ) );
        $request->set_param( 'status', $status );
        if ( isset( $assoc_args['note'] ) ) {
            $request->set_param( 'note', $assoc_args['note'] );
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        \WP_CLI::success( sprintf( 'Order %d set to %s', $order_id, $status ) );
    }

    public static function shipments_list( $args, $assoc_args ): void {
        $limit   = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
        $order   = isset( $assoc_args['order'] ) ? absint( $assoc_args['order'] ) : 0;
        $request = new \WP_REST_Request( 'GET', '/catlaq/v1/shipments' );
        $request->set_param( 'per_page', $limit );
        if ( $order ) {
            $request->set_param( 'order_id', $order );
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        $rows = (array) $response->get_data();
        if ( empty( $rows ) ) {
            \WP_CLI::log( 'No shipments.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $rows,
            array(
                'id',
                'order_id',
                'booking_ref',
                'status',
                'carrier',
                'tracking_number',
                'total_weight',
                'updated_at',
            )
        );
    }

    public static function shipments_update( $args, $assoc_args ): void {
        list( $shipment_id ) = $args;
        $request = new \WP_REST_Request( 'POST', sprintf( '/catlaq/v1/shipments/%d/tracking', (int) $shipment_id ) );

        foreach ( [ 'status', 'tracking', 'carrier', 'note' ] as $param ) {
            if ( isset( $assoc_args[ $param ] ) ) {
                $value = $assoc_args[ $param ];
                $key   = 'tracking' === $param ? 'tracking_number' : $param;
                $request->set_param( $key, $value );
            }
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        $data = $response->get_data();
        \WP_CLI::success( sprintf( 'Shipment %d updated (%s).', $data['id'], $data['status'] ) );
    }

    public static function payments_list( $args, $assoc_args ): void {
        $limit   = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
        $order   = isset( $assoc_args['order'] ) ? absint( $assoc_args['order'] ) : 0;
        $request = new \WP_REST_Request( 'GET', '/catlaq/v1/payments' );
        $request->set_param( 'per_page', $limit );
        if ( $order ) {
            $request->set_param( 'order_id', $order );
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        $rows = (array) $response->get_data();
        if ( empty( $rows ) ) {
            \WP_CLI::log( 'No payment transactions.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $rows,
            array(
                'id',
                'order_id',
                'type',
                'status',
                'amount',
                'currency',
                'provider',
                'provider_ref',
                'created_at',
            )
        );
    }

    public static function payments_update( $args, $assoc_args ): void {
        list( $transaction_id, $status ) = $args;
        $request = new \WP_REST_Request( 'POST', sprintf( '/catlaq/v1/payments/%d/status', (int) $transaction_id ) );
        $request->set_param( 'status', $status );
        if ( isset( $assoc_args['note'] ) ) {
            $request->set_param( 'metadata', [ 'note' => $assoc_args['note'] ] );
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        \WP_CLI::success( sprintf( 'Payment %d set to %s.', $transaction_id, $status ) );
    }

    public static function payments_webhook( $args, $assoc_args ): void {
        $payload = $assoc_args['payload'] ?? '';
        if ( '' === $payload ) {
            \WP_CLI::error( 'Provide --payload=\'{"provider_ref":"..."}\'' );
        }

        $request = new \WP_REST_Request( 'POST', '/catlaq/v1/payments/webhook' );
        $request->set_body( $payload );
        if ( isset( $assoc_args['signature'] ) ) {
            $request->set_header( 'x-catlaq-signature', $assoc_args['signature'] );
        }

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            \WP_CLI::error( $response->as_error() );
        }

        \WP_CLI::success( 'Webhook accepted.' );
    }

    public static function ai_manifest(): void {
        $manifest = AI_Manifest::get();
        \WP_CLI::log( sprintf( "Orchestrator: %s\nPersona: %s", $manifest['orchestrator']['name'], $manifest['orchestrator']['persona'] ) );

        $agents = [];
        foreach ( $manifest['agents'] as $slug => $agent ) {
            $agents[] = [
                'slug'    => $slug,
                'label'   => $agent['label'],
                'scope'   => $agent['scope'],
                'inputs'  => implode( ', ', (array) ( $agent['inputs'] ?? [] ) ),
                'output'  => implode( ', ', (array) ( $agent['output_json'] ?? [] ) ),
            ];
        }

        if ( empty( $agents ) ) {
            \WP_CLI::log( 'No agents defined.' );
            return;
        }

        \WP_CLI\Utils\format_items( 'table', $agents, [ 'slug', 'label', 'scope', 'inputs', 'output' ] );
    }

    public static function ai_rotate_key( $args, $assoc_args ): void {
        $secret = $assoc_args['secret'] ?? null;
        $new    = AI_Logger::rotate_secret( $secret );
        \WP_CLI::success( sprintf( 'AI secret rotated. Current hash: %s', substr( sha1( $new ), 0, 12 ) ) );
    }
}
