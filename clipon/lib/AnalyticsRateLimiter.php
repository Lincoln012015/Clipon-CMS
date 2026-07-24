<?php

final class AnalyticsRateLimiter {
    private string $dir;

    public function __construct(string $dataDir) {
        $this->dir = rtrim($dataDir, '/\\') . '/rate';
    }

    public function allow(string $key, int $limit, int $window): bool {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0755, true)) throw new RuntimeException('storage_error');
        $lock = @fopen($this->dir . '/rate.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('storage_error');
        }
        try {
            $file = $this->dir . '/buckets.php';
            $state = [];
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                $state = is_string($raw) ? json_decode(str_replace("<?php die(); ?>\n", '', $raw), true) : null;
                if (!is_array($state)) throw new RuntimeException('storage_error');
            }
            $now = time();
            foreach ($state as $hash => $row) {
                if (!is_array($row) || (int)($row['until'] ?? 0) <= $now) unset($state[$hash]);
            }
            $bucket = (int)floor($now / $window);
            $hash = hash('sha256', $key . '|' . $window . '|' . $bucket);
            $row = is_array($state[$hash] ?? null) ? $state[$hash] : ['count' => 0, 'until' => ($bucket + 1) * $window];
            $row['count']++;
            $state[$hash] = $row;
            $content = "<?php die(); ?>\n" . json_encode($state, JSON_UNESCAPED_SLASHES);
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) {
                @unlink($tmp);
                throw new RuntimeException('storage_error');
            }
            return $row['count'] <= $limit;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
