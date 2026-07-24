<?php

require_once __DIR__ . '/AnalyticsPageviewStateStore.php';
require_once __DIR__ . '/AnalyticsV3Reducer.php';

final class AnalyticsDayCompactor {
    private string $dir;
    private AnalyticsPageviewStateStore $states;

    public function __construct(string $dataDir) {
        $this->dir = rtrim($dataDir, '/\\');
        $this->states = new AnalyticsPageviewStateStore($dataDir);
    }

    public function compact(string $date, bool $force = false): array {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) throw new InvalidArgumentException('invalid_date');
        if (!$force && strtotime($date . ' 00:00:00 UTC') > time() - 93600) throw new RuntimeException('grace_period');
        $stateDir = $this->dir . '/state/' . $date;
        if (!is_dir($stateDir)) return [];
        $lock = @fopen($stateDir . '/compaction.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('storage_error');
        try {
            $this->states->close($date);
            $manifest = $this->states->manifest($date);
            $data = AnalyticsV3Reducer::reduce($this->states->loadDay($date), $manifest, true);
            $file = $this->dir . '/' . $date . '.php';
            $tmp = $file . '.' . bin2hex(random_bytes(5)) . '.tmp';
            $content = "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) throw new RuntimeException('storage_error');
            $verify = json_decode(str_replace("<?php die(); ?>\n", '', (string)file_get_contents($file)), true);
            if (!is_array($verify) || ($verify['source_manifest'] ?? null) !== $manifest) throw new RuntimeException('checksum_mismatch');
            return $data;
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
