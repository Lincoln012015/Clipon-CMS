<?php
/**
 * Lightweight service registry for Clipon CMS
 * - supports lazy factories (callable)
 * - caches created instances per-request
 */
class ServiceRegistry {
    /** @var array<string,mixed> */
    private $factories = [];
    /** @var array<string,mixed> */
    private $instances = [];

    /**
     * Register a service. Value can be an instance or a factory callable.
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void {
        $this->factories[$key] = $value;
        if (isset($this->instances[$key])) {
            unset($this->instances[$key]);
        }
    }

    /**
     * Register an already-built instance (alias for set)
     */
    public function setInstance(string $key, $instance): void {
        $this->instances[$key] = $instance;
        $this->factories[$key] = $instance;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->factories) || array_key_exists($key, $this->instances);
    }

    /**
     * Get service by key. If a factory (callable) was registered, it will be executed once
     * and the result cached for the remainder of the request.
     * @param string $key
     * @return mixed
     * @throws \RuntimeException
     */
    public function get(string $key) {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (!array_key_exists($key, $this->factories)) {
            throw new \RuntimeException("Service not found in registry: {$key}");
        }

        $value = $this->factories[$key];
        if (is_callable($value)) {
            $instance = $value($this);
        } else {
            $instance = $value;
        }

        $this->instances[$key] = $instance;
        return $instance;
    }

    public function remove(string $key): void {
        if (array_key_exists($key, $this->factories)) unset($this->factories[$key]);
        if (array_key_exists($key, $this->instances)) unset($this->instances[$key]);
    }
}
