<?php
/**
 * Clipon CMS License Management
 * Handles local validation and Guardian checks with the API Proxy.
 * Uses RSA Asymmetric Cryptography for secure validation.
 */
class License {
    private static $licenseData = null;
    private static $isValidCached = null;
    private static $apiUrl = 'https://server.clipon-cms.com/api/verify';

    private static function getApiUrl(): string {
        $override = trim((string)getenv('CLIPON_LICENSE_API_URL'));
        if ($override !== '' && self::isAllowedOverrideUrl($override)) {
            return $override;
        }

        return self::$apiUrl;
    }

    private static function isAllowedOverrideUrl(string $url): bool {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        return $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private static function writeLicenseFile(array $licenseArray): void {
        $file = C_CONFIG_PATH . '/license.php';
        $tpl = "<?php\n";
        $tpl .= "// Clipon CMS License Configuration - Securely managed by Asymmetric License Manager\n";
        $tpl .= "if (!defined('C_CORE_DIR')) exit;\n\n";
        $tpl .= "return " . var_export($licenseArray, true) . ";\n";

        AtomicFileWriter::write($file, $tpl);
    }

    /**
     * Get embedded RSA Public Key.
     * Stored in code to reduce risk of key replacement via file tampering.
     */
    private static function getPublicKey() {
        return <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1BhzaTfeuagsUXxptu0O
VJZLODHYg2pxfLjHfRkd+frZMfVeNOIQO0pKgZo/uAv1lWxKYLdgb/45h/OWoX1U
la+jIhNbPOjM9Aku7rm0B3hNVP2aMLsOJUyCMdj7KLzENaj36czVHr3tVTwVuWFa
zeTIRUar4WYLiiE/WZUGsqM1tdh/3arEkhaMzxchRWOiM7GXE3gww7PohNoHAHHz
i6rez02o4TYxEVc73JB/EJjGNITf32WdEEoW3AoALZF2y41yopI+3j0Hu/NxMgWu
A+ugjC63KJP+w9w2WTzIjGZFEYmqEEMYmwE/iCelYhePtzrJOKLe5oEbO000atRv
2QIDAQAB
-----END PUBLIC KEY-----
PEM;
    }

    /**
     * Get the current domain safely
     */
    private static function getDomain() {
        // Повертаємо строгу перевірку через серверні змінні.
        // Звичайні відвідувачі завжди надсилають реальний Host у заголовках браузера.
        // Якщо пірат підмінить Host на своєму проксі, сайт буде відкриватися лише за "фейковим" запитом, 
        // а для реальних відвідувачів домену pirated.com ліцензія впаде.
        $request = new Request();
        $host = $request->server('HTTP_HOST') ?? $request->server('SERVER_NAME', 'localhost');
        // Відрізаємо порт, якщо він є (наприклад, localhost:8000)
        $host = explode(':', $host)[0];
        return $host;
    }

    /**
     * Load license configuration
     */
    private static function load() {
        if (self::$licenseData !== null) return;

        $file = C_CONFIG_PATH . '/license.php';
        if (file_exists($file)) {
            self::$licenseData = require $file;
        } else {
            self::$licenseData = self::getDefaultData();
        }
    }

    private static function getDefaultData() {
        return [
            'key' => '',
            'domain' => '',
            'payload_string' => '',
            'signature' => '',
            'modules' => [],
            'module_updates' => [],
            'next_check_target' => 0
        ];
    }

    /**
     * Base local validation (RSA Public Key Check)
     */
    public static function isValid() {
        if (self::$isValidCached !== null) {
            return self::$isValidCached;
        }

        self::load();

        if (empty(self::$licenseData['key']) || empty(self::$licenseData['signature']) || empty(self::$licenseData['payload_string'])) {
            self::$isValidCached = false;
            return false;
        }

        $currentDomain = self::getDomain();
        if (self::$licenseData['domain'] !== $currentDomain) {
            self::$isValidCached = false;
            return false;
        }

        // Verify RSA Signature strictly against the exact string signed by Node server
        $payloadString = self::$licenseData['payload_string'];
        $signatureRaw = base64_decode(self::$licenseData['signature']);
        
        $verified = openssl_verify($payloadString, $signatureRaw, self::getPublicKey(), OPENSSL_ALGO_SHA256);
        
        if ($verified !== 1) {
            self::$isValidCached = false;
            return false;
        }

        // Decode payload to verify key and domain deeply
        $payload = json_decode($payloadString, true);
        if (!$payload || !isset($payload['key']) || $payload['key'] !== self::$licenseData['key']) {
            self::$isValidCached = false;
            return false;
        }

        // Захист від Replay Attacks
        
        $issuedAt = $payload["issued_at"] ?? 0;
        if (time() - $issuedAt > 1209600) {
            self::$isValidCached = false;
            return false;
        }

        self::$isValidCached = true;
        return true;
    }

    /**
     * Check if a specific module is allowed
     */
    public static function hasModule($moduleId) {
        if (!self::isValid()) return false;
        return in_array($moduleId, self::$licenseData['modules'] ?? []);
    }

    public static function getModuleUpdates(): array {
        self::load();
        $map = self::$licenseData['module_updates'] ?? [];
        return is_array($map) ? $map : [];
    }

    /**
     * Manual user-triggered license/pro-module synchronization.
     */
    public static function syncLicenseManual(?string $manualKey = null): array {
        self::load();
        $key = trim((string)($manualKey ?? ''));
        if ($key === '') {
            $key = trim((string)(self::$licenseData['key'] ?? ''));
        }
        if (empty($key)) {
            return ['success' => false, 'error' => 'No key provided'];
        }

        return self::verifyWithServer($key, 'manual');
    }

    public static function hasConfiguredKey(): bool {
        self::load();
        return trim((string)(self::$licenseData['key'] ?? '')) !== '';
    }

    /**
     * Hidden method for automated health checks
     */
    public static function syncInternalState() {
        self::load();
        
        $key = self::$licenseData['key'] ?? '';
        if (empty($key)) return;

        $nextTarget = self::$licenseData['next_check_target'] ?? 0;
        
        // Time based TTL check: updates license data gracefully every 24h
        if (time() >= $nextTarget) {
            return self::verifyWithServer($key, 'guardian');
        }
    }

    /**
     * Get next check target time
     */
    public static function getNextCheckTarget() {
        self::load();
        return self::$licenseData['next_check_target'] ?? 0;
    }

    /**
     * Explicit Activation
     */
    public static function activate($key) {
        return self::verifyWithServer($key, 'activation');
    }

    /**
     * Internal server communication
     */
    private static function verifyWithServer($key, string $checkMode = 'manual') {
        if (empty($key)) return ['success' => false, 'error' => 'No key provided'];

        $domain = self::getDomain();
        $cmsVersion = CmsVersion::current();

        $data = [
            'license_key' => (string)$key,
            'domain'      => (string)$domain,
            'php_version' => PHP_VERSION,
            'cms_version' => (string)$cmsVersion,
            'check_mode' => $checkMode,
            'client_modules_versions' => class_exists('ModuleManager')
                ? ModuleManager::getInstalledModuleVersions()
                : []
        ];

        $payload = json_encode($data);
        $result = false;
        $curlExecuted = false;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true, 
            CURLOPT_SSL_VERIFYHOST => 2
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

        // Avoid a second timeout path when cURL request already executed and failed.
        if ($result === false && !$curlExecuted && ini_get('allow_url_fopen')) {
            $ctxOptions = [
                'http' => [
                    'header'  => "Content-type: application/json\r\n" .
                                 'Content-Length: ' . strlen($payload) . "\r\n",
                    'method'  => 'POST',
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

        if ($result === FALSE) {
            // Зміщуємо час наступної перевірки, щоб не класти систему (throttling timeout: 5 minutes)
            self::updateLocalNextCheckTarget(time() + 300);
            return ['success' => false, 'error' => 'License server unreachable'];
        }

        $response = json_decode($result, true);
        
        if ($response && isset($response['success']) && $response['success']) {
            if (!isset($response['payload_string']) || !isset($response['signature'])) {
                return ['success' => false, 'error' => 'Invalid server payload format'];
            }

            // Verify RSA
            $payloadString = $response['payload_string'];
            $signatureRaw = base64_decode($response['signature']);
            $verified = openssl_verify($payloadString, $signatureRaw, self::getPublicKey(), OPENSSL_ALGO_SHA256);
            
            if ($verified !== 1) {
                return ['success' => false, 'error' => 'Server signature mismatch'];
            }

            $localUpdate = self::updateLocalLicense($key, $domain, $response);
            return [
                'success' => true,
                'module_updates' => $localUpdate['module_updates'] ?? [],
            ];
        }

        if ($response && isset($response['success']) && !$response['success']) {
            if (in_array($response['error'], ['invalid_key', 'domain_mismatch'])) {
                self::resetLocalLicense();
            }
        }

        return ['success' => false, 'error' => $response['error'] ?? 'Invalid response from server'];
    }

    /**
     * Clear all license data from local file
     */
    private static function resetLocalLicense() {
        $file = C_CONFIG_PATH . '/license.php';
        if (file_exists($file)) {
            @unlink($file);
        }
        self::$licenseData = self::getDefaultData();
        self::restorePoweredByBadge();
    }

    private static function restorePoweredByBadge(): void {
        if (!class_exists('Settings') || !defined('C_CONFIG_PATH') || !file_exists(C_CONFIG_PATH . '/settings.php')) {
            return;
        }

        $settings = Settings::load();
        if (empty($settings['powered_by_hidden'])) {
            return;
        }

        $settings['powered_by_hidden'] = false;
        Settings::save($settings);
    }

    /**
     * Increment try count when server unreachable to avoid DDOS
     */
    private static function updateLocalNextCheckTarget($targetTime) {
        if (!self::$licenseData) return;
        self::$licenseData['next_check_target'] = $targetTime;

        self::writeLicenseFile(self::$licenseData);
    }

    /**
     * Update local license.php with server data
     */
    private static function updateLocalLicense($key, $domain, $data) {
        $parsedPayload = json_decode($data['payload_string'], true);
        if (!is_array($parsedPayload)) {
            $parsedPayload = [];
        }

        $existingModules = self::$licenseData['modules'] ?? [];
        if (!is_array($existingModules)) {
            $existingModules = [];
        }
        $incomingModules = $parsedPayload['modules'] ?? null;
        $finalModules = is_array($incomingModules) ? $incomingModules : $existingModules;

        $moduleUpdates = isset($data['module_updates']) && is_array($data['module_updates'])
            ? $data['module_updates']
            : (is_array(self::$licenseData['module_updates'] ?? null) ? self::$licenseData['module_updates'] : []);

        $licenseArray = [
            'key' => $key,
            'domain' => $domain,
            'payload_string' => $data['payload_string'],
            'signature' => $data['signature'],
            'modules' => $finalModules,
            'module_updates' => $moduleUpdates,
            'next_check_target' => time() + 86400, // Check again in 24 hours
            'last_verified' => date('Y-m-d H:i:s')
        ];

        self::$licenseData = $licenseArray;

        self::writeLicenseFile($licenseArray);

        return [
            'module_updates' => $moduleUpdates,
        ];
    }
}
