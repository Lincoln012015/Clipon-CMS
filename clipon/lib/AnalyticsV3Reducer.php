<?php

final class AnalyticsV3Reducer {
    public static function reduce(array $pageviews, array $manifest = [], bool $compacted = false): array {
        uasort($pageviews, static fn($a, $b) => self::orderOf((array)$a) <=> self::orderOf((array)$b));
        $visitorSessions = [];
        $resolvedSessions = [];
        foreach ($pageviews as $id => $pv) {
            if (!empty($pv['synthetic'])) {
                $resolvedSessions[$id] = '';
                continue;
            }
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
            'funnels' => ['completed' => [], 'recent' => []],
            'attribution' => ['first_touch' => [], 'last_touch' => [], 'recent' => []],
            'not_found' => ['total' => 0, 'paths' => []], 'risk_filter' => ['total' => 0, 'reasons' => []],
            'system_errors' => ['storage_failures' => 0],
        ];
        $conversionRecords = [];
        foreach ($pageviews as $id => $pv) {
            if (!is_array($pv) || !is_string($id)) continue;
            $path = (string)($pv['path'] ?? '/');
            $visitor = (string)($pv['visitor_hash'] ?? '');
            $session = (string)($resolvedSessions[$id] ?? '');
            $accepted = (int)($pv['accepted_at'] ?? 0);
            $order = self::orderOf($pv);
            $visible = (int)($pv['visible_seconds'] ?? 0);
            $scroll = (int)($pv['scroll_max'] ?? 0);
            $ctx = is_array($pv['context'] ?? null) ? $pv['context'] : [];
            $synthetic = !empty($pv['synthetic']);
            if (!$synthetic) {
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
                if (($ctx['referrer_type'] ?? '') === 'internal') {
                    $label = self::dimension((string)($ctx['referrer'] ?? 'unknown'));
                    $data['internal_referrers'][$label] = ($data['internal_referrers'][$label] ?? 0) + 1;
                }
            }
            foreach (($pv['conversions'] ?? []) as $key => $yes) if ($yes) {
                $data['events']['conversions'][$path][$key] = ($data['events']['conversions'][$path][$key] ?? 0) + 1;
                $data['conversions']['total']++; $data['conversions']['pages'][$path] = ($data['conversions']['pages'][$path] ?? 0) + 1;
                $data['conversions']['types'][$key] = ($data['conversions']['types'][$key] ?? 0) + 1;
                $data['conversions']['recent'][] = [
                    'type' => $key,
                    'uri' => $path,
                    'ts' => $accepted,
                    'utm' => is_array($ctx['utm'] ?? null) ? $ctx['utm'] : [],
                    'ref' => (string)($ctx['referrer'] ?? 'direct'),
                ];
                $conversionRecords[] = ['session' => $session, 'path' => $path, 'type' => $key, 'ts' => $accepted, 'order' => $order, 'context' => $ctx];
            }
            if (!$synthetic && $session !== '') {
                if (!isset($data['sessions'][$session])) {
                    $source = self::sourceForContext($ctx);
                    $touch = self::touchLabel($ctx);
                    $data['sessions'][$session] = [
                        'visitor' => $visitor, 'started_at' => $accepted, 'last_seen_at' => $accepted,
                        'entry_page' => $path, 'exit_page' => $path, 'pageview_count' => 0, 'engaged_time' => 0,
                        'source' => $source, 'path' => [], 'journey' => [], 'first_touch' => $touch, 'last_touch' => $touch,
                    ];
                }
                $s = &$data['sessions'][$session];
                if ($accepted < $s['started_at']) { $s['started_at'] = $accepted; $s['entry_page'] = $path; }
                if ($accepted >= $s['last_seen_at']) { $s['last_seen_at'] = $accepted; $s['exit_page'] = $path; }
                $s['pageview_count']++; $s['engaged_time'] += $visible;
                if (empty($s['path']) || end($s['path']) !== $path) {
                    $s['path'][] = $path;
                    if (count($s['path']) > 20) $s['path'] = array_slice($s['path'], -20);
                }
                $s['journey'][] = ['path' => $path, 'ts' => $accepted, 'order' => $order, 'context' => $ctx];
                if (self::isAttributableTouch($ctx)) $s['last_touch'] = self::touchLabel($ctx);
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
            $source = is_array($session['source'] ?? null) ? $session['source'] : ['type' => 'direct', 'label' => 'direct'];
            $sourceType = (string)($source['type'] ?? 'direct');
            $sourceLabel = self::dimension((string)($source['label'] ?? 'direct'));
            if ($sourceType === 'campaign') {
                $data['traffic_sources']['campaign'][$sourceLabel] = ($data['traffic_sources']['campaign'][$sourceLabel] ?? 0) + 1;
            } elseif ($sourceType === 'external') {
                $data['traffic_sources']['external'][$sourceLabel] = ($data['traffic_sources']['external'][$sourceLabel] ?? 0) + 1;
                $data['referrers'][$sourceLabel] = ($data['referrers'][$sourceLabel] ?? 0) + 1;
            } else {
                $data['traffic_sources']['direct']++;
            }
        }
        unset($session);
        foreach ($conversionRecords as $record) {
            $session = is_array($data['sessions'][$record['session']] ?? null) ? $data['sessions'][$record['session']] : null;
            $journey = $session !== null ? array_values(array_filter(
                is_array($session['journey'] ?? null) ? $session['journey'] : [],
                static fn($step) => is_array($step) && (int)($step['order'] ?? 0) <= (int)$record['order']
            )) : [];
            $path = [];
            foreach ($journey as $step) {
                $stepPath = (string)($step['path'] ?? '/');
                if (empty($path) || end($path) !== $stepPath) $path[] = $stepPath;
            }
            if ($path === []) $path = [$record['path']];
            if (empty($path) || end($path) !== $record['path']) $path[] = $record['path'];
            $path = array_slice(array_values($path), -20);
            $pathKey = implode(' > ', $path);
            $data['funnels']['completed'][$pathKey] = ($data['funnels']['completed'][$pathKey] ?? 0) + 1;
            $data['funnels']['recent'][] = ['path' => $path, 'type' => $record['type'], 'ts' => $record['ts']];

            $first = $journey !== [] ? self::touchLabel((array)($journey[0]['context'] ?? [])) : self::touchLabel($record['context']);
            $last = $first;
            foreach ($journey as $step) {
                $stepContext = is_array($step['context'] ?? null) ? $step['context'] : [];
                if (self::isAttributableTouch($stepContext)) $last = self::touchLabel($stepContext);
            }
            $data['attribution']['first_touch'][$first] = ($data['attribution']['first_touch'][$first] ?? 0) + 1;
            $data['attribution']['last_touch'][$last] = ($data['attribution']['last_touch'][$last] ?? 0) + 1;
            $data['attribution']['recent'][] = [
                'uri' => $record['path'], 'type' => $record['type'], 'first' => $first, 'last' => $last, 'ts' => $record['ts'],
            ];
        }
        foreach ($data['sessions'] as &$session) {
            unset($session['path'], $session['journey'], $session['first_touch'], $session['last_touch']);
        }
        unset($session);
        $data['conversions']['recent'] = array_slice($data['conversions']['recent'], -50);
        $data['funnels']['recent'] = array_slice($data['funnels']['recent'], -50);
        $data['attribution']['recent'] = array_slice($data['attribution']['recent'], -100);
        return $data;
    }

    private static function sourceForContext(array $context): array {
        $utm = is_array($context['utm'] ?? null) ? $context['utm'] : [];
        if (!empty($utm['utm_source'])) {
            $parts = [(string)$utm['utm_source']];
            if (!empty($utm['utm_medium'])) $parts[] = (string)$utm['utm_medium'];
            if (!empty($utm['utm_campaign'])) $parts[] = (string)$utm['utm_campaign'];
            return ['type' => 'campaign', 'label' => self::dimension(implode('/', $parts))];
        }
        if (($context['referrer_type'] ?? '') === 'external') {
            return ['type' => 'external', 'label' => self::dimension((string)($context['referrer'] ?? 'unknown'))];
        }
        return ['type' => 'direct', 'label' => 'direct'];
    }

    private static function touchLabel(array $context): string {
        $source = self::sourceForContext($context);
        if ($source['type'] === 'campaign') return 'utm:' . $source['label'];
        if ($source['type'] === 'external') return 'ref:' . $source['label'];
        return 'direct';
    }

    private static function isAttributableTouch(array $context): bool {
        return !empty($context['utm']['utm_source']) || ($context['referrer_type'] ?? '') === 'external';
    }

    private static function dimension(string $value): string {
        $value = trim($value);
        return $value === '' ? 'unknown' : substr($value, 0, 120);
    }

    private static function orderOf(array $pageview): int {
        if (isset($pageview['accepted_at_us'])) return (int)$pageview['accepted_at_us'];
        return (int)($pageview['accepted_at'] ?? 0) * 1000000;
    }
}
