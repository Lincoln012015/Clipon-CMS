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
        $restored = $html;

        foreach ($blocks as $token => $php) {
            if (!is_string($token) || !is_string($php)) {
                return ['ok' => false, 'html' => $html, 'error' => 'Invalid protected PHP block map'];
            }

            // HTML parsers lowercase attribute names and serialize valueless
            // attributes with an empty value. A protected PHP block used as an
            // attribute can therefore change from __CLIPON_...__ to
            // __clipon_...__="" while it is open in the markup editor.
            $pattern = '/' . preg_quote($token, '/') . '(?:\s*=\s*(["\'])\1)?/i';
            $matchCount = preg_match_all($pattern, $restored);
            if ($matchCount !== 1) {
                return ['ok' => false, 'html' => $html, 'error' => 'Protected PHP block mismatch'];
            }

            $restored = preg_replace_callback($pattern, static fn(): string => $php, $restored, 1);
            if (!is_string($restored)) {
                return ['ok' => false, 'html' => $html, 'error' => 'Protected PHP block mismatch'];
            }
        }

        if (preg_match('/' . preg_quote(self::TOKEN_PREFIX, '/') . '\d+' . preg_quote(self::TOKEN_SUFFIX, '/') . '/i', $restored)) {
            return ['ok' => false, 'html' => $html, 'error' => 'Unknown protected PHP block token'];
        }

        return ['ok' => true, 'html' => $restored, 'error' => null];
    }
}
