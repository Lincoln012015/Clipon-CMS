<?php

require_once __DIR__ . '/AnalyticsEventRequest.php';
require_once __DIR__ . '/AnalyticsPageviewStateStore.php';
require_once __DIR__ . '/AnalyticsRateLimiter.php';
require_once __DIR__ . '/AnalyticsTokenService.php';
require_once __DIR__ . '/Settings.php';

final class AnalyticsEventIngestor {
    private const SESSION_TIMEOUT = 1800;
    private AnalyticsTokenService $tokens;
    private AnalyticsPageviewStateStore $states;
    private AnalyticsRateLimiter $rate;
    private string $dataDir;

    public function __construct(string $dataDir, ?AnalyticsTokenService $tokens = null) {
        $this->dataDir = rtrim($dataDir, '/\\');
        $this->tokens = $tokens ?? new AnalyticsTokenService();
        $this->states = new AnalyticsPageviewStateStore($dataDir);
        $this->rate = new AnalyticsRateLimiter($dataDir);
    }

    public function ingest(AnalyticsEventRequest $event, array $server = []): array {
        try {
            $token = $this->tokens->verify($event->token);
            if (!hash_equals((string)$token['jti'], $event->pageviewId)) return $this->reject('invalid_token');
            if (!hash_equals((string)$token['path'], $event->path)) return $this->reject('token_path_mismatch');
            if (!$this->originAllowed($server)) return $this->reject('invalid_origin');
            if (!$this->rate->allow('event:' . $event->pageviewId, 10, 60)) return $this->reject('event_rate_limit');
            if ($event->type === 'page_view') {
                $network = $this->networkKey($server);
                if (!$this->rate->allow('pv-minute:' . $network, 30, 60)
                    || !$this->rate->allow('pv-hour:' . $network, 300, 3600)) return $this->reject('network_rate_limit');
            }

            $acceptedAt = time();
            $date = gmdate('Y-m-d', $acceptedAt);
            return $this->states->update($date, $event->pageviewId, function(?array $state) use ($event, $token, $server, $acceptedAt, $date) {
                if ($event->type === 'page_view') {
                    if ($state !== null) return ['state' => $state, 'accepted' => true, 'duplicate' => true];
                    $visitor = $this->visitorHash($token, $server, $date);
                    $new = [
                        'path' => $event->path,
                        'mode' => $token['mode'],
                        'created_at' => (int)$token['iat'],
                        'accepted_at' => $acceptedAt,
                        'last_seen_at' => $acceptedAt,
                        'visitor_hash' => $visitor,
                        'session_id' => $this->sessionId($visitor, $acceptedAt),
                        'max_sequence' => $event->sequence,
                        'scroll_max' => 0,
                        'visible_seconds' => 0,
                        'conversions' => [],
                        'context' => is_array($token['ctx'] ?? null) ? $token['ctx'] : [],
                    ];
                    return ['state' => $new, 'accepted' => true, 'duplicate' => false];
                }
                if ($state === null) return ['state' => null, 'accepted' => false, 'reason' => 'event_before_pageview'];
                $state['max_sequence'] = max((int)($state['max_sequence'] ?? 0), $event->sequence);
                $state['last_seen_at'] = $acceptedAt;
                if ($event->type === 'scroll') {
                    $value = $event->data['max_percent'] ?? null;
                    if (!is_int($value) || !in_array($value, [25, 50, 75, 100], true)) {
                        return ['state' => null, 'accepted' => false, 'reason' => 'invalid_event'];
                    }
                    $state['scroll_max'] = max((int)($state['scroll_max'] ?? 0), $value);
                } elseif ($event->type === 'engagement') {
                    $value = $event->data['visible_seconds'] ?? null;
                    if (!is_int($value) || $value < 0 || $value > 43200 || $value > ($acceptedAt - (int)$state['accepted_at']) + 60) {
                        return ['state' => null, 'accepted' => false, 'reason' => 'impossible_engagement'];
                    }
                    $state['visible_seconds'] = max((int)($state['visible_seconds'] ?? 0), $value);
                } elseif ($event->type === 'conversion') {
                    $key = $event->data['key'] ?? null;
                    if (!is_string($key) || !$this->conversionAllowed($key)) {
                        return ['state' => null, 'accepted' => false, 'reason' => 'invalid_event'];
                    }
                    if (!empty($state['conversions'][$key])) return ['state' => $state, 'accepted' => true, 'duplicate' => true];
                    $state['conversions'][$key] = true;
                }
                return ['state' => $state, 'accepted' => true, 'duplicate' => false];
            });
        } catch (InvalidArgumentException $e) {
            return $this->reject($e->getMessage());
        } catch (Throwable $e) {
            $this->logSystemError($e->getMessage());
            return ['accepted' => false, 'reason' => 'storage_error'];
        }
    }

    private function originAllowed(array $server): bool {
        $host = strtolower(preg_replace('/:\\d+$/', '', (string)($server['HTTP_HOST'] ?? '')));
        $origin = (string)($server['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
            if ($host === '' || $originHost === '' || !hash_equals($host, $originHost)) return false;
        }
        $fetchSite = strtolower((string)($server['HTTP_SEC_FETCH_SITE'] ?? ''));
        return $fetchSite === '' || in_array($fetchSite, ['same-origin', 'same-site', 'none'], true);
    }

    private function networkKey(array $server): string {
        $ip = (string)($server['REMOTE_ADDR'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip); $ip = implode('.', array_slice($parts, 0, 3)) . '.0';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip); $ip = $packed === false ? '' : bin2hex(substr($packed, 0, 6));
        }
        return hash('sha256', $ip . '|' . $this->uaFamily((string)($server['HTTP_USER_AGENT'] ?? '')));
    }

    private function visitorHash(array $token, array $server, string $date): string {
        if (($token['mode'] ?? '') === CookieConsentPolicy::MODE_FULL) {
            $visitor = (string)($_COOKIE['clipon_visitor_id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $visitor)) {
                $visitor = bin2hex(random_bytes(16));
                if (!headers_sent()) {
                    setcookie('clipon_visitor_id', $visitor, [
                        'expires' => time() + 31536000, 'path' => '/',
                        'secure' => strtolower((string)($server['HTTPS'] ?? '')) === 'on',
                        'httponly' => true, 'samesite' => 'Lax',
                    ]);
                }
            }
            if (class_exists('SessionManager') && session_status() !== PHP_SESSION_ACTIVE) {
                SessionManager::start();
                SessionManager::enforceActivity();
            }
            return hash('sha256', $visitor);
        }
        return hash_hmac('sha256', $this->networkKey($server), hash('sha256', $date . '|' . C_CONFIG_PATH));
    }

    private function sessionId(string $visitor, int $now): string {
        return hash('sha256', $visitor . '|' . gmdate('Y-m-d', $now) . '|' . (int)floor(($now % 86400) / self::SESSION_TIMEOUT));
    }

    private function uaFamily(string $ua): string {
        foreach (['Firefox', 'Edg', 'Chrome', 'Safari', 'Opera'] as $name) if (stripos($ua, $name) !== false) return strtolower($name);
        return 'other';
    }

    private function conversionAllowed(string $key): bool {
        if (!preg_match('/^[a-z0-9_-]{1,48}$/', $key)) return false;
        foreach (Settings::getConversionTypes() as $type) {
            if (!empty($type['enabled']) && ($type['key'] ?? null) === $key) return true;
        }
        return false;
    }

    private function reject(string $reason): array {
        $this->logRisk($reason);
        return ['accepted' => false, 'reason' => $reason];
    }

    private function logRisk(string $reason): void {
        $this->appendDiagnostic(['at' => time(), 'kind' => 'risk', 'reason' => preg_replace('/[^a-z_]/', '', $reason)]);
    }

    private function logSystemError(string $reason): void {
        $this->appendDiagnostic(['at' => time(), 'kind' => 'storage_error', 'reason' => substr($reason, 0, 80)]);
    }

    private function appendDiagnostic(array $row): void {
        if (!is_dir($this->dataDir)) @mkdir($this->dataDir, 0755, true);
        @file_put_contents($this->dataDir . '/analytics_sys.log', json_encode($row, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
}
