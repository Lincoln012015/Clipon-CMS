<?php

require_once __DIR__ . '/Sanitizer.php';

class MarkupHtmlSanitizer {
    public static function sanitizeMarkupHtml(string $html): string {
        $html = self::removeCommonPickerArtifacts($html, 'clipon-markup-picker', 'markup_picker.js', 'clipon-markup-picker-config');
        $html = self::cleanBodyTag($html, ['88px']);
        $html = self::removeKnownInjectedNodes($html);
        $html = self::removeBlogAttributes($html);
        $html = self::removeShortcodePreviewClasses($html);
        return self::removePickerClasses($html, ['clipon-markup-']);
    }

    private static function removeCommonPickerArtifacts(string $html, string $idPrefix, string $scriptName, string $configId): string {
        if ($html === '') {
            return $html;
        }

        $scriptName = preg_quote($scriptName, '/');
        $idPrefix = preg_quote($idPrefix, '/');
        $configId = preg_quote($configId, '/');

        $html = preg_replace('/<script\b[^>]*\bsrc=["\'][^"\']*' . $scriptName . '[^"\']*["\'][^>]*>\s*<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*\bid=["\']' . $configId . '["\'][^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*\bid=["\']' . $idPrefix . '-style["\'][^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<base\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<div\b[^>]*\bid=["\']' . $idPrefix . '-bar["\'][^>]*>.*?<div\b[^>]*\bid=["\']' . $idPrefix . '-status["\'][^>]*>.*?<\/div>\s*<\/div>/is', '', $html) ?? $html;
        return preg_replace('/<div\b[^>]*\bid=["\']' . $idPrefix . '-bar["\'][^>]*>.*?<\/div>/is', '', $html) ?? $html;
    }

    private static function cleanBodyTag(string $html, array $pickerPaddingValues): string {
        return preg_replace_callback('/<body\b([^>]*)>/i', static function(array $matches) use ($pickerPaddingValues): string {
            $attrs = $matches[1] ?? '';
            $attrs = preg_replace('/\s+cz-shortcut-listen=(["\']).*?\1/i', '', $attrs) ?? $attrs;
            $attrs = preg_replace('/\s+data-new-gr-c-s-check-loaded=(["\']).*?\1/i', '', $attrs) ?? $attrs;
            $attrs = preg_replace('/\s+data-gr-ext-installed(=(["\']).*?\2)?/i', '', $attrs) ?? $attrs;

            $attrs = preg_replace_callback('/\s+style=(["\'])(.*?)\1/is', static function(array $styleMatches) use ($pickerPaddingValues): string {
                $quote = $styleMatches[1];
                $style = Sanitizer::decodeHtmlEntities($styleMatches[2]);
                $parts = array_filter(array_map('trim', explode(';', $style)), static function(string $declaration) use ($pickerPaddingValues): bool {
                    if ($declaration === '') {
                        return false;
                    }

                    foreach ($pickerPaddingValues as $value) {
                        if (preg_match('/^padding-top\s*:\s*' . preg_quote($value, '/') . '\s*$/i', $declaration)) {
                            return false;
                        }
                    }

                    return true;
                });

                if (empty($parts)) {
                    return '';
                }

                return ' style=' . $quote . htmlspecialchars(implode('; ', $parts), ENT_QUOTES, 'UTF-8') . $quote;
            }, $attrs) ?? $attrs;

            return '<body' . rtrim($attrs) . '>';
        }, $html) ?? $html;
    }

    private static function removeKnownInjectedNodes(string $html): string {
        $patterns = [
            '/<div\b[^>]*\bid=["\']cocoCutDLinject["\'][^>]*>.*?<\/div>/is',
            '/<grammarly-desktop-integration\b[^>]*>.*?<\/grammarly-desktop-integration>/is',
            '/<div\b[^>]*\bdata-grammarly-part=["\'][^"\']*["\'][^>]*>.*?<\/div>/is',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return $html;
    }

    private static function removeBlogAttributes(string $html): string {
        return preg_replace('/\s+data-clipon-blog-(?:container|card|field|list|post-field|field-label|pagination|placeholder)=(["\']).*?\1/i', '', $html) ?? $html;
    }

    private static function removeShortcodePreviewClasses(string $html): string {
        return preg_replace('/\s+data-clipon-shortcode-(?:preview|type|attrs|index|meta)=(["\']).*?\1/i', '', $html) ?? $html;
    }

    private static function removePickerClasses(string $html, array $prefixes): string {
        return preg_replace_callback('/\s+class=(["\'])(.*?)\1/is', static function(array $matches) use ($prefixes): string {
            $classes = preg_split('/\s+/', trim($matches[2])) ?: [];
            $classes = array_values(array_filter($classes, static function(string $class) use ($prefixes): bool {
                if ($class === '') {
                    return false;
                }

                foreach ($prefixes as $prefix) {
                    if (strpos($class, $prefix) === 0) {
                        return false;
                    }
                }

                return true;
            }));

            if (empty($classes)) {
                return '';
            }

            return ' class=' . $matches[1] . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . $matches[1];
        }, $html) ?? $html;
    }
}
