<?php

require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsDayReader.php';

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
        $result = [
            'total_hits' => 0,
            'total_uniques' => 0,
            'daily' => [],
            'top_pages' => []
        ];
        $visitors = [];

        foreach ((new AnalyticsDayReader($this->storage))->readRange($from, $to) as $date => $data) {
            $hits = (int)($data['summary']['pageviews'] ?? $data['summary']['hits'] ?? 0);
            $result['total_hits'] += $hits;
            foreach (($data['visitors'] ?? []) as $hash => $_) {
                if (is_string($hash) && $hash !== '') $visitors[$hash] = true;
            }
            $result['daily'][$date] = ['hits' => $hits, 'uniques' => (int)($data['summary']['uniques'] ?? 0)];
            $this->mergeCounts($result['top_pages'], $data['pages'] ?? []);
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
