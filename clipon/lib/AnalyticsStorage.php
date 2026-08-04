<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/JsonStorage.php';

class AnalyticsStorage {
    private string $dataDir;

    public function __construct(string $dataDir) {
        $this->dataDir = $dataDir;
    }

    public function withUpdateLock(callable $callback): void {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $lockPath = $this->dataDir . '/analytics.lock';
        $lockHandle = fopen($lockPath, 'c');
        if (!$lockHandle) {
            $callback();
            return;
        }

        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            $callback();
            return;
        }

        try {
            $callback();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function loadData(string $file): array {
        if (!file_exists($file)) return [];
        $content = file_get_contents($file);
        $json = str_replace("<?php die(); ?>\n", '', $content);
        return json_decode($json, true) ?: [];
    }

    public function saveData(string $file, array $data): void {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $content = "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE);

        $fp = fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $content);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function cleanupOldData(): void {
        $settings = Settings::load();
        $retentionMonths = (int)($settings['analytics_retention'] ?? 36);
        if ($retentionMonths <= 0) return;

        $limitDate = gmdate('Y-m', strtotime("-$retentionMonths months UTC"));

        foreach (glob($this->dataDir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (!preg_match('/^\d{4}-\d{2}(?:-\d{2})?$/', $name)) continue;
            $compareName = substr($name, 0, 7);

            if ($compareName < $limitDate) {
                @unlink($file);
            }
        }

        foreach (glob($this->dataDir . '/state/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $date = basename($dir);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && substr($date, 0, 7) < $limitDate) {
                self::removeTree($dir);
            }
        }

        foreach (glob($this->dataDir . '/cache/*.php') ?: [] as $file) {
            $date = basename($file, '.php');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && substr($date, 0, 7) < $limitDate) {
                @unlink($file);
            }
        }
    }

    public function clearAllData(): bool {
        $success = true;
        foreach (glob($this->dataDir . '/*.php') ?: [] as $file) {
            if (!@unlink($file)) $success = false;
        }

        foreach (['state', 'cache', 'rate'] as $directory) {
            $path = rtrim($this->dataDir, '/\\') . '/' . $directory;
            if (file_exists($path) && !self::removeTree($path)) $success = false;
        }

        foreach (['analytics_sys.log', 'analytics.lock', 'event_rate.lock'] as $name) {
            $path = rtrim($this->dataDir, '/\\') . '/' . $name;
            if (file_exists($path) && !@unlink($path)) $success = false;
        }

        return $success;
    }

    private static function removeTree(string $path): bool {
        if (is_link($path) || is_file($path)) return @unlink($path);
        if (!is_dir($path)) return true;

        $success = true;
        $items = scandir($path);
        if ($items === false) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (!self::removeTree($path . DIRECTORY_SEPARATOR . $item)) $success = false;
        }

        return @rmdir($path) && $success;
    }

    public function listDataFiles(): array {
        if (!is_dir($this->dataDir)) return [];
        $files = glob($this->dataDir . '/*.php');
        if (!is_array($files)) return [];
        return $files;
    }

    public function getDataDir(): string {
        return $this->dataDir;
    }

    public function dayFilePath(string $date): string {
        return rtrim($this->dataDir, '/\\') . '/' . $date . '.php';
    }

    public function emptyDayData(): array {
        return [
            'version' => 2,
            'summary' => [
                'hits' => 0,
                'uniques' => 0,
                'sessions' => 0,
                'bounced_sessions' => 0,
                'engaged_sessions' => 0,
            ],
            'visitors' => [],
            'daily_salt' => '',
            'sessions' => [],
            'pages' => [],
            'entry_pages' => [],
            'exit_pages' => [],
            'traffic_sources' => [
                'direct' => 0,
                'external' => [],
                'campaign' => [],
            ],
            'referrers' => [],
            'internal_referrers' => [],
            'devices' => [],
            'languages' => [],
            'countries' => [],
            'utm' => [],
            'events' => [],
            'time_on_page' => [],
            'conversions' => [
                'total' => 0,
                'pages' => [],
                'types' => [],
                'recent' => [],
            ],
            'funnels' => [
                'completed' => [],
                'recent' => [],
            ],
            'attribution' => [
                'first_touch' => [],
                'last_touch' => [],
                'recent' => [],
            ],
            'bot_filter' => [
                'total' => 0,
                'reasons' => [],
            ],
        ];
    }

    public function loadDay(string $date): array {
        $data = $this->loadData($this->dayFilePath($date));
        if (!is_array($data) || ($data['version'] ?? null) !== 2) {
            $data = [];
        }

        return array_replace_recursive($this->emptyDayData(), $data);
    }

    public function saveDay(string $date, array $data): void {
        $data['version'] = 2;
        $this->saveData($this->dayFilePath($date), $data);
    }

}
