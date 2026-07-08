<?php

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/JsonStorage.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$mediaService = new MediaService();
$manager = new MediaManager();
$action = (string)$request->post('action', '');
$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::json(['error' => __('error_invalid_csrf')], 403);
}

$router = new AdminActionRouter();

$router->on('create_folder', function () use ($request, $manager): void {
    $name = trim((string)$request->post('name', ''));
    $currentDir = (string)$request->post('currentDir', '');

    try {
        $manager->createFolder($name, $currentDir);
        AdminResponder::json(['success' => true]);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('delete_folder', function () use ($request, $manager): void {
    $name = (string)$request->post('name', '');
    $currentDir = (string)$request->post('currentDir', '');

    try {
        $manager->deleteFolder($name, $currentDir);
        AdminResponder::json(['success' => true]);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('rename_folder', function () use ($request, $manager): void {
    $oldName = (string)$request->post('oldName', '');
    $newName = (string)$request->post('newName', '');
    $currentDir = (string)$request->post('currentDir', '');

    try {
        $manager->renameFolder($oldName, $newName, $currentDir);
        AdminResponder::json(['success' => true]);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('delete_file', function () use ($request, $manager): void {
    $filename = (string)$request->post('filename', '');
    $currentDir = (string)$request->post('currentDir', '');
    try {
        $manager->deleteFile($filename, $currentDir);
        AdminResponder::json(['success' => true]);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('move_item', function () use ($request, $manager): void {
    $type = (string)$request->post('type', 'file');
    $name = (string)$request->post('name', '');
    $target = (string)$request->post('target', '');
    $currentDir = (string)$request->post('currentDir', '');

    $name = str_replace(['..', '\\'], '', $name);
    $target = str_replace(['..', '/', '\\'], '', $target);
    $currentDir = trim(str_replace(['..', '\\'], '', $currentDir), '/');

    if ($name === '' || $target === '') {
        AdminResponder::json(['error' => __('error_invalid_parameters')]);
    }

    $targetDir = trim(($currentDir !== '' ? $currentDir . '/' : '') . $target, '/');

    try {
        $manager->moveItem($name, $type ?: 'file', $currentDir, $targetDir);
        AdminResponder::json(['success' => true]);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('bulk_move', function () use ($request, $manager): void {
    $items = json_decode((string)$request->post('items', '[]'), true);
    $currentDir = (string)$request->post('currentDir', '');
    $targetDir = (string)$request->post('targetDir', '');
    if (!is_array($items)) {
        $items = [];
    }

    try {
        $results = $manager->bulkMove($items, $currentDir, $targetDir);
        AdminResponder::json($results);
    } catch (Exception $e) {
        AdminResponder::json(['error' => $e->getMessage()]);
    }
});

$router->on('bulk_delete', function () use ($request, $mediaService): void {
    $items = json_decode((string)$request->post('items', '[]'), true);
    $currentDir = (string)$request->post('currentDir', '');
    if (!is_array($items)) {
        $items = [];
    }

    $safeCurrentDir = $mediaService->sanitizeDir($currentDir);
    $resolvedMediaRoot = realpath($mediaService->getMediaRoot());
    if ($resolvedMediaRoot === false) {
        AdminResponder::json(['error' => __('error_cannot_create_upload_dir')]);
    }

    $mediaRoot = $resolvedMediaRoot . DIRECTORY_SEPARATOR;
    $results = ['deleted' => [], 'errors' => []];

    $deleteDirectoryRecursive = static function (string $dir) use (&$deleteDirectoryRecursive): bool {
        if (!is_dir($dir)) {
            return false;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    };

    foreach ($items as $it) {
        $name = (string)($it['name'] ?? '');
        $type = (string)($it['type'] ?? 'file');

        if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            $results['errors'][] = ['name' => $name, 'error' => __('error_invalid_name')];
            continue;
        }

        $path = realpath($mediaRoot . ($safeCurrentDir !== '' ? $safeCurrentDir . DIRECTORY_SEPARATOR : '') . $name);
        if (!$path || strpos($path, $mediaRoot) !== 0) {
            $results['errors'][] = ['name' => $name, 'error' => __('error_invalid_path')];
            continue;
        }

        if ($type === 'folder') {
            if ($path === rtrim($mediaRoot, DIRECTORY_SEPARATOR)) {
                $results['errors'][] = ['name' => $name, 'error' => __('error_cannot_delete_root')];
                continue;
            }
            if ($deleteDirectoryRecursive($path)) {
                $results['deleted'][] = $name;
            } else {
                $results['errors'][] = ['name' => $name, 'error' => __('error_delete_folder')];
            }
            continue;
        }

        if (!is_file($path)) {
            $results['errors'][] = ['name' => $name, 'error' => __('error_file_not_found')];
            continue;
        }

        if (unlink($path)) {
            $results['deleted'][] = $name;
        } else {
            $results['errors'][] = ['name' => $name, 'error' => __('error_delete_file')];
        }
    }

    AdminResponder::json($results);
});

$router->on('save_alt', function () use ($request, $mediaService): void {
    $filename = (string)$request->post('filename', '');
    $alt = (string)$request->post('alt', '');
    $lang = (string)$request->post('lang', 'uk');

    AdminResponder::json($mediaService->saveMediaAlt($filename, $alt, $lang));
});

$router->dispatch($action, function (): void {
    AdminResponder::jsonError('Unknown action', 400);
});
