<?php
class Csrf
{
    private static function logValidationFailure($incomingToken, $storedToken): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $session = new Session();
        $sessionId = session_id();
        $sessionHash = $sessionId !== '' ? substr(hash('sha256', $sessionId), 0, 12) : 'none';

        $meta = [
            'time' => date('c'),
            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'origin' => (string)($_SERVER['HTTP_ORIGIN'] ?? ''),
            'referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
            'https' => (string)($_SERVER['HTTPS'] ?? ''),
            'xfp' => (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''),
            'cookie_present' => isset($_SERVER['HTTP_COOKIE']) ? 1 : 0,
            'session_id_hash' => $sessionHash,
            'has_user' => $session->has('user') ? 1 : 0,
            'incoming_len' => is_string($incomingToken) ? strlen($incomingToken) : 0,
            'stored_len' => is_string($storedToken) ? strlen($storedToken) : 0,
            'incoming_prefix' => is_string($incomingToken) ? substr($incomingToken, 0, 10) : '',
            'stored_prefix' => is_string($storedToken) ? substr((string)$storedToken, 0, 10) : '',
        ];

        $logDir = defined('C_LOGS_PATH') ? C_LOGS_PATH : dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = '[csrf] ' . json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($logDir . '/csrf_failures.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function init()
    {
        $session = new Session();
        if (!$session->has('csrf_token')) {
            try {
                $session->set('csrf_token', bin2hex(random_bytes(32)));
            } catch (Exception $e) {
                // fallback
                $session->set('csrf_token', bin2hex(openssl_random_pseudo_bytes(32)));
            }
        }
    }

    public static function token()
    {
        $session = new Session();
        return $session->get('csrf_token', '');
    }

    public static function inputField()
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function validate($token): bool
    {
        $session = new Session();
        $storedToken = $session->get('csrf_token');
        if (empty($token) || empty($storedToken)) {
            self::logValidationFailure($token, $storedToken);
            return false;
        }

        $isValid = hash_equals($storedToken, $token);
        if (!$isValid) {
            self::logValidationFailure($token, $storedToken);
        }

        return $isValid;
    }
}
