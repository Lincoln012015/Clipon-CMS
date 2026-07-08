<?php

class PageService
{
    private string $pagesDir;
    private string $conversionConfigFile;
    private PageDirectoryService $directoryService;

    public function __construct(string $pagesDir, string $conversionConfigFile, PageDirectoryService $directoryService)
    {
        $this->pagesDir = rtrim($pagesDir, '/') . '/';
        $this->conversionConfigFile = $conversionConfigFile;
        $this->directoryService = $directoryService;
    }

    public function loadPages(): array
    {
        $pages = [];
        if (!is_dir($this->pagesDir)) {
            return $pages;
        }

        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            $data = read_json_file($file);
            if (!$data) {
                continue;
            }

            $data = $this->normalizePageForAdmin($data, $slug);
            $data['slug'] = $slug;
            $data['order'] = (int)($data['order'] ?? 0);
            $pages[] = $data;
        }

        return $pages;
    }

    public function getAllPagesForParentSelect(string $currentSlug, Session $session): array
    {
        $pages = [];
        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
        $adminLang = (string)$session->get('admin_lang', $primaryLang);

        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $pageSlug = basename($file, '.php');
            if ($pageSlug === $currentSlug || $pageSlug === 'index') {
                continue;
            }

            $pageData = read_json_file($file);
            $pageData = $this->normalizePageForAdmin($pageData, $pageSlug);
            $url = (string)($pageData['url'] ?? ('/' . $pageSlug));

            $title = trim((string)($pageData['locales'][$adminLang]['title'] ?? ''));
            if ($title === '') {
                $title = trim((string)($pageData['locales'][$primaryLang]['title'] ?? ''));
            }
            if ($title === '') {
                $title = $pageSlug;
            }

            $pages[$pageSlug] = [
                'title' => $title,
                'url' => $url,
            ];
        }

        return $pages;
    }

    public function getTemplates(): array
    {
        $templatesDir = __DIR__ . '/../../../templates/';
        $templates = [];
        if (!is_dir($templatesDir)) {
            return $templates;
        }

        foreach (glob($templatesDir . '*.php') ?: [] as $file) {
            $templates[] = basename($file);
        }

        return $templates;
    }

    private function getEnabledLanguageCodes(): array
    {
        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $codes = array_values(array_map(static fn($l) => (string)($l['code'] ?? ''), $activeLangs));
        $codes = array_values(array_filter($codes, static fn($code) => $code !== ''));

        if (empty($codes)) {
            $codes = [(string)(Settings::load()['language'] ?? 'en')];
        }

        return $codes;
    }

    private function normalizeEditingLang(string $editingLang, string $primaryLang, array $enabledLangCodes): string
    {
        if ($editingLang === '' || !in_array($editingLang, $enabledLangCodes, true)) {
            return $primaryLang;
        }

        return $editingLang;
    }

    private function getPrimaryLanguageCode(): string
    {
        $enabledLangCodes = $this->getEnabledLanguageCodes();
        return (string)($enabledLangCodes[0] ?? (Settings::load()['language'] ?? 'en'));
    }

    private function legacyLocaleFromRoot(array $page, string $slug): array
    {
        $locale = [];

        foreach (['title', 'excerpt'] as $field) {
            if (array_key_exists($field, $page)) {
                $locale[$field] = $page[$field];
            }
        }

        if (isset($page['seo']) && is_array($page['seo'])) {
            $locale['seo'] = $page['seo'];
        }

        if (isset($page['content']) && is_array($page['content'])) {
            $locale['content'] = $page['content'];
        }

        $locale['slug'] = $slug;

        return $locale;
    }

    private function normalizePageForAdmin(array $page, string $slug): array
    {
        $primaryLang = $this->getPrimaryLanguageCode();

        if (!isset($page['locales']) || !is_array($page['locales'])) {
            $page['locales'] = [];
        }

        if (!isset($page['locales'][$primaryLang]) || !is_array($page['locales'][$primaryLang]) || empty($page['locales'][$primaryLang])) {
            $legacyLocale = $this->legacyLocaleFromRoot($page, $slug);
            if (!empty($legacyLocale)) {
                $page['locales'][$primaryLang] = $legacyLocale;
            }
        }

        if (empty($page['locales'][$primaryLang]['slug'])) {
            $page['locales'][$primaryLang]['slug'] = $slug;
        }

        return $page;
    }

    private function resolveEffectiveLocalizedSlug(array $page, string $langCode): string
    {
        return trim((string)($page['locales'][$langCode]['slug'] ?? ''));
    }

    public function createPage(array $input, Session $session, array $conversionTypes): array
    {
        $title = trim((string)($input['title'] ?? ''));
        $metaTitle = trim((string)($input['meta_title'] ?? ''));
        $metaDesc = trim((string)($input['meta_description'] ?? ''));

        $slug = trim((string)($input['slug'] ?? ''));
        $template = trim((string)($input['template'] ?? ''));
        $parentId = trim((string)($input['parent_id'] ?? '')) ?: null;

        if ($title === '' || $slug === '' || $template === '') {
            return ['status' => 'error', 'message' => __('fill_all_fields')];
        }

        // Sanitize SEO metadata: strip HTML tags to prevent XSS in meta tags
        $metaTitle = strip_tags($metaTitle);
        $metaDesc = strip_tags($metaDesc);

        if (!preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            return ['status' => 'error', 'message' => __('error_invalid_slug_format_detailed')];
        }

        if (file_exists($this->pagesDir . $slug . '.php')) {
            return ['status' => 'error', 'message' => __('error_slug_exists')];
        }

        $defaultConvTypes = self::getEnabledConversionTypes($conversionTypes);
        $defaultConvType = $defaultConvTypes[0] ?? 'conversion';

        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));

        $pageData = [
            'author' => $session->get('user'),
            'modified' => date('Y-m-d H:i:s'),
            'active' => true,
            'is_home' => false,
            'conversion' => false,
            'conversion_type' => $defaultConvType,
            'directory_id' => null,
            'parent_id' => $parentId,
            'template' => $template,
            'locales' => [
                $primaryLang => [
                    'title' => $title,
                    'seo' => [
                        'meta_title' => $metaTitle,
                        'meta_description' => $metaDesc,
                    ],
                    'content' => [],
                    'slug' => $slug,
                ],
            ],
        ];

        $pageData['url'] = $parentId ? $this->buildUrlFromParent($slug, $parentId) : '/' . $slug;

        if (!$this->atomicWriteAndRebuild($this->pagesDir . $slug . '.php', $pageData)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }
        $this->rebuildConversionMap();

        return ['status' => 'success', 'message' => __('page_created_success')];
    }

    public function deletePage(string $slug): bool
    {
        // Backup all pages/history before multi-file changes.
        $backupDir = $this->createPagesBackup();

        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $childData = read_json_file($file);
            if (($childData['parent_id'] ?? null) === $slug) {
                $childSlug = basename($file, '.php');
                $childData['parent_id'] = null;
                write_json_file($file, $childData);
                $this->updatePageUrlRecursive($childSlug);
            }
        }

        $jsonFile = $this->pagesDir . $slug . '.php';
        if (file_exists($jsonFile)) {
            @unlink($jsonFile);
        }

        if (!$this->rebuildRouteMap()) {
            // rollback
            $this->restorePagesBackup($backupDir);
            return false;
        }
        $this->rebuildConversionMap();

        // remove backup
        $this->removePagesBackup($backupDir);
        return true;
    }

    public function copyPage(string $slug, Session $session, array $conversionTypes): bool
    {
        $jsonFile = $this->pagesDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return false;
        }

        $page = read_json_file($jsonFile);
        $page = $this->normalizePageForAdmin($page, $slug);
        $newSlug = $slug . '_copy';
        $counter = 1;
        while (file_exists($this->pagesDir . $newSlug . '.php')) {
            $newSlug = $slug . '_copy' . $counter;
            $counter++;
        }

        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));

        if (!isset($page['locales']) || !is_array($page['locales'])) {
            $page['locales'] = [];
        }
        if (!isset($page['locales'][$primaryLang]) || !is_array($page['locales'][$primaryLang])) {
            $page['locales'][$primaryLang] = [];
        }
        if (!empty($page['locales'][$primaryLang]['title'])) {
            $page['locales'][$primaryLang]['title'] .= ' (Копія)';
        }

        $defaultConvTypes = self::getEnabledConversionTypes($conversionTypes);
        $page['conversion'] = false;
        $page['conversion_type'] = $defaultConvTypes[0] ?? 'conversion';
        $page['slug'] = $newSlug;
        $page['author'] = $session->get('user');
        $page['modified'] = date('Y-m-d H:i:s');
        $page['url'] = '/' . $newSlug;

        unset($page['translations'], $page['content'], $page['title'], $page['seo']);

        if (!$this->atomicWriteAndRebuild($this->pagesDir . $newSlug . '.php', $page)) {
            return false;
        }
        $this->rebuildConversionMap();

        return true;
    }

    public function updatePage(array $input, Session $session, array $conversionTypes, bool $isAjax): array
    {
        $slug = trim((string)($input['slug'] ?? ''));
        if ($slug === '') {
            return ['status' => 'error', 'message' => __('error_invalid_parameters')];
        }

        $jsonFile = $this->pagesDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return ['status' => 'error', 'message' => __('error_page_not_found_or_inactive')];
        }

        $page = read_json_file($jsonFile);
        $page = $this->normalizePageForAdmin($page, $slug);
        $newSlug = trim((string)($input['new_slug'] ?? ''));
        $enabledConvTypes = self::getEnabledConversionTypes($conversionTypes);

        $editingLang = trim((string)($input['editing_lang'] ?? ''));
        $enabledLangCodes = $this->getEnabledLanguageCodes();
        $primaryLang = (string)($enabledLangCodes[0] ?? (Settings::load()['language'] ?? 'en'));
        $editingLang = $this->normalizeEditingLang($editingLang, $primaryLang, $enabledLangCodes);

        $title = trim((string)($input['title'] ?? ''));
        $metaTitle = trim((string)($input['meta_title'] ?? ''));
        $metaDesc = trim((string)($input['meta_description'] ?? ''));

        // Sanitize SEO metadata: strip HTML tags to prevent XSS in meta tags
        $metaTitle = strip_tags($metaTitle);
        $metaDesc = strip_tags($metaDesc);

        if (!isset($page['locales']) || !is_array($page['locales'])) {
            $page['locales'] = [];
        }
        if (!isset($page['locales'][$editingLang]) || !is_array($page['locales'][$editingLang])) {
            $page['locales'][$editingLang] = [];
        }

        $page['locales'][$editingLang]['title'] = $title;
        $page['locales'][$editingLang]['slug'] = $newSlug;

        if ($newSlug !== '') {
            if (!preg_match('/^[a-z0-9\-_]+$/i', $newSlug)) {
                return ['status' => 'error', 'message' => __('error_invalid_slug_format')];
            }
            if ($this->localizedSlugExists($newSlug, $slug)) {
                return ['status' => 'error', 'message' => __('error_localized_slug_exists')];
            }
        }
        
        if (!isset($page['locales'][$editingLang]['seo']) || !is_array($page['locales'][$editingLang]['seo'])) {
            $page['locales'][$editingLang]['seo'] = [];
        }
        $page['locales'][$editingLang]['seo']['meta_title'] = $metaTitle;
        $page['locales'][$editingLang]['seo']['meta_description'] = $metaDesc;

        // Keep stored schema strictly locales-only.
        unset($page['translations'], $page['content'], $page['title'], $page['seo']);

        $page['conversion'] = !empty($input['is_conversion']);
        $postedType = trim((string)($input['conversion_type'] ?? 'conversion'));
        $page['conversion_type'] = in_array($postedType, $enabledConvTypes, true) ? $postedType : ($enabledConvTypes[0] ?? 'conversion');
        $page['parent_id'] = trim((string)($input['parent_id'] ?? '')) ?: null;
        $page['author'] = $session->get('user');
        $page['modified'] = date('Y-m-d H:i:s');

        if (!empty($page['is_home'])) {
            $page['url'] = '/';
        } elseif (!empty($page['parent_id'])) {
            $targetSlug = ($editingLang === $primaryLang) ? ($newSlug ?: $slug) : $slug;
            $page['url'] = $this->buildUrlFromParent($targetSlug, (string)$page['parent_id']);
        } else {
            $targetSlug = ($editingLang === $primaryLang) ? ($newSlug ?: $slug) : $slug;
            $page['url'] = '/' . $targetSlug;
        }

        if ($editingLang === $primaryLang && $newSlug !== '' && $newSlug !== $slug) {
            $renameResult = $this->renamePrimarySlug($slug, $newSlug, $jsonFile);
            $jsonFile = $renameResult['json_file'];
            $slug = $renameResult['slug'];
        }

        if (!$this->atomicWriteAndRebuild($jsonFile, $page)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        $history = new History();
        $history->save($newSlug ?: $slug, $page, true);
        $this->rebuildConversionMap();

        if ($isAjax) {
            return ['status' => 'success', 'message' => __('ok')];
        }

        return ['status' => 'success', 'message' => __('page_updated_success')];
    }

    public function reorder(array $items): bool
    {
        $directories = $this->directoryService->getDirectories();
        $directories = $this->directoryService->reorderDirectories($directories, $items);

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'page') {
                continue;
            }

            $jsonFile = $this->pagesDir . (string)($item['id'] ?? '') . '.php';
            if (!file_exists($jsonFile)) {
                continue;
            }

            $pageSlug = (string)($item['id'] ?? '');
            $pageData = read_json_file($jsonFile);
            $pageData = $this->normalizePageForAdmin($pageData, $pageSlug);
            $pageData['directory_id'] = $item['parent'] ?? null;
            $pageData['order'] = (int)($item['order'] ?? 0);
            write_json_file($jsonFile, $pageData);
        }

        $this->directoryService->saveDirectories($directories);
        return true;
    }

    public function getHistory(string $slug): array
    {
        if ($slug === '') {
            return ['status' => 'error', 'message' => __('error_slug_required')];
        }

        $history = new History();
        $data = $history->get($slug);

        $pageFile = $this->pagesDir . $slug . '.php';
        $currentHash = '';
        if (file_exists($pageFile)) {
            $currentPageData = read_json_file($pageFile);
            $currentHash = $history->computeDataHash($currentPageData);
        }

        foreach ($data as &$item) {
            $itemHash = $item['data_hash'] ?? '';
            $item['is_current'] = ($currentHash !== '' && $itemHash !== '' && hash_equals($itemHash, $currentHash));
            unset($item['data_hash']);
        }
        unset($item);

        return [
            'status' => 'success',
            'history' => $data,
            'can_restore' => hasPermission('restore_versions'),
            'can_view' => hasPermission('view_versions'),
        ];
    }

    public function restoreVersion(string $slug, string $timestamp): array
    {
        if ($slug === '' || $timestamp === '') {
            return ['status' => 'error', 'message' => __('error_invalid_parameters')];
        }

        $history = new History();
        $versionData = $history->getVersion($slug, $timestamp);
        if (!$versionData) {
            return ['status' => 'error', 'message' => __('error_version_not_found')];
        }

        $pageFile = $this->pagesDir . $slug . '.php';
        if (!$this->atomicWriteAndRebuild($pageFile, $versionData)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        return ['status' => 'success', 'message' => __('restore_success')];
    }

    public function toggleActive(string $slug): array
    {
        if ($slug === '') {
            return ['status' => 'error'];
        }

        $jsonFile = $this->pagesDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return ['status' => 'error'];
        }

        $page = read_json_file($jsonFile);
        $page = $this->normalizePageForAdmin($page, $slug);
        $page['active'] = !($page['active'] ?? true);
        if (!$this->atomicWriteAndRebuild($jsonFile, $page)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        return ['status' => 'success', 'active' => $page['active']];
    }

    public function setHomepage(string $slug): array
    {
        if ($slug === '') {
            return ['status' => 'error'];
        }

        // Backup all pages/history before multi-file changes.
        $backupDir = $this->createPagesBackup();

        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $oldSlug = basename($file, '.php');
            $pageData = read_json_file($file);
            $pageData = $this->normalizePageForAdmin($pageData, $oldSlug);
            if (!empty($pageData['is_home'])) {
                $pageData['is_home'] = false;
                if (!empty($pageData['parent_id'])) {
                    $pageData['url'] = $this->buildUrlFromParent($oldSlug, (string)$pageData['parent_id']);
                } else {
                    $pageData['url'] = '/' . $oldSlug;
                }
                write_json_file($file, $pageData);
            }
        }

        $jsonFile = $this->pagesDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            $this->restorePagesBackup($backupDir);
            return ['status' => 'error'];
        }

        $page = read_json_file($jsonFile);
        $page = $this->normalizePageForAdmin($page, $slug);
        $page['is_home'] = true;
        $page['url'] = '/';
        write_json_file($jsonFile, $page);

        if (!$this->rebuildRouteMap()) {
            // rollback
            $this->restorePagesBackup($backupDir);
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        $this->removePagesBackup($backupDir);
        return ['status' => 'success'];
    }

    public function rebuildRouteMap(): bool
    {
        /** @var RouteMap $routeMap */
        $routeMap = registry()->get('route_map');
        return (bool)$routeMap->rebuild($this->pagesDir);
    }

    private function atomicWriteAndRebuild(string $filePath, array $data): bool
    {
        $existed = file_exists($filePath);
        $backup = $filePath . '.bak.' . time();
        $lockFile = $filePath . '.lock';
        $lockHandle = null;

        try {
            // Acquire exclusive lock on lock file to prevent concurrent writes
            $lockHandle = @fopen($lockFile, 'c');
            if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
                // Couldn't acquire lock; another process may be writing
                return false;
            }

            if ($existed) {
                @copy($filePath, $backup);
            }

            if (!write_json_file($filePath, $data)) {
                if ($existed && file_exists($backup)) {
                    @copy($backup, $filePath);
                    @unlink($backup);
                }
                return false;
            }

            if (!$this->rebuildRouteMap()) {
                if ($existed && file_exists($backup)) {
                    @copy($backup, $filePath);
                    @unlink($backup);
                } else {
                    @unlink($filePath);
                }
                return false;
            }

            if (file_exists($backup)) {
                @unlink($backup);
            }

            return true;
        } finally {
            if ($lockHandle !== null) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($lockFile);
            }
        }
    }

    /**
     * Create a backup directory with copies of all pages and history to allow rollback.
     * @return string Backup directory path
     */
    private function createPagesBackup(): string
    {
        $backupRoot = C_CONTENT_PATH . '/.backup_pages_' . time() . '_' . bin2hex(random_bytes(4)) . '/';
        if (!is_dir($backupRoot)) {
            @mkdir($backupRoot, 0755, true);
        }

        // Copy pages
        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            @copy($file, $backupRoot . basename($file));
        }

        // Copy history if exists
        $historyRoot = C_CONTENT_PATH . '/history/';
        if (is_dir($historyRoot)) {
            $this->copyDirectory($historyRoot, $backupRoot . 'history/');
        }

        return $backupRoot;
    }

    private function restorePagesBackup(string $backupRoot): void
    {
        if (!is_dir($backupRoot)) return;

        foreach (glob($backupRoot . '*.php') ?: [] as $file) {
            @copy($file, $this->pagesDir . basename($file));
        }

        if (is_dir($backupRoot . 'history/')) {
            $this->copyDirectory($backupRoot . 'history/', C_CONTENT_PATH . '/history/');
        }
    }

    private function removePagesBackup(string $backupRoot): void
    {
        if (!is_dir($backupRoot)) return;
        // remove files
        foreach (glob($backupRoot . '*') ?: [] as $f) {
            if (is_dir($f)) {
                $this->rrmdir($f);
            } else {
                @unlink($f);
            }
        }
        @rmdir($backupRoot);
    }

    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            if (is_dir($s)) {
                $this->copyDirectory($s, $d);
            } else {
                @copy($s, $d);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function rebuildConversionMap(): void
    {
        $map = [];
        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $data = read_json_file($file);
            if (!($data['conversion'] ?? false)) {
                continue;
            }

            $slug = basename($file, '.php');
            $url = $data['url'] ?? '/' . $slug;
            $type = $data['conversion_type'] ?? 'conversion';
            $map[$url] = $type;
        }

        write_json_file($this->conversionConfigFile, [
            'pages' => $map,
            'updated_at' => date('c'),
        ]);
    }

    public static function getEnabledConversionTypes(array $conversionTypes): array
    {
        $enabled = [];
        foreach ($conversionTypes as $item) {
            if (!empty($item['key']) && !empty($item['enabled'])) {
                $enabled[] = $item['key'];
            }
        }
        if (empty($enabled)) {
            $enabled[] = 'conversion';
        }

        return $enabled;
    }

    public function getLocalizedFrontendUrl(array $page, string $langCode, string $primaryLang): string
    {
        $isHome = !empty($page['is_home']);
        $primaryUrl = (string)($page['url'] ?? ('/' . ($page['slug'] ?? '')));
        $primaryUrl = '/' . ltrim($primaryUrl, '/');

        if ($langCode === $primaryLang) {
            if ($isHome) {
                return '/';
            }

            return preg_replace('#/+#', '/', $primaryUrl);
        }

        if ($isHome) {
            return '/' . $langCode . '/';
        }

        $localizedSlug = trim((string)($page['locales'][$langCode]['slug'] ?? ''));
        if ($localizedSlug === '') {
            return '';
        }

        $parentId = trim((string)($page['parent_id'] ?? ''));
        if ($parentId !== '') {
            return $this->buildUrlFromParent($localizedSlug, $parentId, $langCode);
        }

        return preg_replace('#/+#', '/', '/' . $langCode . '/' . $localizedSlug);
    }

    private function buildUrlFromParent(string $slug, string $parentId, ?string $langCode = null): string
    {
        $enabledLangCodes = $this->getEnabledLanguageCodes();
        $primaryLang = (string)($enabledLangCodes[0] ?? (Settings::load()['language'] ?? 'en'));
        $effectiveLang = $langCode ?? $primaryLang;
        if (!in_array($effectiveLang, $enabledLangCodes, true)) {
            $effectiveLang = $primaryLang;
        }

        $path = [$slug];
        $currentParentId = $parentId;
        $visited = [];

        while ($currentParentId && !in_array($currentParentId, $visited, true)) {
            $visited[] = $currentParentId;
            $parentFile = $this->pagesDir . $currentParentId . '.php';

            if (!file_exists($parentFile)) {
                break;
            }

            $parentData = read_json_file($parentFile);
            $parentData = $this->normalizePageForAdmin($parentData, $currentParentId);
            $parentSlug = basename($currentParentId, '.php');
            if ($effectiveLang !== $primaryLang) {
                $localizedParentSlug = trim((string)($parentData['locales'][$effectiveLang]['slug'] ?? ''));
                if ($localizedParentSlug === '') {
                    return '';
                }

                $parentSlug = $localizedParentSlug;
            }

            if ($parentSlug !== 'notindex' && $parentSlug !== 'index') {
                array_unshift($path, $parentSlug);
            }

            $currentParentId = $parentData['parent_id'] ?? null;
        }

        $built = '/' . implode('/', $path);
        if ($effectiveLang !== $primaryLang) {
            $built = '/' . $effectiveLang . $built;
        }

        return preg_replace('#/+#', '/', $built);
    }

    private function updatePageUrlRecursive(string $slug): void
    {
        $file = $this->pagesDir . $slug . '.php';
        if (!file_exists($file)) {
            return;
        }

        $data = read_json_file($file);
        $data = $this->normalizePageForAdmin($data, $slug);
        if (!empty($data['is_home'])) {
            $data['url'] = '/';
        } elseif (!empty($data['parent_id'])) {
            $data['url'] = $this->buildUrlFromParent($slug, (string)$data['parent_id']);
        } else {
            $data['url'] = '/' . $slug;
        }

        write_json_file($file, $data);

        foreach (glob($this->pagesDir . '*.php') ?: [] as $childFile) {
            $childSlug = basename($childFile, '.php');
            $childData = read_json_file($childFile);
            $childData = $this->normalizePageForAdmin($childData, $childSlug);
            if (($childData['parent_id'] ?? null) === $slug) {
                $this->updatePageUrlRecursive($childSlug);
            }
        }
    }

    private function localizedSlugExists(string $slug, string $currentPrimarySlug): bool
    {
        if ($slug === '') {
            return false;
        }

        // Case-insensitive slug comparison to prevent file system conflicts on macOS/Windows
        $slugLower = strtolower($slug);

        foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
            $primarySlug = basename($file, '.php');
            if ($primarySlug === $currentPrimarySlug) {
                continue;
            }

            // Перевірка на конфлікт з основним шлагом іншої сторінки (case-insensitive)
            if (strtolower($primarySlug) === $slugLower) {
                return true;
            }

            $data = read_json_file($file);
            $data = $this->normalizePageForAdmin($data, $primarySlug);
            $locales = $data['locales'] ?? [];
            if (!is_array($locales)) {
                continue;
            }

            // Перевірка по всіх мовах цієї сторінки (case-insensitive)
            foreach ($locales as $lCode => $lData) {
                $translatedSlug = trim((string)($lData['slug'] ?? ''));
                if ($translatedSlug !== '' && strtolower($translatedSlug) === $slugLower) {
                    return true;
                }
            }
        }

        return false;
    }

    private function renamePrimarySlug(string $oldSlug, string $newSlug, string $jsonFile): array
    {
        $newJsonFile = $this->pagesDir . $newSlug . '.php';
        if (!file_exists($newJsonFile)) {
            // Backup all pages/history before performing multi-file changes so we can rollback on failure.
            $backupDir = $this->createPagesBackup();

            $renameOk = @rename($jsonFile, $newJsonFile);
            $jsonFile = $newJsonFile;

            $historyRootDir = C_CONTENT_PATH . '/history/';
            $oldHistoryDir = $historyRootDir . $oldSlug . '/';
            $newHistoryDir = $historyRootDir . $newSlug . '/';

            if (is_dir($oldHistoryDir)) {
                if (!is_dir($newHistoryDir)) {
                    @rename($oldHistoryDir, $newHistoryDir);
                } else {
                    foreach (glob($oldHistoryDir . '*.php') ?: [] as $historyFile) {
                        $targetTimestamp = (int)basename($historyFile, '.php');
                        if ($targetTimestamp <= 0) {
                            $targetTimestamp = time();
                        }

                        $targetFile = $newHistoryDir . $targetTimestamp . '.php';
                        while (file_exists($targetFile)) {
                            $targetTimestamp++;
                            $targetFile = $newHistoryDir . $targetTimestamp . '.php';
                        }

                        @rename($historyFile, $targetFile);
                    }
                    @rmdir($oldHistoryDir);
                }
            }

            foreach (glob($this->pagesDir . '*.php') ?: [] as $file) {
                $childSlug = basename($file, '.php');
                $childData = read_json_file($file);
                $childData = $this->normalizePageForAdmin($childData, $childSlug);
                if (($childData['parent_id'] ?? null) === $oldSlug) {
                    $childData['parent_id'] = $newSlug;
                    write_json_file($file, $childData);
                    $this->updatePageUrlRecursive($childSlug);
                }
            }

            // If subsequent rebuild fails, restore from backup
            if (!$this->rebuildRouteMap()) {
                $this->restorePagesBackup($backupDir);
                return ['slug' => $oldSlug, 'json_file' => $jsonFile];
            }

            $this->removePagesBackup($backupDir);

            $oldSlug = $newSlug;
        }

        return [
            'slug' => $oldSlug,
            'json_file' => $jsonFile,
        ];
    }
}
