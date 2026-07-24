<?php

require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsReportCache.php';

/**
 * Base analytics reporting for Dashboard (core).
 * Extended stats (funnels, attribution, full reports) live in pro_analytics module.
 */
class AnalyticsReport {
    private AnalyticsStorage $storage;

    public function __construct(AnalyticsStorage $storage) {
        $this->storage = $storage;
    }

    public function getBasicStats(string $from, string $to): array {
        $files = $this->storage->listDataFiles();
        $result = [
            'total_hits' => 0,
            'total_uniques' => 0,
            'daily' => [],
            'top_pages' => []
        ];
        $visitors = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $data = $this->storage->loadData($file);

            if (strlen($name) === 10 && in_array(($data['version'] ?? null), [2, 3], true)) {
                if ($name >= $from && $name <= $to) {
                    $hits = (int)($data['summary']['pageviews'] ?? $data['summary']['hits'] ?? 0);
                    $result['total_hits'] += $hits;
                    foreach (($data['visitors'] ?? []) as $hash => $_) {
                        if (is_string($hash) && $hash !== '') $visitors[$hash] = true;
                    }
                    $result['daily'][$name] = [
                        'hits' => $hits,
                        'uniques' => $data['summary']['uniques'] ?? 0
                    ];
                    $this->mergeCounts($result['top_pages'], $data['pages'] ?? []);
                }
            }
        }

        $cursor = strtotime($from . ' 00:00:00 UTC');
        $end = strtotime($to . ' 00:00:00 UTC');
        $cache = new AnalyticsReportCache($this->storage->getDataDir());
        while ($cursor !== false && $end !== false && $cursor <= $end) {
            $date = gmdate('Y-m-d', $cursor);
            if (!isset($result['daily'][$date]) && is_dir($this->storage->getDataDir() . '/state/' . $date)) {
                $data = $cache->load($date);
                $hits = (int)($data['summary']['pageviews'] ?? 0);
                $result['total_hits'] += $hits;
                foreach (($data['visitors'] ?? []) as $hash => $_) if (is_string($hash) && $hash !== '') $visitors[$hash] = true;
                $result['daily'][$date] = ['hits' => $hits, 'uniques' => (int)($data['summary']['uniques'] ?? 0)];
                $this->mergeCounts($result['top_pages'], $data['pages'] ?? []);
            }
            $cursor += 86400;
        }

        $result['total_uniques'] = count($visitors);
        arsort($result['top_pages']);
        $result['top_pages'] = array_slice($result['top_pages'], 0, 5, true);

        return $result;
    }

    protected function mergeCounts(array &$target, array $source): void {
        foreach ($source as $key => $count) {
            $target[$key] = ($target[$key] ?? 0) + $count;
        }
    }
}
