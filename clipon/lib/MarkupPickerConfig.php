<?php

class MarkupPickerConfig {
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';
    public const MODE_BLOG_LIST = 'blog-list';
    public const MODE_BLOG_POST = 'blog-post';

    public const SCENARIO_PAGE_CONTENT = 'page-content';
    public const SCENARIO_BLOG_LIST = 'blog-list';
    public const SCENARIO_BLOG_POST = 'blog-post';

    public static function validModes(): array {
        return [
            self::MODE_AUTO,
            self::MODE_MANUAL,
            self::MODE_BLOG_LIST,
            self::MODE_BLOG_POST,
        ];
    }

    public static function normalizeMode(string $mode): string {
        return in_array($mode, self::validModes(), true) ? $mode : self::MODE_MANUAL;
    }

    public static function resolveLaunchState(string $mode): array {
        $mode = self::normalizeMode($mode);

        if ($mode === self::MODE_BLOG_LIST) {
            return [
                'mode' => $mode,
                'scenario' => self::SCENARIO_BLOG_LIST,
                'initialPanel' => self::MODE_BLOG_LIST,
            ];
        }

        if ($mode === self::MODE_BLOG_POST) {
            return [
                'mode' => $mode,
                'scenario' => self::SCENARIO_BLOG_POST,
                'initialPanel' => self::MODE_BLOG_POST,
            ];
        }

        return [
            'mode' => $mode,
            'scenario' => self::SCENARIO_PAGE_CONTENT,
            'initialPanel' => $mode,
        ];
    }

    public static function autoTagDefaults(): array {
        return [
            'tags' => ['h1', 'h2', 'h3', 'p', 'img', 'a', 'span', 'li'],
            'defaultTags' => ['h1', 'h2', 'h3', 'p', 'img', 'a'],
            'exclude' => ['header', 'footer', 'nav', 'aside'],
        ];
    }
}
