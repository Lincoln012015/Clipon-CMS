<?php
function setup_normalize_base_path(string $basePath): string {
    $basePath = str_replace('\\', '/', trim($basePath));
    if ($basePath === '.' || $basePath === './') {
        return '';
    }
    $basePath = rtrim($basePath, '/');
    if ($basePath !== '' && $basePath[0] !== '/') {
        $basePath = '/' . ltrim($basePath, '/');
    }
    return $basePath;
}

function setup_report_warning(array &$report, string $message): void {
    $report['warnings'][] = $message;
}

function setup_build_page_url(string $slug, array $pageData, string $pagesDir): string {
    $parentId = trim((string)($pageData['parent_id'] ?? ''));
    if ($parentId === '') {
        return '/' . $slug;
    }

    $path = [$slug];
    $visited = [];
    while ($parentId !== '' && !in_array($parentId, $visited, true)) {
        $visited[] = $parentId;
        $parentFile = rtrim($pagesDir, '/\\') . '/' . $parentId . '.php';
        if (!file_exists($parentFile)) {
            break;
        }

        $parentData = read_json_file($parentFile);
        array_unshift($path, basename($parentId, '.php'));
        $parentId = trim((string)($parentData['parent_id'] ?? ''));
    }

    return '/' . implode('/', array_filter($path, static fn($part) => $part !== ''));
}

function setup_finalize_homepage_routes(): bool {
    $pagesDir = C_CONTENT_PATH . '/pages/';
    if (!is_dir($pagesDir)) {
        return true;
    }

    $pageFiles = glob($pagesDir . '*.php') ?: [];
    $homeSlug = null;

    foreach ($pageFiles as $file) {
        $pageData = read_json_file($file);
        if (!empty($pageData['is_home'])) {
            $homeSlug = basename($file, '.php');
            break;
        }
    }

    if ($homeSlug === null && file_exists($pagesDir . 'index.php')) {
        $homeSlug = 'index';
    }

    foreach ($pageFiles as $file) {
        $slug = basename($file, '.php');
        $pageData = read_json_file($file);
        if (empty($pageData)) {
            continue;
        }

        $updatedPageData = $pageData;
        if ($homeSlug !== null && $slug === $homeSlug) {
            $updatedPageData['is_home'] = true;
            $updatedPageData['url'] = '/';
        } elseif (!empty($updatedPageData['is_home'])) {
            $updatedPageData['is_home'] = false;
            $updatedPageData['url'] = setup_build_page_url($slug, $updatedPageData, $pagesDir);
        }

        if ($updatedPageData === $pageData) {
            continue;
        }

        if (!write_json_file($file, $updatedPageData)) {
            return false;
        }
    }

    /** @var RouteMapStub $routeMap */
    $routeMap = registry()->get('route_map');
    return (bool)$routeMap->rebuild($pagesDir);
}
