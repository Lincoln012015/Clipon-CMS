<?php

final class AnalyticsV3Reducer {
    public static function reduce(array $pageviews, array $manifest = [], bool $compacted = false): array {
        uasort($pageviews, static fn($a, $b) => (int)($a['accepted_at'] ?? 0) <=> (int)($b['accepted_at'] ?? 0));
        $visitorSessions = [];
        $resolvedSessions = [];
        foreach ($pageviews as $id => $pv) {
            $visitor = (string)($pv['visitor_hash'] ?? '');
            $accepted = (int)($pv['accepted_at'] ?? 0);
            $previous = $visitorSessions[$visitor] ?? null;
            if ($visitor === '' || !is_array($previous) || $accepted - (int)$previous['last'] > 1800) {
                $sessionId = hash('sha256', $visitor . '|' . $accepted . '|' . $id);
            } else {
                $sessionId = $previous['id'];
            }
            $visitorSessions[$visitor] = ['id' => $sessionId, 'last' => $accepted];
            $resolvedSessions[$id] = $sessionId;
        }
        $data = [
            'version' => 3, 'compacted' => $compacted, 'source_manifest' => $manifest,
            'summary' => ['pageviews' => 0, 'hits' => 0, 'uniques' => 0, 'sessions' => 0, 'engaged_sessions' => 0, 'bounced_sessions' => 0],
            'pages' => [], 'visitors' => [], 'sessions' => [], 'entry_pages' => [], 'exit_pages' => [],
            'devices' => [], 'languages' => [], 'countries' => [], 'utm' => [],
            'traffic_sources' => ['direct' => 0, 'external' => [], 'campaign' => []],
            'referrers' => [], 'internal_referrers' => [], 'events' => ['scroll' => [], 'conversions' => []],
            'engagement' => [], 'time_on_page' => [], 'conversions' => ['total' => 0, 'pages' => [], 'types' => [], 'recent' => []],
            'not_found' => ['total' => 0, 'paths' => []], 'risk_filter' => ['total' => 0, 'reasons' => []],
            'system_errors' => ['storage_failures' => 0],
        ];
        foreach ($pageviews as $id => $pv) {
            if (!is_array($pv) || !is_string($id)) continue;
            $path = (string)($pv['path'] ?? '/');
            $visitor = (string)($pv['visitor_hash'] ?? '');
            $session = (string)($resolvedSessions[$id] ?? '');
            $accepted = (int)($pv['accepted_at'] ?? 0);
            $visible = (int)($pv['visible_seconds'] ?? 0);
            $scroll = (int)($pv['scroll_max'] ?? 0);
            $ctx = is_array($pv['context'] ?? null) ? $pv['context'] : [];
            $data['summary']['pageviews']++; $data['summary']['hits']++;
            $data['pages'][$path] = ($data['pages'][$path] ?? 0) + 1;
            if ($visitor !== '') $data['visitors'][$visitor] = 1;
            foreach (['device' => 'devices', 'language' => 'languages', 'country' => 'countries'] as $key => $target) {
                $value = (string)($ctx[$key] ?? 'unknown'); $data[$target][$value] = ($data[$target][$value] ?? 0) + 1;
            }
            foreach (($ctx['utm'] ?? []) as $key => $value) if (is_string($value)) $data['utm'][$key][$value] = ($data['utm'][$key][$value] ?? 0) + 1;
            if ($scroll > 0) $data['events']['scroll'][$path][$scroll . '%'] = ($data['events']['scroll'][$path][$scroll . '%'] ?? 0) + 1;
            $data['engagement'][$path]['visible_seconds'] = ($data['engagement'][$path]['visible_seconds'] ?? 0) + $visible;
            $data['engagement'][$path]['pageviews'] = ($data['engagement'][$path]['pageviews'] ?? 0) + 1;
            foreach (($pv['conversions'] ?? []) as $key => $yes) if ($yes) {
                $data['events']['conversions'][$path][$key] = ($data['events']['conversions'][$path][$key] ?? 0) + 1;
                $data['conversions']['total']++; $data['conversions']['pages'][$path] = ($data['conversions']['pages'][$path] ?? 0) + 1;
                $data['conversions']['types'][$key] = ($data['conversions']['types'][$key] ?? 0) + 1;
                $data['conversions']['recent'][] = ['type' => $key, 'uri' => $path, 'timestamp' => $accepted];
            }
            if ($session !== '') {
                if (!isset($data['sessions'][$session])) {
                    $data['sessions'][$session] = ['visitor' => $visitor, 'started_at' => $accepted, 'last_seen_at' => $accepted, 'entry_page' => $path, 'exit_page' => $path, 'pageview_count' => 0, 'engaged_time' => 0];
                }
                $s = &$data['sessions'][$session];
                if ($accepted < $s['started_at']) { $s['started_at'] = $accepted; $s['entry_page'] = $path; }
                if ($accepted >= $s['last_seen_at']) { $s['last_seen_at'] = $accepted; $s['exit_page'] = $path; }
                $s['pageview_count']++; $s['engaged_time'] += $visible;
                unset($s);
            }
        }
        $data['summary']['uniques'] = count($data['visitors']);
        foreach ($data['sessions'] as &$session) {
            $session['engaged'] = $session['pageview_count'] >= 2 || $session['engaged_time'] >= 10;
            $session['bounced'] = !$session['engaged'];
            $data['summary']['sessions']++;
            $data['summary'][$session['engaged'] ? 'engaged_sessions' : 'bounced_sessions']++;
            $data['entry_pages'][$session['entry_page']] = ($data['entry_pages'][$session['entry_page']] ?? 0) + 1;
            $data['exit_pages'][$session['exit_page']] = ($data['exit_pages'][$session['exit_page']] ?? 0) + 1;
        }
        unset($session);
        $data['conversions']['recent'] = array_slice($data['conversions']['recent'], -50);
        return $data;
    }
}
