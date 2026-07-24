<?php

require_once __DIR__ . '/CookieConsentPolicy.php';
require_once __DIR__ . '/Settings.php';

final class AnalyticsTokenService {
    private const VERSION = 1;
    private const TTL = 600;
    private const CLOCK_SKEW = 30;

    private array $keys;
    private string $activeKid;

    public function __construct(?array $settings = null) {
        $persistGeneratedKey = $settings === null;
        $settings = $settings ?? Settings::load();
        $configured = $settings['analytics_signing_keys'] ?? [];
        $this->keys = [];
        if (is_array($configured)) {
            foreach ($configured as $kid => $secret) {
                if (is_string($kid) && preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $kid)
                    && is_string($secret) && strlen($secret) >= 32) {
                    $this->keys[$kid] = $secret;
                }
            }
        }
        if ($this->keys === []) {
            $kid = 'v1_' . gmdate('Y_m');
            $this->keys[$kid] = bin2hex(random_bytes(32));
            if ($persistGeneratedKey) {
                $settings['analytics_signing_keys'] = $this->keys;
                $settings['analytics_active_kid'] = $kid;
                Settings::save($settings);
            }
        }
        $requested = (string)($settings['analytics_active_kid'] ?? '');
        $this->activeKid = isset($this->keys[$requested]) ? $requested : (string)array_key_first($this->keys);
    }

    public function issue(string $path, string $mode, array $context = [], ?int $now = null): array {
        $now = $now ?? time();
        $id = bin2hex(random_bytes(16));
        $payload = [
            'v' => self::VERSION,
            'kid' => $this->activeKid,
            'jti' => $id,
            'path' => self::normalizePath($path),
            'iat' => $now,
            'exp' => $now + self::TTL,
            'mode' => $mode === CookieConsentPolicy::MODE_FULL ? CookieConsentPolicy::MODE_FULL : CookieConsentPolicy::MODE_BASIC,
            'ctx' => $this->sanitizeContext($context),
        ];
        $encoded = self::base64UrlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return ['pageview_id' => $id, 'token' => $encoded . '.' . self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->keys[$this->activeKid], true)), 'payload' => $payload];
    }

    public function verify(string $token, ?int $now = null): array {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('invalid_token');
        }
        $raw = self::base64UrlDecode($parts[0]);
        $signature = self::base64UrlDecode($parts[1]);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || ($payload['v'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('invalid_token');
        }
        $kid = $payload['kid'] ?? null;
        if (!is_string($kid) || !isset($this->keys[$kid]) || !is_string($signature)
            || !hash_equals(hash_hmac('sha256', $parts[0], $this->keys[$kid], true), $signature)) {
            throw new InvalidArgumentException('invalid_token');
        }
        $now = $now ?? time();
        if (!is_int($payload['iat'] ?? null) || !is_int($payload['exp'] ?? null)
            || $payload['iat'] > $now + self::CLOCK_SKEW || $payload['exp'] < $now - self::CLOCK_SKEW) {
            throw new InvalidArgumentException('expired_token');
        }
        if (!preg_match('/^[a-f0-9]{32}$/', (string)($payload['jti'] ?? ''))
            || !in_array($payload['mode'] ?? '', [CookieConsentPolicy::MODE_BASIC, CookieConsentPolicy::MODE_FULL], true)
            || self::normalizePath((string)($payload['path'] ?? '')) !== ($payload['path'] ?? null)) {
            throw new InvalidArgumentException('invalid_token');
        }
        return $payload;
    }

    public static function normalizePath(string $path): string {
        $path = (string)(parse_url($path, PHP_URL_PATH) ?: '/');
        $decoded = rawurldecode($path);
        $decoded = preg_replace('#/+#', '/', '/' . ltrim($decoded, '/')) ?: '/';
        if ($decoded !== '/') $decoded = rtrim($decoded, '/');
        return substr($decoded, 0, 220);
    }

    private function sanitizeContext(array $context): array {
        $out = [];
        foreach (['device', 'language', 'country', 'referrer_type', 'referrer'] as $key) {
            if (isset($context[$key]) && is_string($context[$key])) $out[$key] = substr($context[$key], 0, 100);
        }
        $utm = is_array($context['utm'] ?? null) ? $context['utm'] : [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            if (isset($utm[$key]) && is_string($utm[$key])) $out['utm'][$key] = substr($utm[$key], 0, 100);
        }
        return $out;
    }

    private static function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) return false;
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
