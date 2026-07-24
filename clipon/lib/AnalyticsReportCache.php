<?php

require_once __DIR__ . '/AnalyticsPageviewStateStore.php';
require_once __DIR__ . '/AnalyticsV3Reducer.php';

final class AnalyticsReportCache {
    private string $dir;
    private AnalyticsPageviewStateStore $states;

    public function __construct(string $dataDir) {
        $this->dir = rtrim($dataDir, '/\\');
        $this->states = new AnalyticsPageviewStateStore($dataDir);
    }

    public function load(string $date): array {
        $manifest = $this->states->manifest($date);
        $file = $this->dir . '/cache/' . $date . '.php';
        if (is_file($file) && time() - (int)filemtime($file) <= 60) {
            $raw = file_get_contents($file);
            $cached = json_decode(str_replace("<?php die(); ?>\n", '', (string)$raw), true);
            if (is_array($cached) && ($cached['_manifest'] ?? null) === $manifest && is_array($cached['data'] ?? null)) return $cached['data'];
        }
        $data = AnalyticsV3Reducer::reduce($this->states->loadDay($date), $manifest, false);
        $cacheDir = dirname($file);
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents($file, "<?php die(); ?>\n" . json_encode(['_manifest' => $manifest, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $data;
    }
}
