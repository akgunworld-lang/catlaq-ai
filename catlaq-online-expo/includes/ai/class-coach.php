<?php
namespace Catlaq\Expo\AI;

use Catlaq\Expo\Settings;

class Coach {
    public static function membership_prompt( array $plan, array $quotas, string $question ): string {
        $lines = [];
        $lines[] = sprintf( 'Plan: %s (%s)', $plan['label'] ?? '', $plan['slug'] ?? '' );

        if ( ! empty( $plan['features'] ) ) {
            $lines[] = 'Features:';
            foreach ( $plan['features'] as $key => $feature ) {
                $lines[] = sprintf( '- %s: %s', $key, is_string( $feature ) ? $feature : wp_json_encode( $feature ) );
            }
        }

        if ( ! empty( $quotas ) ) {
            $lines[] = 'Quotas:';
            foreach ( $quotas as $quota ) {
                $lines[] = sprintf(
                    '- %s: used %d of %s',
                    $quota['metric'] ?? '',
                    (int) ( $quota['used'] ?? 0 ),
                    isset( $quota['limit'] ) ? (string) $quota['limit'] : __( 'Unlimited', 'catlaq-online-expo' )
                );
            }
        }

        if ( '' !== $question ) {
            $lines[] = 'Question: ' . $question;
        }

        $lines[] = 'Remember Catlaq policies: no commissions, fairness, membership upsell only when justified.';

        return implode( "\n", $lines );
    }

    public static function admin_prompt( string $question, array $status_data = [] ): string {
        $settings = Settings::get();
        $lines    = [];

        $lines[] = sprintf(
            'Environment: %s | AI Provider: %s',
            $settings['environment'] ?? 'development',
            $settings['ai_provider'] ?? 'local'
        );

        $missing = (array) ( $status_data['missing_tables'] ?? [] );
        $lines[] = sprintf(
            'Missing tables: %s',
            empty( $missing ) ? 'none' : implode( ', ', $missing )
        );

        $lines[] = 'Task or question: ' . $question;
        $lines[] = 'Provide actionable steps about Catlaq operations. Emphasize no commissions and fairness.';

        return implode( "\n", $lines );
    }
}
