<?php

class BlogService
{
    private string $blogDir;
    private string $pagesDir;
    private ?BlogTagService $tagService = null;

    public function __construct(string $blogDir, string $pagesDir)
    {
        $this->blogDir = rtrim($blogDir, '/') . '/';
        $this->pagesDir = rtrim($pagesDir, '/') . '/';

        if (class_exists('BlogTagService') && defined('C_DATA_PATH')) {
            $this->tagService = new BlogTagService(C_DATA_PATH . '/blog_tags.php', $this->blogDir);
        }
    }

    public function ensureBlogDirectory(): void
    {
        if (!is_dir($this->blogDir)) {
            mkdir($this->blogDir, 0755, true);
        }
    }

    private function resolveEffectiveLocalizedSlug(array $post, string $lang): string
    {
        return trim((string)($post['locales'][$lang]['slug'] ?? ''));
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

    public function loadPosts(): array
    {
        $posts = [];
        if (!is_dir($this->blogDir)) {
            return $posts;
        }

        foreach (glob($this->blogDir . '*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            $data = read_json_file($file);
            if (!$data) {
                continue;
            }

            $data['slug'] = $slug;
            $data['order'] = (int)($data['order'] ?? 0);
            $posts[] = $data;
        }

        usort($posts, static function ($a, $b) {
            return (int)($a['order'] ?? 0) - (int)($b['order'] ?? 0);
        });

        return $posts;
    }

    public function createPost(array $input, Session $session): array
    {
        if (!hasPermission('create_blog')) {
            return ['status' => 'error', 'message' => __('error_no_create_blog_permission') ?: 'Немає прав для створення постів.'];
        }

        $title = trim((string)($input['title'] ?? ''));
        $slugRaw = trim((string)($input['slug'] ?? ''));
        $excerpt = trim((string)($input['excerpt'] ?? ''));
        $template = trim((string)($input['template'] ?? 'blog_post.php')) ?: 'blog_post.php';

        $slug = $slugRaw !== '' ? $slugRaw : $this->slugify($title !== '' ? $title : 'post');

        if (!preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            return ['status' => 'error', 'message' => __('error_invalid_slug_format') ?: 'Некоректний формат URL (slug).'];
        }

        $base = $slug;
        $i = 1;
        while (file_exists($this->blogDir . $slug . '.php')) {
            $slug = $base . '-' . $i;
            $i++;
        }

        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));

        $tags = $this->normalizeTags($input['tags'] ?? [], $primaryLang);

        $postData = [
            'author' => $session->get('user'),
            'created_at' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'tags' => $tags,
            'active' => true,
            'directory_id' => null,
            'template' => $template,
            'thumbnail' => trim((string)($input['thumbnail'] ?? '')),
            'url' => '/blog/' . $slug,
            'locales' => [
                $primaryLang => [
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'seo' => [
                        'meta_title' => '',
                        'meta_description' => '',
                    ],
                    'content' => [],
                    'slug' => $slug,
                ],
            ],
        ];

        if (!$this->atomicWriteAndRebuild($this->blogDir . $slug . '.php', $postData)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        $this->syncTags($tags, $primaryLang);

        return ['status' => 'success', 'message' => __('post_created_success') ?: 'Пост успішно створено.'];
    }

    public function deletePost(string $slug): bool
    {
        $jsonFile = $this->blogDir . $slug . '.php';
        if (file_exists($jsonFile)) {
            @unlink($jsonFile);
        }
        $this->invalidateBlogIndexCache();
        return $this->rebuildRouteMap();
    }

    public function updatePost(array $input, Session $session, bool $isAjax): array
    {
        $slug = (string)($input['slug'] ?? '');
        $jsonFile = $this->blogDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return ['status' => 'error', 'message' => __('error_post_not_found') ?: 'Пост не знайдено.'];
        }

        $post = read_json_file($jsonFile);
        $newSlug = trim((string)($input['new_slug'] ?? ''));

        $editingLang = trim((string)($input['editing_lang'] ?? ''));
        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
        $enabledLangCodes = array_values(array_map(static fn($l) => (string)($l['code'] ?? ''), $activeLangs));
        if ($editingLang === '' || !in_array($editingLang, $enabledLangCodes, true)) {
            $editingLang = $primaryLang;
        }

        $title = trim((string)($input['title'] ?? ''));
        $excerpt = trim((string)($input['excerpt'] ?? ''));
        $metaTitle = trim((string)($input['meta_title'] ?? ''));
        $metaDesc = trim((string)($input['meta_description'] ?? ''));
        $slugPattern = '/^[a-z0-9\-_]+$/i';

        if (!isset($post['locales']) || !is_array($post['locales'])) {
            $post['locales'] = [];
        }
        if (!isset($post['locales'][$editingLang]) || !is_array($post['locales'][$editingLang])) {
            $post['locales'][$editingLang] = [];
        }

        $post['locales'][$editingLang]['title'] = $title;
        $post['locales'][$editingLang]['excerpt'] = $excerpt;
        if ($newSlug !== '') {
            if (!preg_match($slugPattern, $newSlug)) {
                return ['status' => 'error', 'message' => __('error_invalid_slug_format') ?: 'Некоректний формат slug.'];
            }
            if (!$this->isLocalizedSlugAvailable($editingLang, $newSlug, $slug)) {
                return ['status' => 'error', 'message' => __('error_slug_exists') ?: 'Пост з таким slug вже існує.'];
            }
        }
        $post['locales'][$editingLang]['slug'] = $newSlug;
        
        if (!isset($post['locales'][$editingLang]['seo']) || !is_array($post['locales'][$editingLang]['seo'])) {
            $post['locales'][$editingLang]['seo'] = [];
        }
        $post['locales'][$editingLang]['seo']['meta_title'] = $metaTitle;
        $post['locales'][$editingLang]['seo']['meta_description'] = $metaDesc;

        // Keep stored schema strictly locales-only.
        unset($post['translations'], $post['content'], $post['title'], $post['excerpt'], $post['seo']);

        $post['author'] = (string)($input['author'] ?? $session->get('user'));
        $post['date'] = (string)($input['date'] ?? date('Y-m-d'));
        $post['modified'] = date('Y-m-d H:i:s');
        $post['template'] = (string)($input['template'] ?? 'blog_post.php');
        $post['thumbnail'] = trim((string)($input['thumbnail'] ?? ''));

        $post['tags'] = $this->normalizeTags($input['tags'] ?? [], $editingLang);

        if ($editingLang === $primaryLang && $newSlug !== '' && $newSlug !== $slug) {
            if (!preg_match('/^[a-z0-9\-_]+$/i', $newSlug)) {
                return ['status' => 'error', 'message' => __('error_invalid_slug_format') ?: 'Некоректний формат slug.'];
            }
            if (file_exists($this->blogDir . $newSlug . '.php')) {
                return ['status' => 'error', 'message' => __('error_slug_exists') ?: 'Пост з таким slug вже існує.'];
            }

            $newJsonFile = $this->blogDir . $newSlug . '.php';
            rename($jsonFile, $newJsonFile);
            $jsonFile = $newJsonFile;
            $slug = $newSlug;
        }

        $post['url'] = '/blog/' . $slug;

        if (!$this->atomicWriteAndRebuild($jsonFile, $post)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        $this->syncTags($post['tags'], $editingLang);

        if ($isAjax) {
            return ['status' => 'success', 'message' => __('ok')];
        }

        return ['status' => 'success', 'message' => __('post_updated_success') ?: 'Пост оновлено.'];
    }

    public function duplicatePost(string $slug): array
    {
        if (!hasPermission('create_blog')) {
            return ['status' => 'error', 'message' => __('error_no_create_blog_permission') ?: 'Немає прав для створення постів.'];
        }

        if (!preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            return ['status' => 'error', 'message' => __('error_invalid_slug_format') ?: 'Некоректний формат slug.'];
        }

        $jsonFile = $this->blogDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return ['status' => 'error', 'message' => __('error_post_not_found') ?: 'Пост не знайдено.'];
        }

        $post = read_json_file($jsonFile);
        
        // Generate new slug
        $base = $slug;
        $i = 1;
        $newSlug = $base;
        while (file_exists($this->blogDir . $newSlug . '.php')) {
            $newSlug = $base . '-' . $i;
            $i++;
        }

        // Create duplicate post data
        $duplicatePost = $post;
        $duplicatePost['slug'] = $newSlug;
        $duplicatePost['url'] = '/blog/' . $newSlug;
        $duplicatePost['created_at'] = date('Y-m-d H:i:s');
        $duplicatePost['modified'] = date('Y-m-d H:i:s');
        $duplicatePost['date'] = date('Y-m-d');
        $duplicatePost['active'] = false; // Set to inactive by default

        // Update localized slugs if they exist
        if (isset($duplicatePost['locales']) && is_array($duplicatePost['locales'])) {
            foreach ($duplicatePost['locales'] as $lang => $localeData) {
                if (isset($localeData['slug'])) {
                    $localeBase = $localeData['slug'];
                    $j = 1;
                    $newLocaleSlug = $localeBase;
                    while (!$this->isLocalizedSlugAvailable($lang, $newLocaleSlug, $slug)) {
                        $newLocaleSlug = $localeBase . '-' . $j;
                        $j++;
                    }
                    $duplicatePost['locales'][$lang]['slug'] = $newLocaleSlug;
                }
            }
        }

        if (!$this->atomicWriteAndRebuild($this->blogDir . $newSlug . '.php', $duplicatePost)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        return ['status' => 'success', 'message' => __('post_duplicated_success') ?: 'Пост успішно дубльовано.', 'slug' => $newSlug];
    }

    public function toggleActive(string $slug): array
    {
        $jsonFile = $this->blogDir . $slug . '.php';
        if (!file_exists($jsonFile)) {
            return ['status' => 'error'];
        }

        $post = read_json_file($jsonFile);
        $post['active'] = !($post['active'] ?? true);
        if (!$this->atomicWriteAndRebuild($jsonFile, $post)) {
            return ['status' => 'error', 'message' => __('system_error') . ': route map rebuild failed'];
        }

        return ['status' => 'success', 'active' => $post['active']];
    }

    public function reorder(array $items): void
    {
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'post') {
                continue;
            }

            $jsonFile = $this->blogDir . (string)($item['id'] ?? '') . '.php';
            if (!file_exists($jsonFile)) {
                continue;
            }

            $postData = read_json_file($jsonFile);
            $postData['directory_id'] = $item['parent'] ?? null;
            $postData['order'] = (int)($item['order'] ?? 0);
            write_json_file($jsonFile, $postData);
        }
        $this->invalidateBlogIndexCache();
    }

    public function buildTree(array $directories, array $posts): array
    {
        foreach ($directories as &$dir) {
            $dir['order'] = (int)($dir['order'] ?? 0);
            $dir['children'] = [];
            $dir['posts'] = [];
        }
        unset($dir);

        $dirMap = [];
        foreach ($directories as $dir) {
            $dirMap[$dir['id']] = $dir;
        }

        $rootPosts = [];
        foreach ($posts as $post) {
            $dirId = $post['directory_id'] ?? null;
            if ($dirId && isset($dirMap[$dirId])) {
                $dirMap[$dirId]['posts'][] = $post;
            } else {
                $rootPosts[] = $post;
            }
        }

        $rootDirs = $this->buildDirTree($dirMap, null);

        return [
            'root_dirs' => $rootDirs,
            'root_posts' => $rootPosts,
        ];
    }

    private function buildDirTree(array $dirMap, ?string $parentId): array
    {
        $dirs = [];
        foreach ($dirMap as $id => $dir) {
            $dirParent = $dir['parent'] ?? null;
            if ($dirParent != $parentId) {
                continue;
            }

            $dir['children'] = $this->buildDirTree($dirMap, (string)$id);
            $dirs[] = $dir;
        }

        usort($dirs, static function ($a, $b) {
            return (int)($a['order'] ?? 0) - (int)($b['order'] ?? 0);
        });

        return $dirs;
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

        $this->invalidateBlogIndexCache();
        return true;
    }

    private function normalizeTags($raw, ?string $lang = null): array
    {
        if ($this->tagService) {
            return $this->tagService->parseTagIds($raw, $lang, true);
        }

        $parts = is_array($raw) ? $raw : explode(',', (string)$raw);
        return array_values(array_filter(array_map('trim', $parts), static fn($tag) => $tag !== ''));
    }

    private function syncTags(array $tags, ?string $lang = null): void
    {
        if ($this->tagService) {
            $this->tagService->syncFromPostTags($tags, $lang);
        }
    }

    private function invalidateBlogIndexCache(): void
    {
        if (defined('C_DATA_PATH')) {
            @unlink(C_DATA_PATH . '/blog_index.php');
        }
    }

    private function isLocalizedSlugAvailable(string $lang, string $slug, string $currentSlug): bool
    {
        $slugLower = strtolower($slug);
        $configuredLangs = Settings::getLanguages();
        $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));

        foreach (glob($this->blogDir . '*.php') ?: [] as $file) {
            $data = read_json_file($file);
            if (!$data) {
                continue;
            }

            $fileSlug = basename($file, '.php');
            if ($fileSlug === $currentSlug) {
                continue;
            }

            if ($lang === $primaryLang && strtolower($fileSlug) === $slugLower) {
                return false;
            }

            $transSlug = $this->resolveEffectiveLocalizedSlug($data, $lang);
            if ($transSlug !== '' && strtolower($transSlug) === $slugLower) {
                return false;
            }
        }

        return true;
    }

    private function slugify(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return 'post-' . time();
        }

        $s = mb_strtolower($s, 'UTF-8');
        $map = [
            'а' => 'a','б' => 'b','в' => 'v','г' => 'h','ґ' => 'g','д' => 'd','е' => 'e','є' => 'ie','ж' => 'zh','з' => 'z','и' => 'y','і' => 'i','ї' => 'i','й' => 'j',
            'к' => 'k','л' => 'l','м' => 'm','н' => 'n','о' => 'o','п' => 'p','р' => 'r','с' => 's','т' => 't','у' => 'u','ф' => 'f','х' => 'kh','ц' => 'ts','ч' => 'ch',
            'ш' => 'sh','щ' => 'shch','ь' => '','ю' => 'iu','я' => 'ia',
        ];

        $s = strtr($s, $map);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9\-_]+/i', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        $s = trim((string)$s, '-_');

        return $s !== '' ? $s : 'post-' . time();
    }
}
