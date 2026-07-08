<?php

require_once __DIR__ . '/Sanitizer.php';

class AssetUrlNormalizer {
    public static function normalizeHtml(string $html, array $options = []): string {
        if ($html === '') {
            return $html;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (!$loaded) {
            return $html;
        }

        self::normalizeDocument($dom, $options);
        self::decodeEscapedNumericEntities($dom);

        $result = $dom->saveHTML();
        $result = str_replace('<?xml encoding="utf-8" ?>', '', $result);
        return mb_convert_encoding($result, 'UTF-8', 'HTML-ENTITIES');
    }

    public static function decodeEscapedNumericEntities(DOMNode $node): void {
        if ($node->nodeType === XML_TEXT_NODE && is_string($node->nodeValue) && preg_match('/&#(?:x[0-9a-f]+|\d+);/i', $node->nodeValue)) {
            $node->nodeValue = Sanitizer::decodeHtmlEntities($node->nodeValue);
            return;
        }

        if ($node->nodeType === XML_ELEMENT_NODE && $node instanceof DOMElement && $node->hasAttributes()) {
            foreach ($node->attributes as $attribute) {
                if (preg_match('/&#(?:x[0-9a-f]+|\d+);/i', $attribute->value)) {
                    $attribute->value = Sanitizer::decodeHtmlEntities($attribute->value);
                }
            }
        }

        foreach ($node->childNodes as $child) {
            self::decodeEscapedNumericEntities($child);
        }
    }

    public static function normalizeDocument(DOMDocument $dom, array $options = []): void {
        $basePath = self::normalizeBasePath((string)($options['base_path'] ?? ''));
        $assetRootDirs = self::normalizeAssetRootDirs($options['asset_root_dirs'] ?? []);
        $tags = $options['tags'] ?? self::defaultAssetTags();
        $processLinks = (bool)($options['process_links'] ?? false);

        foreach ($tags as $tagName => $attrNames) {
            if ($tagName === 'a' && !$processLinks) {
                continue;
            }

            $attrNames = is_array($attrNames) ? $attrNames : [$attrNames];
            foreach ($dom->getElementsByTagName($tagName) as $node) {
                foreach ($attrNames as $attrName) {
                    if (!$node->hasAttribute($attrName)) {
                        continue;
                    }

                    $value = $node->getAttribute($attrName);
                    $normalized = self::normalizeAttributeUrl($value, $tagName, $basePath, $assetRootDirs, $options);
                    if ($normalized !== $value) {
                        $node->setAttribute($attrName, $normalized);
                    }
                }
            }
        }

        if (!empty($options['process_srcset'])) {
            foreach (['img', 'source'] as $tagName) {
                foreach ($dom->getElementsByTagName($tagName) as $node) {
                    if (!$node->hasAttribute('srcset')) {
                        continue;
                    }

                    $value = $node->getAttribute('srcset');
                    $normalized = self::normalizeSrcset($value, $basePath, $assetRootDirs, $options);
                    if ($normalized !== $value) {
                        $node->setAttribute('srcset', $normalized);
                    }
                }
            }
        }
    }

    public static function defaultAssetTags(): array {
        return [
            'img' => ['src'],
            'source' => ['src'],
            'script' => ['src'],
            'video' => ['src', 'poster'],
            'audio' => ['src'],
            'track' => ['src'],
            'iframe' => ['src'],
            'link' => ['href'],
        ];
    }

    private static function normalizeAttributeUrl(string $url, string $tagName, string $basePath, array $assetRootDirs, array $options): string {
        $url = trim($url);
        if ($url === '' || self::shouldSkipUrl($url)) {
            return $url;
        }

        if ($tagName === 'a' && !empty($options['rewrite_page_links'])) {
            return self::normalizePageLink($url, $basePath, $options);
        }

        return self::normalizeAssetUrl($url, $basePath, $assetRootDirs, (bool)($options['assets_only'] ?? true));
    }

    private static function normalizeSrcset(string $srcset, string $basePath, array $assetRootDirs, array $options): string {
        $items = array_map('trim', explode(',', $srcset));
        $normalized = [];

        foreach ($items as $item) {
            if ($item === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $item, 2);
            $url = $parts[0] ?? '';
            $descriptor = $parts[1] ?? '';
            $url = self::normalizeAssetUrl($url, $basePath, $assetRootDirs, (bool)($options['assets_only'] ?? true));
            $normalized[] = trim($url . ($descriptor !== '' ? ' ' . $descriptor : ''));
        }

        return implode(', ', $normalized);
    }

    private static function normalizeAssetUrl(string $url, string $basePath, array $assetRootDirs, bool $assetsOnly): string {
        if ($url === '' || self::shouldSkipUrl($url)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = isset($parts['path']) ? (string)$parts['path'] : $url;
        if ($path === '' || $path[0] === '/') {
            return $url;
        }

        $targetPath = null;
        foreach ($assetRootDirs as $dirName) {
            if ($path === $dirName || strpos($path, $dirName . '/') === 0) {
                $targetPath = '/assets/' . ltrim($path, '/');
                break;
            }
        }

        if ($targetPath === null) {
            if ($assetsOnly && !self::looksLikeAssetUrl($path)) {
                return $url;
            }
            $targetPath = '/' . ltrim($path, '/');
        }

        return self::withBaseAndSuffix($targetPath, $basePath, $parts);
    }

    private static function normalizePageLink(string $url, string $basePath, array $options): string {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? (string)$parts['path'] : $url;
        if ($path === '' || $path[0] === '/') {
            return $url;
        }

        $pageExtensions = $options['page_extensions'] ?? ['php', 'html', 'htm'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, $pageExtensions, true)) {
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $dirname = dirname($path);

            if ($filename === 'index') {
                $targetPath = ($dirname === '.' || $dirname === '/') ? '/' : $dirname;
            } else {
                $targetPath = ($dirname === '.' || $dirname === '/') ? '/' . $filename : $dirname . '/' . $filename;
            }
        } else {
            $targetPath = '/' . ltrim($path, '/');
        }

        return self::withBaseAndSuffix($targetPath, $basePath, $parts);
    }

    private static function withBaseAndSuffix(string $path, string $basePath, array $parts): string {
        $normalized = $basePath . '/' . ltrim($path, '/');
        $normalized = preg_replace('#/+#', '/', $normalized);
        if ($path === '/') {
            $normalized = $basePath !== '' ? $basePath . '/' : '/';
        }

        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }

    private static function shouldSkipUrl(string $url): bool {
        return (bool)preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|/|#)~i', $url);
    }

    private static function looksLikeAssetUrl(string $url): bool {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?: $url);
        if (preg_match('~^(?:assets|uploads|media|images|img|css|js|fonts?)/~i', $path)) {
            return true;
        }

        return (bool)preg_match('~\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|otf|eot|mp4|webm|ogg|mp3|wav|vtt)(?:$|[?#])~i', $path);
    }

    private static function normalizeBasePath(string $basePath): string {
        $basePath = str_replace('\\', '/', trim($basePath));
        if ($basePath === '.' || $basePath === './' || $basePath === '/') {
            return '';
        }

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && $basePath[0] !== '/') {
            $basePath = '/' . ltrim($basePath, '/');
        }

        return $basePath;
    }

    private static function normalizeAssetRootDirs($assetRootDirs): array {
        if (!is_array($assetRootDirs)) {
            return [];
        }

        $normalized = [];
        foreach ($assetRootDirs as $dirName) {
            $dirName = trim(str_replace('\\', '/', (string)$dirName), '/');
            if ($dirName !== '') {
                $normalized[] = $dirName;
            }
        }

        return array_values(array_unique($normalized));
    }
}
