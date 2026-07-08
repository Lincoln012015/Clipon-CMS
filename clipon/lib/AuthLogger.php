
<?php
/**
 * Простий логгер для подій автентифікації
 * Підтримує обмеження розміру файлу — при перевищенні обрізає старі записи.
 */

define('AUTH_LOG_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

function auth_log(string $level, string $message, array $meta = []): bool {
    $dir = C_LOGS_PATH;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/auth.log';
    $now = date('Y-m-d H:i:s');
    $entry = [$now, strtoupper($level), $message, $meta];
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";

    // Open read/write so we can trim if necessary while holding the lock
    $fp = fopen($file, 'c+');
    if (!$fp) return false;

    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }

    // Move to end and write new line
    fseek($fp, 0, SEEK_END);
    $written = fwrite($fp, $line);
    fflush($fp);

    // Check size and trim oldest records if exceeded
    clearstatcache(true, $file);
    $size = filesize($file);
    $max = AUTH_LOG_MAX_BYTES;
    if ($size !== false && $size > $max) {
        // Preserve the last $max bytes
        $start = $size - $max;
        if ($start < 0) $start = 0;
        if (fseek($fp, $start, SEEK_SET) === 0) {
            $tail = stream_get_contents($fp);
            if ($tail !== false) {
                // overwrite file with tail
                rewind($fp);
                if (ftruncate($fp, 0) !== false) {
                    fwrite($fp, $tail);
                    fflush($fp);
                }
            }
        }
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    return $written !== false;
}

?>
