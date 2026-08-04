<?php

declare(strict_types=1);

class CoreUpdateInstaller
{
    private const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;
    private const MAX_EXTRACTED_BYTES = 256 * 1024 * 1024;
    private const MAX_FILES = 5000;

    private string $root;
    private string $coreDir;
    private string $backupRoot;

    public function __construct(?string $root = null, ?string $coreDir = null, ?string $backupRoot = null)
    {
        $this->root = rtrim($root ?? (string)C_ROOT, '/\\');
        $this->coreDir = rtrim($coreDir ?? (string)C_CORE_DIR, '/\\');
        $this->backupRoot = rtrim($backupRoot ?? ($this->root . '/.clipon_backups/core_updates'), '/\\');
    }

    public function install(array $updateInfo): array
    {
        $url = trim((string)($updateInfo['url'] ?? $updateInfo['package_url'] ?? ''));
        $checksum = strtolower(trim((string)($updateInfo['checksum_sha256'] ?? '')));
        $version = trim((string)($updateInfo['version'] ?? ''));

        if (!$this->isAllowedDownloadUrl($url)) {
            return $this->failure('invalid_package_url');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            return $this->failure('missing_or_invalid_checksum');
        }
        if (!$this->isStrictVersion($version)) {
            return $this->failure('invalid_version');
        }

        $workDir = $this->createWorkDir();
        if ($workDir === null) {
            return $this->failure('backup_directory_unavailable');
        }

        $archive = $workDir . '/package.zip';
        try {
            $this->download($url, $archive);
            return $this->installFromArchive($archive, $checksum, $version, $workDir);
        } catch (Throwable $e) {
            $this->removeTree($workDir);
            return $this->failure($e->getMessage());
        }
    }

    public function installFromArchive(string $archive, string $checksum, string $version, ?string $workDir = null): array
    {
        $checksum = strtolower(trim($checksum));
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            return $this->failure('missing_or_invalid_checksum');
        }
        if (!$this->isStrictVersion($version)) {
            return $this->failure('invalid_version');
        }
        $installedVersion = $this->installedVersion();
        if ($installedVersion !== null && version_compare($version, $installedVersion, '<=')) {
            return $this->failure('version_not_newer');
        }
        if (!is_file($archive) || !is_readable($archive)) {
            return $this->failure('archive_unreadable');
        }
        if ((int)filesize($archive) > self::MAX_ARCHIVE_BYTES) {
            return $this->failure('archive_too_large');
        }

        $actualChecksum = hash_file('sha256', $archive);
        if (!is_string($actualChecksum) || !hash_equals($checksum, strtolower($actualChecksum))) {
            return $this->failure('checksum_mismatch');
        }

        $ownsWorkDir = $workDir === null;
        $workDir = $workDir ?? $this->createWorkDir();
        if ($workDir === null) {
            return $this->failure('backup_directory_unavailable');
        }

        $stage = $workDir . '/stage';
        try {
            $this->extractSafely($archive, $stage);
            $this->validateStagedRelease($stage, $version);
            $backupDir = $this->applyStagedRelease($stage, $version);
            $this->removeTree($workDir);
            return [
                'success' => true,
                'version' => $version,
                'checksum_sha256' => $checksum,
                'backup_path' => $backupDir,
            ];
        } catch (Throwable $e) {
            if ($ownsWorkDir || is_dir($workDir)) {
                $this->removeTree($workDir);
            }
            return $this->failure($e->getMessage());
        }
    }

    private function download(string $url, string $destination): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl_unavailable');
        }

        $output = @fopen($destination, 'wb');
        if ($output === false) {
            throw new RuntimeException('archive_write_failed');
        }

        $written = 0;
        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($output);
            throw new RuntimeException('download_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Clipon-CMS-Core-Updater/1.0',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use ($output, &$written, &$tooLarge): int {
                $length = strlen($data);
                $written += $length;
                if ($written > self::MAX_ARCHIVE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $result = fwrite($output, $data);
                return $result === false ? 0 : $result;
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        fclose($output);

        if ($tooLarge) {
            @unlink($destination);
            throw new RuntimeException('archive_too_large');
        }
        if ($ok !== true || $status < 200 || $status >= 300 || !$this->isAllowedDownloadUrl($finalUrl)) {
            @unlink($destination);
            throw new RuntimeException('download_failed');
        }
    }

    private function extractSafely(string $archive, string $stage): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('zip_extension_unavailable');
        }
        if (!@mkdir($stage, 0700, true) && !is_dir($stage)) {
            throw new RuntimeException('staging_directory_unavailable');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('invalid_zip');
        }

        $totalBytes = 0;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('invalid_archive_file_count');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = is_array($stat) ? (string)($stat['name'] ?? '') : '';
                $normalized = str_replace('\\', '/', $name);
                if (!$this->isSafeArchivePath($normalized)) {
                    throw new RuntimeException('unsafe_archive_path');
                }

                $isDirectory = substr($normalized, -1) === '/';
                $destination = $stage . '/' . rtrim($normalized, '/');
                if ($isDirectory) {
                    if (!is_dir($destination) && !@mkdir($destination, 0700, true)) {
                        throw new RuntimeException('archive_extract_failed');
                    }
                    continue;
                }

                $size = (int)($stat['size'] ?? 0);
                $totalBytes += $size;
                if ($size < 0 || $totalBytes > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('extracted_content_too_large');
                }
                $parent = dirname($destination);
                if (!is_dir($parent) && !@mkdir($parent, 0700, true)) {
                    throw new RuntimeException('archive_extract_failed');
                }

                $input = $zip->getStream($name);
                $output = @fopen($destination, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new RuntimeException('archive_extract_failed');
                }
                $copied = stream_copy_to_stream($input, $output, self::MAX_EXTRACTED_BYTES + 1);
                fclose($input);
                fclose($output);
                if ($copied === false || $copied !== $size) {
                    throw new RuntimeException('archive_extract_failed');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function validateStagedRelease(string $stage, string $expectedVersion): void
    {
        if (!is_file($stage . '/index.php') || !is_dir($stage . '/clipon')) {
            throw new RuntimeException('invalid_release_structure');
        }
        $versionFile = $stage . '/clipon/config/version.php';
        $manifestFile = $stage . '/clipon/config/integrity_manifest.php';
        if (!is_file($versionFile) || !is_file($manifestFile)) {
            throw new RuntimeException('release_metadata_missing');
        }

        $versionData = require $versionFile;
        if (!is_array($versionData) || (string)($versionData['version'] ?? '') !== $expectedVersion) {
            throw new RuntimeException('package_version_mismatch');
        }

        $manifest = require $manifestFile;
        if (!is_array($manifest) || ($manifest['algorithm'] ?? '') !== 'sha256') {
            throw new RuntimeException('invalid_integrity_manifest');
        }
        $required = $manifest['required'] ?? null;
        if (!is_array($required) || $required === []) {
            throw new RuntimeException('invalid_integrity_manifest');
        }
        foreach (['required', 'optional'] as $section) {
            foreach ((array)($manifest[$section] ?? []) as $relative => $hash) {
                $relative = str_replace('\\', '/', (string)$relative);
                if (!$this->isSafeArchivePath($relative) || !preg_match('/^[a-f0-9]{64}$/i', (string)$hash)) {
                    throw new RuntimeException('invalid_integrity_manifest');
                }
                $file = $stage . '/' . $relative;
                if ($section === 'optional' && !is_file($file)) {
                    continue;
                }
                if (!is_file($file) || !hash_equals(strtolower((string)$hash), strtolower((string)hash_file('sha256', $file)))) {
                    throw new RuntimeException('integrity_check_failed');
                }
            }
        }
    }

    private function applyStagedRelease(string $stage, string $version): string
    {
        if (!is_dir($this->coreDir) || !is_writable(dirname($this->coreDir)) || !is_writable($this->root)) {
            throw new RuntimeException('core_not_writable');
        }

        $this->normalizeReleasePermissions($stage);

        $backupDir = $this->backupRoot . '/backup-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version) . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!@mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new RuntimeException('backup_directory_unavailable');
        }

        $oldCore = $backupDir . '/clipon';
        $oldRootFiles = [];
        $newCoreInstalled = false;
        try {
            foreach (['index.php', '.htaccess'] as $rootFile) {
                $current = $this->root . '/' . $rootFile;
                if (is_file($current)) {
                    if (!@copy($current, $backupDir . '/' . $rootFile)) {
                        throw new RuntimeException('backup_failed');
                    }
                    $oldRootFiles[] = $rootFile;
                }
            }

            if (!@rename($this->coreDir, $oldCore)) {
                throw new RuntimeException('backup_failed');
            }
            if (!@rename($stage . '/clipon', $this->coreDir)) {
                @rename($oldCore, $this->coreDir);
                throw new RuntimeException('core_replace_failed');
            }
            $newCoreInstalled = true;

            foreach (['index.php', '.htaccess'] as $rootFile) {
                $source = $stage . '/' . $rootFile;
                if (!is_file($source)) {
                    continue;
                }
                $temp = $this->root . '/.clipon-update-' . $rootFile . '-' . bin2hex(random_bytes(4));
                if (!@copy($source, $temp) || !@chmod($temp, 0644) || !@rename($temp, $this->root . '/' . $rootFile)) {
                    @unlink($temp);
                    throw new RuntimeException('root_file_replace_failed');
                }
            }
        } catch (Throwable $e) {
            if ($newCoreInstalled) {
                $failedCore = $backupDir . '/failed-new-core';
                @rename($this->coreDir, $failedCore);
                @rename($oldCore, $this->coreDir);
            }
            foreach (['index.php', '.htaccess'] as $rootFile) {
                $backup = $backupDir . '/' . $rootFile;
                if (is_file($backup)) {
                    @copy($backup, $this->root . '/' . $rootFile);
                } elseif (!in_array($rootFile, $oldRootFiles, true)) {
                    @unlink($this->root . '/' . $rootFile);
                }
            }
            throw $e;
        }

        return $backupDir;
    }

    private function normalizeReleasePermissions(string $stage): void
    {
        $core = $stage . '/clipon';
        if (!is_dir($core) || !$this->normalizeTreePermissions($core)) {
            throw new RuntimeException('permission_normalization_failed');
        }

        foreach (['index.php', '.htaccess'] as $rootFile) {
            $path = $stage . '/' . $rootFile;
            if (is_file($path) && !@chmod($path, 0644)) {
                throw new RuntimeException('permission_normalization_failed');
            }
        }
    }

    private function normalizeTreePermissions(string $path): bool
    {
        if (is_link($path) || !is_dir($path) || !@chmod($path, 0755)) {
            return false;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_link($child)) {
                return false;
            }
            if (is_dir($child)) {
                if (!$this->normalizeTreePermissions($child)) {
                    return false;
                }
                continue;
            }
            if (!is_file($child) || !@chmod($child, 0644)) {
                return false;
            }
        }

        return true;
    }

    private function createWorkDir(): ?string
    {
        if (!is_dir($this->backupRoot) && !@mkdir($this->backupRoot, 0700, true)) {
            return null;
        }
        $path = $this->backupRoot . '/work-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(6));
        return @mkdir($path, 0700, true) ? $path : null;
    }

    private function isSafeArchivePath(string $path): bool
    {
        if ($path === '' || strpos($path, "\0") !== false || $path[0] === '/' || preg_match('#^[A-Za-z]:/#', $path)) {
            return false;
        }
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    private function isAllowedDownloadUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }
        $host = strtolower(trim((string)$parts['host'], '[]'));
        return $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private function isStrictVersion(string $version): bool
    {
        return preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $version) === 1;
    }

    private function installedVersion(): ?string
    {
        $file = $this->coreDir . '/config/version.php';
        if (!is_file($file)) {
            return null;
        }
        $data = require $file;
        $version = is_array($data) ? trim((string)($data['version'] ?? '')) : '';
        return $this->isStrictVersion($version) ? $version : null;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if (!is_array($items)) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $this->removeTree($path . '/' . $item);
        }
        @rmdir($path);
    }

    private function failure(string $error): array
    {
        return ['success' => false, 'error' => $error];
    }
}
