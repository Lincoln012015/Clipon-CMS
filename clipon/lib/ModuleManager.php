<?php
/**
 * Module Manager for Clipon CMS
 * Handles discovery, registration, and initialization of modules.
 */
class ModuleManager {
    private static $modules = [];
    private static $initialized = false;
    /** @var array<int,mixed> Registered providers discovered from modules */
    private static $providers = [];
    /** @var array<int,mixed> Providers that were successfully registered */
    private static $registeredProviders = [];

    /**
     * Start the module system
     */
    public static function init() {
        if (self::$initialized) return;
        
        

        $modulesDir = defined('C_MODULES_PATH') ? C_MODULES_PATH : (C_ROOT . '/modules');
        if (!is_dir($modulesDir)) {
            mkdir($modulesDir, 0755, true);
        }

        self::discoverModules($modulesDir);

        if (class_exists('CoreUpdater') && empty(CoreUpdater::getPromoModules())) {
            CoreUpdater::syncInternalState();
        }

        self::discoverProProxies();
        self::loadModules();

        // Register Dynamic Permissions for all Promo/Pro modules
        Hooks::addFilter('admin_permissions', function($perms) {
            foreach (self::$modules as $id => $module) {
                if (!empty($module['pro'])) {
                    $perms[] = 'view_' . $id;
                    // Add create and delete permissions for pro_users module
                    if ($id === 'pro_users') {
                        $perms[] = 'create_' . $id;
                        $perms[] = 'delete_' . $id;
                        $perms[] = 'edit_' . $id;
                    }
                }
            }
            return array_unique($perms);
        });
        
        self::$initialized = true;
        Hooks::doAction('modules_loaded', self::$modules);
    }

    /**
     * Scan directory for modules
     */
    private static function discoverModules($dir) {
        $currentCmsVersion = CmsVersion::current();
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (!self::isValidModuleId($item)) continue;
            
            $path = $dir . '/' . $item;
            $entryFile = $path . '/module.php';
            $manifestFile = $path . '/manifest.php';
            
            if (is_dir($path) && file_exists($entryFile)) {
                $rawManifest = [];
                // Secure PHP manifest check: manifest should return an array
                if (file_exists($manifestFile)) {
                    $rawManifest = require $manifestFile;
                }

                $manifest = self::sanitizeManifest($rawManifest, $item);

                $minCmsVersion = self::normalizeModuleVersion($manifest['min_cms_version'] ?? '0.0.0');
                self::$modules[$item] = [
                    'id' => $item,
                    'path' => $path,
                    'entry' => $entryFile,
                    'is_active' => true,
                    'is_virtual' => false,
                    // If manifest says it's PRO, or it has pro_ prefix
                    'pro' => (!empty($manifest['pro'])) || (strpos($item, 'pro_') === 0),
                    'label' => $manifest['name'] ?? $item,
                    'url' => self::sanitizeModuleUrl($manifest['url'] ?? null),
                    'icon' => $manifest['icon'] ?? null,
                    'priority' => $manifest['priority'] ?? null,
                    'hide_in_nav' => !empty($manifest['hide_in_nav']),
                    'description' => $manifest['description'] ?? '',
                    'module_version' => self::normalizeModuleVersion($manifest['version'] ?? '0.0.0'),
                    'latest_version' => self::normalizeModuleVersion($manifest['version'] ?? '0.0.0'),
                    'min_cms_version' => $minCmsVersion,
                    'cms_compatible' => self::isVersionGreaterOrEqual($currentCmsVersion, $minCmsVersion),
                    'has_update' => false,
                ];
            }
        }
    }

    /**
     * Register core PRO proxy files natively (No File I/O)
     */
    private static function discoverProProxies() {
        $publicPromoProxies = [];
        if (class_exists('CoreUpdater')) {
            $publicPromoProxies = CoreUpdater::getPromoModules();
        }

        $promoCatalog = [];
        if (is_array($publicPromoProxies)) {
            foreach ($publicPromoProxies as $serverMeta) {
                $normalized = self::normalizePromoMeta($serverMeta);
                if (!$normalized) {
                    continue;
                }

                $id = $normalized['id'];
                $base = $promoCatalog[$id] ?? null;
                if ($base) {
                    $promoCatalog[$id] = self::mergePromoMeta($base, $normalized);
                } else {
                    $promoCatalog[$id] = $normalized;
                }
            }
        }

        $isLicenseAvailable = class_exists('License') && License::isValid();

        $currentCmsVersion = CmsVersion::current();

        foreach ($promoCatalog as $id => $meta) {
            $modulePath = C_ROOT . '/modules/' . $id;
            $entryFile = $modulePath . '/module.php';
            $latestVersion = self::normalizeModuleVersion($meta['version'] ?? '0.0.0');
            $minCmsVersion = self::normalizeModuleVersion($meta['min_cms_version'] ?? '0.0.0');
            $cmsCompatible = self::isVersionGreaterOrEqual($currentCmsVersion, $minCmsVersion);
            $releaseStatus = (string)($meta['release_status'] ?? 'available');
            $requiresLicense = !isset($meta['requires_license']) || (bool)$meta['requires_license'];
            $hasEntitlement = $isLicenseAvailable && (!$requiresLicense || License::hasModule($id));
            $isReleased = ($releaseStatus === 'available');
            $isMissingPackage = $isReleased && $cmsCompatible && $hasEntitlement && !is_file($entryFile);
            $moduleUrl = self::buildPromoUrl($id, $meta);
            $hasExplicitHideInNav = array_key_exists('hide_in_nav', $meta) && $meta['hide_in_nav'] !== null;
            $hideInNavWhenLicensed = !empty($meta['hide_in_nav_when_licensed']);
            $hideInNav = $hasExplicitHideInNav ? (bool)$meta['hide_in_nav'] : false;

            if ($hideInNavWhenLicensed && $hasEntitlement) {
                $hideInNav = true;
            }

            if (!isset(self::$modules[$id])) {
                self::$modules[$id] = [
                    'id'               => $id,
                    'label'            => $meta['label'],
                    'url'              => $moduleUrl,
                    'icon'             => $meta['icon'],
                    'priority'         => $meta['priority'],
                    'is_active'        => false,
                    'is_virtual'       => true,
                    'pro'              => true,
                    'hide_in_nav'      => $hideInNav,
                    'hide_in_nav_when_licensed' => $hideInNavWhenLicensed,
                    'requires_license' => $requiresLicense,
                    'missing_files'    => $isMissingPackage,
                    'release_status'   => $releaseStatus,
                    'module_version'   => '0.0.0',
                    'latest_version'   => $latestVersion,
                    'min_cms_version'  => $minCmsVersion,
                    'cms_compatible'   => $cmsCompatible,
                    'has_update'       => false,
                    'promo_url'        => $meta['promo_url'],
                    'description'      => $meta['description'],
                    'video_id'         => $meta['video_id']
                ];
            } else {
                // Фізичний модуль є, гарантуємо використання безпечного проксі-роута
                self::$modules[$id]['url']      = $moduleUrl;
                self::$modules[$id]['label']    = self::$modules[$id]['label'] ?? $meta['label'];
                self::$modules[$id]['icon']     = self::$modules[$id]['icon'] ?? $meta['icon'];
                self::$modules[$id]['priority'] = self::$modules[$id]['priority'] ?? $meta['priority'];
                if ($hasExplicitHideInNav || ($hideInNavWhenLicensed && $hasEntitlement)) {
                    self::$modules[$id]['hide_in_nav'] = $hideInNav;
                }
                self::$modules[$id]['hide_in_nav_when_licensed'] = $hideInNavWhenLicensed;
                self::$modules[$id]['missing_files'] = false;
                self::$modules[$id]['requires_license'] = isset(self::$modules[$id]['requires_license'])
                    ? (bool)self::$modules[$id]['requires_license']
                    : $requiresLicense;
                self::$modules[$id]['release_status'] = self::$modules[$id]['release_status'] ?? $releaseStatus;
                $installedVersion = self::normalizeModuleVersion(self::$modules[$id]['module_version'] ?? '0.0.0');
                self::$modules[$id]['module_version'] = $installedVersion;
                self::$modules[$id]['latest_version'] = $latestVersion;
                self::$modules[$id]['min_cms_version'] = $minCmsVersion;
                self::$modules[$id]['cms_compatible'] = $cmsCompatible;
                self::$modules[$id]['has_update'] = self::isVersionGreater($latestVersion, $installedVersion);
                self::$modules[$id]['promo_url'] = self::$modules[$id]['promo_url'] ?? $meta['promo_url'];
                self::$modules[$id]['description'] = self::$modules[$id]['description'] ?? $meta['description'];
                self::$modules[$id]['video_id'] = self::$modules[$id]['video_id'] ?? $meta['video_id'];
            }
        }
    }

    private static function normalizePromoMeta($meta): ?array {
        if (!is_array($meta)) {
            return null;
        }

        $id = (string)($meta['id'] ?? '');
        if (!self::isValidModuleId($id)) {
            return null;
        }

        return [
            'id' => $id,
            'label' => self::sanitizePlainText($meta['label'] ?? $id, $id),
            'version' => self::normalizeModuleVersion($meta['version'] ?? '0.0.0'),
            'min_cms_version' => self::normalizeModuleVersion($meta['min_cms_version'] ?? '0.0.0'),
            'icon' => self::sanitizeIconName($meta['icon'] ?? 'analytics'),
            'file' => isset($meta['file']) ? (string)$meta['file'] : '',
            'priority' => isset($meta['priority']) ? (int)$meta['priority'] : 100,
            'promo_url' => self::sanitizePromoUrl($meta['promo_url'] ?? 'https://clipon-cms.com/pro'),
            'description' => self::sanitizePlainText($meta['description'] ?? '', ''),
            'video_id' => self::sanitizeVideoId($meta['video_id'] ?? ''),
            'release_status' => in_array((string)($meta['release_status'] ?? ''), ['available', 'coming_soon', 'in_development'], true)
                ? (string)$meta['release_status']
                : 'available',
            'requires_license' => !isset($meta['requires_license']) || (bool)$meta['requires_license'],
            'hide_in_nav' => array_key_exists('hide_in_nav', $meta) ? (bool)$meta['hide_in_nav'] : null,
            'hide_in_nav_when_licensed' => array_key_exists('hide_in_nav_when_licensed', $meta) && (bool)$meta['hide_in_nav_when_licensed']
        ];
    }

    private static function normalizeModuleVersion($version): string {
        return CmsVersion::normalize((string)$version);
    }

    /**
     * Basic manifest sanitization to avoid executing/using unsafe values.
     * Returns a normalized array with allowed keys only.
     */
    private static function sanitizeManifest($manifest, string $folderName): array {
        if (!is_array($manifest)) {
            if (class_exists('Log')) {
                Log::warning('Module manifest for ' . $folderName . ' is not an array; falling back to defaults.');
            }
            return [];
        }

        $allowed = [
            'id', 'name', 'version', 'pro', 'description', 'hide_in_nav', 'icon', 'url', 'priority', 'min_cms_version'
        ];

        $out = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $manifest)) continue;
            $val = $manifest[$k];
            // Cast booleans and strings to safe types
            if ($k === 'pro' || $k === 'hide_in_nav') {
                $out[$k] = ($val === true || $val === 1 || $val === '1');
            } elseif ($k === 'priority') {
                $out[$k] = is_numeric($val) ? (int)$val : null;
            } else {
                $out[$k] = is_scalar($val) ? (string)$val : null;
            }
        }

        // Ensure id consistency with folder name when absent
        if (empty($out['id']) || !self::isValidModuleId((string)$out['id']) || (string)$out['id'] !== $folderName) {
            $out['id'] = $folderName;
        }

        foreach (['name', 'description'] as $key) {
            if (isset($out[$key])) {
                $out[$key] = self::sanitizePlainText($out[$key], '');
            }
        }

        if (isset($out['icon'])) {
            $out['icon'] = self::sanitizeIconName($out['icon']);
        }

        if (array_key_exists('url', $out)) {
            $out['url'] = self::sanitizeModuleUrl($out['url']);
        }

        return $out;
    }

    private static function isValidModuleId(string $id): bool {
        return preg_match('/^[a-z0-9_\-]+$/', $id) === 1;
    }

    private static function sanitizePlainText($value, string $fallback): string {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $text = trim(strip_tags((string)$value));
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', '', $text);
        if ($text === null || $text === '') {
            return $fallback;
        }

        return mb_strlen($text) > 180 ? mb_substr($text, 0, 180) : $text;
    }

    private static function sanitizeIconName($value): string {
        $icon = is_scalar($value) ? trim((string)$value) : '';
        return preg_match('/^[a-zA-Z0-9_]+$/', $icon) ? $icon : 'analytics';
    }

    private static function sanitizeModuleUrl($value): ?string {
        if (!is_scalar($value)) {
            return null;
        }

        $url = trim((string)$value);
        if ($url === '' || str_contains($url, "\0") || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || str_starts_with($url, '//') || str_contains($path, '..')) {
            return null;
        }

        return $url;
    }

    private static function sanitizePromoUrl($value): string {
        $url = is_scalar($value) ? trim((string)$value) : '';
        return preg_match('#^https://clipon-cms\.com(?:/.*)?$#i', $url) ? $url : 'https://clipon-cms.com/pro';
    }

    private static function sanitizeVideoId($value): string {
        $videoId = is_scalar($value) ? trim((string)$value) : '';
        return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $videoId) ? $videoId : '';
    }

    private static function isVersionGreater(string $candidate, string $baseline): bool {
        return CmsVersion::compare($candidate, $baseline) > 0;
    }

    private static function isVersionGreaterOrEqual(string $candidate, string $baseline): bool {
        return CmsVersion::compare($candidate, $baseline) >= 0;
    }

    private static function mergePromoMeta(array $base, array $override): array {
        $merged = $base;
        foreach ($override as $key => $value) {
            if ($key === 'id') {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    private static function buildPromoUrl(string $id, array $meta): string {
        $proxyFile = isset($meta['file']) ? trim((string)$meta['file']) : '';
        if ($proxyFile !== '' && preg_match('/^[a-z0-9_\-]+\.php$/i', $proxyFile)) {
            $fullProxyPath = C_CORE_DIR . '/admin/pro/' . $proxyFile;
            if (is_file($fullProxyPath)) {
                // Пряма посилання на файл проксі (для зворотної сумісності)
                return 'pro/' . $proxyFile;
            }
        }

        // Універсальний роут з параметром модуля
        return 'pro/smart_stub.php?module=' . rawurlencode($id);
    }

    /**
     * Include all found active modules
     */
    private static function loadModules() {
        $isPro = class_exists('License') && License::isValid();

        foreach (self::$modules as $id => $module) {
            if (!$module['is_active']) continue;

            // Strict Gate: check license for PRO modules
            if ($module['pro']) {
                $releaseStatus = (string)($module['release_status'] ?? 'available');
                $requiresLicense = !isset($module['requires_license']) || (bool)$module['requires_license'];
                $cmsCompatible = !isset($module['cms_compatible']) || (bool)$module['cms_compatible'];

                if (!$cmsCompatible) {
                    self::$modules[$id]['is_active'] = false;
                    continue;
                }

                if ($releaseStatus !== 'available') {
                    self::$modules[$id]['is_active'] = false;
                    self::$modules[$id]['requires_license'] = $requiresLicense;
                    continue;
                }

                if ($requiresLicense && (!$isPro || !License::hasModule($id))) {
                    self::$modules[$id]['is_active'] = false;
                    self::$modules[$id]['requires_license'] = true;
                    continue;
                }
                
                // Перевірка фізичної наявності файлів для активованого PRO модуля
                if ($module['is_virtual'] || !file_exists($module['entry'])) {
                    self::$modules[$id]['is_active'] = false;
                    self::$modules[$id]['missing_files'] = true;
                    continue;
                }
            }

            // Define constant for module path to help internal includes
            $constName = 'M_' . strtoupper(str_replace('-', '_', $id)) . '_PATH';
            if (!defined($constName)) {
                define($constName, $module['path']);
            }
            
            require_once $module['entry'];
        }
        // After including module entry files, allow modules to register providers
        try {
            self::registerAllProviders();
            self::bootAllProviders();
        } catch (\Throwable $e) {
            // Non-fatal: log and continue
            if (class_exists('Log')) {
                Log::error('Module provider registration/boot failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Modules call this to register their provider instance with ModuleManager.
     * @param mixed $provider
     */
    public static function registerProvider($provider): void {
        if ($provider === null) return;
        self::$providers[] = $provider;
    }

    private static function registerAllProviders(): void {
        if (empty(self::$providers)) return;
        $registry = null;
        if (function_exists('registry')) {
            $registry = registry();
        }

        foreach (self::$providers as $p) {
            if (!is_object($p)) continue;
            if (method_exists($p, 'register')) {
                try {
                    $providerName = is_object($p) ? get_class($p) : 'unknown';
                    if (class_exists('Log')) {
                        Log::debug('Registering provider: ' . $providerName);
                    }
                    if ($registry !== null) {
                        $p->register($registry);
                    } else {
                        $p->register(null);
                    }
                    self::$registeredProviders[] = $p;
                } catch (\Throwable $e) {
                    if (class_exists('Log')) {
                        $msg = 'Provider register error: ' . $e->getMessage() . ' in ' . (isset($providerName) ? $providerName : 'unknown');
                        Log::error($msg);
                        if (method_exists($e, 'getTraceAsString')) {
                            Log::error($e->getTraceAsString());
                        }
                    }
                }
            }
        }
    }

    private static function bootAllProviders(): void {
        if (empty(self::$registeredProviders)) return;
        $registry = function_exists('registry') ? registry() : null;
        foreach (self::$registeredProviders as $p) {
            if (!is_object($p)) continue;
            if (method_exists($p, 'boot')) {
                try {
                    $providerName = is_object($p) ? get_class($p) : 'unknown';
                    if (class_exists('Log')) {
                        Log::debug('Booting provider: ' . $providerName);
                    }
                    if ($registry !== null) {
                        $p->boot($registry);
                    } else {
                        $p->boot(null);
                    }
                } catch (\Throwable $e) {
                    if (class_exists('Log')) {
                        $msg = 'Provider boot error: ' . $e->getMessage() . ' in ' . (isset($providerName) ? $providerName : 'unknown');
                        Log::error($msg);
                        if (method_exists($e, 'getTraceAsString')) {
                            Log::error($e->getTraceAsString());
                        }
                    }
                }
            }
        }
    }

    /**
     * Get list of discovered modules
     */
    public static function getModules() {
        return self::$modules;
    }

    /**
     * Check if module is loaded
     */
    public static function isLoaded($id) {
        return isset(self::$modules[$id]);
    }

    public static function getInstalledModuleVersions(): array {
        $versions = [];
        foreach (self::$modules as $id => $module) {
            if (!is_array($module) || !empty($module['is_virtual'])) {
                continue;
            }

            $versions[$id] = self::normalizeModuleVersion($module['module_version'] ?? '0.0.0');
        }

        return $versions;
    }

    /**
     * Check if a specific module is physically present AND active (considering license)
     * Useful for UI Dispatcher to decide whether to show real page or a stub.
     */
    public static function isProAvailable($id) {
        if (!isset(self::$modules[$id])) return false;
        return self::$modules[$id]['is_active'] === true;
    }

    /**
     * Check if module's license is active, regardless of physical file presence.
     */
    public static function isProLicensed(string $id): bool {
        // If license system explicitly grants the module, consider it licensed
        if (class_exists('License') && License::isValid() && License::hasModule($id)) {
            return true;
        }

        // If module is not discovered, we cannot infer entitlement from modules list
        if (!isset(self::$modules[$id])) {
            return false;
        }

        $module = self::$modules[$id];
        // Non-PRO modules are considered licensed by default
        if (empty($module['pro'])) {
            return true;
        }

        $requiresLicense = !isset($module['requires_license']) || (bool)$module['requires_license'];
        if (!$requiresLicense) {
            return true;
        }

        // Fallback: no valid license entitlement found
        return false;
    }

    /**
     * Check if module files are missing.
     */
    public static function isModuleMissing(string $id): bool {
        if (!empty(self::$modules[$id]['missing_files'])) {
            return class_exists('License') && License::isValid() && License::hasModule($id);
        }

        // If the module is not discovered but the license grants it, treat as missing files
        if (!isset(self::$modules[$id]) && class_exists('License') && License::isValid() && License::hasModule($id)) {
            return true;
        }

        return false;
    }

    /**
     * Return the effective PRO module state for UI and proxy routing.
     */
    public static function getProModuleState(string $id): string {
        if (!self::isProLicensed($id)) {
            return 'unlicensed';
        }

        if (self::isModuleMissing($id)) {
            return 'missing_files';
        }

        return 'licensed';
    }
}
