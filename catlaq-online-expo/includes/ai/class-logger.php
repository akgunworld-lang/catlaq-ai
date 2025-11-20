<?php
namespace Catlaq\Expo\AI;

use DateTimeImmutable;

class Logger {
    const OPTION_KEY = 'catlaq_ai_secret';

    public static function log( ?int $user_id, string $agent, string $context, array $payload ): void {
        global $wpdb;

        $table = "{$wpdb->prefix}catlaq_ai_logs";
        $record = [
            'user_id'           => $user_id,
            'agent'             => $agent,
            'context'           => $context,
            'encrypted_payload' => self::encrypt( $payload ),
            'created_at'        => current_time( 'mysql' ),
        ];

        $wpdb->insert(
            $table,
            $record,
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    public static function decrypt_record( array $row ): array {
        $decoded = self::decrypt( $row['encrypted_payload'] );
        return array_merge(
            $row,
            [
                'payload' => $decoded,
            ]
        );
    }

    public static function rotate_secret( ?string $new_secret = null ): string {
        global $wpdb;

        $old_secret = get_option( self::OPTION_KEY );
        $old_key    = $old_secret ? self::hash_key( $old_secret ) : null;

        if ( empty( $new_secret ) ) {
            $new_secret = bin2hex( random_bytes( 32 ) );
        }

        update_option( self::OPTION_KEY, $new_secret );
        $new_key = self::hash_key( $new_secret );

        if ( $old_key ) {
            $table  = "{$wpdb->prefix}catlaq_ai_logs";
            $last_id = 0;
            $batch   = 100;

            do {
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d",
                        $last_id,
                        $batch
                    ),
                    ARRAY_A
                );

                if ( empty( $rows ) ) {
                    break;
                }

                foreach ( $rows as $row ) {
                    $last_id = (int) $row['id'];
                    $data    = self::decrypt_with_key( $row['encrypted_payload'], $old_key );
                    if ( empty( $data ) ) {
                        continue;
                    }

                    $re_encrypted = self::encrypt_with_key( $data, $new_key );
                    $wpdb->update(
                        $table,
                        [
                            'encrypted_payload' => $re_encrypted,
                        ],
                        [ 'id' => $row['id'] ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            } while ( count( $rows ) === $batch );
        }

        return $new_secret;
    }

    private static function encrypt( array $payload ): string {
        $key = self::get_key_bytes();
        return self::encrypt_with_key( $payload, $key );
    }

    private static function decrypt( string $payload ): array {
        $key = self::get_key_bytes();
        return self::decrypt_with_key( $payload, $key );
    }

    private static function encrypt_with_key( array $payload, string $key ): string {
        $nonce = random_bytes( 12 );
        $json  = wp_json_encode( $payload );

        $tag        = '';
        $ciphertext = openssl_encrypt(
            $json,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        $package = [
            'nonce' => base64_encode( $nonce ),
            'tag'   => base64_encode( $tag ),
            'data'  => base64_encode( $ciphertext ),
            'ts'    => ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( DATE_ATOM ),
        ];

        return wp_json_encode( $package );
    }

    private static function decrypt_with_key( string $payload, string $key ): array {
        $package = json_decode( $payload, true );

        if ( ! is_array( $package ) ) {
            return [];
        }

        $nonce = base64_decode( $package['nonce'] ?? '' );
        $tag   = base64_decode( $package['tag'] ?? '' );
        $data  = base64_decode( $package['data'] ?? '' );

        $plaintext = openssl_decrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        $decoded = json_decode( $plaintext ?: '', true );
        return is_array( $decoded ) ? $decoded : [];
    }

    private static function get_key_bytes(): string {
        $secret = get_option( self::OPTION_KEY );
        if ( empty( $secret ) ) {
            $secret = bin2hex( random_bytes( 32 ) );
            update_option( self::OPTION_KEY, $secret );
        }

        return self::hash_key( $secret );
    }

    private static function hash_key( string $secret ): string {
        return substr( hash( 'sha256', $secret, true ), 0, 32 );
    }
}
