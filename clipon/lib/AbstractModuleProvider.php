<?php
/**
 * Optional base provider with no-op implementations.
 */
abstract class AbstractModuleProvider implements ModuleProviderInterface {
    public function register(ServiceRegistry $registry): void {
        // no-op by default
    }

    public function boot(ServiceRegistry $registry): void {
        // no-op by default
    }
}
