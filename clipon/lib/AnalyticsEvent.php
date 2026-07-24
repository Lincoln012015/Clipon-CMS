<?php

require_once __DIR__ . '/AnalyticsStorage.php';

class AnalyticsEvent {
    private const EVENT_CATEGORY_MAX = 32;
    private const EVENT_ACTION_MAX = 48;
    private const EVENT_LABEL_MAX = 180;
    private const ENGAGED_SECONDS = 10;

    private AnalyticsStorage $storage;

    public function __construct(AnalyticsStorage $storage) {
        $this->storage = $storage;
    }

    public function trackEvent(string $category, string $action, ?string $label = null): void {
        $category = $this->normalizeEventPart($category, self::EVENT_CATEGORY_MAX);
        $action = $this->normalizeEventPart($action, self::EVENT_ACTION_MAX);
        $label = $this->normalizeLabel($label);

        if ($category === '' || $action === '') {
            return;
        }
        if ($category === 'system') return;

        $this->storage->withUpdateLock(function() use ($category, $action, $label) {
            $date = date('Y-m-d');
            $data = $this->storage->loadDay($date);

            if (!isset($data['events'])) $data['events'] = [];
            if (!isset($data['events'][$category])) $data['events'][$category] = [];

            if ($label !== null && $label !== '') {
                if (!isset($data['events'][$category][$label])) $data['events'][$category][$label] = [];
                $data['events'][$category][$label][$action] = ($data['events'][$category][$label][$action] ?? 0) + 1;
            } else {
                $data['events'][$category][$action] = ($data['events'][$category][$action] ?? 0) + 1;
            }

            $this->storage->saveDay($date, $data);
        });
    }

    public function trackBasicEvent(string $category, string $action, ?string $label = null): void {
        $category = $this->normalizeEventPart($category, self::EVENT_CATEGORY_MAX);
        $action = $this->normalizeEventPart($action, self::EVENT_ACTION_MAX);
        $label = $this->normalizeLabel($label);

        if ($category === '' || $action === '') {
            return;
        }
        if ($category === 'system') return;

        $this->storage->withUpdateLock(function() use ($category, $action, $label) {
            $date = date('Y-m-d');
            $data = $this->storage->loadDay($date);

            if (!isset($data['events'][$category])) $data['events'][$category] = [];
            if ($label !== null && $label !== '') {
                if (!isset($data['events'][$category][$label])) $data['events'][$category][$label] = [];
                $data['events'][$category][$label][$action] = ($data['events'][$category][$label][$action] ?? 0) + 1;
            } else {
                $data['events'][$category][$action] = ($data['events'][$category][$action] ?? 0) + 1;
            }

            $this->storage->saveDay($date, $data);
        });
    }

    private function recordSessionEngagement(int $now, int $lastHit): void {
        $duration = $now - $lastHit;
        if ($duration <= 0 || $duration > 1800) {
            return;
        }

        $session = new Session();
        $sessionId = (string)$session->get('analytics_session_id', '');
        $sessionDate = (string)$session->get('analytics_session_date', '');
        if ($sessionId === '' || $sessionDate === '') {
            return;
        }

        $sessionData = $this->storage->loadDay($sessionDate);
        if (!isset($sessionData['sessions'][$sessionId]) || !is_array($sessionData['sessions'][$sessionId])) {
            return;
        }

        $this->recordSessionEngagementInData($sessionData, $sessionId, $now, $lastHit);
        $this->storage->saveDay($sessionDate, $sessionData);
    }

    private function recordSessionEngagementInData(array &$sessionData, string $sessionId, int $now, int $lastHit): void {
        if (!isset($sessionData['sessions'][$sessionId]) || !is_array($sessionData['sessions'][$sessionId])) {
            return;
        }

        $duration = $now - $lastHit;
        if ($duration <= 0 || $duration > 1800) {
            return;
        }

        $row = &$sessionData['sessions'][$sessionId];
        $wasBounced = !empty($row['bounced']);
        $wasEngaged = !empty($row['engaged']);
        $row['engaged_time'] = (int)($row['engaged_time'] ?? 0) + $duration;
        $row['last_seen_at'] = $now;
        $isEngaged = (int)($row['pageview_count'] ?? 0) >= 2 || (int)$row['engaged_time'] >= self::ENGAGED_SECONDS;
        $row['engaged'] = $isEngaged;
        $row['bounced'] = !$isEngaged;

        if ($wasBounced && !$row['bounced']) {
            $sessionData['summary']['bounced_sessions'] = max(0, (int)($sessionData['summary']['bounced_sessions'] ?? 0) - 1);
        } elseif (!$wasBounced && $row['bounced']) {
            $sessionData['summary']['bounced_sessions'] = (int)($sessionData['summary']['bounced_sessions'] ?? 0) + 1;
        }

        if (!$wasEngaged && $row['engaged']) {
            $sessionData['summary']['engaged_sessions'] = (int)($sessionData['summary']['engaged_sessions'] ?? 0) + 1;
        } elseif ($wasEngaged && !$row['engaged']) {
            $sessionData['summary']['engaged_sessions'] = max(0, (int)($sessionData['summary']['engaged_sessions'] ?? 0) - 1);
        }
        unset($row);
    }

    private function recordTime(array &$data, string $uri, int $now, int $lastHit, int $timeout): void {
        $duration = $now - $lastHit;
        if ($duration > 0 && $duration <= $timeout) {
            if (!isset($data['time_on_page'])) $data['time_on_page'] = [];
            if (!isset($data['time_on_page'][$uri])) $data['time_on_page'][$uri] = ['t' => 0, 'c' => 0];
            $data['time_on_page'][$uri]['t'] += $duration;
            $data['time_on_page'][$uri]['c'] += 1;
        }
    }

    private function normalizeUri(string $uri): string {
        $path = trim($uri);
        if ($path === '') {
            return '/';
        }

        $path = preg_replace('#/+#', '/', $path);
        if ($path === null || $path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function normalizeEventPart(string $value, int $maxLength): string {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9_:%\-\.\/ ]+/u', '', $value);
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return trim($value);
    }

    private function normalizeLabel(?string $label): ?string {
        if ($label === null) {
            return null;
        }

        $clean = $this->normalizeEventPart($label, self::EVENT_LABEL_MAX);
        return $clean === '' ? null : $clean;
    }
}
