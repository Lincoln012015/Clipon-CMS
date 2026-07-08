<?php

require_once __DIR__ . '/RequestSecurity.php';

class SessionManager {
    public const LIFETIME = 7200; // 2 hours
    public const IDLE_TIMEOUT = 7200; // 2 hours
    public const WARNING_BEFORE = 300; // 5 min
    public const REGENERATE_INTERVAL = 300; // 5 min

    public static function start(): void {
        global $request;
        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = [
                'lifetime' => self::LIFETIME,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => RequestSecurity::isSecure($request)
            ];
            
            if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                session_set_cookie_params($cookieParams);
            } else {
                session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'], '', $cookieParams['secure'], $cookieParams['httponly']);
            }
            
            session_start();
            Csrf::init();
        }
    }

    public static function enforceActivity(): void {
        $now = time();
        if (session_status() !== PHP_SESSION_ACTIVE) return;

        $session = new Session();

        if ($session->has('user')) {
            $last = $session->get('last_activity') ?? ($session->get('created') ?? $now);
            if (($now - $last) > self::IDLE_TIMEOUT) {
                self::destroy();
                self::respondExpired();
                exit;
            }

            if (self::shouldTouchActivity()) {
                $session->set('last_activity', $now);
            }

            $created = $session->get('created') ?? $now;
            if (($now - $created) > self::REGENERATE_INTERVAL) {
                session_regenerate_id(true);
                $session->set('created', $now);
            }
        }
    }

    public static function refreshActivity(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;

        $session = new Session();
        if (!$session->has('user')) return false;

        $now = time();
        $last = (int)($session->get('last_activity') ?? ($session->get('created') ?? $now));
        if (($now - $last) > self::IDLE_TIMEOUT) {
            self::destroy();
            return false;
        }

        $session->set('last_activity', $now);
        if (!$session->has('created')) {
            $session->set('created', $now);
        }

        return true;
    }

    public static function state(): array {
        $now = time();
        $session = new Session();
        $authenticated = $session->has('user');
        $last = (int)($session->get('last_activity') ?? ($session->get('created') ?? $now));
        $expiresAt = $authenticated ? ($last + self::IDLE_TIMEOUT) : null;
        $secondsRemaining = $authenticated ? max(0, $expiresAt - $now) : 0;

        return [
            'status' => 'success',
            'authenticated' => $authenticated,
            'expires_at' => $expiresAt,
            'seconds_remaining' => $secondsRemaining,
            'warning_before' => self::WARNING_BEFORE,
        ];
    }

    public static function expiredPayload(): array {
        return [
            'status' => 'error',
            'code' => 'session_expired',
            'message' => function_exists('__')
                ? __('session_expired')
                : 'Session expired due to inactivity. Please login again.',
        ];
    }

    public static function destroy(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    private static function shouldTouchActivity(): bool {
        global $request;

        $uri = (string)$request->server('REQUEST_URI', '');
        $method = strtoupper((string)$request->server('REQUEST_METHOD', 'GET'));

        if ($method === 'GET' && strpos($uri, '/clipon/admin/api/session.php') !== false) {
            return false;
        }

        return true;
    }

    private static function isApiRequest(): bool {
        global $request;

        $uri = (string)$request->server('REQUEST_URI', '');
        $accept = strtolower((string)$request->server('HTTP_ACCEPT', ''));
        $contentType = strtolower((string)$request->server('CONTENT_TYPE', $request->server('HTTP_CONTENT_TYPE', '')));
        $legacyApiEndpoints = [
            '/clipon/admin/upload_handler.php',
            '/clipon/admin/create_folder.php',
            '/clipon/admin/delete_folder.php',
            '/clipon/admin/delete_media.php',
            '/clipon/admin/move_media.php',
            '/clipon/admin/rename_folder.php',
            '/clipon/admin/bulk_move_media.php',
            '/clipon/admin/bulk_delete_media.php',
            '/clipon/admin/save_media_alt.php',
        ];

        foreach ($legacyApiEndpoints as $endpoint) {
            if (strpos($uri, $endpoint) !== false) {
                return true;
            }
        }

        return strpos($uri, '/clipon/admin/api/') !== false
            || $request->isAjax()
            || strpos($accept, 'application/json') !== false
            || strpos($contentType, 'application/json') !== false;
    }

    private static function respondExpired(): void {
        if (self::isApiRequest()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(401);
            }
            echo json_encode(self::expiredPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $loginUrl = C_ADMIN_URL . '/login.php';
        header('Location: ' . $loginUrl . '?timeout=1');
    }
}
