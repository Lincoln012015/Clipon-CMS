<?php

require_once __DIR__ . '/Sanitizer.php';

class BlogInlineFields {
    private const PLAIN_LOCALE_FIELDS = ['title', 'excerpt'];

    public static function plain($value): string {
        return Sanitizer::plainText($value);
    }

    public static function isPlainLocaleField(string $key): bool {
        return in_array($key, self::PLAIN_LOCALE_FIELDS, true);
    }

    public static function saveMetadataField(array &$postData, string $key, $content): bool {
        if ($key === 'thumbnail') {
            $postData['thumbnail'] = self::plain($content);
            return true;
        }

        return false;
    }
}
