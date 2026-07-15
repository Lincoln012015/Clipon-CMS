<?php

final class IntegrationLogger
{
    public static function write(string $provider, string $operation, int $status, string $externalId, int $durationMs): void
    {
        if (!defined('C_LOGS_PATH')) return;
        if (!is_dir(C_LOGS_PATH)) @mkdir(C_LOGS_PATH, 0755, true);
        $entry = [
            'time' => gmdate('c'), 'provider' => substr($provider, 0, 64),
            'operation' => substr($operation, 0, 16), 'status' => $status,
            'external_id' => substr(preg_replace('/[\x00-\x1F\x7F]/', '', $externalId) ?? '', 0, 120),
            'duration_ms' => max(0, $durationMs)
        ];
        @file_put_contents(C_LOGS_PATH . '/integrations.jsonl', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    public static function recent(string $provider, int $limit = 20): array
    {
        $file = defined('C_LOGS_PATH') ? C_LOGS_PATH . '/integrations.jsonl' : '';
        if ($file === '' || !is_file($file)) return [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && ($row['provider'] ?? '') === $provider) $out[] = $row;
            if (count($out) >= $limit) break;
        }
        return $out;
    }
}
