<?php

require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsReportCache.php';

/**
 * Resolves mixed analytics storage into one authoritative payload per day.
 * Priority: open V3 state > daily snapshot > legacy monthly archive.
 */
final class AnalyticsDayReader {
    private AnalyticsStorage $storage;
    private AnalyticsReportCache $cache;

    public function __construct(AnalyticsStorage $storage) {
        $this->storage = $storage;
        $this->cache = new AnalyticsReportCache($storage->getDataDir());
    }

    /** @return array<string,array<string,mixed>> */
    public function readRange(string $from, string $to): array {
        if (!self::validDate($from) || !self::validDate($to)) return [];
        if ($from > $to) [$from, $to] = [$to, $from];

        $days = [];
        $dailySnapshots = [];
        foreach ($this->storage->listDataFiles() as $file) {
            $name = basename($file, '.php');
            $data = $this->storage->loadData($file);
            if (preg_match('/^\d{4}-\d{2}$/', $name)) {
                foreach ($data as $date => $day) {
                    if (is_string($date) && self::validDate($date) && $date >= $from && $date <= $to && is_array($day)) {
                        $days[$date] ??= $day;
                    }
                }
            } elseif (self::validDate($name) && $name >= $from && $name <= $to && is_array($data)) {
                // A daily snapshot is more precise than a legacy monthly row.
                $days[$name] = $data;
                $dailySnapshots[$name] = true;
            }
        }

        $stateRoot = rtrim($this->storage->getDataDir(), '/\\') . '/state';
        foreach (glob($stateRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $date = basename($dir);
            if (!self::validDate($date) || $date < $from || $date > $to) continue;
            if (is_file($dir . '/.closed')) {
                // Recovery path for an old/partial compaction that closed state
                // without leaving a verified daily snapshot.
                if (empty($dailySnapshots[$date])) $days[$date] = $this->cache->load($date);
                continue;
            }
            // Loading also performs lazy compaction once the grace period ends.
            $days[$date] = $this->cache->load($date);
        }

        ksort($days);
        return $days;
    }

    private static function validDate(string $date): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
