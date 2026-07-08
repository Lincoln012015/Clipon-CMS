<?php

require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/Settings.php';

class ContentTemplateImporter
{
    private string $pagesDir;
    private string $templatesDir;

    public function __construct(?string $pagesDir = null, ?string $templatesDir = null)
    {
        $this->pagesDir = rtrim($pagesDir ?? (C_CONTENT_PATH . '/pages'), '/\\');
        $this->templatesDir = rtrim($templatesDir ?? C_TEMPLATES_PATH, '/\\');
    }

    /**
     * @return array<string,string>
     */
    public static function scanTemplateContent(string $templatePath): array
    {
        if (!is_file($templatePath)) {
            return [];
        }

        $html = file_get_contents($templatePath);
        if ($html === false || trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $loaded = $doc->loadHTML($encoded);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' clipon ')]");
        if (!$nodes) {
            return [];
        }

        $content = [];
        $index = 0;
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $key = $node->getAttribute('data-key');
            if ($key === '') {
                $key = $node->getAttribute('id');
            }
            if ($key === '') {
                $key = 'clipon_' . $index;
            }

            $content[$key] = self::extractNodeValue($node);
            $index++;
        }

        return $content;
    }

    /**
     * @return array{ok:bool,error:?string,template_keys:int,pages_scanned:int,pages_updated:int,primary_added:int,secondary_added:int,kept:int}
     */
    public function importForTemplate(string $template): array
    {
        $template = $this->normalizeTemplateName($template);
        if ($template === '') {
            return $this->result(false, 'Invalid template name.');
        }

        $templateContent = self::scanTemplateContent($this->templatesDir . '/' . $template);
        if (empty($templateContent)) {
            return $this->result(false, 'No editable content keys found in template.');
        }

        $languages = Settings::getLanguages();
        $enabledLanguages = array_values(array_filter($languages, static fn($lang) => !empty($lang['enabled'])));
        $primaryLang = Settings::normalizeLanguageCode((string)($enabledLanguages[0]['code'] ?? (Settings::load()['language'] ?? 'en')));
        if ($primaryLang === '') {
            $primaryLang = 'en';
        }

        $enabledCodes = [];
        foreach ($enabledLanguages as $lang) {
            $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
            if ($code !== '') {
                $enabledCodes[] = $code;
            }
        }
        if (empty($enabledCodes)) {
            $enabledCodes[] = $primaryLang;
        }

        $stats = $this->result(true, null, count($templateContent));

        foreach (glob($this->pagesDir . '/*.php') ?: [] as $file) {
            $data = read_json_file($file);
            if (empty($data)) {
                continue;
            }
            if ($this->normalizeTemplateName((string)($data['template'] ?? '')) !== $template) {
                continue;
            }

            $stats['pages_scanned']++;
            $slug = basename($file, '.php');
            $changed = false;

            if (!isset($data['locales']) || !is_array($data['locales'])) {
                $data['locales'] = [];
            }
            if (!isset($data['locales'][$primaryLang]) || !is_array($data['locales'][$primaryLang])) {
                $data['locales'][$primaryLang] = $this->legacyLocaleFromRoot($data, $slug);
                $changed = true;
            }
            if (!isset($data['locales'][$primaryLang]['content']) || !is_array($data['locales'][$primaryLang]['content'])) {
                $data['locales'][$primaryLang]['content'] = [];
                $changed = true;
            }
            if (empty($data['locales'][$primaryLang]['slug'])) {
                $data['locales'][$primaryLang]['slug'] = $slug;
                $changed = true;
            }

            foreach ($templateContent as $key => $value) {
                if (!array_key_exists($key, $data['locales'][$primaryLang]['content'])) {
                    $data['locales'][$primaryLang]['content'][$key] = $value;
                    $stats['primary_added']++;
                    $changed = true;
                } else {
                    $stats['kept']++;
                }
            }

            foreach ($enabledCodes as $code) {
                if ($code === $primaryLang) {
                    continue;
                }
                if (!isset($data['locales'][$code]) || !is_array($data['locales'][$code])) {
                    $data['locales'][$code] = $this->emptyLocale($slug);
                    $changed = true;
                }
                if (!isset($data['locales'][$code]['content']) || !is_array($data['locales'][$code]['content'])) {
                    $data['locales'][$code]['content'] = [];
                    $changed = true;
                }
                foreach ($templateContent as $key => $_value) {
                    if (!array_key_exists($key, $data['locales'][$code]['content'])) {
                        $data['locales'][$code]['content'][$key] = '';
                        $stats['secondary_added']++;
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                continue;
            }

            unset($data['translations'], $data['content'], $data['title'], $data['seo']);
            $data['modified'] = date('Y-m-d H:i:s');
            if (write_json_file($file, $data)) {
                $stats['pages_updated']++;
            }
        }

        return $stats;
    }

    private static function extractNodeValue(DOMElement $node): string
    {
        $tag = strtolower($node->tagName);
        if ($tag === 'img') {
            return $node->getAttribute('src');
        }
        if (in_array($tag, ['input', 'textarea'], true)) {
            return $node->getAttribute('value');
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return html_entity_decode(trim($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalizeTemplateName(string $template): string
    {
        $template = trim(str_replace('\\', '/', $template));
        if ($template === '' || strpos($template, '..') !== false || strpos($template, "\0") !== false) {
            return '';
        }

        return ltrim($template, '/');
    }

    /**
     * @return array<string,mixed>
     */
    private function legacyLocaleFromRoot(array $data, string $slug): array
    {
        $locale = $this->emptyLocale($slug);
        foreach (['title', 'excerpt'] as $field) {
            if (array_key_exists($field, $data)) {
                $locale[$field] = $data[$field];
            }
        }
        if (isset($data['seo']) && is_array($data['seo'])) {
            $locale['seo'] = $data['seo'];
        }
        if (isset($data['content']) && is_array($data['content'])) {
            $locale['content'] = $data['content'];
        }

        return $locale;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyLocale(string $slug): array
    {
        return [
            'title' => '',
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
            ],
            'content' => [],
            'slug' => $slug,
        ];
    }

    /**
     * @return array{ok:bool,error:?string,template_keys:int,pages_scanned:int,pages_updated:int,primary_added:int,secondary_added:int,kept:int}
     */
    private function result(bool $ok, ?string $error, int $templateKeys = 0): array
    {
        return [
            'ok' => $ok,
            'error' => $error,
            'template_keys' => $templateKeys,
            'pages_scanned' => 0,
            'pages_updated' => 0,
            'primary_added' => 0,
            'secondary_added' => 0,
            'kept' => 0,
        ];
    }
}
