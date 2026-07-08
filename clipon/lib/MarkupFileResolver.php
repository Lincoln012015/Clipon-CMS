<?php

class MarkupFileResolver {
    private const EDITABLE_EXTENSIONS = ['html', 'htm', 'php'];

    public static function normalizeFileName(string $file): string {
        $file = str_replace('\\', '/', $file);
        return ltrim($file, '/');
    }

    public static function resolveEditableFile(string $file): ?string {
        $file = self::normalizeFileName($file);
        if ($file === '' || strpos($file, '..') !== false) {
            return null;
        }

        $parts = array_values(array_filter(explode('/', $file), static fn($part) => $part !== ''));
        $reserved = ['assets', 'bin', 'clipon', 'config', 'content', 'data', 'docs', 'logs', 'modules', 'node_modules', 'templates', 'vendor'];
        if (isset($parts[0]) && in_array($parts[0], $reserved, true)) {
            return null;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EDITABLE_EXTENSIONS, true)) {
            return null;
        }

        $root = realpath(C_ROOT);
        $path = realpath(C_ROOT . '/' . $file);
        if ($root === false || $path === false || !is_file($path)) {
            return null;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($path, $rootPrefix) !== 0) {
            return null;
        }

        return $path;
    }

    public static function listEditableTemplates(): array {
        $templateRoot = self::templateRoot();
        if ($templateRoot === null || !is_dir($templateRoot)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }

            $ext = strtolower($item->getExtension());
            if (!in_array($ext, self::EDITABLE_EXTENSIONS, true)) {
                continue;
            }

            $path = $item->getRealPath();
            if ($path === false) {
                continue;
            }

            $templatePrefix = rtrim($templateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($path, $templatePrefix) !== 0) {
                continue;
            }

            $files[] = str_replace('\\', '/', substr($path, strlen($templateRoot) + 1));
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    public static function resolveTemplateFile(string $file): ?string {
        $rawFile = str_replace('\\', '/', $file);
        if ($rawFile === '' || str_starts_with($rawFile, '/') || preg_match('/^[a-z]:\//i', $rawFile)) {
            return null;
        }

        $file = self::normalizeFileName($rawFile);
        if ($file === '' || strpos($file, '..') !== false) {
            return null;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EDITABLE_EXTENSIONS, true)) {
            return null;
        }

        $templateRoot = self::templateRoot();
        if ($templateRoot === null) {
            return null;
        }

        $path = realpath($templateRoot . '/' . $file);
        if ($path === false || !is_file($path)) {
            return null;
        }

        $templatePrefix = rtrim($templateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($path, $templatePrefix) !== 0) {
            return null;
        }

        return $path;
    }

    private static function templateRoot(): ?string {
        $root = defined('C_TEMPLATES_PATH') ? C_TEMPLATES_PATH : (defined('C_ROOT') ? C_ROOT . '/templates' : '');
        if ($root === '') {
            return null;
        }

        $real = realpath($root);
        return $real === false ? null : $real;
    }
}
