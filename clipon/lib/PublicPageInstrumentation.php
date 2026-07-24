<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/PoweredBy.php';
require_once __DIR__ . '/CookieConsentPolicy.php';
require_once __DIR__ . '/CookieConsentRenderer.php';
require_once __DIR__ . '/AnalyticsTokenService.php';

class PublicPageInstrumentation {
    public static function inject(string $html): string {
        $html = self::injectAnalyticsTask($html);
        $html = (new CookieConsentRenderer(Settings::load(), new CookieConsentPolicy(Settings::load(), new Request())))->inject($html);
        return PoweredBy::injectIntoHtml($html);
    }

    private static function injectAnalyticsTask(string $html): string {
        if (http_response_code() >= 400) {
            return $html;
        }
        $request = new Request();
        $settings = Settings::load();
        $policy = new CookieConsentPolicy($settings, $request);
        $mode = $policy->analyticsModeForRequest();
        $endpointUrl = self::analyticsEndpointUrl();
        $path = AnalyticsTokenService::normalizePath((string)$request->server('REQUEST_URI', '/'));
        $issued = (new AnalyticsTokenService($settings))->issue($path, $mode, self::context($request));
        $config = [
            'version' => 1, 'endpoint' => $endpointUrl, 'tokenEndpoint' => self::analyticsTokenEndpointUrl(),
            'path' => $path, 'pageviewId' => $issued['pageview_id'], 'token' => $issued['token'],
        ];
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $snippet = '<script type="application/json" id="clipon-analytics-config">' . $json . '</script>'
            . '<script src="' . htmlspecialchars(self::analyticsAssetUrl(), ENT_QUOTES, 'UTF-8') . '" defer></script>';
        return self::appendBeforeBodyClose($html, $snippet);
    }

    private static function appendBeforeBodyClose(string $html, string $snippet): string {
        if (strpos($html, '</body>') !== false) {
            return str_replace('</body>', $snippet . '</body>', $html);
        }
        return $html . $snippet;
    }

    private static function ensureSessionStarted(): void {
        if (session_status() === PHP_SESSION_ACTIVE || !class_exists('SessionManager')) {
            return;
        }

        SessionManager::start();
        SessionManager::enforceActivity();
    }

    private static function analyticsEndpointUrl(): string {
        $base = defined('C_BASE_URL') ? (string)C_BASE_URL : (defined('CMS_BASE_PATH') ? (string)CMS_BASE_PATH : '');
        $base = rtrim($base, '/');
        if ($base === '/' || $base === '\\') {
            $base = '';
        }

        return $base . '/clipon/admin/api/track_event.php';
    }

    private static function analyticsTokenEndpointUrl(): string {
        return preg_replace('#track_event\\.php$#', 'analytics_token.php', self::analyticsEndpointUrl());
    }

    private static function analyticsAssetUrl(): string {
        $base = defined('C_BASE_URL') ? rtrim((string)C_BASE_URL, '/') : '';
        return $base . '/clipon/assets/js/analytics.js';
    }

    private static function context(Request $request): array {
        $ua = strtolower((string)$request->server('HTTP_USER_AGENT', ''));
        $device = preg_match('/mobile|android|iphone/', $ua) ? 'mobile' : (preg_match('/tablet|ipad/', $ua) ? 'tablet' : 'desktop');
        $language = strtolower(substr((string)$request->server('HTTP_ACCEPT_LANGUAGE', 'unknown'), 0, 2));
        $refHost = strtolower((string)parse_url((string)$request->server('HTTP_REFERER', ''), PHP_URL_HOST));
        $host = strtolower((string)$request->server('HTTP_HOST', ''));
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') $utm[$key] = trim($value);
        }
        return [
            'device' => $device, 'language' => preg_match('/^[a-z]{2}$/', $language) ? $language : 'unknown',
            'country' => strtoupper(substr((string)$request->server('HTTP_CF_IPCOUNTRY', 'unknown'), 0, 2)),
            'referrer_type' => $refHost === '' ? 'direct' : ($refHost === $host ? 'internal' : 'external'),
            'referrer' => $refHost === '' ? 'direct' : $refHost, 'utm' => $utm,
        ];
    }
}
