<?php

require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/BlogExcerpt.php';
require_once __DIR__ . '/MarkupFileResolver.php';
if (file_exists(__DIR__ . '/../admin/lib/BlogTagService.php')) {
    require_once __DIR__ . '/../admin/lib/BlogTagService.php';
}

class Blog {
    private $blogDir;
    private $request;
    private $session;
    private $tagService = null;
    private static $defaultPaginationStyleInjected = false;

    public function __construct() {
        $this->blogDir = C_CONTENT_PATH . '/blog/';
        $this->request = new Request();
        $this->session = new Session();
        if (class_exists('BlogTagService') && defined('C_DATA_PATH')) {
            $this->tagService = new BlogTagService(C_DATA_PATH . '/blog_tags.php', $this->blogDir);
        }
    }

    private function getPrimaryLang(): string {
        if (class_exists('Settings')) {
            $langs = Settings::getLanguages();
            foreach ($langs as $lang) {
                if (!empty($lang['enabled']) && !empty($lang['code'])) {
                    return (string)$lang['code'];
                }
            }

            return (string)(Settings::load()['language'] ?? 'en');
        }

        return 'en';
    }

    private function getRequestedLang(array $opts): ?string {
        $lang = $opts['lang'] ?? ($this->session->has('site_lang') ? $this->session->get('site_lang') : null);
        $lang = is_string($lang) ? trim($lang) : '';
        return $lang !== '' ? $lang : null;
    }

    private function getLocalizedText($value, ?string $lang): string {
        if (is_array($value)) {
            if ($lang !== null && isset($value[$lang])) {
                return (string)$value[$lang];
            }
            // Fallback to first available or primary
            return (string)(reset($value) ?: '');
        }
        return (string)$value;
    }

    private function buildPostUrl(array $post, ?string $lang): string {
        $primary = $this->getPrimaryLang();
        $slug = (string)($post['slug'] ?? '');
        if ($slug === '') {
            return '';
        }

        // Prefer RouteMap/resource_url resolution so behavior matches other helpers
        if ($lang !== null) {
            $resolved = null;
            if (function_exists('resource_url')) {
                $resolved = resource_url($slug, 'blog', $lang);
            }
            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
            // Fallback to primary URL when localized slug isn't available
        }

        return '/blog/' . $slug;
    }

    private function esc($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function paginationSettings(): array {
        if (!class_exists('Settings')) {
            return [
                'blog_pagination_alignment' => 'center',
                'blog_pagination_gap' => '8px',
                'blog_pagination_radius' => '8px',
                'blog_pagination_padding' => '8px 12px',
                'blog_pagination_font_size' => '14px',
                'blog_pagination_prev_text' => 'Prev',
                'blog_pagination_next_text' => 'Next',
                'blog_pagination_colors' => [
                    'background' => '#ffffff',
                    'text' => '#111827',
                    'active_background' => '#2563eb',
                    'active_text' => '#ffffff',
                    'border' => '#d1d5db',
                    'disabled' => '#9ca3af',
                ],
                'blog_pagination_custom_css' => '',
            ];
        }

        return Settings::sanitizeBlogSettings(Settings::load());
    }

    private function paginationStyleVars(array $settings): string {
        $colors = is_array($settings['blog_pagination_colors'] ?? null) ? $settings['blog_pagination_colors'] : [];
        $alignMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
        $alignment = $alignMap[$settings['blog_pagination_alignment'] ?? 'center'] ?? 'center';
        $vars = [
            '--clipon-blog-pagination-align' => $alignment,
            '--clipon-blog-pagination-gap' => (string)($settings['blog_pagination_gap'] ?? '8px'),
            '--clipon-blog-pagination-radius' => (string)($settings['blog_pagination_radius'] ?? '8px'),
            '--clipon-blog-pagination-padding' => (string)($settings['blog_pagination_padding'] ?? '8px 12px'),
            '--clipon-blog-pagination-font-size' => (string)($settings['blog_pagination_font_size'] ?? '14px'),
            '--clipon-blog-pagination-bg' => (string)($colors['background'] ?? '#ffffff'),
            '--clipon-blog-pagination-text' => (string)($colors['text'] ?? '#111827'),
            '--clipon-blog-pagination-active-bg' => (string)($colors['active_background'] ?? '#2563eb'),
            '--clipon-blog-pagination-active-text' => (string)($colors['active_text'] ?? '#ffffff'),
            '--clipon-blog-pagination-border' => (string)($colors['border'] ?? '#d1d5db'),
            '--clipon-blog-pagination-disabled' => (string)($colors['disabled'] ?? '#9ca3af'),
        ];

        $style = '';
        foreach ($vars as $name => $value) {
            $style .= $name . ':' . $value . ';';
        }
        return $style;
    }

    private function paginationLabel(array $settings, ?string $lang, string $key): string {
        $fallbackKey = $key === 'prev' ? 'blog_pagination_prev_text' : 'blog_pagination_next_text';
        $fallback = (string)($settings[$fallbackKey] ?? ($key === 'prev' ? 'Prev' : 'Next'));
        if (empty($settings['blog_pagination_localized_labels_enabled'])) {
            return $fallback;
        }

        $labels = is_array($settings['blog_pagination_labels'] ?? null) ? $settings['blog_pagination_labels'] : [];
        $lang = is_string($lang) ? trim($lang) : '';

        if ($lang !== '' && isset($labels[$lang]) && is_array($labels[$lang])) {
            $localized = trim((string)($labels[$lang][$key] ?? ''));
            if ($localized !== '') {
                return $localized;
            }
        }

        return $fallback;
    }

    private function defaultPaginationStyleTag(array $settings): string {
        if (self::$defaultPaginationStyleInjected) {
            return '';
        }

        self::$defaultPaginationStyleInjected = true;
        $customCss = str_ireplace('</style', '', trim((string)($settings['blog_pagination_custom_css'] ?? '')));
        $css = '.blog-pagination{display:flex;flex-wrap:wrap;justify-content:var(--clipon-blog-pagination-align);align-items:center;gap:var(--clipon-blog-pagination-gap);margin:24px 0}.blog-pagination a,.blog-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:2.25rem;padding:var(--clipon-blog-pagination-padding);border:1px solid var(--clipon-blog-pagination-border);border-radius:var(--clipon-blog-pagination-radius);background:var(--clipon-blog-pagination-bg);color:var(--clipon-blog-pagination-text);font-size:var(--clipon-blog-pagination-font-size);font-weight:700;line-height:1.2;text-decoration:none}.blog-pagination a:hover{filter:brightness(.97)}.blog-pagination .active{background:var(--clipon-blog-pagination-active-bg);border-color:var(--clipon-blog-pagination-active-bg);color:var(--clipon-blog-pagination-active-text)}.blog-pagination .disabled,.blog-pagination .ellipsis{color:var(--clipon-blog-pagination-disabled);cursor:default}@media(max-width:640px){.blog-pagination{gap:calc(var(--clipon-blog-pagination-gap) * .75)}.blog-pagination a,.blog-pagination span{min-width:2rem}}';
        if ($customCss !== '') {
            $css .= $customCss;
        }

        return '<style id="clipon-blog-pagination-style">' . $css . '</style>';
    }

    private function normalizePerPage($value): int {
        $perPage = (int)$value;
        if ($perPage < 1) {
            return 1;
        }
        if ($perPage > 50) {
            return 50;
        }
        return $perPage;
    }

    private function getPageParam(array $opts): string {
        $pageParam = isset($opts['page_param']) ? trim((string)$opts['page_param']) : 'p';
        return preg_match('/^[A-Za-z0-9_-]+$/', $pageParam) ? $pageParam : 'p';
    }

    private function applyUrlTagFilter(array $opts): array {
        if (empty($opts['tags'])) {
            if (!empty($this->request->query('tags'))) {
                $opts['tags'] = $this->request->query('tags');
            } elseif (!empty($this->request->query('tag'))) {
                $opts['tags'] = $this->request->query('tag');
            }
        }

        return $opts;
    }

    private function resolveRequestedTagIds($tags, ?string $lang): array {
        $parts = array_map('trim', explode(',', strtolower((string)$tags)));
        $parts = array_values(array_filter($parts, static fn($tag) => $tag !== ''));
        if (empty($parts)) {
            return [];
        }

        if ($this->tagService) {
            return array_values(array_filter(array_map(function($tag) use ($lang) {
                return $this->tagService->resolveTagId($tag, $lang) ?: $tag;
            }, $parts), static fn($tag) => $tag !== ''));
        }

        return $parts;
    }

    private function postTagIds($raw, ?string $lang): array {
        if ($this->tagService) {
            return $this->tagService->parseTagIds($raw, $lang, false);
        }

        $tags = is_string($raw) ? array_map('trim', explode(',', $raw)) : (is_array($raw) ? $raw : []);
        return array_values(array_filter(array_map(static fn($tag) => strtolower(trim((string)$tag)), $tags), static fn($tag) => $tag !== ''));
    }

    private function tagLabel(string $id, ?string $lang): string {
        return $this->tagService ? $this->tagService->labelFor($id, $lang) : $id;
    }

    private function decoratePostTags(array $post, ?string $lang): array {
        $ids = $this->postTagIds($post['tags'] ?? [], $lang);
        $localized = [];
        foreach ($ids as $id) {
            $localized[] = [
                'id' => $id,
                'label' => $this->tagLabel($id, $lang),
            ];
        }
        $post['tags'] = $ids;
        $post['tag_ids'] = $ids;
        $post['display_tags'] = array_map(static fn($tag) => $tag['label'], $localized);
        $post['localized_tags'] = $localized;
        return $post;
    }

    private function paginatePosts(array $posts, array $opts): array {
        $perPage = $this->normalizePerPage($opts['per_page'] ?? 10);
        $pageParam = $this->getPageParam($opts);
        $page = max(1, (int)$this->request->query($pageParam, 1));
        $total = count($posts);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'posts' => array_slice($posts, $offset, $perPage),
            'allPosts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'pageParam' => $pageParam,
        ];
    }

    private function paginationPages(int $page, int $totalPages): array {
        $pages = [1, $page - 1, $page, $page + 1, $totalPages];
        $pages = array_values(array_unique(array_filter($pages, static function($item) use ($totalPages) {
            return $item >= 1 && $item <= $totalPages;
        })));
        sort($pages, SORT_NUMERIC);
        return $pages;
    }

    private function paginationHref(string $pageParam, int $page): string {
        $query = $this->request->query();
        $query[$pageParam] = $page;
        return '?' . http_build_query($query);
    }

    private function renderDefaultPagination(int $total, int $perPage, int $page, int $totalPages, string $pageParam, ?string $lang = null): string {
        if ($total <= $perPage || $totalPages <= 1) {
            return '';
        }

        $settings = $this->paginationSettings();
        $prevText = $this->esc($this->paginationLabel($settings, $lang, 'prev'));
        $nextText = $this->esc($this->paginationLabel($settings, $lang, 'next'));
        $style = $this->esc($this->paginationStyleVars($settings));
        $output = $this->defaultPaginationStyleTag($settings);
        $output .= '<div class="blog-pagination" style="' . $style . '">';
        if ($page > 1) {
            $href = $this->esc($this->paginationHref($pageParam, $page - 1));
            $output .= '<a href="' . $href . '" class="prev">' . $prevText . '</a>';
        } else {
            $output .= '<span class="prev disabled">' . $prevText . '</span>';
        }

        $lastRendered = 0;
        foreach ($this->paginationPages($page, $totalPages) as $pageNumber) {
            if ($lastRendered > 0 && $pageNumber > $lastRendered + 1) {
                $output .= '<span class="ellipsis">...</span>';
            }

            $href = $this->esc($this->paginationHref($pageParam, $pageNumber));
            $class = $pageNumber === $page ? ' class="active"' : '';
            $output .= '<a href="' . $href . '"' . $class . '>' . $pageNumber . '</a>';
            $lastRendered = $pageNumber;
        }

        if ($page < $totalPages) {
            $href = $this->esc($this->paginationHref($pageParam, $page + 1));
            $output .= '<a href="' . $href . '" class="next">' . $nextText . '</a>';
        } else {
            $output .= '<span class="next disabled">' . $nextText . '</span>';
        }

        $output .= '</div>';
        return $output;
    }

    private function findFirstElementChild(DOMNode $node): ?DOMElement {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function classList(DOMElement $element): array {
        return preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    }

    private function hasClassLike(DOMElement $element, array $needles): bool {
        foreach ($this->classList($element) as $class) {
            $class = strtolower($class);
            foreach ($needles as $needle) {
                if (strpos($class, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function setClass(DOMElement $element, string $className, bool $enabled): void {
        $classes = array_values(array_filter($this->classList($element), static fn($class) => $class !== ''));
        $classes = array_values(array_filter($classes, static fn($class) => $class !== $className));
        if ($enabled) {
            $classes[] = $className;
        }

        if (empty($classes)) {
            $element->removeAttribute('class');
        } else {
            $element->setAttribute('class', implode(' ', array_unique($classes)));
        }
    }

    private function findPaginationElement(DOMElement $root, string $role): ?DOMElement {
        $elements = [];
        if ($root->tagName) {
            $elements[] = $root;
        }
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        if ($role === 'page') {
            foreach ($elements as $element) {
                if ($this->hasClassLike($element, ['active', 'current'])) {
                    return $element;
                }
            }
            foreach ($elements as $element) {
                if (preg_match('/^\d+$/', trim($element->textContent))) {
                    return $element;
                }
            }
            return null;
        }

        if ($role === 'ellipsis') {
            foreach ($elements as $element) {
                if ($this->hasClassLike($element, ['ellipsis'])) {
                    return $element;
                }
            }
            foreach ($elements as $element) {
                if (trim($element->textContent) === '...') {
                    return $element;
                }
            }
            return null;
        }

        $texts = $role === 'prev'
            ? ['prev', 'previous', '<', '‹', '←']
            : ['next', '>', '›', '→'];

        foreach ($elements as $element) {
            if ($this->hasClassLike($element, [$role])) {
                return $element;
            }
        }

        foreach ($elements as $element) {
            $text = strtolower(trim($element->textContent));
            if (in_array($text, $texts, true)) {
                return $element;
            }
        }

        return null;
    }

    private function paginationUnit(DOMElement $element, DOMElement $root): DOMElement {
        $parent = $element->parentNode;
        if ($parent instanceof DOMElement && $parent !== $root) {
            $tag = strtolower($parent->tagName);
            if ($tag === 'li' || $this->hasClassLike($parent, ['page-item', 'pagination-item'])) {
                return $parent;
            }
        }

        return $element;
    }

    private function findClickableElement(DOMElement $unit): DOMElement {
        $tag = strtolower($unit->tagName);
        if (in_array($tag, ['a', 'button', 'span'], true)) {
            return $unit;
        }

        foreach (['a', 'button', 'span'] as $tagName) {
            $items = $unit->getElementsByTagName($tagName);
            if ($items->length > 0 && $items->item(0) instanceof DOMElement) {
                return $items->item(0);
            }
        }

        return $unit;
    }

    private function replaceElementTag(DOMElement $element, string $tagName): DOMElement {
        $doc = $element->ownerDocument;
        $replacement = $doc->createElement($tagName);
        foreach ($element->attributes as $attr) {
            if (strtolower($attr->name) === 'href') {
                continue;
            }
            $replacement->setAttribute($attr->name, $attr->value);
        }
        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }
        if ($element->parentNode) {
            $element->parentNode->replaceChild($replacement, $element);
        }
        return $replacement;
    }

    private function clearPaginationStateClasses(DOMElement $unit): void {
        $targets = [$unit];
        foreach ($unit->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement) {
                $targets[] = $element;
            }
        }

        foreach ($targets as $element) {
            $this->setClass($element, 'active', false);
            $this->setClass($element, 'current', false);
            $this->setClass($element, 'disabled', false);
        }
    }

    private function preparePaginationUnit(DOMElement $templateUnit, int $targetPage, array $pagination, string $role, bool $disabled = false): DOMElement {
        $unit = $templateUnit->cloneNode(true);
        if (!$unit instanceof DOMElement) {
            return $templateUnit;
        }

        $this->clearPaginationStateClasses($unit);
        $clickable = $this->findClickableElement($unit);
        if ($disabled) {
            if (!in_array(strtolower($clickable->tagName), ['span'], true)) {
                $clickable = $this->replaceElementTag($clickable, 'span');
                if (!$clickable->parentNode) {
                    $unit = $clickable;
                }
            }
            $this->setClass($clickable, 'disabled', true);
        } else {
            if (strtolower($clickable->tagName) !== 'a') {
                $clickable = $this->replaceElementTag($clickable, 'a');
                if (!$clickable->parentNode) {
                    $unit = $clickable;
                }
            }
            $clickable->setAttribute('href', $this->paginationHref($pagination['pageParam'], $targetPage));
            $this->setClass($clickable, 'disabled', false);
        }

        if ($role === 'page') {
            while ($clickable->firstChild) {
                $clickable->removeChild($clickable->firstChild);
            }
            $clickable->appendChild($clickable->ownerDocument->createTextNode((string)$targetPage));
            $this->setClass($clickable, 'active', $targetPage === $pagination['page']);
        }

        return $unit;
    }

    private function prepareEllipsisUnit(?DOMElement $ellipsisUnit, DOMElement $pageUnit): DOMElement {
        if ($ellipsisUnit) {
            $unit = $ellipsisUnit->cloneNode(true);
            if ($unit instanceof DOMElement) {
                $clickable = $this->findClickableElement($unit);
                while ($clickable->firstChild) {
                    $clickable->removeChild($clickable->firstChild);
                }
                $clickable->appendChild($clickable->ownerDocument->createTextNode('...'));
                return $unit;
            }
        }

        $doc = $pageUnit->ownerDocument;
        $span = $doc->createElement('span', '...');
        $span->setAttribute('class', 'ellipsis');
        return $span;
    }

    private function renderCustomPagination(array $pagination, string $templateHtml, ?string $lang = null): string {
        if ($pagination['total'] <= $pagination['perPage'] || $pagination['totalPages'] <= 1) {
            return '';
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?><body>' . $templateHtml . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$loaded) {
            return $this->renderDefaultPagination($pagination['total'], $pagination['perPage'], $pagination['page'], $pagination['totalPages'], $pagination['pageParam'], $lang);
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $this->renderDefaultPagination($pagination['total'], $pagination['perPage'], $pagination['page'], $pagination['totalPages'], $pagination['pageParam'], $lang);
        }

        $root = $this->findFirstElementChild($body);
        if (!$root) {
            return $this->renderDefaultPagination($pagination['total'], $pagination['perPage'], $pagination['page'], $pagination['totalPages'], $pagination['pageParam'], $lang);
        }

        $pageElement = $this->findPaginationElement($root, 'page');
        if (!$pageElement) {
            return $this->renderDefaultPagination($pagination['total'], $pagination['perPage'], $pagination['page'], $pagination['totalPages'], $pagination['pageParam'], $lang);
        }

        $pageUnit = $this->paginationUnit($pageElement, $root);
        $itemsParent = $pageUnit->parentNode;
        if (!$itemsParent instanceof DOMElement) {
            return $this->renderDefaultPagination($pagination['total'], $pagination['perPage'], $pagination['page'], $pagination['totalPages'], $pagination['pageParam'], $lang);
        }

        $prevElement = $this->findPaginationElement($root, 'prev');
        $nextElement = $this->findPaginationElement($root, 'next');
        $ellipsisElement = $this->findPaginationElement($root, 'ellipsis');
        $prevUnit = $prevElement ? $this->paginationUnit($prevElement, $root) : $pageUnit;
        $nextUnit = $nextElement ? $this->paginationUnit($nextElement, $root) : $pageUnit;
        $ellipsisUnit = $ellipsisElement ? $this->paginationUnit($ellipsisElement, $root) : null;

        while ($itemsParent->firstChild) {
            $itemsParent->removeChild($itemsParent->firstChild);
        }

        $itemsParent->appendChild($this->preparePaginationUnit($prevUnit, max(1, $pagination['page'] - 1), $pagination, 'prev', $pagination['page'] <= 1));

        $lastRendered = 0;
        foreach ($this->paginationPages($pagination['page'], $pagination['totalPages']) as $pageNumber) {
            if ($lastRendered > 0 && $pageNumber > $lastRendered + 1) {
                $itemsParent->appendChild($this->prepareEllipsisUnit($ellipsisUnit, $pageUnit));
            }
            $itemsParent->appendChild($this->preparePaginationUnit($pageUnit, $pageNumber, $pagination, 'page'));
            $lastRendered = $pageNumber;
        }

        $itemsParent->appendChild($this->preparePaginationUnit($nextUnit, min($pagination['totalPages'], $pagination['page'] + 1), $pagination, 'next', $pagination['page'] >= $pagination['totalPages']));

        return mb_convert_encoding($doc->saveHTML($root), 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * Parse shortcode attributes
     * e.g. per_page=3 tags="php, js"
     */
    public static function parseAttrs($str) {
        $atts = [];
        $pattern = '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*(\S+)/';
        preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $key = $m[1] ?: $m[3];
            $val = $m[2] ?: $m[4];
            $atts[$key] = $val;
        }
        return $atts;
    }

    /**
     * Get posts based on criteria
     */
    public function getPosts($opts = []) {
        $posts = $this->getCachedPosts(); 
        if ($posts === null) {
            $posts = [];
            if (!is_dir($this->blogDir)) return [];

            $files = glob($this->blogDir . '*.php');
            foreach ($files as $file) {
                $data = read_json_file($file);
                if (!$data) continue;
                $data['slug'] = basename($file, '.php');
                $posts[] = $data;
            }
            $this->cachePosts($posts);
        }

        $requestedLang = $opts['lang'] ?? ($this->session->has('site_lang') ? $this->session->get('site_lang') : null);
        $requestedLang = is_string($requestedLang) && trim($requestedLang) !== '' ? trim($requestedLang) : null;
        $requestedTagIds = !empty($opts['tags']) ? $this->resolveRequestedTagIds($opts['tags'], $requestedLang) : [];

        $filtered = [];
        foreach ($posts as $data) {
            // Check active
            $isActive = $data['active'] ?? true;
            if (!empty($opts['active_only']) && !$isActive) continue;
            
            // Check directory
            if (!empty($opts['dir']) && ($data['directory_id'] ?? '') !== $opts['dir']) continue;

            // Check tags (filter by intersection of requested tags and post tags)
            if (!empty($requestedTagIds)) {
                $postTags = $this->postTagIds($data['tags'] ?? [], $requestedLang);
                if (!empty($requestedTagIds)) {
                    if (empty(array_intersect($requestedTagIds, $postTags))) continue;
                }
            }

            // Filter by language
            // Post is eligible if:
            // 1. No language requested
            // 2. Post has top-level lang == requested
            // 3. Post is primary language AND (requested == primary OR has locale for requested)
            if (!empty($requestedLang)) {
                $postLang = $data['lang'] ?? null;

                if ($postLang) {
                    // Specific single-language post
                    if ($postLang !== $requestedLang) continue;
                } else {
                    if (class_exists('Settings')) {
                        $langs = Settings::getLanguages();
                        $primary = (string)($langs[0]['code'] ?? $this->getPrimaryLang());
                        if ($requestedLang !== $primary) {
                            // If not primary, check if we have locale data for it
                            if (!isset($data['locales'][$requestedLang])) continue;
                        }
                    }
                }
            }

            // Apply locale overlay to post data for list view
            if (!empty($requestedLang) && isset($data['locales'][$requestedLang]) && is_array($data['locales'][$requestedLang])) {
                $localeData = $data['locales'][$requestedLang];
                if (isset($localeData['title'])) $data['title'] = $localeData['title'];
                if (isset($localeData['excerpt'])) $data['excerpt'] = $localeData['excerpt'];
                if (isset($localeData['content'])) $data['content'] = $localeData['content'];
                if (isset($localeData['seo'])) $data['seo'] = $localeData['seo'];
                // Update slug if localized slug exists
                if (isset($localeData['slug'])) $data['slug'] = $localeData['slug'];
            }

            $data = $this->decoratePostTags($data, $requestedLang);
            $filtered[] = $data;
        }

        // Sort by date desc
        usort($filtered, function($a, $b) {
            $da = $a['date'] ?? $a['created_at'] ?? '0';
            $db = $b['date'] ?? $b['created_at'] ?? '0';
            return strtotime($db) - strtotime($da);
        });

        return $filtered;
    }

    /**
     * Get indexed posts from cache file
     */
    private function getCachedPosts(): ?array {
        $cacheFile = C_DATA_PATH . '/blog_index.php';
        if (!file_exists($cacheFile)) return null;
        
        // If file exists but is older than blog directory, invalidate (basic safety)
        if (filemtime($cacheFile) < filemtime($this->blogDir)) return null;

        return read_json_file($cacheFile);
    }

    /**
     * Save posts to cache file (minified index)
     */
    public function cachePosts(array $posts): bool {
        $cacheFile = C_DATA_PATH . '/blog_index.php';
        
        // We only store essential data for lists to keep it small
        $index = [];
        foreach ($posts as $post) {
            $index[] = [
                'slug' => $post['slug'] ?? '',
                'title' => $post['title'] ?? '',
                'excerpt' => $post['excerpt'] ?? '',
                'date' => $post['date'] ?? ($post['created_at'] ?? ''),
                'author' => $post['author'] ?? '',
                'active' => $post['active'] ?? true,
                'directory_id' => $post['directory_id'] ?? null,
                'tags' => $post['tags'] ?? [],
                'thumbnail' => $post['thumbnail'] ?? '',
                'lang' => $post['lang'] ?? null,
                'locales' => $post['locales'] ?? [], // Need locales for filtering/overlay
                'seo' => [
                    'meta_description' => $post['seo']['meta_description'] ?? ''
                ]
            ];
        }

        return write_json_file($cacheFile, $index);
    }

    /**
     * Render widget (shortcode)
     */
    public function widget($opts = []) {
        $template = $opts['template'] ?? null;
        $requestedLang = $this->getRequestedLang($opts);

        // Default options
        $opts = array_merge(['active_only' => true], $opts);
        $opts = $this->applyUrlTagFilter($opts);

        $posts = $this->getPosts($opts);
        $pagination = $this->paginatePosts($posts, $opts);
        $postsToShow = $pagination['posts'];
        $allPosts = $pagination['allPosts'];
        $total = $pagination['total'];
        $page = $pagination['page'];
        $perPage = $pagination['perPage'];
        $totalPages = $pagination['totalPages'];
        $pageParam = $pagination['pageParam'];

        // Render HTML
        $output = '';
        
        if ($template) {
             // Render using template partial
             $tplPath = MarkupFileResolver::resolveTemplateFile((string)$template);
             if ($tplPath !== null) {
                 ob_start();
                 // Expose paginated posts plus pagination metadata to partial.
                 $posts = $postsToShow;
                 include $tplPath;
                 $output = ob_get_clean();
             } else {
                 $output = '<!-- Blog template not found -->';
             }
        } else {
            // Default simple rendering
            $output .= '<div class="blog-list">';
            foreach ($postsToShow as $post) {
                $url = $this->buildPostUrl($post, $requestedLang);
                $title = $this->esc($this->getLocalizedText($post['title'] ?? '', $requestedLang));
                $date = $this->esc($post['date'] ?? '');
                $excerpt = $this->esc(BlogExcerpt::forPost($post, $requestedLang));
                $safeUrl = $this->esc($url);
                $thumbnail = !empty($post['thumbnail']) ? $this->esc($post['thumbnail']) : '';
                
                $output .= "<div class=\"blog-post\">";
                if ($thumbnail) {
                    $output .= "<div class=\"blog-thumbnail\"><a href=\"{$safeUrl}\"><img src=\"{$thumbnail}\" alt=\"{$title}\"></a></div>";
                }
                $output .= "<h3><a href=\"{$safeUrl}\">{$title}</a></h3>";
                $output .= "<small>{$date}</small>";
                $output .= "<p>{$excerpt}</p>";
                $output .= "</div>";
            }
            $output .= '</div>';
            
            $output .= $this->renderDefaultPagination($total, $perPage, $page, $totalPages, $pageParam, $requestedLang);
        }

        return $output;
    }

    /**
     * Render widget using inline loop template
     */
    public function widgetLoop($opts = [], $templateContent = '') {
        $requestedLang = $this->getRequestedLang($opts);
        
        // Default options
        $opts = array_merge(['active_only' => true], $opts);
        $opts = $this->applyUrlTagFilter($opts);

        $posts = $this->getPosts($opts);
        $pagination = $this->paginatePosts($posts, $opts);
        $postsToShow = $pagination['posts'];
        $total = $pagination['total'];
        $page = $pagination['page'];
        $perPage = $pagination['perPage'];
        $totalPages = $pagination['totalPages'];
        $pageParam = $pagination['pageParam'];

        $output = '';
        foreach ($postsToShow as $post) {
            $itemHtml = $templateContent;
            
            // Build clickable tag links if tags present
            $tagsHtml = '';
            $postTags = $this->postTagIds($post['tags'] ?? [], $requestedLang);

            if (!empty($postTags)) {
                // Determine base URL for tag links
                $requestUri = (string)$this->request->server('REQUEST_URI', '');
                $baseUrl = $opts['tag_url'] ?? ($requestUri !== '' ? strtok($requestUri, '?') : '/');
                $links = [];
                foreach ($postTags as $tagId) {
                    $tagId = trim((string)$tagId);
                    if ($tagId === '') continue;
                    $label = $this->tagLabel($tagId, $requestedLang);
                    $href = $baseUrl . (strpos($baseUrl, '?') === false ? '?tag=' : '&tag=') . urlencode($tagId);
                    $links[] = '<a href="' . $this->esc($href) . '" class="blog-tag">' . $this->esc($label) . '</a>';
                }
                $tagsHtml = implode(', ', $links);
            }

            $excerpt = $this->esc(BlogExcerpt::forPost($post, $requestedLang));

            // Standard variables
            $vars = [
                '{{title}}' => $this->esc($this->getLocalizedText($post['title'] ?? '', $requestedLang)),
                '{{date}}' => $this->esc($post['date'] ?? ''),
                '{{author}}' => $this->esc($post['author'] ?? ''),
                '{{excerpt}}' => $excerpt,
                '{{url}}' => $this->esc($this->buildPostUrl($post, $requestedLang)),
                '{{slug}}' => $this->esc($post['slug'] ?? ''),
                '{{tags}}' => $tagsHtml,
                '{{thumbnail}}' => !empty($post['thumbnail']) ? $this->esc($post['thumbnail']) : '',
                '{{thumbnail_img}}' => !empty($post['thumbnail']) ? '<img src="' . $this->esc($post['thumbnail']) . '" alt="' . $this->esc($this->getLocalizedText($post['title'] ?? '', $requestedLang)) . '">' : '',
            ];
            
            // Handle conditions {{if var}}...{{endif}}
            $itemHtml = preg_replace_callback('/\{\{if\s+(\w+)\}\}(.*?)\{\{endif\}\}/s', function($matches) use ($vars) {
                $varName = '{{' . $matches[1] . '}}';
                $content = $matches[2];
                // If variable exists and is not empty string
                return (!empty($vars[$varName])) ? $content : '';
            }, $itemHtml);

            // Replaces
            $itemHtml = str_replace(array_keys($vars), array_values($vars), $itemHtml);
            
            // Extra: handle {{image}} (extract first image from content)
            if (strpos($itemHtml, '{{image}}') !== false) {
                 $img = '';
                 $contentToSearch = $this->getLocalizedText($post['content'] ?? '', $requestedLang);
                 if (!empty($contentToSearch)) {
                     if (preg_match('/<img[^>]+src="([^">]+)"/', $contentToSearch, $m)) {
                         $src = $m[1];
                         $img = '<img src="' . $this->esc($src) . '" alt="' . $this->esc($this->getLocalizedText($post['title'] ?? '', $requestedLang)) . '">';
                     }
                 }
                 $itemHtml = str_replace('{{image}}', $img, $itemHtml);
            }

            $output .= $itemHtml;
        }

        if (($opts['pagination'] ?? '') !== 'none') {
            $output .= $this->renderDefaultPagination($total, $perPage, $page, $totalPages, $pageParam, $requestedLang);
        }

        return $output;
    }

    public function widgetPagination($opts = [], $templateContent = '') {
        $requestedLang = $this->getRequestedLang($opts);
        $opts = array_merge(['active_only' => true], $opts);
        $opts = $this->applyUrlTagFilter($opts);

        $posts = $this->getPosts($opts);
        $pagination = $this->paginatePosts($posts, $opts);

        return $this->renderCustomPagination($pagination, $templateContent, $requestedLang);
    }
}
