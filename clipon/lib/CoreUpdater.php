<?php

/**
 * Core update checks for free CMS ядро.
 * Не залежить від ліцензійного ключа.
 */
class CoreUpdater
{
    private static $stateData = null;
    private static $apiUrl = 'https://server.clipon-cms.com/api/core/latest';

    private static function getApiUrl(): string
    {
        $override = trim((string)getenv('CLIPON_CORE_UPDATE_API_URL'));
        if ($override !== '' && self::isAllowedOverrideUrl($override)) {
            return $override;
        }

        return self::$apiUrl;
    }

    private static function isAllowedOverrideUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        return $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private static function getDefaultState(): array
    {
        return [
            'core_update_info' => null,
            'promo_modules' => [],
            'promo_modules_hash' => '',
            'last_checked' => null,
            'last_check_status' => null,
            'next_check_target' => 0,
        ];
    }

    private static function loadState(): void
    {
        if (self::$stateData !== null) {
            return;
        }

        $file = C_CONFIG_PATH . '/updates.php';
        if (!is_file($file)) {
            self::$stateData = self::getDefaultState();
            return;
        }

        $data = require $file;
        self::$stateData = is_array($data)
            ? array_merge(self::getDefaultState(), $data)
            : self::getDefaultState();
    }

    private static function saveState(): void
    {
        if (self::$stateData === null) {
            return;
        }

        $file = C_CONFIG_PATH . '/updates.php';
        $tpl = "<?php\n";
        $tpl .= "// Clipon CMS Core Updates state\n";
        $tpl .= "if (!defined('C_CORE_DIR')) exit;\n\n";
        $tpl .= 'return ' . var_export(self::$stateData, true) . ";\n";

        AtomicFileWriter::write($file, $tpl);
    }

    private static function getDomain(): string
    {
        $request = new Request();
        $host = $request->server('HTTP_HOST') ?? $request->server('SERVER_NAME', 'localhost');
        $host = explode(':', (string)$host)[0];
        return (string)$host;
    }

    public static function getUpdateInfo(): ?array
    {
        self::loadState();
        $info = self::$stateData['core_update_info'] ?? null;
        return is_array($info) ? $info : null;
    }

    public static function getPromoModules(): array
    {
        self::loadState();
        $list = self::$stateData['promo_modules'] ?? [];
        return is_array($list) ? $list : [];
    }

    public static function getPromoModulesHash(): string
    {
        self::loadState();
        return (string)(self::$stateData['promo_modules_hash'] ?? '');
    }

    public static function getNextCheckTarget(): int
    {
        self::loadState();
        return (int)(self::$stateData['next_check_target'] ?? 0);
    }

    public static function getLastCheckStatus(): ?array
    {
        self::loadState();

        // If we have explicit last_check_status, return it
        $status = self::$stateData['last_check_status'] ?? null;
        if (is_array($status)) {
            return $status;
        }

        // Fallback: build status from existing data if we have last_checked
        $lastChecked = self::$stateData['last_checked'] ?? null;
        $updateInfo = self::$stateData['core_update_info'] ?? null;

        if ($lastChecked && is_array($updateInfo)) {
            return [
                'core_available' => !empty($updateInfo['version']),
                'core_version' => $updateInfo['version'] ?? null,
                'core_url' => $updateInfo['url'] ?? null,
                'changelog' => $updateInfo['changelog'] ?? '',
                'checksum_sha256' => $updateInfo['checksum_sha256'] ?? '',
                'checked_at' => $lastChecked
            ];
        }

        return null;
    }

    public static function getLastChecked(): ?string
    {
        self::loadState();
        return self::$stateData['last_checked'] ?? null;
    }

    public static function checkForCoreUpdatesManual(): array
    {
        return self::requestUpdateInfo('manual');
    }

    public static function syncInternalState(): ?array
    {
        self::loadState();
        if (time() < self::getNextCheckTarget()) {
            return null;
        }

        return self::requestUpdateInfo('guardian');
    }

    private static function requestUpdateInfo(string $checkMode): array
    {
        self::loadState();

        $data = [
            'domain' => self::getDomain(),
            'php_version' => PHP_VERSION,
            'cms_version' => CmsVersion::current(),
            'check_mode' => $checkMode,
            'client_modules_hash' => self::getPromoModulesHash(),
        ];

        $payload = json_encode($data);
        if ($payload === false) {
            return ['success' => false, 'error' => 'Failed to encode request payload'];
        }

        $result = false;
        $curlExecuted = false;
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init(self::getApiUrl());
            if ($ch !== false) {
                $curlExecuted = true;
                curl_setopt_array($ch, $options);
                $result = curl_exec($ch);
                @curl_close($ch);
            }
        }

        // Avoid a second network timeout when cURL request already executed and failed.
        if ($result === false && !$curlExecuted && ini_get('allow_url_fopen')) {
            $ctxOptions = [
                'http' => [
                    'header' => "Content-type: application/json\r\n" .
                        'Content-Length: ' . strlen($payload) . "\r\n",
                    'method' => 'POST',
                    'content' => $payload,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];
            $context = stream_context_create($ctxOptions);
            $result = @file_get_contents(self::getApiUrl(), false, $context);
        }

        if ($result === false) {
            self::$stateData['next_check_target'] = time() + 300;
            self::saveState();
            return ['success' => false, 'error' => 'Core update server unreachable'];
        }

        $response = json_decode($result, true);
        if (!is_array($response) || !isset($response['success']) || !$response['success']) {
            self::$stateData['next_check_target'] = time() + 300;
            self::saveState();
            return ['success' => false, 'error' => is_array($response) ? (string)($response['error'] ?? 'Invalid response') : 'Invalid response'];
        }

        $updateInfo = null;
        if (isset($response['update_info']) && is_array($response['update_info'])) {
            $updateInfo = $response['update_info'];
        }

        $changelog = '';
        if (isset($response['changelog']) && is_string($response['changelog'])) {
            $changelog = trim($response['changelog']);
        } elseif (is_array($updateInfo) && isset($updateInfo['changelog']) && is_string($updateInfo['changelog'])) {
            $changelog = trim($updateInfo['changelog']);
        }

        if (is_array($updateInfo) && $changelog !== '') {
            $updateInfo['changelog'] = $changelog;
        }

        if (is_array($updateInfo)) {
            $checksum = isset($updateInfo['checksum_sha256']) ? strtolower((string)$updateInfo['checksum_sha256']) : '';
            $updateInfo['checksum_sha256'] = preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : '';
        }

        $existingPromoModules = self::getPromoModules();
        $incomingPromoModules = $response['promo_modules'] ?? null;
        $promoModulesUpdated = is_array($incomingPromoModules);
        $finalPromoModules = $promoModulesUpdated ? $incomingPromoModules : $existingPromoModules;
        $finalPromoHash = isset($response['promo_modules_hash'])
            ? (string)$response['promo_modules_hash']
            : (string)(self::$stateData['promo_modules_hash'] ?? '');

        self::$stateData['core_update_info'] = $updateInfo;
        self::$stateData['promo_modules'] = $finalPromoModules;
        self::$stateData['promo_modules_hash'] = $finalPromoHash;
        self::$stateData['last_checked'] = date('Y-m-d H:i:s');
        self::$stateData['next_check_target'] = time() + 86400;

        // Store last check status for UI persistence
        $checkStatus = [
            'core_available' => is_array($updateInfo) && !empty($updateInfo['version']),
            'core_version' => is_array($updateInfo) ? ($updateInfo['version'] ?? null) : null,
            'core_url' => is_array($updateInfo) ? ($updateInfo['url'] ?? null) : null,
            'changelog' => $changelog,
            'checksum_sha256' => is_array($updateInfo) ? ($updateInfo['checksum_sha256'] ?? '') : '',
            'checked_at' => date('Y-m-d H:i:s'),
        ];
        self::$stateData['last_check_status'] = $checkStatus;

        self::saveState();

        return [
            'success' => true,
            'update_info' => $updateInfo,
            'latest_version' => (string)($response['latest_version'] ?? ''),
            'changelog' => $changelog,
        ];
    }
}
