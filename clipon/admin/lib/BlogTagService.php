<?php

require_once __DIR__ . '/../../lib/Sanitizer.php';

class BlogTagService
{
    private string $tagsFile;
    private string $blogDir;

    public function __construct(string $tagsFile, string $blogDir)
    {
        $this->tagsFile = $tagsFile;
        $this->blogDir = rtrim($blogDir, '/') . '/';
    }

    public function getTags(array $posts = [], ?string $lang = null): array
    {
        $lang = $this->resolveLang($lang);
        $map = $this->readTagMap();

        foreach ($posts as $post) {
            foreach ($this->parseTagIds($post['tags'] ?? [], $lang, false) as $id) {
                if (!isset($map[$id])) {
                    $map[$id] = $this->buildTagRecord($id, $id, $lang);
                }
                $map[$id]['count'] = (int)($map[$id]['count'] ?? 0) + 1;
                if (!isset($map[$id]['posts']) || !is_array($map[$id]['posts'])) {
                    $map[$id]['posts'] = [];
                }
                $summary = $this->postSummary($post, $lang);
                if ($summary['slug'] !== '') {
                    $map[$id]['posts'][$summary['slug']] = $summary;
                }
            }
        }

        foreach ($map as &$tag) {
            $tag = $this->presentTag($tag, $lang);
        }
        unset($tag);

        uasort($map, static function ($a, $b) {
            return strnatcasecmp((string)($a['label'] ?? $a['id'] ?? ''), (string)($b['label'] ?? $b['id'] ?? ''));
        });

        return array_values($map);
    }

    public function syncFromPostTags(array $tags, ?string $lang = null): void
    {
        $lang = $this->resolveLang($lang);
        $this->parseTagIds($tags, $lang, true);
    }

    public function syncFromPosts(array $posts, ?string $lang = null): void
    {
        $tags = [];
        foreach ($posts as $post) {
            foreach ($this->splitRawTags($post['tags'] ?? []) as $tag) {
                $tags[] = $tag;
            }
        }

        $this->syncFromPostTags($tags, $lang);
    }

    public function createTag(string $name, ?string $lang = null): array
    {
        $lang = $this->resolveLang($lang);
        $name = $this->cleanName($name);
        if ($name === '') {
            return ['status' => 'error', 'message' => __('blog_tag_required')];
        }

        $map = $this->readTagMap();
        $existingId = $this->resolveTagIdFromMap($name, $lang, $map);
        if ($existingId !== '') {
            return ['status' => 'success', 'message' => __('blog_tag_exists'), 'tag' => $this->presentTag($map[$existingId], $lang)];
        }

        $id = $this->uniqueId($this->slugify($name), $map);
        $record = $this->buildTagRecord($id, $name, $lang);
        $map[$id] = $record;
        $this->writeTags(array_values($map));

        return ['status' => 'success', 'message' => __('blog_tag_created'), 'tag' => $this->presentTag($record, $lang)];
    }

    public function renameTag(string $id, string $name, ?string $lang = null): array
    {
        return $this->updateTagLocale($id, $lang, $name);
    }

    public function updateTagLocale(string $id, ?string $lang, string $label): array
    {
        $id = $this->normalizeId($id);
        $lang = $this->resolveLang($lang);
        $label = $this->cleanName($label);
        if ($id === '' || $label === '') {
            return ['status' => 'error', 'message' => __('blog_tag_invalid')];
        }

        $map = $this->readTagMap();
        if (!isset($map[$id])) {
            return ['status' => 'error', 'message' => __('blog_tag_not_found')];
        }

        $nextLabelKey = $this->slugify($label);
        foreach ($map as $existingId => $tag) {
            if ($existingId === $id) {
                continue;
            }
            if ($this->slugify((string)($tag['labels'][$lang] ?? '')) === $nextLabelKey) {
                return ['status' => 'error', 'message' => __('blog_tag_duplicate')];
            }
        }

        $map[$id]['labels'][$lang] = $label;
        $this->writeTags(array_values($map));
        $this->invalidateBlogIndexCache();

        return ['status' => 'success', 'message' => __('blog_tag_renamed'), 'tag' => $this->presentTag($map[$id], $lang)];
    }

    public function updateTagTranslations(string $id, array $labels): array
    {
        $id = $this->normalizeId($id);
        if ($id === '') {
            return ['status' => 'error', 'message' => __('blog_tag_invalid')];
        }

        $map = $this->readTagMap();
        if (!isset($map[$id])) {
            return ['status' => 'error', 'message' => __('blog_tag_not_found')];
        }

        foreach ($this->activeLanguageCodes() as $lang) {
            $label = $this->cleanName((string)($labels[$lang] ?? ''));
            if ($label === '') {
                continue;
            }
            $labelKey = $this->slugify($label);
            foreach ($map as $existingId => $tag) {
                if ($existingId !== $id && $this->slugify((string)($tag['labels'][$lang] ?? '')) === $labelKey) {
                    return ['status' => 'error', 'message' => __('blog_tag_duplicate')];
                }
            }
            $map[$id]['labels'][$lang] = $label;
        }

        $this->writeTags(array_values($map));
        $this->invalidateBlogIndexCache();

        return ['status' => 'success', 'message' => __('blog_tag_renamed'), 'tag' => $this->presentTag($map[$id], $this->resolveLang(null))];
    }

    public function deleteTag(string $id): array
    {
        $id = $this->normalizeId($id);
        if ($id === '') {
            return ['status' => 'error', 'message' => __('blog_tag_invalid')];
        }

        $map = $this->readTagMap();
        $deletedTag = $map[$id] ?? null;
        unset($map[$id]);
        $this->removeTagFromPosts($id, is_array($deletedTag) ? $deletedTag : null);
        $this->writeTags(array_values($map));
        $this->invalidateBlogIndexCache();

        return ['status' => 'success', 'message' => __('blog_tag_deleted')];
    }

    public function parseTagIds($raw, ?string $lang = null, bool $createMissing = true): array
    {
        $lang = $this->resolveLang($lang);
        $map = $this->readTagMap();
        $tags = [];
        $changed = false;

        foreach ($this->splitRawTags($raw) as $value) {
            $id = $this->resolveTagIdFromMap($value, $lang, $map);
            if ($id === '' && $createMissing) {
                $label = $this->cleanName($value);
                if ($label === '') {
                    continue;
                }
                $id = $this->uniqueId($this->slugify($label), $map);
                $map[$id] = $this->buildTagRecord($id, $label, $lang);
                $changed = true;
            }
            if ($id === '') {
                $id = $this->slugify($value);
            }
            if ($id !== '' && !in_array($id, $tags, true)) {
                $tags[] = $id;
            }
        }

        if ($changed) {
            $this->writeTags(array_values($map));
        }

        return $tags;
    }

    public function resolveTagId(string $value, ?string $lang = null): string
    {
        return $this->resolveTagIdFromMap($value, $this->resolveLang($lang), $this->readTagMap());
    }

    public function labelFor(string $id, ?string $lang = null): string
    {
        $tag = $this->readTagMap()[$this->normalizeId($id)] ?? null;
        if (!$tag) {
            return $id;
        }

        return $this->localizedValue($tag['labels'] ?? [], $this->resolveLang($lang), (string)$tag['id']);
    }

    private function removeTagFromPosts(string $id, ?array $deletedTag = null): void
    {
        $removeKeys = [$id => true, $this->slugify($id) => true];
        if ($deletedTag) {
            foreach (($deletedTag['labels'] ?? []) as $label) {
                $key = $this->slugify((string)$label);
                if ($key !== '') {
                    $removeKeys[$key] = true;
                }
            }
        }

        foreach (glob($this->blogDir . '*.php') ?: [] as $file) {
            $post = read_json_file($file);
            if (!$post) {
                continue;
            }

            $next = [];
            $changed = false;
            foreach ($this->splitRawTags($post['tags'] ?? []) as $tagValue) {
                $tagId = $this->resolveTagId($tagValue);
                $key = $tagId !== '' ? $tagId : $this->slugify($tagValue);
                if (isset($removeKeys[$key])) {
                    $changed = true;
                    continue;
                }
                $next[] = $key;
            }

            if ($changed) {
                $post['tags'] = $next;
                write_json_file($file, $post);
            }
        }
    }

    private function readTagMap(): array
    {
        $map = [];
        foreach ($this->readStoredTags() as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $record = $this->normalizeStoredTag($tag);
            if ($record !== null) {
                $map[$record['id']] = $record;
            }
        }

        return $map;
    }

    private function normalizeStoredTag(array $tag): ?array
    {
        $primary = $this->primaryLang();
        $labels = isset($tag['labels']) && is_array($tag['labels']) ? $tag['labels'] : [];

        $id = $this->normalizeId((string)($tag['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        if (empty($labels[$primary])) {
            $labels[$primary] = $id;
        }

        $cleanLabels = [];
        foreach ($labels as $lang => $label) {
            $lang = $this->normalizeLangCode((string)$lang);
            $label = $this->cleanName((string)$label);
            if ($lang !== '' && $label !== '') {
                $cleanLabels[$lang] = $label;
            }
        }

        return [
            'id' => $id,
            'labels' => $cleanLabels,
            'count' => 0,
        ];
    }

    private function readStoredTags(): array
    {
        $data = read_json_file($this->tagsFile);
        if (isset($data['tags']) && is_array($data['tags'])) {
            return $data['tags'];
        }

        return is_array($data) ? $data : [];
    }

    private function writeTags(array $tags): bool
    {
        $clean = [];
        $seen = [];
        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $record = $this->normalizeStoredTag($tag);
            if ($record === null || isset($seen[$record['id']])) {
                continue;
            }
            $seen[$record['id']] = true;
            unset($record['count']);
            $clean[] = $record;
        }

        usort($clean, static function ($a, $b) {
            $aLabel = reset($a['labels']) ?: $a['id'];
            $bLabel = reset($b['labels']) ?: $b['id'];
            return strnatcasecmp((string)$aLabel, (string)$bLabel);
        });

        return write_json_file($this->tagsFile, ['tags' => $clean]);
    }

    private function presentTag(array $tag, string $lang): array
    {
        $label = $this->localizedValue($tag['labels'] ?? [], $lang, (string)$tag['id']);

        return [
            'id' => (string)$tag['id'],
            'name' => $label,
            'label' => $label,
            'labels' => $tag['labels'] ?? [],
            'count' => (int)($tag['count'] ?? 0),
            'posts' => array_values($tag['posts'] ?? []),
        ];
    }

    private function postSummary(array $post, string $lang): array
    {
        $primary = $this->primaryLang();
        $slug = (string)($post['slug'] ?? '');
        $title = trim((string)($post['locales'][$lang]['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($post['locales'][$primary]['title'] ?? ''));
        }
        if ($title === '') {
            $title = trim((string)($post['title'] ?? ''));
        }
        if ($title === '') {
            $title = $slug !== '' ? $slug : 'post';
        }

        return [
            'slug' => $slug,
            'title' => $title,
            'url' => $slug !== '' ? ('/blog/' . $slug) : '',
        ];
    }

    private function buildTagRecord(string $id, string $label, string $lang): array
    {
        $id = $this->normalizeId($id) ?: 'tag';
        $label = $this->cleanName($label) ?: $id;
        return [
            'id' => $id,
            'labels' => [$lang => $label],
            'count' => 0,
        ];
    }

    private function splitRawTags($raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string)$raw);
        $tags = [];
        $seen = [];
        foreach ($parts as $part) {
            $value = $this->cleanName((string)$part);
            if ($value === '') {
                continue;
            }
            $key = $this->slugify($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = $value;
        }

        return $tags;
    }

    private function resolveTagIdFromMap(string $value, string $lang, array $map): string
    {
        $value = $this->cleanName($value);
        if ($value === '') {
            return '';
        }
        $normalized = $this->normalizeId($value);
        if ($normalized !== '' && isset($map[$normalized])) {
            return $normalized;
        }
        $slug = $this->slugify($value);
        foreach ($map as $id => $tag) {
            if ($this->slugify((string)($tag['labels'][$lang] ?? '')) === $slug) {
                return $id;
            }
        }

        foreach ($map as $id => $tag) {
            foreach (($tag['labels'] ?? []) as $label) {
                if ($this->slugify((string)$label) === $slug) {
                    return $id;
                }
            }
        }

        return '';
    }

    private function localizedValue(array $values, string $lang, string $fallback): string
    {
        $primary = $this->primaryLang();
        $value = trim((string)($values[$lang] ?? ''));
        if ($value !== '') {
            return $value;
        }
        $value = trim((string)($values[$primary] ?? ''));
        if ($value !== '') {
            return $value;
        }
        foreach ($values as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $fallback;
    }

    private function uniqueId(string $base, array $map): string
    {
        $base = $this->normalizeId($base) ?: 'tag';
        $id = $base;
        $i = 2;
        while (isset($map[$id])) {
            $id = $base . '-' . $i;
            $i++;
        }

        return $id;
    }

    private function activeLanguageCodes(): array
    {
        if (class_exists('Settings')) {
            $langs = Settings::getLanguages();
            $codes = [];
            foreach ($langs as $lang) {
                if (!empty($lang['enabled']) && !empty($lang['code'])) {
                    $codes[] = (string)$lang['code'];
                }
            }
            if (!empty($codes)) {
                return $codes;
            }
        }

        return ['en'];
    }

    private function primaryLang(): string
    {
        $codes = $this->activeLanguageCodes();
        return $codes[0] ?? 'en';
    }

    private function resolveLang(?string $lang): string
    {
        $lang = $this->normalizeLangCode((string)$lang);
        return $lang !== '' ? $lang : $this->primaryLang();
    }

    private function normalizeLangCode(string $lang): string
    {
        if (class_exists('Settings')) {
            return Settings::normalizeLanguageCode($lang);
        }
        $lang = trim(str_replace('_', '-', $lang));
        if ($lang === '') {
            return '';
        }
        $parts = explode('-', $lang);
        $base = strtolower($parts[0] ?? '');
        if (!preg_match('/^[a-z]{2}$/', $base)) {
            return '';
        }
        if (count($parts) === 1) {
            return $base;
        }
        $region = strtoupper($parts[1]);
        return preg_match('/^[A-Z]{2}$/', $region) ? $base . '-' . $region : '';
    }

    private function cleanName(string $name): string
    {
        return Sanitizer::plainText($name);
    }

    private function normalizeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9\-_]+/', '', $id) ?? '';
        return trim($id, '-_');
    }

    private function slugify(string $name): string
    {
        $name = $this->cleanName($name);
        $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ie','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'i','й'=>'j',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch',
            'ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'iu','я'=>'ia',
        ];
        $name = strtr($name, $map);
        if (function_exists('iconv')) {
            $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        }
        $name = preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $name;
        $name = trim(preg_replace('/-+/', '-', $name) ?? $name, '-');

        return $name !== '' ? strtolower($name) : 'tag';
    }

    private function invalidateBlogIndexCache(): void
    {
        if (defined('C_DATA_PATH')) {
            @unlink(C_DATA_PATH . '/blog_index.php');
        }
    }
}
