<?php
// clipon/admin/api/media_list.php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../lib/Auth.php';
header('Content-Type: application/json');

AdminAccess::requireUserApi($session);

// Current directory (relative to public assets/)
$currentDir = $request->query('dir', '');

// Security: prevent directory traversal. Only allow alphanumeric, underscores, dashes and slashes.
// No ".." allowed.
if (strpos($currentDir, '..') !== false) {
    $currentDir = '';
}
$currentDir = preg_replace('#/+#', '/', $currentDir); // Remove multiple slashes
$currentDir = trim($currentDir, '/');

$mediaRootPath = C_ROOT . '/assets';
if (!is_dir($mediaRootPath)) {
    mkdir($mediaRootPath, 0775, true);
}

$resolvedMediaRoot = realpath($mediaRootPath);
if ($resolvedMediaRoot === false) {
    echo json_encode([
        'items' => [],
        'currentDir' => '',
        'error' => __('error_cannot_create_upload_dir')
    ]);
    exit;
}

$mediaRoot = $resolvedMediaRoot . DIRECTORY_SEPARATOR;
$currentPath = realpath($mediaRoot . $currentDir);

// Ensure the resolved path is still within the public assets root
if (!$currentPath || strpos($currentPath, $mediaRoot) !== 0 || !is_dir($currentPath)) {
    $currentPath = $mediaRoot;
    $currentDir = '';
} else {
    // Add trailing separator to currentPath
    $currentPath .= DIRECTORY_SEPARATOR;
}

$result = [];

// Get directories first
$items = scandir($currentPath);
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $currentPath . $item;
    if (is_dir($fullPath)) {
        $folderRelDir = ($currentDir ? $currentDir . '/' : '') . $item;
        $result[] = [
            'type' => 'folder',
            'name' => $item,
            'dir' => $folderRelDir,
            'path' => '/assets/' . $folderRelDir
        ];
    }
}

// Get files (зображення + локальне відео)
$files = glob($currentPath . '*.{jpg,jpeg,png,gif,webp,mp4}', GLOB_BRACE);

foreach($files as $file) {
    $fileName = basename($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
    $isVideo = in_array($ext, ['mp4']);

    $result[] = [
        'type' => 'file',
        'path' => '/assets/' . ($currentDir ? $currentDir . '/' : '') . $fileName,
        'name' => $fileName,
        'is_image' => $isImage,
        'is_video' => $isVideo,
    ];
}

echo json_encode([
    'items' => $result,
    'currentDir' => $currentDir
]);
?>
