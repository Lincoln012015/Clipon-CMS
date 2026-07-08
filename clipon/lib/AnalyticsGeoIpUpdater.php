<?php

require_once __DIR__ . '/JsonStorage.php';

class AnalyticsGeoIpUpdater {
    private const SOURCE_URL = 'https://www.ipdeny.com/ipblocks/data/aggregated/';
    private const ARCHIVE_URL = 'https://www.ipdeny.com/ipblocks/data/aggregated/all-zones.tar.gz';
    private const UPDATE_INTERVAL = 2592000;
    private const RETRY_INTERVAL = 86400;
    private const AUTO_LOCK_TTL = 900;
    private const MANUAL_MAX_SECONDS = 6;
    private const AUTO_MAX_SECONDS = 4;

    private string $dataDir;

    public function __construct(?string $dataDir = null) {
        $this->dataDir = rtrim($dataDir ?? (defined('C_DATA_PATH') ? C_DATA_PATH : dirname(__DIR__, 3) . '/data'), '/\\') . '/geoip';
    }

    public function status(): array {
        $meta = $this->loadMeta();
        $dbFile = $this->databasePath();
        $exists = is_readable($dbFile) && filesize($dbFile) > 0;
        $lastUpdated = (int)($meta['last_updated'] ?? 0);
        $nextUpdate = (int)($meta['next_update'] ?? 0);
        $status = (string)($meta['status'] ?? '');

        if (!$exists) {
            $status = $status === 'error' ? 'error' : 'missing';
        } elseif ($nextUpdate > 0 && $nextUpdate <= time()) {
            $status = 'outdated';
        } elseif ($status === '' || $status === 'missing') {
            $status = 'installed';
        }

        return [
            'status' => $status,
            'last_updated' => $lastUpdated,
            'next_update' => $nextUpdate,
            'source' => (string)($meta['source'] ?? self::SOURCE_URL),
            'error' => (string)($meta['error'] ?? ''),
            'ranges_count' => (int)($meta['ranges_count'] ?? 0),
            'database_path' => $dbFile,
            'exists' => $exists,
        ];
    }

    public function shouldUpdate(): bool {
        $status = $this->status();
        if (empty($status['exists'])) {
            return $this->canRetry();
        }

        return (int)$status['next_update'] <= time() && $this->canRetry();
    }

    public function scheduleAutoUpdate(): void {
        if (!$this->shouldUpdate() || !$this->acquireAutoLock()) {
            return;
        }

        register_shutdown_function(function (): void {
            $this->update(false);
        });
    }

    public function update(bool $manual = false): array {
        if (!$manual && !$this->canRetry()) {
            return $this->status();
        }

        $this->ensureDataDir();

        $startedAt = microtime(true);
        $ranges = $this->downloadArchiveRanges($manual ? 6 : 2);
        $errors = [];

        if (empty($ranges)) {
            $ranges = $this->downloadCountryRanges($manual, $startedAt, $errors);
        }

        if (empty($ranges)) {
            $error = 'GeoIP download failed';
            if (!empty($errors)) {
                $error .= ': ' . implode(',', array_slice($errors, 0, 12));
            }
            $this->saveMeta([
                'status' => 'error',
                'error' => $error,
                'last_attempt' => time(),
                'next_update' => time() + self::RETRY_INTERVAL,
                'source' => self::SOURCE_URL,
            ]);
            return $this->status();
        }

        ksort($ranges);
        $content = implode("\n", array_keys($ranges)) . "\n";
        if (!$this->writeFileAtomic($this->databasePath(), $content)) {
            $this->saveMeta([
                'status' => 'error',
                'error' => 'Failed to write GeoIP database',
                'last_attempt' => time(),
                'next_update' => time() + self::RETRY_INTERVAL,
                'source' => self::SOURCE_URL,
            ]);
            return $this->status();
        }

        $now = time();
        $this->saveMeta([
            'status' => 'installed',
            'error' => '',
            'last_updated' => $now,
            'last_attempt' => $now,
            'next_update' => $now + self::UPDATE_INTERVAL,
            'source' => self::SOURCE_URL,
            'ranges_count' => count($ranges),
        ]);

        return $this->status();
    }

    public function databasePath(): string {
        return $this->dataDir . '/geoip.csv';
    }

    public function metaPath(): string {
        return $this->dataDir . '/meta.php';
    }

    public function parseZoneContent(string $content, string $country): array {
        $country = strtoupper($country);
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '/') === false) {
                continue;
            }
            [$ip, $prefix] = explode('/', $line, 2);
            $prefixInt = (int)$prefix;
            $maxPrefix = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
            if (!ctype_digit($prefix) || $prefixInt < 0 || $prefixInt > $maxPrefix || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $rows[] = $line . ',' . $country;
        }

        return $rows;
    }

    protected function download(string $url, int $timeout): ?string {
        $result = false;
        $curlExecuted = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                $curlExecuted = true;
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'CliponCMS GeoIP Updater',
                ]);
                $result = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                @curl_close($ch);
                if ($result === false || $code >= 400) {
                    return null;
                }
            }
        }

        if ($result === false && !$curlExecuted && ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => "User-Agent: CliponCMS GeoIP Updater\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $result = @file_get_contents($url, false, $context);
        }

        return is_string($result) && $result !== '' ? $result : null;
    }

    private function downloadArchiveRanges(int $timeout): array {
        if (!class_exists('PharData')) {
            return [];
        }

        $content = $this->download(self::ARCHIVE_URL, $timeout);
        if ($content === null) {
            return [];
        }

        $this->ensureDataDir();
        $token = bin2hex(random_bytes(4));
        $gzPath = $this->dataDir . '/all-zones.' . $token . '.tar.gz';
        $tarPath = $this->dataDir . '/all-zones.' . $token . '.tar';
        $extractDir = $this->dataDir . '/extract-' . $token;

        if (file_put_contents($gzPath, $content, LOCK_EX) === false) {
            return [];
        }

        $ranges = [];
        try {
            $gz = new PharData($gzPath);
            $gz->decompress();
            $tar = new PharData($tarPath);
            $tar->extractTo($extractDir, null, true);

            foreach (glob($extractDir . '/*-aggregated.zone') ?: [] as $file) {
                $name = basename($file);
                if (!preg_match('/^([a-z]{2})-aggregated\.zone$/', $name, $m)) {
                    continue;
                }
                $country = strtoupper($m[1]);
                $zone = file_get_contents($file);
                if (!is_string($zone)) {
                    continue;
                }
                foreach ($this->parseZoneContent($zone, $country) as $line) {
                    $ranges[$line] = true;
                }
            }
        } catch (Throwable $e) {
            $ranges = [];
        } finally {
            $this->deletePath($gzPath);
            $this->deletePath($tarPath);
            $this->deletePath($extractDir);
        }

        return $ranges;
    }

    private function downloadCountryRanges(bool $manual, float $startedAt, array &$errors): array {
        $ranges = [];
        $consecutiveFailures = 0;
        $failureLimit = $manual ? 6 : 3;
        $maxSeconds = $manual ? self::MANUAL_MAX_SECONDS : self::AUTO_MAX_SECONDS;

        foreach ($this->countryCodes() as $country) {
            if ((microtime(true) - $startedAt) >= $maxSeconds) {
                $errors[] = 'timeout';
                break;
            }

            $content = $this->download(self::SOURCE_URL . strtolower($country) . '-aggregated.zone', $manual ? 2 : 1);
            if ($content === null) {
                $errors[] = $country;
                $consecutiveFailures++;
                if (empty($ranges) && $consecutiveFailures >= $failureLimit) {
                    break;
                }
                continue;
            }

            $consecutiveFailures = 0;
            foreach ($this->parseZoneContent($content, $country) as $line) {
                $ranges[$line] = true;
            }
        }

        return $ranges;
    }

    private function loadMeta(): array {
        return read_json_file($this->metaPath());
    }

    private function saveMeta(array $meta): void {
        $current = $this->loadMeta();
        write_json_file($this->metaPath(), array_merge($current, $meta));
    }

    private function canRetry(): bool {
        $meta = $this->loadMeta();
        $lastAttempt = (int)($meta['last_attempt'] ?? 0);
        return $lastAttempt === 0 || $lastAttempt <= time() - self::RETRY_INTERVAL;
    }

    private function acquireAutoLock(): bool {
        $this->ensureDataDir();
        $lock = $this->dataDir . '/update.lock';
        if (is_file($lock) && (int)@filemtime($lock) > time() - self::AUTO_LOCK_TTL) {
            return false;
        }

        return @file_put_contents($lock, (string)time(), LOCK_EX) !== false;
    }

    private function ensureDataDir(): void {
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0755, true);
        }
    }

    private function writeFileAtomic(string $path, string $content): bool {
        $this->ensureDataDir();
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0644);
        return true;
    }

    private function deletePath(string $path): void {
        if (is_dir($path)) {
            foreach (glob(rtrim($path, '/\\') . '/*') ?: [] as $child) {
                $this->deletePath($child);
            }
            @rmdir($path);
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function countryCodes(): array {
        return [
            'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ','BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ',
            'CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR',
            'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY','HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP',
            'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT',
            'MU','MV','MW','MX','MY','MZ','NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA','RE','RO','RS','RU','RW',
            'SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ','TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG',
            'UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW',
        ];
    }
}
