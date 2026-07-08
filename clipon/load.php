<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/JsonStorage.php';

$pageSlugRaw = $request->query('page', 'notindex');

if (!is_string($pageSlugRaw) || $pageSlugRaw === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid page parameter']);
    exit;
}

// Reject path traversal or multi-segment paths
if (strpos($pageSlugRaw, '/') !== false || strpos($pageSlugRaw, '..') !== false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid page parameter']);
    exit;
}

// Allow only safe slug characters (alphanumeric, dash, underscore)
if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $pageSlugRaw)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid page slug format']);
    exit;
}

$pageSlug = $pageSlugRaw;
$pageFile = C_CONTENT_PATH . '/pages/' . $pageSlug . '.php';

$configuredLangs = Settings::getLanguages();
$activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
$primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
$activeLangCodes = array_values(array_map(static fn($l) => (string)($l['code'] ?? ''), $activeLangs));
$currentLang = $request->string('lang', $primaryLang);
if (!in_array($currentLang, $activeLangCodes, true)) {
    $currentLang = $primaryLang;
}

if (!file_exists($pageFile)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Page not found']);
    exit;
}

$pageData = read_json_file($pageFile);

// Перевірка активності сторінки
if (!($pageData['active'] ?? true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => __('error_page_not_found_or_inactive')]);
    exit;
}

// Enforce locales-only schema; support graceful fallback for legacy data
if (!isset($pageData['locales']) || !is_array($pageData['locales']) || empty($pageData['locales'])) {
    // If no locales exist but legacy 'title'/'seo'/'content' fields are present,
    // use them as fallback for primary language to maintain backward compatibility
    if (!isset($pageData['locales'])) {
        $pageData['locales'] = [];
    }
    
    if (empty($pageData['locales'][$primaryLang])) {
        $pageData['locales'][$primaryLang] = [
            'title' => (string)($pageData['title'] ?? ''),
            'seo' => (array)($pageData['seo'] ?? []),
            'content' => (array)($pageData['content'] ?? []),
            'slug' => basename($pageFile, '.php'),
        ];
    }
    
    if (empty($pageData['locales'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Page locales schema is corrupted or empty.']);
        exit;
    }
}

/** @var object $contentMapper */
$contentMapper = registry()->get('content_mapper');
$mappedData = $contentMapper->mapForRead($pageData, $currentLang, $primaryLang);
$mappedData['current_lang'] = $currentLang;
$mappedData['primary_lang'] = $primaryLang;

header('Content-Type: application/json');
echo json_encode($mappedData);
?>