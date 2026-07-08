<?php

require_once __DIR__ . '/BlogMarkupPreviewAnnotator.php';

class MarkupShortcodePreview {
    public static function renderMarkupShortcodePreviews(string $html): string {
        if ($html === '' || strpos($html, '[blog_') === false) {
            return $html;
        }

        $loopIndex = 0;
        $lastLoopIndex = 0;
        $loopIndexesByPageParam = [];

        return preg_replace_callback('/\[(blog_loop|blog_pagination)([^\]]*)\](.*?)\[\/\1\]/s', static function(array $matches) use (&$loopIndex, &$lastLoopIndex, &$loopIndexesByPageParam): string {
            $shortcode = $matches[0];
            $type = $matches[1];
            $attrs = self::parseAttrs($matches[2] ?? '');
            $previewAttrs = $attrs;
            $previewAttrs['per_page'] = 1;
            $template = $matches[3] ?? '';
            $preview = '';
            $listIndex = $lastLoopIndex;

            if ($type === 'blog_loop') {
                $loopIndex++;
                $listIndex = $loopIndex;
                $lastLoopIndex = $listIndex;
                $pageParam = isset($attrs['page_param']) ? trim((string)$attrs['page_param']) : '';
                if ($pageParam !== '') {
                    $loopIndexesByPageParam[$pageParam] = $listIndex;
                }
                $template = BlogMarkupPreviewAnnotator::annotateBlogLoopTemplate($template);
            } elseif ($type === 'blog_pagination') {
                $pageParam = isset($attrs['page_param']) ? trim((string)$attrs['page_param']) : '';
                if ($pageParam !== '' && isset($loopIndexesByPageParam[$pageParam])) {
                    $listIndex = $loopIndexesByPageParam[$pageParam];
                }
                $template = BlogMarkupPreviewAnnotator::annotateBlogPaginationTemplate($template);
            }

            $preview = $type === 'blog_loop'
                ? BlogMarkupPreviewAnnotator::mockBlogLoopPreview($template)
                : BlogMarkupPreviewAnnotator::mockBlogPaginationPreview($template);

            $encoded = htmlspecialchars(base64_encode($shortcode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $encodedAttrs = htmlspecialchars(base64_encode($matches[2] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $encodedMeta = htmlspecialchars(base64_encode(json_encode([
                'type' => $type,
                'index' => $listIndex,
                'attrs' => $attrs,
                'attrs_raw' => trim($matches[2] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $class = $type === 'blog_loop' ? 'clipon-shortcode-preview-blog-loop' : 'clipon-shortcode-preview-blog-pagination';
            $blogRole = $type === 'blog_loop' ? ' data-clipon-blog-container="1"' : ' data-clipon-blog-pagination="1"';
            return '<div class="clipon-shortcode-preview ' . $class . '" data-clipon-shortcode-preview="' . $encoded . '" data-clipon-shortcode-type="' . $type . '" data-clipon-shortcode-attrs="' . $encodedAttrs . '" data-clipon-shortcode-index="' . (int)$listIndex . '" data-clipon-shortcode-meta="' . $encodedMeta . '"' . $blogRole . '>' . $preview . '</div>';
        }, $html) ?? $html;
    }

    public static function restoreMarkupShortcodePreviews(string $html): string {
        if ($html === '' || strpos($html, 'data-clipon-shortcode-preview') === false) {
            return $html;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-clipon-shortcode-preview]');
        if (!$nodes || $nodes->length === 0) {
            return $html;
        }

        $replacements = [];
        foreach (iterator_to_array($nodes) as $index => $node) {
            if (!$node instanceof DOMElement || !$node->parentNode) {
                continue;
            }

            $encoded = $node->getAttribute('data-clipon-shortcode-preview');
            $shortcode = base64_decode($encoded, true);
            if (!is_string($shortcode) || $shortcode === '') {
                continue;
            }

            $marker = 'CLIPON_SHORTCODE_RESTORE_' . $index;
            $replacements['<!--' . $marker . '-->'] = $shortcode;
            $node->parentNode->replaceChild($dom->createComment($marker), $node);
        }

        $result = $dom->saveHTML();
        $result = str_replace('<?xml encoding="utf-8" ?>', '', $result);
        $result = mb_convert_encoding($result, 'UTF-8', 'HTML-ENTITIES');
        return str_replace(array_keys($replacements), array_values($replacements), $result);
    }

    private static function parseAttrs(string $str): array {
        if (class_exists('Blog')) {
            return Blog::parseAttrs($str);
        }

        $atts = [];
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*(\S+)/', $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1] ?: $match[3];
            $value = $match[2] ?: $match[4];
            $atts[$key] = $value;
        }

        return $atts;
    }

}
