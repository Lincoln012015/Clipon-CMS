<?php
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/JsonStorage.php';
require_once __DIR__ . '/lib/Sanitizer.php';
require_once __DIR__ . '/lib/BlogInlineFields.php';

// Перевірка авторизації
if (!$session->has('user')) {
    http_response_code(403);
    exit(__('error_unauthorized'));
}

if (!$request->isPost()) {
    http_response_code(405);
    exit(__('error_method_not_allowed'));
}

$csrfToken = $request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    exit(__('error_invalid_csrf'));
}

$key = $request->post('key', '');
$content = $request->post('content', '');
$contentKind = $request->post('content_kind', '');

// Sanitize HTML content to prevent XSS. Image src values are stored as plain
// URLs, so sanitize them without parsing as HTML to avoid wrapping in <p>.
if ($contentKind === 'image_src') {
    $content = Sanitizer::sanitizeImageSource($content);
} elseif ($contentKind === 'link_href') {
    $content = Sanitizer::sanitizeLinkHref($content);
} elseif (!empty($content) && is_string($content)) {
    $content = Sanitizer::sanitize($content);
}

$page = $request->post('page', 'index');
$type = $request->post('type', 'page'); // 'page' or 'blog'
$lang = $request->string('lang');
$action = $request->post('action', 'save_content');

if (!is_string($page) || $page === '') {
    http_response_code(400);
    exit(__('error_missing_parameters'));
}

// Reject path traversal or nested paths in content identifiers.
if (strpos($page, '/') !== false || strpos($page, '..') !== false) {
    http_response_code(400);
    exit(__('error_invalid_parameters'));
}

if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $page)) {
    http_response_code(400);
    exit(__('error_invalid_parameters'));
}

$type = ($type === 'blog') ? 'blog' : 'page';

$requiredPermission = ($type === 'blog') ? 'edit_blog' : 'edit_pages';
if (!hasPermission($requiredPermission)) {
    http_response_code(403);
    exit($type === 'blog' ? __('error_no_edit_blog_permission') : __('error_no_edit_pages_permission'));
}

$baseDir = ($type === 'blog') ? '/blog/' : '/pages/';
$pageFile = C_CONTENT_PATH . $baseDir . $page . '.php';

if (!file_exists($pageFile)) {
    http_response_code(404);
    exit(__('error_page_not_found_or_inactive'));
}

$pageData = read_json_file($pageFile);

if ($action === 'create_version') {
    require_once __DIR__ . '/lib/History.php';
    $history = new History();
    // Save only if there are changes compared to last history version
    $saved = $history->save($page, $pageData, true);
    if ($saved) {
        echo __('version_created');
    } else {
        echo __('no_changes');
    }
    exit;
}

if (empty($key)) {
    http_response_code(400);
    exit(__('error_missing_parameters'));
}

// Locales-only write flow.
$configuredLangs = Settings::getLanguages();
$activeLangs = array_values(array_filter($configuredLangs, fn($l) => !empty($l['enabled'])));
$primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
$enabledLangCodes = array_values(array_map(fn($l) => (string)$l['code'], $activeLangs));

if ($lang === '' || !in_array($lang, $enabledLangCodes, true)) {
    $lang = $primaryLang;
}

$isBlogMetadataSave = $type === 'blog' && BlogInlineFields::saveMetadataField($pageData, $key, $content);

if (!$isBlogMetadataSave) {
    if ($type === 'blog' && BlogInlineFields::isPlainLocaleField($key)) {
        $content = BlogInlineFields::plain($content);
    }

    /** @var object $contentMapper */
    $contentMapper = registry()->get('content_mapper');
    $contentMapper->prepareForWrite($pageData, $lang, $primaryLang, $key, $content);
}

if ($type === 'blog') {
    if (empty($pageData['author'])) {
        $pageData['author'] = $session->get('user');
    }
    $pageData['modified_by'] = $session->get('user');
} else {
    $pageData['author'] = $session->get('user');
}
$pageData['modified'] = date('Y-m-d H:i:s');

// Atomic write with rollback on route map rebuild failure
$existed = file_exists($pageFile);
$backup = $pageFile . '.bak.' . time();
if ($existed) {
    @copy($pageFile, $backup);
}

if (!write_json_file($pageFile, $pageData)) {
    if ($existed && file_exists($backup)) {
        @copy($backup, $pageFile);
        @unlink($backup);
    }
    http_response_code(500);
    exit(__('system_error'));
}

// Try to rebuild route map; rollback on failure
/** @var object $routeMap */
$routeMap = registry()->get('route_map');
if (!$routeMap->rebuild()) {
    if ($existed && file_exists($backup)) {
        @copy($backup, $pageFile);
        @unlink($backup);
    } else {
        @unlink($pageFile);
    }
    http_response_code(500);
    exit(__('system_error') . ': route map rebuild failed');
}

if (file_exists($backup)) @unlink($backup);

// Refresh blog index if needed
if ($type === 'blog') {
    if (file_exists(__DIR__ . '/lib/Blog.php')) {
        require_once __DIR__ . '/lib/Blog.php';
        $blogSvc = new Blog();
        // Since getPosts([]) without cache will trigger re-read and then we cache it
        // But better to clear existing cache file first to force re-read of fresh files
        $cacheFile = C_DATA_PATH . '/blog_index.php';
        if (file_exists($cacheFile)) @unlink($cacheFile);
        $blogSvc->getPosts(['active_only' => false]); 
    }
}

echo __('ok');
?>
