<?php
// Front Controller - Main Entry Point
// Handles all requests, routing, redirects, and page rendering

$rawRequestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$rawScriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$rawBasePath = dirname($rawScriptName);
if ($rawBasePath === DIRECTORY_SEPARATOR) $rawBasePath = '';
$normalizedRawPath = $rawRequestPath;
if ($rawBasePath !== '' && strpos($rawBasePath, '/clipon') !== 0 && strpos($normalizedRawPath, $rawBasePath) === 0) {
    $normalizedRawPath = substr($normalizedRawPath, strlen($rawBasePath));
}
if ($normalizedRawPath === '') $normalizedRawPath = '/';

$isAnalyticsEventEndpoint = preg_match('#^/clipon/admin/api/track_event\.php$#', $normalizedRawPath);
$needsSessionAwareBootstrap = isset($_GET['edit'])
    || (!$isAnalyticsEventEndpoint && preg_match('#^/clipon/(admin|setup\.php|save\.php|load\.php|check_edit\.php|index\.php)(/|$)#', $normalizedRawPath));

if (!$needsSessionAwareBootstrap && !defined('CLIPON_SESSIONLESS_BOOTSTRAP')) {
    define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
}

require_once __DIR__ . '/clipon/bootstrap.php';
require_once __DIR__ . '/clipon/lib/Analytics.php';
require_once __DIR__ . '/clipon/lib/CanonicalUrl.php';

function clipon_start_session_if_needed(): void {
    if (session_status() === PHP_SESSION_ACTIVE || !class_exists('SessionManager')) {
        return;
    }

    SessionManager::start();
    SessionManager::enforceActivity();
}

function clipon_prepare_public_analytics_session(): void {
    $policy = new CookieConsentPolicy(Settings::load(), new Request());
    if ($policy->canUseFullAnalytics()) {
        clipon_start_session_if_needed();
    }
}

function clipon_track_public_request(): void {
    clipon_prepare_public_analytics_session();
    Analytics::track();
}

// Allow direct access to CMS admin files
$requestPath = parse_url((string) $request->server('REQUEST_URI', '/'), PHP_URL_PATH);

// Базова корекція шляху, якщо сайт знаходиться в підпапці
$scriptName = (string) $request->server('SCRIPT_NAME', '/index.php');
$basePath = defined('C_BASE_URL') ? C_BASE_URL : dirname($scriptName);
if ($basePath === DIRECTORY_SEPARATOR) $basePath = '';
define('CMS_BASE_PATH', $basePath);

if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
if ($requestPath === '') $requestPath = '/';

if (preg_match('#^/clipon/#', $requestPath)) {
    // 1. Чіткий "Білий список" дозволених структур
    $isAllowed = false;
    
    // Дозволяємо адмінку, асети та головні скрипти редактора
    if (preg_match('#^/clipon/(admin|assets|lang)/#', $requestPath) || 
        preg_match('#^/clipon/(cms\.js|save\.php|load\.php|check_edit\.php|setup\.php|index\.php)?$#', $requestPath) ||
        $requestPath === '/clipon/') {
        $isAllowed = true;
    }

    if (!$isAllowed) {
        http_response_code(403);
        exit('Access Denied: Restricted CMS directory');
    }

    $fullPath = realpath(__DIR__ . $requestPath);
    $rootPath = realpath(__DIR__);

    // Перевірка, що шлях не вийшов за межі проекту і файл існує
    if (!$fullPath || strpos($fullPath, $rootPath) !== 0 || !file_exists($fullPath)) {
        http_response_code(404);
        exit('CMS File Not Found: ' . htmlspecialchars($requestPath));
    }
    
    // Якщо це папка (наприклад /clipon/admin), перенаправляємо на версію зі слешем
    if (is_dir($fullPath)) {
        if (!str_ends_with($requestPath, '/')) {
            header("Location: $requestPath/");
            exit;
        }
        $fullPath = rtrim($fullPath, '/') . '/index.php';
    }

    if (file_exists($fullPath)) {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        
        // Обробка PHP
        if ($ext === 'php') {
            include $fullPath; // chdir() більше не потрібен, ми виправили шляхи в файлах
            exit;
        }
        
        // Обробка статики (MIME-типи)
        $mimes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'ico'   => 'image/x-icon',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject'
        ];

        if (isset($mimes[$ext])) {
            header("Content-Type: " . $mimes[$ext]);
        }
        if (preg_match('#^/clipon/assets/(js|css)/#', $requestPath)) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        readfile($fullPath);
        exit;
    }
}

// Allow static files to be served directly
if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp|zip)$#i', $requestPath)) {
    return false;
}

header('Content-Type: text/html; charset=utf-8');

// Include RouteMap
// If composer autoload is present, include it to load dependencies (Parsedown)
if (file_exists(__DIR__ . '/clipon/vendor/autoload.php')) {
    require_once __DIR__ . '/clipon/vendor/autoload.php';
}

// Include Renderer
require_once __DIR__ . '/clipon/lib/Renderer.php';
require_once __DIR__ . '/clipon/lib/Translation.php';
require_once __DIR__ . '/clipon/lib/Settings.php';
require_once __DIR__ . '/clipon/lib/PublicPageInstrumentation.php';

// Check if CMS is installed
if (!file_exists(C_CONFIG_PATH . '/settings.php')) {
    header('Location: ' . CMS_BASE_PATH . '/clipon/setup.php');
    exit;
}

// Remove query string if present
$path = $requestPath;

// Normalize the path (remove .php, trailing slashes, etc.)
$normalizedPath = rtrim($path, '/');
if (empty($normalizedPath)) {
    $normalizedPath = '/';
}
$normalizedPath = preg_replace('/\.php$/', '', $normalizedPath);

// Language detection
$configuredLangs = Settings::getLanguages();
$langCodes = [];
foreach ($configuredLangs as $l) {
    if (!empty($l['enabled'])) $langCodes[] = $l['code'];
}
$primaryLang = (string)($langCodes[0] ?? (Settings::load()['language'] ?? 'en'));
$currentLang = $primaryLang;
$editingLang = $primaryLang;
$pathWithoutLang = $normalizedPath;
$hadLanguagePrefix = false;

$pathParts = explode('/', ltrim($normalizedPath, '/'));
if (!empty($pathParts[0]) && in_array($pathParts[0], $langCodes, true)) {
    $currentLang = array_shift($pathParts);
    $pathWithoutLang = '/' . implode('/', $pathParts);
    if ($pathWithoutLang === '') $pathWithoutLang = '/';
    $hadLanguagePrefix = true;
}

$editingLang = $currentLang;
if ($request->query('edit') !== null) {
    clipon_start_session_if_needed();
}
if ($request->query('edit') !== null && $session->has('user')) {
    $requestedEditLang = $request->string('edit_lang');
    if ($requestedEditLang !== '' && in_array($requestedEditLang, $langCodes, true)) {
        $editingLang = $requestedEditLang;
    }
}

/** @var object $siteLanguage */
$siteLanguage = registry()->get('site_language');
if (!$siteLanguage->setCurrent($currentLang)) {
    $siteLanguage->setCurrent($primaryLang);
}
Translation::init();

// Get RouteMap instance
$routeMap = registry()->get('route_map');

$lookupPath = $normalizedPath;

// Check for redirects first
$redirect = $routeMap->findRedirect($lookupPath);
if ($redirect) {
    http_response_code($redirect['code']);
    header('Location: ' . $redirect['target']);
    exit;
}

// Find the route
$route = $routeMap->findRoute($lookupPath);

if ($route) {
    $routeLang = $route['lang'] ?? $primaryLang;
    if ($routeLang === $primaryLang && $hadLanguagePrefix) {
        $redirectTarget = $pathWithoutLang;
        $queryString = (string)$request->server('QUERY_STRING', '');
        if ($queryString !== '') {
            $redirectTarget .= '?' . $queryString;
        }
        header('Location: ' . $redirectTarget, true, 301);
        exit;
    }

    if ($routeLang !== $currentLang) {
        $route = null;
    }
}

if ($route && isset($route['active']) && $route['active'] === false) {
    $route = null;
}

if ($route) {
    $canonicalTarget = clipon_trailing_slash_redirect_target(
        $requestPath,
        CMS_BASE_PATH,
        (string)$request->server('QUERY_STRING', ''),
        (string)$request->server('REQUEST_METHOD', 'GET')
    );
    if ($canonicalTarget !== null) {
        header('Location: ' . $canonicalTarget, true, 301);
        exit;
    }
}

if (!$route) {
    // 404 - Page not found
    http_response_code(404);
    clipon_track_public_request();
    $errorPage = __DIR__ . '/404.php';
    if (file_exists($errorPage)) {
        ob_start();
        include $errorPage;
        echo PublicPageInstrumentation::inject((string)ob_get_clean());
    } else {
        echo PublicPageInstrumentation::inject('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Сторінка не знайдена</title></head><body><h1>404 - Сторінка не знайдена</h1><p>Запитувана сторінка не існує.</p></body></html>');
    }
    exit;
}

// Get the page slug
$pageSlug = $route['slug'];

// Determine template path
$templateName = $route['template'] ?? ($pageSlug . '.php');
$templatePath = __DIR__ . '/templates/' . $templateName;

// Determine data path based on type
$type = $route['type'] ?? 'page';
$baseDir = ($type === 'blog') ? '/blog/' : '/pages/';
$jsonPath = C_CONTENT_PATH . $baseDir . $pageSlug . '.php';

if (file_exists($templatePath)) {
    // Load data
    $pageData = [];
    if (file_exists($jsonPath)) {
        require_once __DIR__ . '/clipon/lib/JsonStorage.php';
        $pageData = read_json_file($jsonPath);
        if (!$pageData) {
            $pageData = []; // Handle invalid JSON gracefully
        }
    }
    
    // Ensure type and slug are present in data for Renderer
    $pageData['type'] = $type;
    $pageData['slug'] = $pageSlug; // Ensure correct slug from router
    $pageData['current_lang'] = $editingLang;
    $pageData['primary_lang'] = $primaryLang;
    $pageData['current_path'] = $lookupPath;

    // Collect only active route alternates for canonical/hreflang generation
    $alternateUrls = $routeMap->getActiveAlternates($pageSlug, $type);
    $pageData['alternate_urls'] = $alternateUrls;

    // Render template with data
    try {
        // Apply translations using ContentMapper
        /** @var object $contentMapper */
        $contentMapper = registry()->get('content_mapper');
        $renderData = $contentMapper->mapForRead($pageData, $editingLang, $primaryLang);
        
        // Ensure critical metadata is preserved/overridden correctly
        $renderData['type'] = $type;
        $renderData['slug'] = $pageSlug;
        $renderData['current_lang'] = $editingLang;
        $renderData['primary_lang'] = $primaryLang;
        $renderData['current_path'] = $lookupPath;
        $renderData['alternate_urls'] = $alternateUrls;

        // Flatten content array to make it accessible by ID
        if (isset($renderData['content']) && is_array($renderData['content'])) {
            $contentBlocks = $renderData['content'];
            // Remove keys from content that should not overwrite root properties
            unset($contentBlocks['title'], $contentBlocks['seo'], $contentBlocks['slug'], $contentBlocks['url']);
            $renderData = array_merge($renderData, $contentBlocks);
        }

        $renderer = new Renderer($templatePath, $renderData);
        clipon_track_public_request();
        echo $renderer->render();
    } catch (Exception $e) {
        http_response_code(500);
        clipon_track_public_request();
        error_log('Clipon render error: ' . $e->getMessage());
        echo PublicPageInstrumentation::inject('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>500 - Server Error</title></head><body><h1>500 - Server Error</h1><p>Unable to render this page.</p></body></html>');
    }

} else {
    // Minimal fallback rendering for missing template, still using locales-only mapping.
    if (file_exists($jsonPath)) {
        require_once __DIR__ . '/clipon/lib/JsonStorage.php';
        $pageData = read_json_file($jsonPath);
        if (is_array($pageData)) {
            $pageData['type'] = $type;
            $pageData['slug'] = $pageSlug;
            $pageData['current_lang'] = $editingLang;
            $pageData['primary_lang'] = $primaryLang;
            $pageData['current_path'] = $lookupPath;

            /** @var object $contentMapper */
            $contentMapper = registry()->get('content_mapper');
            $renderData = $contentMapper->mapForRead($pageData, $editingLang, $primaryLang);

            $siteSettings = Settings::load();
            $siteName = $siteSettings['site_name'] ?? 'Clipon CMS';
            $siteUrl = c_site_url();

            $titleRaw = (string)($renderData['title'] ?? 'Untitled');
            $seoRaw = is_array($renderData['seo'] ?? null) ? $renderData['seo'] : [];
            $metaTitleRaw = (string)($seoRaw['meta_title'] ?? '');
            if ($metaTitleRaw === '') {
                $metaTitleRaw = $titleRaw . ' | ' . $siteName;
            }
            $metaDescriptionRaw = (string)($seoRaw['meta_description'] ?? ($siteSettings['site_description'] ?? ''));
            $content = $renderData['content'] ?? [];

            $title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
            $metaTitle = htmlspecialchars($metaTitleRaw, ENT_QUOTES, 'UTF-8');
            $metaDescription = htmlspecialchars($metaDescriptionRaw, ENT_QUOTES, 'UTF-8');

            $html = '<!DOCTYPE html><html lang="' . htmlspecialchars($editingLang, ENT_QUOTES, 'UTF-8') . '"><head>';
            $html .= '<meta charset="UTF-8">';
            $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            $html .= '<title>' . $metaTitle . '</title>';
            if ($metaDescription !== '') {
                $html .= '<meta name="description" content="' . $metaDescription . '">';
            }

            $canonicalPath = '/' . ltrim($lookupPath, '/');
            if ($canonicalPath === '') {
                $canonicalPath = '/';
            }

            if ($siteUrl !== '') {
                $canonical = $siteUrl . $canonicalPath;
            } else {
                $scheme = (!empty($request->server('HTTPS')) && $request->server('HTTPS') !== 'off') ? 'https' : 'http';
                $host = $request->server('HTTP_HOST', $request->server('SERVER_NAME', 'localhost'));
                $canonical = $scheme . '://' . $host . $canonicalPath;
            }
            $html .= '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '</head><body>';
            $html .= '<h1>' . $title . '</h1>';
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    $html .= '<div id="' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '">' . Sanitizer::sanitize(is_scalar($value) ? (string)$value : '') . '</div>';
                }
            } else {
                $html .= Sanitizer::sanitize(is_scalar($content) ? (string)$content : '');
            }
            $html .= '</body></html>';

            clipon_track_public_request();
            echo PublicPageInstrumentation::inject($html);
        } else {
            http_response_code(500);
            clipon_track_public_request();
            echo PublicPageInstrumentation::inject('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Помилка</title></head><body><h1>Внутрішня помилка сервера</h1></body></html>');
        }
    } else {
        http_response_code(404);
        clipon_track_public_request();
        echo PublicPageInstrumentation::inject('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Сторінка не знайдена</title></head><body><h1>404 - Сторінка не знайдена</h1></body></html>');
    }
}
