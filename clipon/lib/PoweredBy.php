<?php

class PoweredBy {
    private const URL = 'https://clipon-cms.com/';

    public static function injectIntoHtml(string $html): string {
        $badge = self::badgeHtml();
        if ($badge === '') {
            return $html;
        }

        $html = self::injectStyles($html);

        if (preg_match_all('/<\/footer\s*>/i', $html, $matches, PREG_OFFSET_CAPTURE) && !empty($matches[0])) {
            $lastMatch = end($matches[0]);
            $offset = (int)$lastMatch[1] + strlen((string)$lastMatch[0]);
            return substr_replace($html, $badge, $offset, 0);
        }

        if (preg_match('/<\/body\s*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $badge, (int)$match[0][1], 0);
        }

        return $html . $badge;
    }

    public static function render(): string {
        $badge = self::badgeHtml();
        return $badge === '' ? '' : self::styles() . $badge;
    }

    private static function badgeHtml(): string {
        $settings = class_exists('Settings') ? Settings::load() : [];
        $isLicensed = class_exists('License') && License::isValid();
        $isHidden = !empty($settings['powered_by_hidden']) && $isLicensed;

        if ($isHidden) {
            return '';
        }

        $theme = (string)($settings['powered_by_theme'] ?? 'light');
        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        return '<div class="clipon-powered-by clipon-powered-by-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '" aria-label="Clipon CMS attribution">' .
            '<span>Powered by</span> ' .
            '<a href="' . self::URL . '" target="_blank" rel="noopener">Clipon CMS</a>' .
            '</div>';
    }

    private static function injectStyles(string $html): string {
        if (strpos($html, 'id="clipon-powered-by-styles"') !== false) {
            return $html;
        }

        $styles = self::styles();
        if (preg_match('/<\/head\s*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $styles, (int)$match[0][1], 0);
        }

        return $styles . $html;
    }

    private static function styles(): string {
        return '<style id="clipon-powered-by-styles">
.clipon-powered-by.clipon-powered-by{all:initial;display:block;box-sizing:border-box;inline-size:100%;max-inline-size:100%;min-inline-size:0;margin:0;padding:12px 16px;border-radius:0;font:600 13px/1.4 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;text-align:center;float:none;clear:both;contain:layout paint;overflow:hidden;overflow-wrap:anywhere}
.clipon-powered-by.clipon-powered-by *{all:unset;box-sizing:border-box}
.clipon-powered-by.clipon-powered-by a{color:inherit;text-decoration:none;border-bottom:1px solid currentColor;cursor:pointer}
.clipon-powered-by.clipon-powered-by a:hover{opacity:.8}
.clipon-powered-by.clipon-powered-by-light{background:#fff;color:#1f2937;border-top:1px solid rgba(15,23,42,.12)}
.clipon-powered-by.clipon-powered-by-dark{background:#111827;color:#f9fafb;border-top:1px solid rgba(255,255,255,.12)}
.clipon-powered-by.clipon-powered-by span{color:inherit;opacity:.68}
@media (max-width:480px){.clipon-powered-by.clipon-powered-by{padding:10px 12px;font-size:12px}}
</style>';
    }
}
