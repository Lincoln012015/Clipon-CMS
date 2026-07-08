<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/AssetUrlNormalizer.php';
require_once __DIR__ . '/PublicPageInstrumentation.php';

class Renderer {
    private $templatePath;
    private $data;
    private $mediaMeta = null;

    public function __construct($templatePath, $data) {
        $this->templatePath = $templatePath;
        $this->data = $data;
    }

    private function getMediaMeta() {
        if ($this->mediaMeta === null) {
            $metaFile = C_CONFIG_PATH . '/media_meta.php';
            if (file_exists($metaFile)) {
                require_once __DIR__ . '/JsonStorage.php';
                $this->mediaMeta = read_json_file($metaFile);
            } else {
                $this->mediaMeta = [];
            }
        }
        return $this->mediaMeta;
    }

    private function coreAssetVersion(string $relativePath): int {
        $normalized = '/' . ltrim($relativePath, '/');
        $fullPath = rtrim((string) C_ASSETS_PATH, '/\\') . $normalized;
        if (!is_file($fullPath)) {
            return 1;
        }

        $mtime = @filemtime($fullPath);
        return ($mtime !== false && $mtime > 0) ? (int)$mtime : 1;
    }

    public function render() {
        if (!file_exists($this->templatePath)) {
            throw new Exception("Template file not found: " . $this->templatePath);
        }

        // 1. Execute PHP within the template using Output Buffering
        // This allows users to use include, loops, and variables inside their templates
        $data = $this->data;
        if (!isset($data['site'])) {
            $data['site'] = Settings::load();
        }

        // Apply SEO defaults if missing in individual page data
        $siteName = $data['site']['site_name'] ?? 'Clipon CMS';
        if (empty($data['seo']['meta_title'])) {
            $pageTitle = $data['title'] ?? 'Clipon';
            $data['seo']['meta_title'] = $pageTitle . ' | ' . $siteName;
        }
        if (empty($data['seo']['meta_description']) && !empty($data['site']['site_description'])) {
            $data['seo']['meta_description'] = $data['site']['site_description'];
        }

        extract($data); // Make all data keys available as variables in the template
        
        ob_start();
        try {
            include $this->templatePath;
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
        $html = ob_get_clean();

        // 2. Process shortcodes if Blog service exists
        if (file_exists(__DIR__ . '/Blog.php')) {
            require_once __DIR__ . '/Blog.php';
            $blogSvc = new Blog();

            // Handle [blog_pagination]...[/blog_pagination] before the generic [blog] shortcode
            $html = preg_replace_callback('/\[blog_pagination([^\]]*)\](.*?)\[\/blog_pagination\]/s', function($m) use ($blogSvc) {
                $opts = Blog::parseAttrs($m[1] ?? '');
                if (empty($opts['lang']) && !empty($this->data['current_lang'])) {
                    $opts['lang'] = (string)$this->data['current_lang'];
                }
                $template = $m[2] ?? '';
                return $blogSvc->widgetPagination($opts, $template);
            }, $html);

            // Handle [blog_loop]...[/blog_loop]
            $html = preg_replace_callback('/\[blog_loop([^\]]*)\](.*?)\[\/blog_loop\]/s', function($m) use ($blogSvc) {
                $opts = Blog::parseAttrs($m[1] ?? '');
                if (empty($opts['lang']) && !empty($this->data['current_lang'])) {
                    $opts['lang'] = (string)$this->data['current_lang'];
                }
                $template = $m[2] ?? '';
                return $blogSvc->widgetLoop($opts, $template);
            }, $html);

            // Handle [blog]
            $html = preg_replace_callback('/\[blog(?!_)([^\]]*)\]/', function($m) use ($blogSvc) {
                $opts = Blog::parseAttrs($m[1] ?? '');
                if (empty($opts['lang']) && !empty($this->data['current_lang'])) {
                    $opts['lang'] = (string)$this->data['current_lang'];
                }
                return $blogSvc->widget($opts);
            }, $html);
        }
        
        // 3. Use DOMDocument to parse HTML and inject CMS content
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $doc->loadHTML($html);
        libxml_clear_errors();

        $session = new Session();
        $isAuthorized = $session->has('user');
        $xpath = new DOMXPath($doc);
        
        // Inject SEO, Language, and Editor scripts
        $this->injectServiceTags($doc, $isAuthorized);

        // 4. Find all 'clipon' elements and replace content
        // We support: data-key, id, and automatic index (clipon_0, clipon_1...)
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' clipon ')]");
        $index = 0;

        foreach ($nodes as $node) {
            $key = $node->getAttribute('data-key');
            if (!$key) $key = $node->getAttribute('id');
            if (!$key) $key = 'clipon_' . $index;
            
            if (isset($this->data[$key])) {
                $value = $this->localizedValue($this->data[$key]);
                
                if ($node->nodeName === 'img') {
                    if (!empty($value) && strip_tags($value) === $value) {
                        $node->setAttribute('src', $value);
                    }
                } else {
                    // Inject content as HTML fragment
                    $content = $value;
                    if (file_exists(__DIR__ . '/Sanitizer.php')) {
                        require_once __DIR__ . '/Sanitizer.php';
                        $content = Sanitizer::sanitize($content);
                    }

                    if ($node instanceof DOMElement && self::contentNeedsFallbackStyles($content, $node)) {
                        self::addClass($node, 'clipon-rich');
                    }

                    // Clear existing
                    while ($node->hasChildNodes()) {
                        $node->removeChild($node->firstChild);
                    }

                    $tempDoc = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $tempDoc->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();
                    
                    self::unwrapParagraphForTextContainer($node, $tempDoc);

                    foreach ($tempDoc->childNodes as $child) {
                        if ($child->nodeType === XML_PI_NODE || $child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                            continue;
                        }
                        $node->appendChild($doc->importNode($child, true));
                    }
                }
            }

            if ($node instanceof DOMElement && strtolower($node->nodeName) === 'a') {
                $this->injectEditableHref($node, $key);
            }
            $index++;
        }

        $this->injectReadonlyBlogMetadata($doc);

        // 5. Global media Alt localization
        $images = $doc->getElementsByTagName('img');
        $meta = $this->getMediaMeta();
        $lang = (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? 'en'));

        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $fileName = basename($src);
            if (isset($meta[$fileName]['alt'])) {
                $altData = $meta[$fileName]['alt'];
                if (is_array($altData)) {
                    $localizedAlt = $altData[$lang] ?? null;
                } else {
                    $localizedAlt = $altData;
                }

                if (is_string($localizedAlt) && $localizedAlt !== '' && (!$img->hasAttribute('alt') || empty($img->getAttribute('alt')))) {
                    $img->setAttribute('alt', $localizedAlt);
                }
            }
        }

        AssetUrlNormalizer::normalizeDocument($doc, [
            'base_path' => defined('CMS_BASE_PATH') ? CMS_BASE_PATH : '',
            'process_srcset' => true,
            'assets_only' => true,
        ]);

        $html = $doc->saveHTML();

        // 6. Inject analytics, consent banner and powered-by for public HTML.
        return PublicPageInstrumentation::inject($html);
    }

    private function localizedValue($value): string {
        if (is_array($value)) {
            $lang = (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? 'en'));
            if (isset($value[$lang])) {
                $value = $value[$lang];
            } else {
                $value = implode(', ', $value);
            }
        }

        return is_scalar($value) ? (string)$value : '';
    }

    private function injectEditableHref(DOMElement $node, string $key): void {
        $hrefKey = $key . '_href';
        if (!array_key_exists($hrefKey, $this->data)) {
            return;
        }

        if (!class_exists('Sanitizer') && file_exists(__DIR__ . '/Sanitizer.php')) {
            require_once __DIR__ . '/Sanitizer.php';
        }

        $href = $this->localizedValue($this->data[$hrefKey]);
        if (class_exists('Sanitizer')) {
            $href = Sanitizer::sanitizeLinkHref($href);
        }

        if ($href === '') {
            $node->removeAttribute('href');
            return;
        }

        $node->setAttribute('href', $href);
    }

    private static function unwrapParagraphForTextContainer(DOMNode $targetNode, DOMDocument $fragmentDoc): void {
        if (!$targetNode instanceof DOMElement || !self::isTextContainerTag($targetNode->tagName)) {
            return;
        }

        $children = [];
        foreach ($fragmentDoc->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE || $child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                continue;
            }
            if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '') {
                continue;
            }
            $children[] = $child;
        }

        if (count($children) !== 1 || !$children[0] instanceof DOMElement || strtolower($children[0]->tagName) !== 'p') {
            return;
        }

        $paragraph = $children[0];
        self::mergeClassAttribute($targetNode, $paragraph);
        self::mergeStyleAttribute($targetNode, $paragraph);

        while ($paragraph->firstChild) {
            $fragmentDoc->insertBefore($paragraph->firstChild, $paragraph);
        }
        $fragmentDoc->removeChild($paragraph);
    }

    private static function isTextContainerTag(string $tagName): bool {
        return in_array(strtolower($tagName), [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'li', 'span', 'a', 'strong', 'em', 'b', 'i', 'u', 's',
            'small', 'label', 'button'
        ], true);
    }

    private static function contentNeedsFallbackStyles(string $content, DOMElement $target): bool {
        if ($content === '') {
            return false;
        }

        if (preg_match('/<(blockquote|pre|code|ul|ol|li|table|thead|tbody|tr|th|td|hr|iframe|video|source|img)\b/i', $content)) {
            return true;
        }

        return !self::isTextContainerTag($target->tagName)
            && preg_match('/<(p|h1|h2|h3|h4|h5|h6)\b/i', $content) === 1;
    }

    private static function addClass(DOMElement $target, string $className): void {
        $classes = preg_split('/\s+/', trim($target->getAttribute('class')));
        $classes = array_values(array_unique(array_filter($classes)));
        if (!in_array($className, $classes, true)) {
            $classes[] = $className;
        }
        $target->setAttribute('class', implode(' ', $classes));
    }

    private static function mergeClassAttribute(DOMElement $target, DOMElement $source): void {
        if (!$source->hasAttribute('class')) {
            return;
        }

        $classes = preg_split('/\s+/', trim($target->getAttribute('class') . ' ' . $source->getAttribute('class')));
        $classes = array_values(array_unique(array_filter($classes)));
        if ($classes) {
            $target->setAttribute('class', implode(' ', $classes));
        }
    }

    private static function mergeStyleAttribute(DOMElement $target, DOMElement $source): void {
        if (!$source->hasAttribute('style')) {
            return;
        }

        $targetStyle = trim($target->getAttribute('style'));
        $sourceStyle = trim($source->getAttribute('style'));
        $style = trim($targetStyle . ($targetStyle !== '' && $sourceStyle !== '' ? '; ' : '') . $sourceStyle);
        if ($style !== '') {
            $target->setAttribute('style', $style);
        }
    }

    private function injectReadonlyBlogMetadata(DOMDocument $doc): void {
        if (($this->data['type'] ?? '') !== 'blog') {
            return;
        }

        $dateNode = $doc->getElementById('date');
        if ($dateNode && isset($this->data['date'])) {
            $date = (string)$this->data['date'];
            $this->replaceNodeText($dateNode, $date);
            if ($dateNode->nodeName === 'time' && $date !== '') {
                $dateNode->setAttribute('datetime', $date);
            }
        }

        $authorNode = $doc->getElementById('author');
        if ($authorNode && isset($this->data['author'])) {
            $this->replaceNodeText($authorNode, (string)$this->data['author']);
        }

        $tagsNode = $doc->getElementById('tags');
        if ($tagsNode && isset($this->data['tags'])) {
            $tags = $this->localizedBlogTagLabels($this->data['tags']);

            while ($tagsNode->hasChildNodes()) {
                $tagsNode->removeChild($tagsNode->firstChild);
            }

            foreach ($tags as $tag) {
                $span = $doc->createElement('span');
                $span->setAttribute('class', 'blog-tag');
                $span->appendChild($doc->createTextNode(trim($tag)));
                $tagsNode->appendChild($span);
            }
        }
    }

    private function localizedBlogTagLabels($rawTags): array {
        if (isset($this->data['localized_tags']) && is_array($this->data['localized_tags'])) {
            $labels = [];
            foreach ($this->data['localized_tags'] as $tag) {
                $label = is_array($tag) ? trim((string)($tag['label'] ?? '')) : trim((string)$tag);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
            if ($labels) {
                return array_values(array_unique($labels));
            }
        }

        if (is_string($rawTags)) {
            $rawTags = array_map('trim', explode(',', $rawTags));
        }
        $ids = is_array($rawTags) ? array_values(array_filter(array_map('strval', $rawTags), static fn($tag) => trim($tag) !== '')) : [];
        if (!$ids) {
            return [];
        }

        if (defined('C_DATA_PATH') && defined('C_CONTENT_PATH') && file_exists(__DIR__ . '/../admin/lib/BlogTagService.php')) {
            require_once __DIR__ . '/JsonStorage.php';
            require_once __DIR__ . '/../admin/lib/BlogTagService.php';
            $lang = (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? ''));
            $service = new BlogTagService(C_DATA_PATH . '/blog_tags.php', C_CONTENT_PATH . '/blog/');
            $labels = [];
            foreach ($service->parseTagIds($ids, $lang, false) as $id) {
                $label = trim($service->labelFor($id, $lang));
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
            if ($labels) {
                return array_values(array_unique($labels));
            }
        }

        return array_values(array_unique(array_map(static fn($tag) => trim((string)$tag), $ids)));
    }

    private function replaceNodeText(DOMNode $node, string $text): void {
        while ($node->hasChildNodes()) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($node->ownerDocument->createTextNode($text));
    }

    private function injectServiceTags($doc, $isAuthorized) {
        $htmlTag = $doc->getElementsByTagName('html')->item(0);
        if ($htmlTag) {
            $htmlTag->setAttribute('lang', (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? 'en')));
        }

        $head = $doc->getElementsByTagName('head')->item(0);
        if (!$head) return;

        // SEO Meta
        $seoData = $this->data['seo'] ?? [];
        if (!is_array($seoData)) {
            $seoData = [];
        }

        $titleText = $seoData['meta_title'] ?? ($this->data['title'] ?? '');
        $descText = $seoData['meta_description'] ?? '';

        $lang = (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? 'en'));
        if (is_array($titleText)) {
            $titleText = $titleText[$lang] ?? '';
        }
        if (is_array($descText)) {
            $descText = $descText[$lang] ?? '';
        }

        if ($titleText) {
            $titleNode = $doc->getElementsByTagName('title')->item(0) ?: $doc->createElement('title');
            $titleNode->nodeValue = (string)$titleText;
            if (!$titleNode->parentNode) $head->appendChild($titleNode);
        }

        if ($descText) {
            $xpath = new DOMXPath($doc);
            $metaDesc = $xpath->query('//meta[@name="description"]')->item(0) ?: $doc->createElement('meta');
            $metaDesc->setAttribute('name', 'description');
            $metaDesc->setAttribute('content', (string)$descText);
            if (!$metaDesc->parentNode) $head->appendChild($metaDesc);
        }

        // Canonical URL
        $canonicalPath = (string)($this->data['current_path'] ?? '/');
        $canonicalHref = $this->buildAbsoluteUrl($canonicalPath);
        if ($canonicalHref !== '') {
            $xpath = new DOMXPath($doc);
            $canonical = $xpath->query('//link[@rel="canonical"]')->item(0) ?: $doc->createElement('link');
            $canonical->setAttribute('rel', 'canonical');
            $canonical->setAttribute('href', $canonicalHref);
            if (!$canonical->parentNode) {
                $head->appendChild($canonical);
            }
        }

        // Hreflang alternates for all visitors, not only editors
        $alternates = $this->data['alternate_urls'] ?? [];
        if (is_array($alternates) && !empty($alternates)) {
            $langs = Settings::getLanguages();
            $enabledLangs = [];
            foreach ($langs as $l) {
                $code = Settings::normalizeLanguageCode((string)($l['code'] ?? ''));
                if (!empty($l['enabled']) && $code !== '' && Settings::isValidLanguageCode($code)) {
                    $enabledLangs[] = $code;
                }
            }
            if (empty($enabledLangs)) {
                $enabledLangs = ['uk'];
            }

            $primaryLang = $this->data['primary_lang'] ?? $enabledLangs[0];
            $primaryPath = $alternates[$primaryLang] ?? null;

            foreach ($alternates as $langKey => $altPath) {
                if (!is_string($altPath) || $altPath === '') {
                    continue;
                }

                $hreflang = Settings::normalizeLanguageCode((string)$langKey);
                if (!Settings::isValidLanguageCode($hreflang)) {
                    continue;
                }
                $href = $this->buildAbsoluteUrl($altPath);
                if ($href === '') {
                    continue;
                }

                $altLink = $doc->createElement('link');
                $altLink->setAttribute('rel', 'alternate');
                $altLink->setAttribute('hreflang', $hreflang);
                $altLink->setAttribute('href', $href);
                $head->appendChild($altLink);
            }

            if ($primaryPath !== null) {
                $xDefaultHref = $this->buildAbsoluteUrl($primaryPath);
                if ($xDefaultHref !== '') {
                    $xDefault = $doc->createElement('link');
                    $xDefault->setAttribute('rel', 'alternate');
                    $xDefault->setAttribute('hreflang', 'x-default');
                    $xDefault->setAttribute('href', $xDefaultHref);
                    $head->appendChild($xDefault);
                }
            }
        }

        // CSS for editor content
        $contentStyle = $doc->createElement('link');
        $contentStyle->setAttribute('rel', 'stylesheet');
        $base = defined('CMS_BASE_PATH') ? CMS_BASE_PATH : '';
        $contentStyle->setAttribute('href', $base . '/clipon/assets/css/tiptap-content.css?v=' . $this->coreAssetVersion('/css/tiptap-content.css'));
        $head->appendChild($contentStyle);

        if ($isAuthorized) {
            require_once C_CORE_DIR . '/lib/Auth.php';
            $csrfToken = getCsrfToken();
            
            $head->appendChild($doc->createElement('script', 'window.CLIPON_CSRF_TOKEN = "' . $csrfToken . '";'));
            
            $metaType = $doc->createElement('meta');
            $metaType->setAttribute('name', 'cms-type');
            $metaType->setAttribute('content', $this->data['type'] ?? 'page');
            $head->appendChild($metaType);
            
            $metaLang = $doc->createElement('meta');
            $metaLang->setAttribute('name', 'cms-lang');
            $metaLang->setAttribute('content', (string)($this->data['current_lang'] ?? ($this->data['primary_lang'] ?? 'en')));
            $head->appendChild($metaLang);

            $langs = Settings::getLanguages();
            $pageSlug = $this->data['slug'] ?? '';

            // Client-side config for language switcher
            $slugs = ['primary' => $pageSlug];
            $localesData = $this->data['locales'] ?? [];
            if (!empty($localesData)) {
                foreach ($localesData as $lc => $tr) {
                    if (!empty($tr['slug'])) $slugs[$lc] = $tr['slug'];
                }
            }
            $head->appendChild($doc->createElement('script', 'window.CLIPON_TRANSLATED_SLUGS = ' . json_encode($slugs) . ';'));
            
            $enabledLangs = array_filter($langs, fn($l) => !empty($l['enabled']));
            $head->appendChild($doc->createElement('script', 'window.CLIPON_LANGS = ' . json_encode(array_values($enabledLangs)) . ';'));

            $inlineEditorI18n = [
                'linkDialog' => __('inline_link_dialog'),
                'linkTextLabel' => __('inline_link_text_label'),
                'linkTextPlaceholder' => __('inline_link_text_placeholder'),
                'linkHrefLabel' => __('inline_link_href_label'),
                'linkHrefPlaceholder' => __('inline_link_href_placeholder'),
                'save' => __('save'),
                'saving' => __('inline_saving'),
                'saved' => __('saved'),
                'saveError' => __('inline_save_error'),
                'contentSaveError' => __('inline_content_save_error'),
                'invalidLink' => __('inline_link_invalid'),
                'saveLinkError' => __('inline_link_save_error'),
                'backToAdmin' => __('to_admin'),
                'mediaTitle' => __('inline_media_title'),
                'upload' => __('inline_upload'),
                'close' => __('close'),
                'loading' => __('loading'),
                'home' => __('home'),
                'emptyFolder' => __('inline_empty_folder'),
                'imageSaveError' => __('inline_image_save_error'),
                'uploadFailed' => __('inline_upload_failed'),
                'forbiddenSession' => __('inline_forbidden_session'),
                'addBlock' => __('inline_add_block'),
                'added' => __('added'),
            ];
            $head->appendChild($doc->createElement(
                'script',
                'window.CLIPON_INLINE_EDITOR_I18N = ' . json_encode($inlineEditorI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
            ));

            if (!empty($pageSlug)) {
                $metaSlug = $doc->createElement('meta');
                $metaSlug->setAttribute('name', 'page-slug');
                $metaSlug->setAttribute('content', $pageSlug);
                $head->appendChild($metaSlug);
            }

            $base = defined('CMS_BASE_PATH') ? CMS_BASE_PATH : '';
            $baseScript = $doc->createElement('script', 'window.CMS_BASE_PATH = "' . $base . '";');
            $head->appendChild($baseScript);

            $cmsJs = $doc->createElement('script');
            $cmsJs->setAttribute('src', $base . '/clipon/assets/js/main.js?v=' . $this->coreAssetVersion('/js/main.js'));
            $cmsJs->setAttribute('type', 'module');
            $head->appendChild($cmsJs);
        }
    }

    private function buildAbsoluteUrl(string $path): string {
        $siteUrl = c_site_url();
        $normalizedPath = '/' . ltrim((string)$path, '/');
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath);

        if ($siteUrl !== '') {
            return $siteUrl . $normalizedPath;
        }

        $request = new Request();
        $https = $request->server('HTTPS');
        $scheme = (!empty($https) && $https !== 'off') ? 'https://' : 'http://';
        $host = $request->server('HTTP_HOST') ?? ($request->server('SERVER_NAME', 'localhost'));
        return $scheme . $host . $normalizedPath;
    }

}
