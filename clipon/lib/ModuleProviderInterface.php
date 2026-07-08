<?php
/**
 * Interface for module providers.
 * Modules should provide a provider implementing these methods
 * to register services and boot hooks.
 */
interface ModuleProviderInterface {
    /**
     * Register services/factories in the ServiceRegistry.
     * @param ServiceRegistry $registry
     * @return void
     */
    public function register(ServiceRegistry $registry): void;

    /**
     * Boot the module (attach hooks, perform runtime wiring).
     * @param ServiceRegistry $registry
     * @return void
     */
    public function boot(ServiceRegistry $registry): void;
}
