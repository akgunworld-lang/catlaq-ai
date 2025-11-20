<?php
namespace Catlaq\Expo;

class Module_Registrar {
    /**
     * @var array<string,callable>
     */
    private $modules = [];

    public function register( string $id, callable $factory ): void {
        $this->modules[ $id ] = $factory;
    }

    public function boot_all(): void {
        foreach ( $this->modules as $factory ) {
            $instance = $factory();
            if ( method_exists( $instance, 'boot' ) ) {
                $instance->boot();
            }
        }
    }
}
