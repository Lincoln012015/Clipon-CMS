<?php

class MediaService
{
    private string $mediaRoot;
    private string $publicPrefix = '/assets/';
    private string $metaFile;

    public function __construct(?string $mediaRoot = null, ?string $metaFile = null)
    {
        $base = $mediaRoot ?? (C_ROOT . '/assets');
        $this->mediaRoot = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->metaFile = $metaFile ?? (C_CONFIG_PATH . '/media_meta.php');

        if (!is_dir($this->mediaRoot)) {
            mkdir($this->mediaRoot, 0775, true);
        }
    }

    public function sanitizeDir(string $dir): string
    {
        $dir = str_replace(['..', '\\'], '', $dir);
        return trim($dir, '/');
    }

    public function ensureExistingDir(string $dir): string
    {
        $safe = $this->sanitizeDir($dir);
        $path = $this->mediaRoot . ($safe !== '' ? $safe . DIRECTORY_SEPARATOR : '');

        if (!is_dir($path)) {
            return '';
        }

        return $safe;
    }

    public function listDirectories(string $currentDir): array
    {
        $path = $this->getCurrentPath($currentDir);
        $directories = [];

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($path . $item)) {
                $directories[] = $item;
            }
        }

        return $directories;
    }

    public function listMediaFiles(string $currentDir): array
    {
        $path = $this->getCurrentPath($currentDir);
        $files = glob($path . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];

        usort($files, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return $files;
    }

    public function loadMediaMeta(): array
    {
        if (!file_exists($this->metaFile)) {
            return [];
        }

        return read_json_file($this->metaFile);
    }

    public function saveMediaAlt(string $filename, string $alt, string $lang): array
    {
        if ($filename === '') {
            return ['success' => false, 'error' => __('error_filename_required')];
        }

        if (preg_match('/[\/\\\\]/', $filename)) {
            return ['success' => false, 'error' => __('error_invalid_filename')];
        }

        $mediaMeta = $this->loadMediaMeta();

        if (!isset($mediaMeta[$filename])) {
            $mediaMeta[$filename] = [];
        }

        if (!isset($mediaMeta[$filename]['alt']) || !is_array($mediaMeta[$filename]['alt'])) {
            $oldValue = is_string($mediaMeta[$filename]['alt'] ?? null) ? $mediaMeta[$filename]['alt'] : '';
            $mediaMeta[$filename]['alt'] = [];
            if ($oldValue !== '') {
                $mediaMeta[$filename]['alt']['uk'] = $oldValue;
            }
        }

        $mediaMeta[$filename]['alt'][$lang] = $alt;

        if (write_json_file($this->metaFile, $mediaMeta)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => __('error_save_metadata')];
    }

    public function getAllDirectories(): array
    {
        $rootReal = realpath($this->mediaRoot);
        $dirs = [''];

        if ($rootReal === false) {
            return $dirs;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                continue;
            }

            $path = $file->getPathname();
            $rel = ltrim(str_replace($rootReal, '', $path), DIRECTORY_SEPARATOR);
            $dirs[] = $rel;
        }

        sort($dirs);
        return $dirs;
    }

    public function getCurrentPath(string $currentDir): string
    {
        $safe = $this->sanitizeDir($currentDir);
        return $this->mediaRoot . ($safe !== '' ? $safe . DIRECTORY_SEPARATOR : '');
    }

    public function getUploadsRoot(): string
    {
        return $this->getMediaRoot();
    }

    public function getMediaRoot(): string
    {
        return $this->mediaRoot;
    }

    public function getPublicPath(string $relativePath): string
    {
        return $this->publicPrefix . ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}
