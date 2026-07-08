<?php

require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/ContentTemplateImporter.php';

class ContentLocaleInitializer
{
    private string $pagesDir;
    private string $blogDir;
    private string $templatesDir;

    public function __construct(?string $pagesDir = null, ?string $blogDir = null, ?string $templatesDir = null)
    {
        $this->pagesDir = rtrim($pagesDir ?? (C_CONTENT_PATH . '/pages'), '/\\');
        $this->blogDir = rtrim($blogDir ?? (C_CONTENT_PATH . '/blog'), '/\\');
        $this->templatesDir = rtrim($templatesDir ?? (C_TEMPLATES_PATH), '/\\');
    }

    /**
     * @param array<int,string> $languageCodes
     * @return array{pages:int,blog:int,files:int,languages:int}
     */
    public function initialize(array $languageCodes): array
    {
        $languageCodes = $this->normalizeLanguageCodes($languageCodes);
        if (empty($languageCodes)) {
            return $this->stats(0, 0, 0, 0);
        }

        $pageStats = $this->initializeDirectory($this->pagesDir, $languageCodes, 'page');
        $blogStats = $this->initializeDirectory($this->blogDir, $languageCodes, 'blog');

        return $this->stats(
            $pageStats['items'],
            $blogStats['items'],
            $pageStats['files'] + $blogStats['files'],
            count($languageCodes)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $oldLanguages
     * @param array<int,array<string,mixed>> $newLanguages
     * @return array<int,string>
     */
    public static function detectAddedLanguageCodes(array $oldLanguages, array $newLanguages): array
    {
        $oldCodes = [];
        foreach ($oldLanguages as $lang) {
            if (!is_array($lang)) {
                continue;
            }

            $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
            if ($code !== '') {
                $oldCodes[$code] = true;
            }
        }

        $added = [];
        foreach ($newLanguages as $lang) {
            if (!is_array($lang)) {
                continue;
            }

            $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
            if ($code !== '' && !isset($oldCodes[$code])) {
                $added[$code] = $code;
            }
        }

        return array_values($added);
    }

    /**
     * @param array<int,string> $languageCodes
     * @return array{items:int,files:int}
     */
    private function initializeDirectory(string $dir, array $languageCodes, string $type): array
    {
        $items = 0;
        $files = 0;

        if (!is_dir($dir)) {
            return ['items' => 0, 'files' => 0];
        }

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            $data = read_json_file($file);
            if (empty($data)) {
                continue;
            }

            if (!isset($data['locales']) || !is_array($data['locales'])) {
                $data['locales'] = [];
            }

            $changed = false;
            foreach ($languageCodes as $code) {
                if (isset($data['locales'][$code]) && is_array($data['locales'][$code])) {
                    continue;
                }

                $data['locales'][$code] = $this->emptyLocale($type, $slug, $data);
                $items++;
                $changed = true;
            }

            if (!$changed) {
                continue;
            }

            $data['modified'] = date('Y-m-d H:i:s');
            if (write_json_file($file, $data)) {
                $files++;
            }
        }

        return ['items' => $items, 'files' => $files];
    }

    /**
     * @param array<int,string> $languageCodes
     * @return array<int,string>
     */
    private function normalizeLanguageCodes(array $languageCodes): array
    {
        $normalized = [];
        foreach ($languageCodes as $code) {
            $code = Settings::normalizeLanguageCode((string)$code);
            if ($code !== '' && Settings::isValidLanguageCode($code)) {
                $normalized[$code] = $code;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyLocale(string $type, string $slug, array $data): array
    {
        $locale = [
            'title' => '',
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
            ],
            'content' => $this->emptyContentMap($this->findSourceContent($data)),
            'slug' => $slug,
        ];

        if ($type === 'blog') {
            $locale['excerpt'] = '';
        }

        return $locale;
    }

    /**
     * @return array<string,mixed>
     */
    private function findSourceContent(array $data): array
    {
        if (isset($data['locales']) && is_array($data['locales'])) {
            foreach ($data['locales'] as $locale) {
                if (isset($locale['content']) && is_array($locale['content']) && !empty($locale['content'])) {
                    return $locale['content'];
                }
            }
        }

        if (isset($data['content']) && is_array($data['content']) && !empty($data['content'])) {
            return $data['content'];
        }

        return $this->extractTemplateContent((string)($data['template'] ?? ''));
    }

    /**
     * @param array<string,mixed> $content
     * @return array<string,mixed>
     */
    private function emptyContentMap(array $content): array
    {
        $empty = [];
        foreach ($content as $key => $value) {
            $empty[$key] = is_array($value) ? $this->emptyContentMap($value) : '';
        }

        return $empty;
    }

    /**
     * @return array<string,string>
     */
    private function extractTemplateContent(string $template): array
    {
        $template = trim($template);
        if ($template === '' || strpos($template, '..') !== false || strpos($template, "\0") !== false) {
            return [];
        }

        $templatePath = $this->templatesDir . '/' . ltrim($template, '/\\');
        if (!is_file($templatePath)) {
            return [];
        }

        return ContentTemplateImporter::scanTemplateContent($templatePath);
    }

    /**
     * @return array{pages:int,blog:int,files:int,languages:int}
     */
    private function stats(int $pages, int $blog, int $files, int $languages): array
    {
        return [
            'pages' => $pages,
            'blog' => $blog,
            'files' => $files,
            'languages' => $languages,
        ];
    }
}
