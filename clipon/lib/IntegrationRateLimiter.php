<?php

final class IntegrationRateLimiter
{
    public function __construct(private string $directory, private int $limit = 60, private int $window = 60) {}

    public function consume(string $provider, string $ip): array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            return ['allowed' => true, 'retry_after' => 0];
        }
        $file = $this->directory . '/' . hash('sha256', $provider . '|' . $ip) . '.php';
        $now = time();
        $handle = fopen($file, 'c+');
        if (!$handle) return ['allowed' => true, 'retry_after' => 0];
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $raw = preg_replace('/^<\?php die\(\); \?>\s*/', '', (string)$raw);
            $state = json_decode((string)$raw, true);
            $start = is_array($state) ? (int)($state['start'] ?? $now) : $now;
            $count = is_array($state) ? (int)($state['count'] ?? 0) : 0;
            if (($now - $start) >= $this->window) { $start = $now; $count = 0; }
            $allowed = $count < $this->limit;
            if ($allowed) $count++;
            ftruncate($handle, 0); rewind($handle);
            fwrite($handle, "<?php die(); ?>\n" . json_encode(['start' => $start, 'count' => $count]));
            fflush($handle);
            return ['allowed' => $allowed, 'retry_after' => $allowed ? 0 : max(1, $this->window - ($now - $start))];
        } finally {
            flock($handle, LOCK_UN); fclose($handle);
        }
    }
}
