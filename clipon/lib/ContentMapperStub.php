<?php
/**
 * Class ContentMapper
 * Stub version for core functionality without multi-language overrides.
 */
class ContentMapperStub {
    private static function legacyLocaleFromRoot(array $data): array {
        $locale = [];
        foreach (['title', 'excerpt'] as $field) {
            if (array_key_exists($field, $data)) {
                $locale[$field] = $data[$field];
            }
        }
        if (isset($data['seo']) && is_array($data['seo'])) {
            $locale['seo'] = $data['seo'];
        }
        if (isset($data['content']) && is_array($data['content'])) {
            $locale['content'] = $data['content'];
        }
        if (isset($data['slug']) && is_string($data['slug']) && $data['slug'] !== '') {
            $locale['slug'] = $data['slug'];
        }
        return $locale;
    }

    private static function normalizeLocales(array $data, string $primaryLang): array {
        $locales = $data['locales'] ?? [];
        if (!is_array($locales)) {
            $locales = [];
        }
        if (!isset($locales[$primaryLang]) || !is_array($locales[$primaryLang]) || empty($locales[$primaryLang])) {
            $legacyLocale = self::legacyLocaleFromRoot($data);
            if (!empty($legacyLocale)) {
                $locales[$primaryLang] = $legacyLocale;
            }
        }
        return $locales;
    }

    private static function mergeLocaleOverlay(array $base, array $overlay): array {
        $merged = $base;
        foreach (['title', 'excerpt', 'slug'] as $field) {
            if (array_key_exists($field, $overlay) && $overlay[$field] !== '') {
                $merged[$field] = $overlay[$field];
            }
        }
        if (!isset($merged['seo']) || !is_array($merged['seo'])) {
            $merged['seo'] = [];
        }
        if (isset($overlay['seo']) && is_array($overlay['seo'])) {
            $merged['seo'] = array_merge($merged['seo'], $overlay['seo']);
        }
        if (!isset($merged['content']) || !is_array($merged['content'])) {
            $merged['content'] = [];
        }
        if (isset($overlay['content']) && is_array($overlay['content'])) {
            $merged['content'] = array_merge($merged['content'], $overlay['content']);
        }
        return $merged;
    }

    public static function mapForRead(array $data, string $lang, string $primaryLang): array {
        $result = $data;
        unset($result['translations'], $result['title'], $result['excerpt'], $result['seo'], $result['content']);

        $locales = self::normalizeLocales($data, $primaryLang);

        if (isset($locales[$primaryLang]) && is_array($locales[$primaryLang])) {
            $result = self::mergeLocaleOverlay($result, $locales[$primaryLang]);
        }

        if (isset($result['title']) && is_string($result['title'])) {
            $result['title'] = htmlspecialchars($result['title'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($result['excerpt']) && is_string($result['excerpt'])) {
            $result['excerpt'] = htmlspecialchars($result['excerpt'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($result['seo']) && is_array($result['seo'])) {
            if (isset($result['seo']['meta_title']) && is_string($result['seo']['meta_title'])) {
                $result['seo']['meta_title'] = htmlspecialchars($result['seo']['meta_title'], ENT_QUOTES, 'UTF-8');
            }
            if (isset($result['seo']['meta_description']) && is_string($result['seo']['meta_description'])) {
                $result['seo']['meta_description'] = htmlspecialchars($result['seo']['meta_description'], ENT_QUOTES, 'UTF-8');
            }
        }

        return $result;
    }

    public static function prepareForWrite(array &$pageData, string $lang, string $primaryLang, string $key, $content): void {
        $pageData['locales'] = self::normalizeLocales($pageData, $primaryLang);
        if (!isset($pageData['locales'][$primaryLang])) {
            $pageData['locales'][$primaryLang] = [];
        }

        unset($pageData['translations'], $pageData['title'], $pageData['excerpt'], $pageData['seo'], $pageData['content']);

        if ($key === 'title' || $key === 'excerpt' || $key === 'seo') {
            $pageData['locales'][$primaryLang][$key] = $content;
        } else {
            if (!isset($pageData['locales'][$primaryLang]['content'])) {
                $pageData['locales'][$primaryLang]['content'] = [];
            }
            $pageData['locales'][$primaryLang]['content'][$key] = $content;
        }
    }
}
