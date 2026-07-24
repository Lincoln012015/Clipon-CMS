<?php

final class AnalyticsPageviewStateStore {
    public const SHARDS = 64;
    private string $dataDir;

    public function __construct(string $dataDir) {
        $this->dataDir = rtrim($dataDir, '/\\');
    }

    public function shardId(string $pageviewId): string {
        return str_pad(dechex(hexdec(substr(hash('sha256', $pageviewId), 0, 2)) >> 2), 2, '0', STR_PAD_LEFT);
    }

    public function update(string $date, string $pageviewId, callable $transition): array {
        $dir = $this->dataDir . '/state/' . $date;
        if (!$this->ensureDir($dir)) throw new RuntimeException('storage_error');
        if (is_file($dir . '/.closed')) throw new RuntimeException('day_closed');
        $id = $this->shardId($pageviewId);
        $lock = @fopen($dir . '/' . $id . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('storage_error');
        }
        try {
            $file = $dir . '/' . $id . '.php';
            $shard = ['version' => 1, 'shards' => self::SHARDS, 'pageviews' => []];
            if (is_file($file)) {
                $decoded = $this->read($file);
                if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1
                    || ($decoded['shards'] ?? null) !== self::SHARDS || !is_array($decoded['pageviews'] ?? null)) {
                    throw new RuntimeException('storage_error');
                }
                $shard = $decoded;
            }
            $current = is_array($shard['pageviews'][$pageviewId] ?? null) ? $shard['pageviews'][$pageviewId] : null;
            $result = $transition($current);
            if (!is_array($result) || !array_key_exists('state', $result)) throw new RuntimeException('storage_error');
            if (is_array($result['state'])) {
                $shard['pageviews'][$pageviewId] = $result['state'];
                $this->atomicWrite($file, $shard);
            }
            unset($result['state']);
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function loadDay(string $date): array {
        $result = [];
        $dir = $this->dataDir . '/state/' . $date;
        if (!is_dir($dir)) return [];
        foreach (glob($dir . '/[0-3][0-9a-f].php') ?: [] as $file) {
            try {
                $data = $this->read($file);
                if (is_array($data) && ($data['version'] ?? null) === 1 && ($data['shards'] ?? null) === self::SHARDS) {
                    foreach (($data['pageviews'] ?? []) as $id => $state) if (is_array($state)) $result[$id] = $state;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $result;
    }

    public function manifest(string $date): array {
        $dir = $this->dataDir . '/state/' . $date;
        $manifest = [];
        foreach (glob($dir . '/[0-3][0-9a-f].php') ?: [] as $file) {
            $manifest[basename($file)] = hash_file('sha256', $file);
        }
        ksort($manifest);
        return $manifest;
    }

    public function close(string $date): void {
        $dir = $this->dataDir . '/state/' . $date;
        if (!$this->ensureDir($dir) || @file_put_contents($dir . '/.closed', (string)time(), LOCK_EX) === false) {
            throw new RuntimeException('storage_error');
        }
    }

    private function ensureDir(string $dir): bool {
        return is_dir($dir) || @mkdir($dir, 0755, true);
    }

    private function read(string $file): array {
        $raw = @file_get_contents($file);
        if (!is_string($raw)) throw new RuntimeException('storage_error');
        $data = json_decode(str_replace("<?php die(); ?>\n", '', $raw), true);
        if (!is_array($data)) throw new RuntimeException('storage_error');
        return $data;
    }

    private function atomicWrite(string $file, array $data): void {
        $tmp = dirname($file) . '/.' . basename($file) . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $content = "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('storage_error');
        }
    }
}
