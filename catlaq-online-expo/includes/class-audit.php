<?php
namespace Catlaq\Expo;

class Audit {
    const TABLE              = 'catlaq_audit_log';
    const OPTION_LAST_PRUNE  = 'catlaq_log_last_prune';
    const OPTION_RETENTION   = 'catlaq_log_retention_days';
    const DEFAULT_RETENTION  = 90; // days

    public static function log( string $action, array $context = [] ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->insert(
            $table,
            [
                'actor'      => get_current_user_id() ?: null,
                'action'     => $action,
                'context'    => wp_json_encode( $context ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        self::maybe_prune();
    }

    public static function tail( int $limit = 20 ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = max( 1, $limit );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, actor, action, context, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function prune( ?int $days = null ): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $days  = $days ?? self::retention_days();

        if ( $days <= 0 ) {
            return 0;
        }

        $threshold = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $threshold
            )
        );

        update_option( self::OPTION_LAST_PRUNE, time() );

        return (int) $wpdb->rows_affected;
    }

    public static function retention_days(): int {
        $days = (int) get_option( self::OPTION_RETENTION, self::DEFAULT_RETENTION );
        return max( 0, $days );
    }

    private static function maybe_prune(): void {
        $last = (int) get_option( self::OPTION_LAST_PRUNE, 0 );
        if ( $last && ( time() - $last ) < DAY_IN_SECONDS ) {
            return;
        }

        self::prune();
    }
}
