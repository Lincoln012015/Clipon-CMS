<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsGeoResolver.php';
require_once __DIR__ . '/AnalyticsBotFilter.php';
require_once __DIR__ . '/CookieConsentPolicy.php';
require_once __DIR__ . '/RequestSecurity.php';

class AnalyticsTracker {
    private const VISITOR_COOKIE = 'clipon_visitor_id';
    private const VISITOR_COOKIE_TTL = 31536000;
    private const SESSION_TIMEOUT = 1800;
    private const ENGAGED_SECONDS = 10;

    private AnalyticsStorage $storage;
    private AnalyticsGeoResolver $geoResolver;
    private ?AnalyticsBotFilter $botFilter = null;
    private string $dataDir;
    private ?array $conversionMap = null;
    private array $featureFlags = ['funnels' => false, 'attribution' => false];

    public function __construct(AnalyticsStorage $storage, string $dataDir) {
        $this->storage = $storage;
        $this->geoResolver = new AnalyticsGeoResolver();
        $this->dataDir = $dataDir;
    }

    public function track(): void {
        global $request, $session;

        $uri = (string)$request->server('REQUEST_URI', '');
        if (str_starts_with($uri, '/clipon/')) return;

        $ua = (string)$request->server('HTTP_USER_AGENT', '');
        $settings = Settings::load();
        $filterResult = $this->botFilter($settings)->evaluate($request, $uri);
        if (empty($filterResult['allowed'])) {
            $this->recordFilteredRequest((string)($filterResult['reason'] ?? 'unknown'), $settings);
            return;
        }

        $proAnalyticsAvailable = class_exists('ProAnalyticsPolicy') && ProAnalyticsPolicy::isAvailable();
        $this->featureFlags = [
            'funnels' => $proAnalyticsAvailable && !empty($settings['enable_funnels']),
            'attribution' => $proAnalyticsAvailable && !empty($settings['enable_attribution']),
        ];

        $policy = new CookieConsentPolicy($settings, $request);
        if ($policy->analyticsModeForRequest() !== CookieConsentPolicy::MODE_FULL) {
            $this->trackBasic($request, $ua);
            return;
        }

        $cleanUri = $this->normalizeUri((string)(parse_url($uri, PHP_URL_PATH) ?: $uri));
        $now = time();
        $date = date('Y-m-d', $now);
        $visitorId = $this->visitorId($request, $session);
        $visitorHash = hash('sha256', $visitorId);
        $device = $this->getDevice($ua);
        $lang = $this->detectLanguage($request, $session);
        $utm = $this->currentUtm($request);
        $ref = $this->referrer($request);
        $country = $this->resolveCountry($request, $this->getIp($request));

        $this->storage->withUpdateLock(function() use ($request, $session, $cleanUri, $now, $date, $visitorHash, $device, $lang, $utm, $ref, $country) {
            $dayData = $this->storage->loadDay($date);

            $lastHit = (int)$session->get('analytics_last_hit_time', 0);
            $sessionId = (string)$session->get('analytics_session_id', '');
            $sessionDate = (string)$session->get('analytics_session_date', '');
            $isNewSession = $sessionId === '' || $sessionDate === '' || $lastHit === 0 || ($now - $lastHit) > self::SESSION_TIMEOUT;

            if (!$isNewSession && $sessionDate !== $date) {
                $sessionDayData = $this->storage->loadDay($sessionDate);
            } else {
                $sessionDayData = &$dayData;
            }

            if ($isNewSession) {
                $sessionId = $this->newId();
                $sessionDate = $date;
                $session->set('analytics_session_id', $sessionId);
                $session->set('analytics_session_date', $sessionDate);
                $session->set('analytics_session_hits', 0);
                $session->remove('analytics_prev_page_uri');
                $sessionDayData = &$dayData;

                $source = $this->sessionSource($utm, $ref);
                $sessionDayData['sessions'][$sessionId] = [
                    'visitor' => $visitorHash,
                    'started_at' => $now,
                    'last_seen_at' => $now,
                    'entry_page' => $cleanUri,
                    'exit_page' => $cleanUri,
                    'pageview_count' => 0,
                    'engaged_time' => 0,
                    'engaged' => false,
                    'bounced' => true,
                    'source' => $source,
                    'device' => $device,
                    'language' => $lang,
                    'country' => $country,
                ];

                $sessionDayData['summary']['sessions'] = ($sessionDayData['summary']['sessions'] ?? 0) + 1;
                $sessionDayData['summary']['bounced_sessions'] = ($sessionDayData['summary']['bounced_sessions'] ?? 0) + 1;
                $sessionDayData['entry_pages'][$cleanUri] = ($sessionDayData['entry_pages'][$cleanUri] ?? 0) + 1;
                $this->moveSessionExitPage($sessionDayData, null, $cleanUri);
                $this->recordTrafficSource($sessionDayData, $source);
                $this->captureTouchContext($utm, $ref['label'], $device, $cleanUri);
            }

            $dayData['summary']['hits'] = ($dayData['summary']['hits'] ?? 0) + 1;
            if (empty($dayData['visitors'][$visitorHash])) {
                $dayData['visitors'][$visitorHash] = 1;
                $dayData['summary']['uniques'] = ($dayData['summary']['uniques'] ?? 0) + 1;
            }

            $pageKey = (http_response_code() === 404) ? '404:' . $cleanUri : $cleanUri;
            $dayData['pages'][$pageKey] = ($dayData['pages'][$pageKey] ?? 0) + 1;
            $dayData['devices'][$device] = ($dayData['devices'][$device] ?? 0) + 1;
            $dayData['languages'][$lang] = ($dayData['languages'][$lang] ?? 0) + 1;
            $dayData['countries'][$country] = ($dayData['countries'][$country] ?? 0) + 1;
            foreach ($utm as $param => $val) {
                $dayData['utm'][$param][$val] = ($dayData['utm'][$param][$val] ?? 0) + 1;
            }
            if ($ref['type'] === 'internal') {
                $dayData['internal_referrers'][$ref['label']] = ($dayData['internal_referrers'][$ref['label']] ?? 0) + 1;
            }

            $prevUri = (string)$session->get('analytics_prev_page_uri', '');
            if ($prevUri !== '' && $lastHit > 0) {
                $this->recordTime($dayData, $this->normalizeUri($prevUri), $now, $lastHit, self::SESSION_TIMEOUT);
            }

            if (!isset($sessionDayData['sessions'][$sessionId])) {
                $sessionDayData['sessions'][$sessionId] = [
                    'visitor' => $visitorHash,
                    'started_at' => $now,
                    'last_seen_at' => $now,
                    'entry_page' => $cleanUri,
                    'exit_page' => $cleanUri,
                    'pageview_count' => 0,
                    'engaged_time' => 0,
                    'engaged' => false,
                    'bounced' => true,
                    'source' => $this->sessionSource($utm, $ref),
                    'device' => $device,
                    'language' => $lang,
                    'country' => $country,
                ];
                $this->moveSessionExitPage($sessionDayData, null, $cleanUri);
            }

            $previousExitPage = $sessionDayData['sessions'][$sessionId]['exit_page'] ?? null;
            $sessionDayData['sessions'][$sessionId]['last_seen_at'] = $now;
            $sessionDayData['sessions'][$sessionId]['exit_page'] = $cleanUri;
            $sessionDayData['sessions'][$sessionId]['pageview_count'] = (int)($sessionDayData['sessions'][$sessionId]['pageview_count'] ?? 0) + 1;
            $this->moveSessionExitPage($sessionDayData, is_string($previousExitPage) ? $previousExitPage : null, $cleanUri);
            $this->refreshSessionBounceState($sessionDayData, $sessionId);

            $session->set('analytics_session_hits', (int)$session->get('analytics_session_hits', 0) + 1);
            $session->set('analytics_prev_page_uri', $cleanUri);
            $session->set('last_page_uri', $cleanUri);
            $session->set('last_hit_time', $now);
            $session->set('analytics_last_hit_time', $now);

            if ($this->featureFlags['funnels']) {
                $this->rememberFunnelStep($cleanUri, http_response_code());
            }

            if ($conversion = $this->getConversionForUri($cleanUri)) {
                $this->recordConversion($dayData, $cleanUri, $conversion, $utm, $ref['label'], $visitorHash);
            }

            $this->storage->saveDay($date, $dayData);
            if (!$isNewSession && $sessionDate !== $date) {
                $this->storage->saveDay($sessionDate, $sessionDayData);
            }
        });

        if (random_int(1, 200) === 1) {
            $this->storage->cleanupOldData();
        }
    }

    private function trackBasic(Request $request, string $ua): void {
        $uri = (string)$request->server('REQUEST_URI', '');
        $cleanUri = $this->normalizeUri((string)(parse_url($uri, PHP_URL_PATH) ?: $uri));
        $now = time();
        $date = date('Y-m-d', $now);
        $device = $this->getDevice($ua);
        $lang = $this->detectLanguage($request, null);
        $utm = $this->currentUtm($request);
        $ref = $this->referrer($request);
        $ip = $this->getIp($request);
        $country = $this->resolveCountry($request, $ip);
        $host = strtolower((string)$request->server('HTTP_HOST', ''));

        $this->storage->withUpdateLock(function() use ($request, $cleanUri, $date, $device, $lang, $utm, $ref, $ip, $ua, $host, $country) {
            $dayData = $this->storage->loadDay($date);
            if (empty($dayData['daily_salt']) || !is_string($dayData['daily_salt'])) {
                $dayData['daily_salt'] = $this->newId();
            }

            $visitorHash = hash('sha256', $dayData['daily_salt'] . '|' . $host . '|' . $ip . '|' . $ua);
            $dayData['summary']['hits'] = ($dayData['summary']['hits'] ?? 0) + 1;
            if (empty($dayData['visitors'][$visitorHash])) {
                $dayData['visitors'][$visitorHash] = 1;
                $dayData['summary']['uniques'] = ($dayData['summary']['uniques'] ?? 0) + 1;
            }

            $pageKey = (http_response_code() === 404) ? '404:' . $cleanUri : $cleanUri;
            $dayData['pages'][$pageKey] = ($dayData['pages'][$pageKey] ?? 0) + 1;
            $dayData['devices'][$device] = ($dayData['devices'][$device] ?? 0) + 1;
            $dayData['languages'][$lang] = ($dayData['languages'][$lang] ?? 0) + 1;
            $dayData['countries'][$country] = ($dayData['countries'][$country] ?? 0) + 1;
            foreach ($utm as $param => $val) {
                $dayData['utm'][$param][$val] = ($dayData['utm'][$param][$val] ?? 0) + 1;
            }

            $source = $this->sessionSource($utm, $ref);
            $this->recordTrafficSource($dayData, $source);
            if ($ref['type'] === 'internal') {
                $dayData['internal_referrers'][$ref['label']] = ($dayData['internal_referrers'][$ref['label']] ?? 0) + 1;
            }

            if ($conversion = $this->getConversionForUri($cleanUri)) {
                $this->recordBasicConversion($dayData, $cleanUri, $conversion, $utm, $ref['label'], $visitorHash);
            }

            $this->storage->saveDay($date, $dayData);
        });
    }

    private function visitorId(Request $request, Session $session): string {
        $cookie = (string)$request->cookie(self::VISITOR_COOKIE, '');
        if (preg_match('/^[a-f0-9]{32}$/', $cookie)) {
            $session->set('analytics_visitor_id', $cookie);
            return $cookie;
        }

        $sessionVisitor = (string)$session->get('analytics_visitor_id', '');
        if (preg_match('/^[a-f0-9]{32}$/', $sessionVisitor)) {
            return $sessionVisitor;
        }

        $visitor = $this->newId();
        $session->set('analytics_visitor_id', $visitor);
        if (!headers_sent()) {
            setcookie(self::VISITOR_COOKIE, $visitor, [
                'expires' => time() + self::VISITOR_COOKIE_TTL,
                'path' => '/',
                'secure' => RequestSecurity::isSecure($request),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $visitor;
    }

    private function newId(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return md5(uniqid('analytics', true));
        }
    }

    private function currentUtm(Request $request): array {
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $param) {
            $raw = $request->query($param);
            if (!is_string($raw)) continue;
            $val = $this->normalizeDimensionValue($raw);
            if ($val !== '') $utm[$param] = $val;
        }
        return $utm;
    }

    private function referrer(Request $request): array {
        $raw = $request->server('HTTP_REFERER', '');
        if (!is_string($raw) || trim($raw) === '') {
            return ['type' => 'direct', 'label' => 'direct'];
        }

        $host = strtolower((string)$request->server('HTTP_HOST', ''));
        $refHost = strtolower((string)parse_url($raw, PHP_URL_HOST));
        if ($refHost === '') {
            return ['type' => 'unknown', 'label' => 'unknown'];
        }

        $label = $this->normalizeDimensionValue($refHost);
        if ($host !== '' && strcasecmp($refHost, $host) === 0) {
            return ['type' => 'internal', 'label' => $label];
        }

        return ['type' => 'external', 'label' => $label];
    }

    private function sessionSource(array $utm, array $ref): array {
        if (!empty($utm['utm_source'])) {
            $parts = [$utm['utm_source']];
            if (!empty($utm['utm_medium'])) $parts[] = $utm['utm_medium'];
            if (!empty($utm['utm_campaign'])) $parts[] = $utm['utm_campaign'];
            return ['type' => 'campaign', 'label' => implode('/', $parts), 'utm' => $utm, 'referrer' => $ref['label']];
        }

        if ($ref['type'] === 'external') {
            return ['type' => 'external', 'label' => $ref['label'], 'utm' => [], 'referrer' => $ref['label']];
        }

        return ['type' => 'direct', 'label' => 'direct', 'utm' => [], 'referrer' => $ref['label']];
    }

    private function recordTrafficSource(array &$data, array $source): void {
        if (($source['type'] ?? '') === 'campaign') {
            $label = $source['label'] ?? 'unknown';
            $data['traffic_sources']['campaign'][$label] = ($data['traffic_sources']['campaign'][$label] ?? 0) + 1;
            return;
        }

        if (($source['type'] ?? '') === 'external') {
            $label = $source['label'] ?? 'unknown';
            $data['traffic_sources']['external'][$label] = ($data['traffic_sources']['external'][$label] ?? 0) + 1;
            $data['referrers'][$label] = ($data['referrers'][$label] ?? 0) + 1;
            return;
        }

        $data['traffic_sources']['direct'] = ($data['traffic_sources']['direct'] ?? 0) + 1;
    }

    private function moveSessionExitPage(array &$data, ?string $oldExit, string $newExit): void {
        if ($oldExit === $newExit) {
            return;
        }

        if ($oldExit !== null && $oldExit !== '' && isset($data['exit_pages'][$oldExit])) {
            $data['exit_pages'][$oldExit]--;
            if ($data['exit_pages'][$oldExit] <= 0) {
                unset($data['exit_pages'][$oldExit]);
            }
        }

        if ($newExit !== '') {
            $data['exit_pages'][$newExit] = ($data['exit_pages'][$newExit] ?? 0) + 1;
        }
    }

    private function refreshSessionBounceState(array &$data, string $sessionId): void {
        if (!isset($data['sessions'][$sessionId])) return;
        $row = &$data['sessions'][$sessionId];
        $wasBounced = !empty($row['bounced']);
        $wasEngaged = !empty($row['engaged']);
        $pageviews = (int)($row['pageview_count'] ?? 0);
        $engagedTime = (int)($row['engaged_time'] ?? 0);
        $isEngaged = $pageviews >= 2 || $engagedTime >= self::ENGAGED_SECONDS;

        $row['engaged'] = $isEngaged;
        $row['bounced'] = !$isEngaged;

        if ($wasBounced && !$row['bounced']) {
            $data['summary']['bounced_sessions'] = max(0, (int)($data['summary']['bounced_sessions'] ?? 0) - 1);
        } elseif (!$wasBounced && $row['bounced']) {
            $data['summary']['bounced_sessions'] = (int)($data['summary']['bounced_sessions'] ?? 0) + 1;
        }

        if (!$wasEngaged && $row['engaged']) {
            $data['summary']['engaged_sessions'] = (int)($data['summary']['engaged_sessions'] ?? 0) + 1;
        } elseif ($wasEngaged && !$row['engaged']) {
            $data['summary']['engaged_sessions'] = max(0, (int)($data['summary']['engaged_sessions'] ?? 0) - 1);
        }
        unset($row);
    }

    private function getConversionForUri(string $uri): ?string {
        if ($this->conversionMap === null) {
            $file = C_CONFIG_PATH . '/conversions.php';
            $config = file_exists($file) ? read_json_file($file) : [];
            $this->conversionMap = is_array($config['pages'] ?? null) ? $config['pages'] : [];
        }

        return $this->conversionMap[$uri] ?? null;
    }

    private function recordConversion(array &$data, string $uri, string $type, array $utm, string $ref, string $visitorHash): void {
        $session = new Session();
        $convKey = $this->generateConversionKey($uri, $type);
        $seen = $session->get('conv_seen', []);
        if (isset($seen[$convKey])) return;

        $seen[$convKey] = time();
        if (count($seen) > 100) {
            asort($seen);
            $seen = array_slice($seen, -100, null, true);
        }
        $session->set('conv_seen', $seen);

        $data['conversions']['total'] = ($data['conversions']['total'] ?? 0) + 1;
        $data['conversions']['pages'][$uri] = ($data['conversions']['pages'][$uri] ?? 0) + 1;
        $data['conversions']['types'][$type] = ($data['conversions']['types'][$type] ?? 0) + 1;
        $data['conversions']['recent'][] = [
            'uri' => $uri,
            'type' => $type,
            'ts' => time(),
            'utm' => $utm,
            'ref' => $ref,
            'v' => $visitorHash,
        ];
        if (count($data['conversions']['recent']) > 50) {
            $data['conversions']['recent'] = array_slice($data['conversions']['recent'], -50);
        }

        $this->recordAdvancedConversion($data, $uri, $type, $utm, $ref);
    }

    private function recordBasicConversion(array &$data, string $uri, string $type, array $utm, string $ref, string $visitorHash): void {
        // Privacy/basic mode is pageview-based and can count repeated visits to a conversion URI.
        $data['conversions']['total'] = ($data['conversions']['total'] ?? 0) + 1;
        $data['conversions']['pages'][$uri] = ($data['conversions']['pages'][$uri] ?? 0) + 1;
        $data['conversions']['types'][$type] = ($data['conversions']['types'][$type] ?? 0) + 1;
        $data['conversions']['recent'][] = [
            'uri' => $uri,
            'type' => $type,
            'ts' => time(),
            'utm' => $utm,
            'ref' => $ref,
            'v' => $visitorHash,
        ];
        if (count($data['conversions']['recent']) > 50) {
            $data['conversions']['recent'] = array_slice($data['conversions']['recent'], -50);
        }
    }

    private function recordAdvancedConversion(array &$data, string $uri, string $type, array $utm, string $ref): void {
        if (!$this->featureFlags['attribution'] && !$this->featureFlags['funnels']) return;

        if ($this->featureFlags['attribution']) {
            $session = new Session();
            $first = $session->get('attr_first_touch');
            $last = $session->get('attr_last_touch');
            $firstLabel = $first['label'] ?? $this->buildTouchLabel($utm, $ref);
            $lastLabel = $last['label'] ?? $this->buildTouchLabel($utm, $ref);
            $data['attribution']['first_touch'][$firstLabel] = ($data['attribution']['first_touch'][$firstLabel] ?? 0) + 1;
            $data['attribution']['last_touch'][$lastLabel] = ($data['attribution']['last_touch'][$lastLabel] ?? 0) + 1;
            $data['attribution']['recent'][] = ['uri' => $uri, 'type' => $type, 'first' => $firstLabel, 'last' => $lastLabel, 'ts' => time()];
            if (count($data['attribution']['recent']) > 100) $data['attribution']['recent'] = array_slice($data['attribution']['recent'], -100);
        }

        if ($this->featureFlags['funnels']) {
            $session = new Session();
            $path = $session->get('funnel_steps', []);
            if (empty($path)) $path = [$uri];
            if (empty($path) || end($path) !== $uri) $path[] = $uri;
            $pathKey = implode(' > ', array_slice($path, -20));
            $data['funnels']['completed'][$pathKey] = ($data['funnels']['completed'][$pathKey] ?? 0) + 1;
            $data['funnels']['recent'][] = ['path' => $path, 'type' => $type, 'ts' => time()];
            if (count($data['funnels']['recent']) > 50) $data['funnels']['recent'] = array_slice($data['funnels']['recent'], -50);
        }
    }

    private function buildTouchLabel(array $utm, string $ref): string {
        if (!empty($utm['utm_source'])) {
            $parts = [$utm['utm_source']];
            if (!empty($utm['utm_medium'])) $parts[] = $utm['utm_medium'];
            if (!empty($utm['utm_campaign'])) $parts[] = $utm['utm_campaign'];
            return 'utm:' . implode('/', $parts);
        }

        if ($ref !== 'direct' && $ref !== '') return 'ref:' . $ref;
        return 'direct';
    }

    private function captureTouchContext(array $utm, string $ref, string $device, string $uri): void {
        if (!$this->featureFlags['attribution']) return;
        $label = $this->buildTouchLabel($utm, $ref);
        $session = new Session();
        if (!$session->has('attr_first_touch')) {
            $session->set('attr_first_touch', ['label' => $label, 'ts' => time(), 'utm' => $utm, 'ref' => $ref, 'device' => $device, 'entry' => $uri]);
        }
        if ($ref !== 'internal') {
            $session->set('attr_last_touch', ['label' => $label, 'ts' => time(), 'utm' => $utm, 'ref' => $ref, 'device' => $device, 'page' => $uri]);
        }
    }

    private function rememberFunnelStep(string $uri, int $status = 200): void {
        if ($status >= 400 || $status === 0) return;
        $session = new Session();
        $steps = $session->get('funnel_steps', []);
        if (empty($steps) || end($steps) !== $uri) {
            $steps[] = $uri;
            if (count($steps) > 20) $steps = array_slice($steps, -20);
            $session->set('funnel_steps', $steps);
        }
    }

    private function normalizeDimensionValue(string $value): string {
        $value = trim(mb_strtolower($value));
        if ($value === '') return '';
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value);
        if ($value === null) return '';
        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120) : $value;
    }

    private function normalizeUri(string $uri): string {
        $path = trim($uri);
        if ($path === '') return '/';
        $path = preg_replace('#/+#', '/', $path);
        if ($path === null || $path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return mb_strlen($path) > 320 ? mb_substr($path, 0, 320) : $path;
    }

    private function generateConversionKey(string $uri, string $type): string {
        global $request;
        $components = [$uri, $type];
        if (is_object($request) && ($cid = (string)$request->query('cid', '')) !== '') $components[] = 'cid:' . $cid;
        if (is_object($request)) {
            $postData = $request->post();
            if (!empty($postData)) {
                unset($postData['csrf_token'], $postData['_token'], $postData['ts'], $postData['random']);
                $components[] = 'post:' . md5(json_encode($postData));
            }
        }
        $components[] = 'w:' . floor(time() / 300);
        return md5(implode('|', $components));
    }

    private function getIp(Request $request): string {
        $cfIp = $request->server('HTTP_CF_CONNECTING_IP');
        if (is_string($cfIp) && filter_var($cfIp, FILTER_VALIDATE_IP)) return $cfIp;
        $remoteAddr = $request->server('REMOTE_ADDR');
        if (is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) return $remoteAddr;
        return '127.0.0.1';
    }

    private function getDevice(string $ua): string {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) return 'tablet';
        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $ua)) return 'mobile';
        return 'desktop';
    }

    private function detectLanguage(Request $request, ?Session $session): string {
        $accept = (string)$request->server('HTTP_ACCEPT_LANGUAGE', '');
        $browserLanguage = $this->languageFromAcceptHeader($accept);
        if ($browserLanguage !== '') {
            return $browserLanguage;
        }

        if ($session instanceof Session) {
            $sessionLanguage = Settings::normalizeLanguageCode((string)$session->get('site_lang', ''));
            if ($sessionLanguage !== '' && Settings::isValidLanguageCode($sessionLanguage)) {
                return strtolower(explode('-', $sessionLanguage)[0]);
            }
        }

        return 'unknown';
    }

    private function languageFromAcceptHeader(string $accept): string {
        if (trim($accept) === '') {
            return '';
        }

        $languages = [];
        foreach (explode(',', $accept) as $index => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $code = Settings::normalizeLanguageCode((string)($pieces[0] ?? ''));
            if ($code === '' || !Settings::isValidLanguageCode($code)) {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $piece) {
                if (preg_match('/^q=([0-9.]+)$/i', $piece, $m)) {
                    $quality = max(0.0, min(1.0, (float)$m[1]));
                }
            }

            if ($quality <= 0) {
                continue;
            }

            $languages[] = [
                'code' => strtolower(explode('-', $code)[0]),
                'quality' => $quality,
                'index' => $index,
            ];
        }

        if (empty($languages)) {
            return '';
        }

        usort($languages, function(array $a, array $b): int {
            $qualityCompare = $b['quality'] <=> $a['quality'];
            return $qualityCompare !== 0 ? $qualityCompare : ($a['index'] <=> $b['index']);
        });

        return $languages[0]['code'];
    }

    private function resolveCountry(Request $request, string $ip): string {
        return $this->geoResolver->resolveCountry($request, $ip);
    }

    private function botFilter(array $settings): AnalyticsBotFilter {
        if ($this->botFilter === null) {
            $this->botFilter = new AnalyticsBotFilter($settings);
        }
        return $this->botFilter;
    }

    private function recordFilteredRequest(string $reason, array $settings): void {
        if (empty($settings['analytics_bot_filter_debug'])) {
            return;
        }

        $allowedReasons = [
            AnalyticsBotFilter::REASON_METHOD => true,
            AnalyticsBotFilter::REASON_NON_HTML => true,
            AnalyticsBotFilter::REASON_EMPTY_UA => true,
            AnalyticsBotFilter::REASON_BOT_UA => true,
            AnalyticsBotFilter::REASON_DENYLIST => true,
            AnalyticsBotFilter::REASON_PROBE_PATH => true,
            AnalyticsBotFilter::REASON_BROWSER_HEADERS => true,
        ];
        if (!isset($allowedReasons[$reason])) {
            $reason = 'unknown';
        }

        $date = date('Y-m-d');
        $this->storage->withUpdateLock(function() use ($date, $reason) {
            $dayData = $this->storage->loadDay($date);
            $dayData['bot_filter']['total'] = (int)($dayData['bot_filter']['total'] ?? 0) + 1;
            $dayData['bot_filter']['reasons'][$reason] = (int)($dayData['bot_filter']['reasons'][$reason] ?? 0) + 1;
            $this->storage->saveDay($date, $dayData);
        });
    }

    private function recordTime(array &$data, string $uri, int $now, int $lastHit, int $timeout): void {
        $duration = $now - $lastHit;
        if ($duration > 0 && $duration <= $timeout) {
            $data['time_on_page'][$uri]['t'] = ($data['time_on_page'][$uri]['t'] ?? 0) + $duration;
            $data['time_on_page'][$uri]['c'] = ($data['time_on_page'][$uri]['c'] ?? 0) + 1;
        }
    }
}
