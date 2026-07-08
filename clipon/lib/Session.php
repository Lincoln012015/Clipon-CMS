<?php

class Session
{
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function flash(string $key, $value): void
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][$key] = $value;
    }

    public function getFlash(string $key, $default = null)
    {
        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        return $default;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['flash'][$key]);
    }
}
