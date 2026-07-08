<?php
/**
 * Clipon CMS Bootstrap
 * Defined constants for file system paths and URLs.
 * This file allows moving the 'clipon' core directory easily.
 */

// PHP Version Check (v8.0.0+ required by the runtime)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error: PHP Update Required</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #333; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f7f6; }
            .container { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
            h1 { color: #e74c3c; margin-top: 0; }
            p { margin-bottom: 20px; }
            code { background: #eee; padding: 2px 5px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>PHP Version Update Required</h1>
            <p>Clipon CMS requires <strong>PHP 8.0.0</strong> or higher to function correctly.</p>
            <p>Your current version is: <code><?php echo PHP_VERSION; ?></code></p>
            <p>Please contact your hosting provider or system administrator to upgrade PHP.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Root directory of the whole project
if (!defined('C_ROOT')) {
    if (getenv('CLIPON_ROOT')) {
        define('C_ROOT', rtrim(getenv('CLIPON_ROOT'), '/\\'));
    } else {
        // C_ROOT is the parent of 'clipon' directory (where config, content, templates live)
        define('C_ROOT', dirname(__DIR__));
    }
}

// Core directory (usually 'clipon')
if (!defined('C_CORE_DIR')) {
    define('C_CORE_DIR', __DIR__);
}

// Global functions file
require_once C_CORE_DIR . '/lib/JsonStorage.php';

function c_site_settings(): array {
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settingsFile = C_CONFIG_PATH . '/settings.php';
    $settings = [];

    if (file_exists($settingsFile) && is_readable($settingsFile)) {
        $loaded = read_json_file($settingsFile);
        if (is_array($loaded)) {
            $settings = $loaded;
        }
    }

    return $settings;
}

function c_site_url(): string {
    return rtrim((string) (c_site_settings()['site_url'] ?? ''), '/');
}

function c_site_base_path(): string {
    $siteUrl = c_site_url();
    if ($siteUrl === '') {
        return '';
    }

    $basePath = rtrim((string) parse_url($siteUrl, PHP_URL_PATH), '/');
    if ($basePath === '' || $basePath === '/' || $basePath === '\\') {
        return '';
    }

    if ($basePath[0] !== '/') {
        $basePath = '/' . ltrim($basePath, '/');
    }

    return $basePath;
}

// Config directory (external by default)
if (!defined('C_CONFIG_PATH')) {
    define('C_CONFIG_PATH', C_ROOT . '/config');
}

// Content directory (external by default)
if (!defined('C_CONTENT_PATH')) {
    define('C_CONTENT_PATH', C_ROOT . '/content');
}

// Assets directory (JS/CSS/Fonts)
if (!defined('C_ASSETS_PATH')) {
    define('C_ASSETS_PATH', C_CORE_DIR . '/assets');
}

// Data directory (internal or external)
if (!defined('C_DATA_PATH')) {
    define('C_DATA_PATH', C_ROOT . '/data');
}

// Uploads directory
define('C_UPLOADS_PATH', C_ROOT . '/assets/uploads');

// Logs directory
define('C_LOGS_PATH', C_ROOT . '/logs');

// Template directory
define('C_TEMPLATES_PATH', C_ROOT . '/templates');

// Modules directory (external by default)
if (!defined('C_MODULES_PATH')) {
    define('C_MODULES_PATH', C_ROOT . '/modules');
}

// Helper to calculate absolute URLs dynamically
if (!defined('C_BASE_URL')) {
    define('C_BASE_URL', c_site_base_path());
    define('C_ADMIN_URL', C_BASE_URL . '/clipon/admin');
    define('C_ASSETS_URL', C_BASE_URL . '/clipon/assets');
}

// Helper to get absolute path from relative to core
function c_core_path($path = '') {
    return C_CORE_DIR . '/' . ltrim($path, '/');
}

// Helper to get relative URL for assets
function c_assets_url($path = '') {
    return C_ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Autoloader for internal classes
 */
spl_autoload_register(function($class) {
    // 1. Core platform classes
    $file = C_CORE_DIR . '/lib/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // 2. Admin shared UI classes
    $adminLibFile = C_CORE_DIR . '/admin/lib/' . $class . '.php';
    if (file_exists($adminLibFile)) {
        require_once $adminLibFile;
        return;
    }
});

/**
 * Composer autoloader
 */
if (file_exists(C_CORE_DIR . '/vendor/autoload.php')) {
    require_once C_CORE_DIR . '/vendor/autoload.php';
}

/**
 * Initialize Core Services
 */
$request = new Request();
$session = new Session();

// Start session management
$sessionlessBootstrap = defined('CLIPON_SESSIONLESS_BOOTSTRAP') && CLIPON_SESSIONLESS_BOOTSTRAP;
if (class_exists('SessionManager') && !$sessionlessBootstrap) {
    SessionManager::start();
    SessionManager::enforceActivity();
}

/**
 * Initialize Module System
 */
// Initialize Service Registry early so modules can register services.
require_once C_CORE_DIR . '/lib/ServiceRegistry.php';
$registry = new ServiceRegistry();

// Expose some core services for convenience
$registry->setInstance('request', isset($request) ? $request : null);
$registry->setInstance('session', isset($session) ? $session : null);

// Default stubs as lazy factories — modules may override these during their registration
$registry->set('route_map', function($r) { return new RouteMapStub(); });
$registry->set('content_mapper', function($r) { return new ContentMapperStub(); });
$registry->set('site_language', function($r) { return new SiteLanguageStub(); });

// Make registry globally accessible via helper
$GLOBALS['app_registry'] = $registry;
if (!function_exists('registry')) {
    function registry() {
        return $GLOBALS['app_registry'];
    }
}

if (class_exists('ModuleManager')) {
    ModuleManager::init();
}

// Initialize Analytics DI container (collection + basic dashboard stats).
if (class_exists('Analytics') && class_exists('AnalyticsContainer')) {
    if (!Analytics::hasContainer()) {
        Analytics::setContainer(new AnalyticsContainer(C_DATA_PATH . '/analytics'));
    }
}

// PRO analytics service: module overrides; core stub serves mock/locked states.
if ($registry instanceof ServiceRegistry && !$registry->has('pro_analytics.service')) {
    $registry->set('pro_analytics.service', function () {
        return new ProAnalyticsStubService();
    });
}

/**
 * Guardian: Periodic License Check (1% Request Probability)
 * Run after the response so the check does not block page rendering.
 */
if ((class_exists('License') || class_exists('CoreUpdater')) && rand(1, 100) === 1) {
    $shouldSyncLicense = false;
    $shouldSyncCore = false;

    if (class_exists('License') && License::isValid() && time() >= License::getNextCheckTarget()) {
        $shouldSyncLicense = true;
    }

    if (class_exists('CoreUpdater') && time() >= CoreUpdater::getNextCheckTarget()) {
        $shouldSyncCore = true;
    }

    if ($shouldSyncLicense || $shouldSyncCore) {
        register_shutdown_function(function () use ($shouldSyncLicense, $shouldSyncCore): void {
            try {
                if ($shouldSyncLicense && class_exists('License')) {
                    License::syncInternalState();
                }

                if ($shouldSyncCore && class_exists('CoreUpdater')) {
                    CoreUpdater::syncInternalState();
                }
            } catch (Throwable $e) {
                error_log('Clipon guardian sync failed: ' . $e->getMessage());
            }
        });
    }
}
