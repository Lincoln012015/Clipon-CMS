<?php

class BlogMarkupPreviewAnnotator {
    public static function mockBlogLoopPreview(string $template): string {
        $template = trim($template) !== '' ? $template : '<article><h3>{{title}}</h3><p>{{excerpt}}</p></article>';
        $vars = [
            '{{title}}' => 'Sample blog post',
            '{{date}}' => date('Y-m-d'),
            '{{author}}' => 'Clipon CMS',
            '{{excerpt}}' => 'This is a preview post shown only inside the markup editor.',
            '{{url}}' => '#',
            '{{slug}}' => 'sample-blog-post',
            '{{tags}}' => '<span class="blog-tag">Preview</span>',
            '{{thumbnail}}' => 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22640%22 height=%22360%22 viewBox=%220 0 640 360%22%3E%3Crect width=%22640%22 height=%22360%22 fill=%22%23e5e7eb%22/%3E%3Ctext x=%22320%22 y=%22188%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2228%22 fill=%22%236b7280%22%3EBlog image%3C/text%3E%3C/svg%3E',
            '{{thumbnail_img}}' => '<img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22640%22 height=%22360%22 viewBox=%220 0 640 360%22%3E%3Crect width=%22640%22 height=%22360%22 fill=%22%23e5e7eb%22/%3E%3Ctext x=%22320%22 y=%22188%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2228%22 fill=%22%236b7280%22%3EBlog image%3C/text%3E%3C/svg%3E" alt="Sample blog post">',
            '{{image}}' => '<img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22640%22 height=%22360%22 viewBox=%220 0 640 360%22%3E%3Crect width=%22640%22 height=%22360%22 fill=%22%23e5e7eb%22/%3E%3Ctext x=%22320%22 y=%22188%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2228%22 fill=%22%236b7280%22%3EBlog image%3C/text%3E%3C/svg%3E" alt="Sample blog post">',
        ];

        $template = preg_replace_callback('/\{\{if\s+(\w+)\}\}(.*?)\{\{endif\}\}/s', static function(array $matches) use ($vars): string {
            return !empty($vars['{{' . $matches[1] . '}}'] ?? '') ? $matches[2] : '';
        }, $template) ?? $template;

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    public static function mockBlogPaginationPreview(string $template): string {
        $template = trim($template) !== '' ? $template : '<nav class="blog-pagination"><a href="#">1</a><a href="#">2</a></nav>';
        return preg_replace('/href=(["\']).*?\1/i', 'href="#"', $template) ?? $template;
    }

    public static function annotateBlogLoopTemplate(string $template): string {
        $template = trim($template) !== '' ? $template : '<article><h3>{{title}}</h3><p>{{excerpt}}</p></article>';
        $template = self::addAttributeToFirstElement($template, 'data-clipon-blog-card', '1');
        $template = self::addAttributeToTagsMatchingAttribute($template, 'a', 'href', '{{url}}', 'data-clipon-blog-field', 'link', '{{url}}');
        $template = self::addAttributeToTagsMatchingAttribute($template, 'img', 'src', '{{thumbnail}}', 'data-clipon-blog-field', 'image', '{{thumbnail}}');
        $template = self::addAttributeToTagsMatchingText($template, 'title', 'data-clipon-blog-field');
        $template = self::addAttributeToTagsMatchingText($template, 'excerpt', 'data-clipon-blog-field');
        $template = self::addAttributeToTagsMatchingText($template, 'date', 'data-clipon-blog-field');
        $template = self::addAttributeToTagsMatchingText($template, 'author', 'data-clipon-blog-field');
        $template = self::addAttributeToTagsMatchingText($template, 'tags', 'data-clipon-blog-field');
        $template = self::addAttributeToElementsContainingPlaceholder($template, 'title', 'data-clipon-blog-field');
        $template = self::addAttributeToElementsContainingPlaceholder($template, 'excerpt', 'data-clipon-blog-field');
        $template = self::addAttributeToElementsContainingPlaceholder($template, 'date', 'data-clipon-blog-field');
        $template = self::addAttributeToElementsContainingPlaceholder($template, 'author', 'data-clipon-blog-field');
        $template = self::addAttributeToElementsContainingPlaceholder($template, 'tags', 'data-clipon-blog-field');
        $template = self::wrapBarePlaceholder($template, 'thumbnail_img', 'image');
        return self::wrapBarePlaceholder($template, 'image', 'image');
    }

    public static function annotateBlogPaginationTemplate(string $template): string {
        $template = trim($template) !== '' ? $template : '<nav class="blog-pagination"><a href="#">1</a><a href="#">2</a></nav>';
        return self::addAttributeToFirstElement($template, 'data-clipon-blog-pagination', '1');
    }

    private static function addAttributeToFirstElement(string $html, string $name, string $value): string {
        return preg_replace_callback('/<([a-z][a-z0-9:-]*)(\s[^>]*)?>/i', static function(array $matches) use ($name, $value): string {
            return self::addAttributeToOpeningTag($matches[0], $name, $value);
        }, $html, 1) ?? $html;
    }

    private static function addAttributeToTagsMatchingAttribute(string $html, string $tag, string $attribute, string $needle, string $name, string $value, ?string $placeholder = null): string {
        $tag = preg_quote($tag, '/');
        $attribute = preg_quote($attribute, '/');
        $needle = preg_quote($needle, '/');
        return preg_replace_callback('/<' . $tag . '\b[^>]*\b' . $attribute . '=(["\'])' . $needle . '\1[^>]*>/i', static function(array $matches) use ($name, $value, $placeholder): string {
            return self::addPlaceholderAttribute(self::addAttributeToOpeningTag($matches[0], $name, $value), $placeholder);
        }, $html) ?? $html;
    }

    private static function addAttributeToTagsMatchingText(string $html, string $field, string $name): string {
        $placeholder = preg_quote('{{' . $field . '}}', '/');
        return preg_replace_callback('/<([a-z][a-z0-9:-]*)(\s[^>]*)?>(\s*)' . $placeholder . '(\s*)<\/\1>/i', static function(array $matches) use ($name, $field): string {
            $opening = self::addPlaceholderAttribute(
                self::addAttributeToOpeningTag('<' . $matches[1] . ($matches[2] ?? '') . '>', $name, $field),
                '{{' . $field . '}}'
            );
            return $opening . $matches[3] . '{{' . $field . '}}' . $matches[4] . '</' . $matches[1] . '>';
        }, $html) ?? $html;
    }

    private static function addAttributeToElementsContainingPlaceholder(string $html, string $field, string $name): string {
        $placeholder = '{{' . $field . '}}';
        if (strpos($html, $placeholder) === false) {
            return $html;
        }

        $tags = '(?:h[1-6]|p|span|small|time|strong|em|b|i|div|figcaption|a)';
        return preg_replace_callback('/<(' . $tags . ')(\s[^>]*)?>((?:(?!<\1\b).)*?' . preg_quote($placeholder, '/') . '.*?)<\/\1>/is', static function(array $matches) use ($name, $field, $placeholder): string {
            $openingTag = '<' . $matches[1] . ($matches[2] ?? '') . '>';
            if (preg_match('/\s' . preg_quote($name, '/') . '=(["\'])' . preg_quote($field, '/') . '\1/i', $openingTag)) {
                return $matches[0];
            }
            if (preg_match('/\s' . preg_quote($name, '/') . '=(["\'])' . preg_quote($field, '/') . '\1/i', $matches[3] ?? '')) {
                return $matches[0];
            }

            $opening = self::addPlaceholderAttribute(
                self::addAttributeToOpeningTag($openingTag, $name, $field),
                $placeholder
            );
            return $opening . $matches[3] . '</' . $matches[1] . '>';
        }, $html) ?? $html;
    }

    private static function wrapBarePlaceholder(string $html, string $placeholderName, string $field): string {
        $placeholder = '{{' . $placeholderName . '}}';
        return preg_replace('/' . preg_quote($placeholder, '/') . '/', '<span data-clipon-blog-field="' . htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-clipon-blog-placeholder="' . htmlspecialchars($placeholderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $placeholder . '</span>', $html, 1) ?? $html;
    }

    private static function addPlaceholderAttribute(string $tag, ?string $placeholder): string {
        if ($placeholder === null || $placeholder === '') {
            return $tag;
        }

        $key = trim($placeholder);
        if (preg_match('/^\{\{\s*([a-zA-Z0-9_]+)\s*\}\}$/', $key, $matches)) {
            $key = $matches[1];
        }

        return self::addAttributeToOpeningTag($tag, 'data-clipon-blog-placeholder', $key);
    }

    private static function addAttributeToOpeningTag(string $tag, string $name, string $value): string {
        if (preg_match('/\s' . preg_quote($name, '/') . '=(["\']).*?\1/i', $tag)) {
            return $tag;
        }

        return preg_replace('/\s*\/?>$/', ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . (str_ends_with($tag, '/>') ? ' />' : '>'), $tag, 1) ?? $tag;
    }
}
