<?php

class Sanitizer {
    /**
     * Списки дозволених тегів та атрибутів
     */
    private static $allowedTags = [
        'p', 'br', 'strong', 'em', 'u', 's', 'ol', 'ul', 'li', 
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'a', 'img', 'div', 'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'iframe', 'video', 'source', 'hr'
    ];

    private static $allowedAttributes = [
        'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel',
        'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'style',
        'type', 'controls'
    ];

    /**
     * Очищує HTML від небезпечних тегів та атрибутів (XSS protection)
     */
    public static function sanitize($html) {
        if (empty($html)) return '';

        // Використовуємо DOMDocument для парсингу
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Пригнічуємо помилки парсингу невалідного HTML
        libxml_use_internal_errors(true);
        
        // Завантажуємо фрагмент як повний UTF-8 документ. Це не дає DOMDocument
        // серіалізувати кирилицю як numeric entities на кшталт &#1041;.
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>'
        );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) return '';

        self::cleanNode($body);

        // Повертаємо очищений HTML
        return self::serializeChildren($dom, $body);
    }

    public static function decodeHtmlEntities($value): string {
        $decoded = (string)$value;

        for ($i = 0; $i < 3; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        return $decoded;
    }

    public static function plainText($value): string {
        $plain = self::decodeHtmlEntities(strip_tags((string)$value));
        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    public static function sanitizeImageSource($value): string {
        $src = trim(self::decodeHtmlEntities(strip_tags((string)$value)));
        if ($src === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F<>"\'`]/u', $src)) {
            return '';
        }

        if (strpos($src, '//') === 0) {
            return '';
        }

        $scheme = parse_url($src, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '' && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return '';
        }

        return $src;
    }

    public static function sanitizeLinkHref($value): string {
        $href = trim(self::decodeHtmlEntities(strip_tags((string)$value)));
        if ($href === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F<>"\'`]/u', $href)) {
            return '';
        }

        if (strpos($href, '//') === 0) {
            return '';
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '' && !in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }

        return $href;
    }

    private static function cleanNode($node) {
        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $tagName = strtolower($child->nodeName);

                    // Якщо тег не дозволений - видаляємо його, але залишаємо текст всередині
                    if (!in_array($tagName, self::$allowedTags)) {
                        while ($child->hasChildNodes()) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }

                    // Очищуємо атрибути
                    if ($child->hasAttributes()) {
                        $attrs = [];
                        foreach ($child->attributes as $attr) {
                            $attrs[] = $attr->nodeName;
                        }

                        foreach ($attrs as $attrName) {
                            $lowerAttr = strtolower($attrName);
                            
                            // 1. Видаляємо всі обробники подій (on*)
                            if (strpos($lowerAttr, 'on') === 0) {
                                $child->removeAttribute($attrName);
                                continue;
                            }

                            // 2. Видаляємо недозволені атрибути
                            if (!in_array($lowerAttr, self::$allowedAttributes)) {
                                $child->removeAttribute($attrName);
                                continue;
                            }

                            // 3. Захист від небезпечних URL в href/src
                            if ($lowerAttr === 'href') {
                                $href = self::sanitizeLinkHref($child->getAttribute($attrName));
                                if ($href === '') {
                                    $child->removeAttribute($attrName);
                                    continue;
                                }
                                $child->setAttribute($attrName, $href);
                            } elseif ($lowerAttr === 'src') {
                                $src = self::sanitizeImageSource($child->getAttribute($attrName));
                                if ($src === '') {
                                    $child->removeAttribute($attrName);
                                    continue;
                                }
                                $child->setAttribute($attrName, $src);
                            } elseif ($lowerAttr === 'style') {
                                $style = self::sanitizeStyle($child->getAttribute($attrName));
                                if ($style === '') {
                                    $child->removeAttribute($attrName);
                                    continue;
                                }
                                $child->setAttribute($attrName, $style);
                            }
                        }
                    }

                    // Рекурсивно очищуємо вкладені елементи
                    self::cleanNode($child);
                }
            }
        }
    }

    private static function sanitizeStyle(string $style): string {
        $style = trim(self::decodeHtmlEntities($style));
        if ($style === '') {
            return '';
        }

        if (
            preg_match('/[\x00-\x1F\x7F`<>]/u', $style) ||
            preg_match('/(?:url\s*\(|expression\s*\(|@import|var\s*\()/i', $style)
        ) {
            return '';
        }

        $allowed = [
            'color',
            'background-color',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
            'font-size',
            'line-height',
        ];

        $clean = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                return '';
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (!in_array($property, $allowed, true) || $value === '' || !self::isAllowedStyleValue($property, $value)) {
                return '';
            }

            $clean[] = $property . ': ' . $value;
        }

        return implode('; ', $clean);
    }

    private static function isAllowedStyleValue(string $property, string $value): bool {
        if (preg_match('/[;{}]/', $value)) {
            return false;
        }

        switch ($property) {
            case 'color':
            case 'background-color':
                return self::isAllowedColorValue($value);
            case 'text-align':
                return preg_match('/^(left|right|center|justify)$/i', $value) === 1;
            case 'font-weight':
                return preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/i', $value) === 1;
            case 'font-style':
                return preg_match('/^(normal|italic|oblique)$/i', $value) === 1;
            case 'text-decoration':
                return preg_match('/^(none|underline|line-through|overline)(\s+(none|underline|line-through|overline))*$/i', $value) === 1;
            case 'font-size':
                return self::isAllowedCssLength($value);
            case 'line-height':
                return self::isAllowedCssLength($value) || preg_match('/^(normal|[0-9]+(?:\.[0-9]+)?)$/i', $value) === 1;
        }

        return false;
    }

    private static function isAllowedColorValue(string $value): bool {
        if (preg_match('/^#[a-f0-9]{3,8}$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value) === 1) {
            return true;
        }

        return preg_match('/^[a-z]+$/i', $value) === 1;
    }

    private static function isAllowedCssLength(string $value): bool {
        return preg_match('/^(?:0|[0-9]+(?:\.[0-9]+)?(?:px|em|rem|%))$/i', $value) === 1;
    }

    private static function serializeChildren(DOMDocument $dom, DOMNode $node): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }
        return $html;
    }
}
