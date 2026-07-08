<?php

class MediaManager
{
    private string $mediaRoot;
    private string $mediaPublicPrefix = '/assets/';

    public function __construct()
    {
        $root = C_ROOT . '/assets';
        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }

        $realRoot = realpath($root);
        if ($realRoot === false) {
            throw new RuntimeException(__('error_cannot_create_upload_dir'));
        }

        $this->mediaRoot = $realRoot . DIRECTORY_SEPARATOR;
    }

    /**
     * Sanitize and validate directory path
     */
    private function getSafePath(string $dir): string
    {
        $dir = str_replace('\\', '/', trim($dir));
        $dir = preg_replace('#/+#', '/', $dir);
        $dir = trim((string)$dir, '/');

        if ($dir === '') {
            return '';
        }

        $parts = explode('/', $dir);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new Exception(__('error_invalid_parameters'));
            }
        }

        return implode('/', $parts);
    }

    /**
     * Get full absolute path for a directory or file within public assets
     */
    private function getFullPath(string $relativeDir, string $name = ''): string
    {
        return $this->resolveAssetPath($relativeDir, $name);
    }

    private function resolveAssetPath(string $relativeDir, string $name = '', bool $mustExist = false, bool $allowRoot = true): string
    {
        $safeDir = $this->getSafePath($relativeDir);
        $name = trim($name);

        if ($name !== '' && (preg_match('/[\/\\\\]/', $name) || $name === '.' || $name === '..' || strpos($name, '..') !== false)) {
            throw new Exception(__('error_invalid_filename'));
        }

        if (!$allowRoot && $safeDir === '' && $name === '') {
            throw new Exception(__('error_invalid_parameters'));
        }

        $relative = $safeDir;
        if ($name !== '') {
            $relative = $relative !== '' ? ($relative . '/' . $name) : $name;
        }

        $path = $this->mediaRoot . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($name === '') {
            $path .= $relative !== '' ? DIRECTORY_SEPARATOR : '';
        }

        if ($mustExist) {
            $real = realpath($path);
            if ($real === false || !$this->isPathInsideMediaRoot($real)) {
                throw new Exception(__('error_invalid_parameters'));
            }

            return $real;
        }

        $parent = $name !== '' ? dirname($path) : rtrim($path, DIRECTORY_SEPARATOR);
        $parentReal = realpath($parent);
        if ($parentReal === false || !$this->isPathInsideMediaRoot($parentReal)) {
            throw new Exception(__('error_invalid_parameters'));
        }

        $normalized = $parentReal;
        if ($name !== '') {
            $normalized .= DIRECTORY_SEPARATOR . $name;
        } elseif ($relative !== '') {
            $normalized .= DIRECTORY_SEPARATOR;
        } else {
            $normalized = $this->mediaRoot;
        }

        if (!$this->isPathInsideMediaRoot($normalized)) {
            throw new Exception(__('error_invalid_parameters'));
        }

        return $normalized;
    }

    private function isPathInsideMediaRoot(string $path): bool
    {
        $root = rtrim($this->mediaRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalized = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return strpos($normalized, $root) === 0;
    }

    /**
     * Handle file upload
     */
    public function upload(array $file, string $targetDir): array
    {
        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }

        $safeDir = $this->getSafePath($targetDir);
        $uploadDir = $this->getFullPath($safeDir);

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                throw new Exception(__('error_cannot_create_upload_dir'));
            }
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception(__('error_invalid_file_type'));
        }

        if (!$this->isAllowedUploadedMime($file['tmp_name'] ?? '', $fileExtension)) {
            throw new Exception(__('error_invalid_file_type'));
        }

        $fileName = $this->sanitizeFileName($file['name']);
        $destPath = $uploadDir . $fileName;

        // If file exists, append timestamp
        if (file_exists($destPath)) {
            $nameOnly = pathinfo($fileName, PATHINFO_FILENAME);
            $fileName = $nameOnly . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $fileName;
        }

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $relativePath = $safeDir ? ($safeDir . '/' . $fileName) : $fileName;
            return ['success' => true, 'filename' => $fileName, 'relativePath' => $relativePath];
        }

        throw new Exception(__('error_upload_generic'));
    }

    /**
     * Delete file
     */
    public function deleteFile(string $filename, string $currentDir): bool
    {
        $filePath = $this->getFullPath($currentDir, $filename);

        if (!$filePath || !file_exists($filePath) || is_dir($filePath)) {
            throw new Exception(__('error_file_not_found'));
        }

        if (unlink($filePath)) {
            return true;
        }

        throw new Exception(__('error_delete_file'));
    }

    /**
     * Bulk move items (files or folders)
     */
    public function bulkMove(array $items, string $currentDir, string $targetDir): array
    {
        $results = ['moved' => [], 'errors' => []];
        $safeCurrentDir = $this->getSafePath($currentDir);
        $safeTargetDir = $this->getSafePath($targetDir);
        $targetPath = $this->getFullPath($safeTargetDir);

        if (!is_dir($targetPath)) {
            throw new Exception(__('error_target_folder_not_found'));
        }

        foreach ($items as $it) {
            $name = $it['name'] ?? '';
            $type = $it['type'] ?? 'file';

            try {
                $source = $this->resolveAssetPath($safeCurrentDir, $name, true, false);
                $dest = $this->resolveAssetPath($safeTargetDir, $name, false, false);

                if (!file_exists($source)) {
                    throw new Exception(__('error_invalid_source'));
                }

                if ($type === 'folder') {
                    $sourceReal = realpath($source);
                    $targetReal = realpath($targetPath);
                    if ($sourceReal === false || $targetReal === false || strpos(rtrim($targetReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, rtrim($sourceReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
                        throw new Exception(__('error_cannot_move_folder_into_itself'));
                    }
                }

                if (file_exists($dest)) {
                    throw new Exception(__('error_target_exists'));
                }

                if (rename($source, $dest)) {
                    $oldRel = $safeCurrentDir ? ($safeCurrentDir . '/' . $name) : $name;
                    $newRel = $safeTargetDir ? ($safeTargetDir . '/' . $name) : $name;

                    if ($type === 'folder') {
                        $this->updateMediaReferencesByPrefix($oldRel, $newRel);
                    } else {
                        $this->updateMediaReferencesForFile($oldRel, $newRel);
                    }

                    $results['moved'][] = $name;
                } else {
                    throw new Exception(__('error_move_item'));
                }
            } catch (Exception $e) {
                $results['errors'][] = ['name' => $name, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Move single file/folder item.
     */
    public function moveItem(string $name, string $type, string $currentDir, string $targetDir): bool
    {
        $result = $this->bulkMove([
            ['name' => $name, 'type' => $type ?: 'file']
        ], $currentDir, $targetDir);

        if (!empty($result['moved'])) {
            return true;
        }

        $error = $result['errors'][0]['error'] ?? __('error_move_item');
        throw new Exception($error);
    }

    /**
     * Create folder
     */
    public function createFolder(string $name, string $currentDir): bool
    {
        $name = preg_replace('/[^\p{L}0-9._\-\s!;:(),]/u', '', $name);
        if (empty($name)) {
            throw new Exception(__('error_folder_name_required'));
        }

        $targetPath = $this->resolveAssetPath($currentDir, $name, false, false);

        if (file_exists($targetPath)) {
            throw new Exception(__('error_folder_exists'));
        }

        if (mkdir($targetPath, 0755, true)) {
            return true;
        }

        throw new Exception(__('error_create_folder'));
    }

    /**
     * Rename folder
     */
    public function renameFolder(string $oldName, string $newName, string $currentDir): bool
    {
        $oldName = preg_replace('/[^\p{L}0-9._\-\s!;:(),]/u', '', $oldName);
        $newName = preg_replace('/[^\p{L}0-9._\-\s!;:(),]/u', '', $newName);

        if ($oldName === '' || $newName === '') {
            throw new Exception(__('error_invalid_parameters'));
        }

        $oldPath = $this->resolveAssetPath($currentDir, $oldName, true, false);
        $newPath = $this->resolveAssetPath($currentDir, $newName, false, false);

        if (!is_dir($oldPath)) {
            throw new Exception(__('error_folder_not_found'));
        }

        if (file_exists($newPath)) {
            throw new Exception(__('error_folder_exists'));
        }

        if (rename($oldPath, $newPath)) {
            $safeCurrentDir = $this->getSafePath($currentDir);
            $oldRel = $safeCurrentDir ? ($safeCurrentDir . '/' . $oldName) : $oldName;
            $newRel = $safeCurrentDir ? ($safeCurrentDir . '/' . $newName) : $newName;
            $this->updateMediaReferencesByPrefix($oldRel, $newRel);
            return true;
        }

        throw new Exception(__('error_rename_folder'));
    }

    /**
     * Delete folder recursively
     */
    public function deleteFolder(string $name, string $currentDir): bool
    {
        $path = $this->resolveAssetPath($currentDir, $name, true, false);

        if (!is_dir($path)) {
            throw new Exception(__('error_folder_not_found'));
        }

        if ($this->rrmdir($path)) {
            return true;
        }

        throw new Exception(__('error_delete_folder'));
    }

    private function rrmdir($dir): bool
    {
        $realDir = realpath($dir);
        if ($realDir === false || !$this->isPathInsideMediaRoot($realDir)) {
            return false;
        }

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $child = $dir . DIRECTORY_SEPARATOR . $object;
                    $realChild = realpath($child);
                    if ($realChild === false || !$this->isPathInsideMediaRoot($realChild)) {
                        return false;
                    }
                    if (is_dir($child) && !is_link($child))
                        $this->rrmdir($child);
                    else
                        unlink($child);
                }
            }
            return rmdir($dir);
        }
        return false;
    }

    private function sanitizeFileName(string $filename): string
    {
        return preg_replace('/[^\p{L}0-9._\-]/u', '_', $filename);
    }

    private function isAllowedUploadedMime(string $tmpPath, string $extension): bool
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return false;
        }

        $allowed = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'mp4' => ['video/mp4', 'application/mp4'],
        ];

        if (!isset($allowed[$extension])) {
            return false;
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (is_string($detected)) {
                    $mime = strtolower($detected);
                }
            }
        }

        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = mime_content_type($tmpPath);
            if (is_string($detected)) {
                $mime = strtolower($detected);
            }
        }

        return $mime !== '' && in_array($mime, $allowed[$extension], true);
    }

    private function updateMediaReferencesForFile(string $oldRelativePath, string $newRelativePath): void
    {
        $oldPath = $this->mediaPublicPrefix . ltrim(str_replace('\\', '/', $oldRelativePath), '/');
        $newPath = $this->mediaPublicPrefix . ltrim(str_replace('\\', '/', $newRelativePath), '/');
        $this->replaceInContentFiles($oldPath, $newPath);
    }

    private function updateMediaReferencesByPrefix(string $oldRelativePrefix, string $newRelativePrefix): void
    {
        $oldPrefix = rtrim($this->mediaPublicPrefix . ltrim(str_replace('\\', '/', $oldRelativePrefix), '/'), '/') . '/';
        $newPrefix = rtrim($this->mediaPublicPrefix . ltrim(str_replace('\\', '/', $newRelativePrefix), '/'), '/') . '/';
        $this->replaceInContentFiles($oldPrefix, $newPrefix);
    }

    private function replaceInContentFiles(string $search, string $replace): void
    {
        if ($search === $replace || $search === '') {
            return;
        }

        $variants = [
            [$search, $replace],
            [str_replace('/', '\\/', $search), str_replace('/', '\\/', $replace)],
        ];

        $roots = [];
        if (defined('C_CONTENT_PATH') && is_dir(C_CONTENT_PATH)) {
            $roots[] = C_CONTENT_PATH;
        }
        if (defined('C_CONFIG_PATH') && is_dir(C_CONFIG_PATH)) {
            $roots[] = C_CONFIG_PATH;
        }

        $allowedExtensions = ['php', 'html', 'htm', 'json', 'md', 'txt', 'xml', 'js', 'css'];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $content = @file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                $updated = $content;
                foreach ($variants as $variant) {
                    [$s, $r] = $variant;
                    if ($s !== '' && strpos($updated, $s) !== false) {
                        $updated = str_replace($s, $r, $updated);
                    }
                }

                if ($updated !== $content) {
                    @file_put_contents($path, $updated, LOCK_EX);
                }
            }
        }
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('error_file_too_large');
            case UPLOAD_ERR_PARTIAL:
                return __('error_upload_partial');
            case UPLOAD_ERR_NO_FILE:
                return __('error_no_file_uploaded');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
            default:
                return __('error_upload_generic');
        }
    }
}
