<?php
namespace Catlaq\Expo;

use Catlaq\Expo\Helpers;

class Activator {
    public static function activate(): void {
        self::register_capabilities();
        Migrator::maybe_upgrade();
    }

    private static function register_capabilities(): void {
        $caps = Helpers\get_capabilities();
        if ( empty( $caps ) || ! function_exists( 'wp_roles' ) ) {
            return;
        }

        $wp_roles = wp_roles();
        if ( ! $wp_roles ) {
            return;
        }

        foreach ( $caps as $cap => $required_cap ) {
            foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
                $role = $wp_roles->get_role( $role_slug );
                if ( ! $role || ! $role->has_cap( $required_cap ) ) {
                    continue;
                }
                $role->add_cap( $cap );
            }
        }
    }
}
