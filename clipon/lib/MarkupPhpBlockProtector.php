<?php

class MarkupPhpBlockProtector {
    private const TOKEN_PREFIX = '__CLIPON_PHP_BLOCK_';
    private const TOKEN_SUFFIX = '__';

    public static function protect(string $html): array {
        $blocks = [];
        $protected = preg_replace_callback('/<\?(?!xml\b)(?:php|=)?[\s\S]*?\?>/i', static function(array $matches) use (&$blocks): string {
            $index = count($blocks);
            $token = self::TOKEN_PREFIX . $index . self::TOKEN_SUFFIX;
            $blocks[$token] = $matches[0];
            return $token;
        }, $html);

        return [
            'html' => is_string($protected) ? $protected : $html,
            'blocks' => $blocks,
        ];
    }

    public static function restore(string $html, array $blocks): array {
        foreach ($blocks as $token => $php) {
            if (!is_string($token) || !is_string($php)) {
                return ['ok' => false, 'html' => $html, 'error' => 'Invalid protected PHP block map'];
            }

            if (substr_count($html, $token) !== 1) {
                return ['ok' => false, 'html' => $html, 'error' => 'Protected PHP block mismatch'];
            }
        }

        if (preg_match('/' . preg_quote(self::TOKEN_PREFIX, '/') . '\d+' . preg_quote(self::TOKEN_SUFFIX, '/') . '/', str_replace(array_keys($blocks), '', $html))) {
            return ['ok' => false, 'html' => $html, 'error' => 'Unknown protected PHP block token'];
        }

        return ['ok' => true, 'html' => strtr($html, $blocks), 'error' => null];
    }
}
