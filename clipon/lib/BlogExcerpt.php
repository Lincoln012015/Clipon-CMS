<?php

require_once __DIR__ . '/Sanitizer.php';

class BlogExcerpt
{
    public static function forPost(array $post, ?string $lang = null): string
    {
        $excerpt = self::localizedField($post, 'excerpt', $lang);
        return $excerpt !== '' ? self::plain($excerpt) : '';
    }

    private static function localizedField(array $post, string $field, ?string $lang): string
    {
        if (isset($post[$field]) && is_scalar($post[$field])) {
            $value = trim((string)$post[$field]);
            if ($value !== '') {
                return $value;
            }
        }

        $locales = isset($post['locales']) && is_array($post['locales']) ? $post['locales'] : [];
        if ($lang !== null && isset($locales[$lang][$field]) && is_scalar($locales[$lang][$field])) {
            $value = trim((string)$locales[$lang][$field]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function plain(string $html): string
    {
        return Sanitizer::plainText($html);
    }

}
