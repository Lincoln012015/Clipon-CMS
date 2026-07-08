<?php

class Gate {
    public static function hasPermission(string $permission): bool {
        global $session;
        if (!$session->has('user')) {
            return false;
        }

        if (self::isAdmin()) {
            return true;
        }

        if (($session->get('role', '')) === 'moderator') {
            $permissions = $session->get('permissions', []);
            return in_array($permission, $permissions, true);
        }

        return false;
    }

    public static function requirePermission(string $permission, string $redirectUrl = 'index.php'): void {
        if (!self::hasPermission($permission)) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    public static function isAdmin(): bool {
        global $session;
        return $session->get('role') === 'admin';
    }

    public static function requireAdmin(string $redirectUrl = 'login.php'): void {
        if (!self::isAdmin()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
}
