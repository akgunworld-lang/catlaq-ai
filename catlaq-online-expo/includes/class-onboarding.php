<?php
namespace Catlaq\Expo;

class Onboarding {
    const META_KEY = '_catlaq_onboarding_step';

    public static function current_step( int $user_id ): int {
        return (int) get_user_meta( $user_id, self::META_KEY, true ) ?: 0;
    }

    public static function advance( int $user_id ): void {
        $next = self::current_step( $user_id ) + 1;
        update_user_meta( $user_id, self::META_KEY, $next );
    }

    public static function reset( int $user_id ): void {
        delete_user_meta( $user_id, self::META_KEY );
    }

    public static function steps(): array {
        return [
            'Complete profile',
            'Verify company',
            'Publish first RFQ',
            'Open agreement room',
        ];
    }

    public static function step_label( int $step ): string {
        $steps = self::steps();
        return $steps[ min( $step, count( $steps ) - 1 ) ] ?? '';
    }
}
