<?php

class AnalyticsBotFilter {
    public const REASON_METHOD = 'method';
    public const REASON_NON_HTML = 'non_html';
    public const REASON_EMPTY_UA = 'empty_ua';
    public const REASON_BOT_UA = 'bot_ua';
    public const REASON_DENYLIST = 'denylist';
    public const REASON_PROBE_PATH = 'probe_path';
    public const REASON_BROWSER_HEADERS = 'browser_headers';

    private const DEFAULT_UA_PATTERNS = [
        'bot',
        'googlebot',
        'bingbot',
        'crawler',
        'spider',
        'robot',
        'crawling',
        'headless',
        'uptime',
        'monitor',
        'scrapy',
        'python-requests',
        'python-urllib',
        'aiohttp',
        'httpx',
        'curl',
        'wget',
        'libwww',
        'go-http-client',
        'java/',
        'okhttp',
        'apache-httpclient',
        'httpclient',
        'postman',
        'insomnia',
        'phantomjs',
        'selenium',
        'playwright',
        'puppeteer',
        'lighthouse',
        'pagespeed',
        'gtmetrix',
        'pingdom',
        'statuscake',
        'semrush',
        'ahrefs',
        'mj12bot',
        'dotbot',
        'petalbot',
        'bytespider',
        'yandex',
        'baiduspider',
        'duckduckbot',
        'facebookexternalhit',
        'slurp',
    ];

    private array $allowPatterns;
    private array $denyPatterns;

    public function __construct(array $settings = []) {
        $this->allowPatterns = $this->sanitizePatterns($settings['analytics_bot_allowlist'] ?? []);
        $this->denyPatterns = $this->sanitizePatterns($settings['analytics_bot_denylist'] ?? []);
    }

    public static function sanitizePatterns($patterns): array {
        if (is_string($patterns)) {
            $patterns = preg_split('/\R/', $patterns) ?: [];
        }
        if (!is_array($patterns)) {
            return [];
        }

        $clean = [];
        $seen = [];
        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern === '') {
                continue;
            }
            $pattern = preg_replace('/[\x00-\x1F\x7F]+/u', '', $pattern);
            if ($pattern === null || $pattern === '') {
                continue;
            }
            $pattern = mb_strlen($pattern) > 160 ? mb_substr($pattern, 0, 160) : $pattern;
            $key = mb_strtolower($pattern);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $pattern;
            if (count($clean) >= 100) {
                break;
            }
        }

        return $clean;
    }

    public function evaluate(Request $request, string $uri): array {
        $method = strtoupper((string)$request->method());
        if ($method !== 'GET') {
            return $this->blocked(self::REASON_METHOD);
        }

        $path = $this->normalizeUri((string)(parse_url($uri, PHP_URL_PATH) ?: $uri));
        $accept = strtolower(trim((string)$request->server('HTTP_ACCEPT', '')));
        if ($accept !== '' && !$this->acceptsHtml($accept)) {
            return $this->blocked(self::REASON_NON_HTML);
        }

        $ua = (string)$request->server('HTTP_USER_AGENT', '');
        if ($this->matchesConfiguredPattern($ua, $path, $this->allowPatterns)) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($this->matchesConfiguredPattern($ua, $path, $this->denyPatterns)) {
            return $this->blocked(self::REASON_DENYLIST);
        }

        if ($this->isMachineOrProbePath($path)) {
            return $this->blocked(self::REASON_PROBE_PATH);
        }

        if (trim($ua) === '') {
            return $this->blocked(self::REASON_EMPTY_UA);
        }

        if ($this->matchesAny($ua, self::DEFAULT_UA_PATTERNS)) {
            return $this->blocked(self::REASON_BOT_UA);
        }

        if ($this->hasAutomatedBrowserHeaders($request, $ua)) {
            return $this->blocked(self::REASON_BROWSER_HEADERS);
        }

        return ['allowed' => true, 'reason' => null];
    }

    private function blocked(string $reason): array {
        return ['allowed' => false, 'reason' => $reason];
    }

    private function matchesConfiguredPattern(string $ua, string $path, array $patterns): bool {
        return $this->matchesAny($ua, $patterns) || $this->matchesAny($path, $patterns);
    }

    private function matchesAny(string $value, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && stripos($value, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasAutomatedBrowserHeaders(Request $request, string $ua): bool {
        if (!$this->looksLikeBrowser($ua)) {
            return false;
        }

        $acceptLanguage = trim((string)$request->server('HTTP_ACCEPT_LANGUAGE', ''));
        return $acceptLanguage === '';
    }

    private function looksLikeBrowser(string $ua): bool {
        return preg_match('/mozilla\/|chrome\/|safari\/|firefox\/|edg\/|opr\//i', $ua) === 1;
    }

    private function acceptsHtml(string $accept): bool {
        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml+xml');
    }

    private function isMachineOrProbePath(string $path): bool {
        $path = strtolower($path);
        $path = preg_replace('#/+#', '/', $path) ?: '/';

        if (preg_match('#^/(robots\.txt|security\.txt|ads\.txt|app-ads\.txt|sitemap(?:[-_a-z0-9]*)?\.xml|wp-sitemap\.xml|favicon\.ico|apple-touch-icon(?:-[0-9x]+)?(?:-precomposed)?\.png|browserconfig\.xml|manifest\.json|site\.webmanifest)$#', $path)) {
            return true;
        }

        if (preg_match('#^/(\.well-known|wp-admin|wp-content|wp-includes|wordpress|wp|xmlrpc\.php|adminer\.php|phpmyadmin|pma|vendor|node_modules|uploads|upload|files|backup|backups|cgi-bin)(/|$)#', $path)) {
            return true;
        }

        if (preg_match('#^/(actuator|server-status|server-info|\.env|\.git|composer\.(?:json|lock)|package(?:-lock)?\.json|yarn\.lock|config\.php|config\.json|database\.sql|dump\.sql)(/|$)#', $path)) {
            return true;
        }

        return preg_match('#\.(?:php[0-9]?|asp|aspx|jsp|cgi|env|bak|backup|old|orig|save|sql|zip|tar|gz|rar|7z)(?:/|$)#', $path) === 1;
    }

    private function normalizeUri(string $uri): string {
        $path = trim($uri);
        if ($path === '') return '/';
        $path = preg_replace('#/+#', '/', $path);
        if ($path === null || $path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return mb_strlen($path) > 320 ? mb_substr($path, 0, 320) : $path;
    }
}
