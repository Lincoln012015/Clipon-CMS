<?php

require_once __DIR__ . '/JsonStorage.php';

class PrimaryLanguageMigrator
{
    private string $pagesDir;
    private string $blogDir;
    private string $historyDir;
    private string $logsDir;

    public function __construct(
        ?string $pagesDir = null,
        ?string $blogDir = null,
        ?string $historyDir = null,
        ?string $logsDir = null
    ) {
        $this->pagesDir = rtrim($pagesDir ?? (C_CONTENT_PATH . '/pages'), '/\\');
        $this->blogDir = rtrim($blogDir ?? (C_CONTENT_PATH . '/blog'), '/\\');
        $this->historyDir = rtrim($historyDir ?? (C_CONTENT_PATH . '/history'), '/\\');
        $this->logsDir = rtrim($logsDir ?? (C_LOGS_PATH . '/migrations'), '/\\');
    }

    /**
     * @return array{success:bool,dry_run:bool,changed:bool,report_path:?string,error:?string,warnings:array<int,string>,stats:array<string,int>,plan:array<string,mixed>}
     */
    public function migrate(string $oldPrimary, string $newPrimary, bool $dryRun = false): array
    {
        $oldPrimary = Settings::normalizeLanguageCode($oldPrimary);
        $newPrimary = Settings::normalizeLanguageCode($newPrimary);

        if ($oldPrimary === '' || $newPrimary === '') {
            return $this->result(false, $dryRun, false, null, 'Invalid language code for primary migration.');
        }

        if ($oldPrimary === $newPrimary) {
            return $this->result(true, $dryRun, false, null, null);
        }

        $warnings = [];
        $pagePlan = $this->buildSlugPlan($this->pagesDir, $newPrimary, $warnings);
        if ($pagePlan['error'] !== null) {
            return $this->result(false, $dryRun, false, null, $pagePlan['error'], $warnings);
        }

        $blogPlan = $this->buildSlugPlan($this->blogDir, $newPrimary, $warnings);
        if ($blogPlan['error'] !== null) {
            return $this->result(false, $dryRun, false, null, $blogPlan['error'], $warnings);
        }

        $renamePages = $this->extractRenames($pagePlan['plan']);
        $renameBlog = $this->extractRenames($blogPlan['plan']);
        $hasChanges = !empty($renamePages) || !empty($renameBlog);

        $stats = [
            'pages_processed' => count($pagePlan['plan']),
            'blog_processed' => count($blogPlan['plan']),
            'pages_renamed' => count($renamePages),
            'blog_renamed' => count($renameBlog),
            'history_moved' => 0,
        ];

        $plan = [
            'old_primary' => $oldPrimary,
            'new_primary' => $newPrimary,
            'pages' => $pagePlan['plan'],
            'blog' => $blogPlan['plan'],
        ];

        if ($dryRun) {
            return $this->result(true, true, $hasChanges, null, null, $warnings, $stats, $plan);
        }

        $timestamp = date('Ymd_His');
        $reportDir = $this->logsDir . '/primary_lang_swap_' . $timestamp;
        $backupDir = $reportDir . '/backup';
        $reportPath = $reportDir . '/report.json';

        if (!$this->ensureDir($backupDir)) {
            return $this->result(false, false, false, null, 'Failed to create migration backup directory.');
        }

        try {
            $this->backupScope($this->pagesDir, $backupDir . '/pages');
            $this->backupScope($this->blogDir, $backupDir . '/blog');
            $this->backupDirectorySnapshot($this->historyDir, $backupDir . '/history');

            $this->applyRenamesTwoPhase($this->pagesDir, $renamePages);
            $this->applyRenamesTwoPhase($this->blogDir, $renameBlog);

            $this->migratePagesData($oldPrimary, $newPrimary, $renamePages);
            $this->migrateBlogData($oldPrimary, $newPrimary, $renameBlog);

            $stats['history_moved'] += $this->migrateHistoryDirs($renamePages);
            $stats['history_moved'] += $this->migrateHistoryDirs($renameBlog);

            /** @var object $routeMap */
            $routeMap = registry()->get('route_map');
            $routeMap->clearCache();
            if (!$routeMap->rebuild($this->pagesDir . '/')) {
                $error = $routeMap->getLastError() ?: 'Route map rebuild failed after primary language migration.';
                throw new RuntimeException($error);
            }

            $reportData = [
                'timestamp' => date('c'),
                'old_primary' => $oldPrimary,
                'new_primary' => $newPrimary,
                'stats' => $stats,
                'warnings' => $warnings,
                'plan' => $plan,
            ];
            file_put_contents($reportPath, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $this->result(true, false, $hasChanges, $reportPath, null, $warnings, $stats, $plan);
        } catch (Throwable $e) {
            $rollbackWarnings = [];
            try {
                $this->restoreScope($backupDir . '/pages', $this->pagesDir);
                $this->restoreScope($backupDir . '/blog', $this->blogDir);
                $this->restoreDirectorySnapshot($backupDir . '/history', $this->historyDir);
                /** @var object $routeMap */
                $routeMap = registry()->get('route_map');
                $routeMap->clearCache();
                $routeMap->rebuild($this->pagesDir . '/');
            } catch (Throwable $rollbackError) {
                $rollbackWarnings[] = 'Rollback failed: ' . $rollbackError->getMessage();
            }

            if (!empty($rollbackWarnings)) {
                $warnings = array_values(array_merge($warnings, $rollbackWarnings));
            }

            return $this->result(false, false, $hasChanges, $reportPath, $e->getMessage(), $warnings, $stats, $plan);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $plan
     * @return array<string,string>
     */
    private function extractRenames(array $plan): array
    {
        $map = [];
        foreach ($plan as $currentSlug => $entry) {
            $targetSlug = (string)($entry['target_slug'] ?? $currentSlug);
            if ($targetSlug !== $currentSlug) {
                $map[$currentSlug] = $targetSlug;
            }
        }

        return $map;
    }

    /**
     * @param string $dir
     * @param string $newPrimary
     * @param array<int,string> $warnings
     * @return array{plan:array<string,array<string,mixed>>,error:?string}
     */
    private function buildSlugPlan(string $dir, string $newPrimary, array &$warnings): array
    {
        $plan = [];
        $targetUsage = [];

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $currentSlug = basename($file, '.php');
            $data = read_json_file($file);
            if (!is_array($data) || empty($data)) {
                $warnings[] = 'Skipped invalid JSON file: ' . $file;
                continue;
            }

            $targetSlug = trim((string)($data['locales'][$newPrimary]['slug'] ?? ''));
            if ($targetSlug === '') {
                $targetSlug = $currentSlug;
            }

            if (!preg_match('/^[a-z0-9\-_]+$/i', $targetSlug)) {
                $warnings[] = "Skipped invalid target slug '{$targetSlug}' in '{$currentSlug}', keeping current slug.";
                $targetSlug = $currentSlug;
            }

            $plan[$currentSlug] = [
                'target_slug' => $targetSlug,
                'is_home' => !empty($data['is_home']),
            ];

            if (!isset($targetUsage[$targetSlug])) {
                $targetUsage[$targetSlug] = [];
            }
            $targetUsage[$targetSlug][] = $currentSlug;
        }

        foreach ($targetUsage as $targetSlug => $sources) {
            if (count($sources) > 1) {
                return [
                    'plan' => $plan,
                    'error' => "Primary migration slug collision for '{$targetSlug}' from: " . implode(', ', $sources),
                ];
            }
        }

        return ['plan' => $plan, 'error' => null];
    }

    /**
     * @param array<string,string> $renameMap
     */
    private function applyRenamesTwoPhase(string $dir, array $renameMap): void
    {
        if (empty($renameMap)) {
            return;
        }

        $tmpMap = [];
        foreach ($renameMap as $old => $new) {
            $oldPath = $dir . '/' . $old . '.php';
            if (!file_exists($oldPath)) {
                throw new RuntimeException("Cannot rename missing file: {$oldPath}");
            }

            $tmp = $dir . '/.__primary_swap_' . $old . '_' . bin2hex(random_bytes(3)) . '.tmp';
            if (!rename($oldPath, $tmp)) {
                throw new RuntimeException("Failed to stage rename: {$oldPath}");
            }
            $tmpMap[$tmp] = $dir . '/' . $new . '.php';
        }

        foreach ($tmpMap as $tmp => $finalPath) {
            if (file_exists($finalPath)) {
                throw new RuntimeException("Target file already exists during migration: {$finalPath}");
            }
            if (!rename($tmp, $finalPath)) {
                throw new RuntimeException("Failed to finalize rename: {$finalPath}");
            }
        }
    }

    /**
     * @param array<string,string> $renameMap
     */
    private function migratePagesData(string $oldPrimary, string $newPrimary, array $renameMap): void
    {
        $pages = [];
        foreach (glob($this->pagesDir . '/*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            $data = read_json_file($file);
            if (!is_array($data) || empty($data)) {
                continue;
            }
            $pages[$slug] = $data;
        }

        $inverseRename = array_flip($renameMap);

        foreach ($pages as $slug => &$data) {
            if (!isset($data['locales']) || !is_array($data['locales'])) {
                $data['locales'] = [];
            }
            if (!isset($data['locales'][$newPrimary]) || !is_array($data['locales'][$newPrimary])) {
                $data['locales'][$newPrimary] = is_array($data['locales'][$oldPrimary] ?? null)
                    ? $data['locales'][$oldPrimary]
                    : [];
            }
            if (!isset($data['locales'][$oldPrimary]) || !is_array($data['locales'][$oldPrimary])) {
                $data['locales'][$oldPrimary] = [];
            }

            $oldSlug = $inverseRename[$slug] ?? $slug;

            $data['locales'][$newPrimary]['slug'] = $slug;
            if (trim((string)($data['locales'][$oldPrimary]['slug'] ?? '')) === '') {
                $data['locales'][$oldPrimary]['slug'] = $oldSlug;
            }

            $parentId = trim((string)($data['parent_id'] ?? ''));
            if ($parentId !== '' && isset($renameMap[$parentId])) {
                $data['parent_id'] = $renameMap[$parentId];
            }
        }
        unset($data);

        foreach ($pages as $slug => &$data) {
            $data['url'] = $this->buildPrimaryPageUrl($slug, $pages);
            $data['modified'] = date('Y-m-d H:i:s');
        }
        unset($data);

        foreach ($pages as $slug => $data) {
            write_json_file($this->pagesDir . '/' . $slug . '.php', $data);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $pages
     */
    private function buildPrimaryPageUrl(string $slug, array $pages): string
    {
        $visited = [];
        $path = [$slug];
        $currentParent = trim((string)($pages[$slug]['parent_id'] ?? ''));

        while ($currentParent !== '' && !in_array($currentParent, $visited, true)) {
            $visited[] = $currentParent;
            if (!isset($pages[$currentParent])) {
                break;
            }

            $parentSlug = $currentParent;
            if ($parentSlug !== 'index' && $parentSlug !== 'notindex') {
                array_unshift($path, $parentSlug);
            }

            $currentParent = trim((string)($pages[$currentParent]['parent_id'] ?? ''));
        }

        if (!empty($pages[$slug]['is_home'])) {
            return '/';
        }

        return '/' . implode('/', $path);
    }

    /**
     * @param array<string,string> $renameMap
     */
    private function migrateBlogData(string $oldPrimary, string $newPrimary, array $renameMap): void
    {
        $inverseRename = array_flip($renameMap);

        foreach (glob($this->blogDir . '/*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            $data = read_json_file($file);
            if (!is_array($data) || empty($data)) {
                continue;
            }

            if (!isset($data['locales']) || !is_array($data['locales'])) {
                $data['locales'] = [];
            }
            if (!isset($data['locales'][$newPrimary]) || !is_array($data['locales'][$newPrimary])) {
                $data['locales'][$newPrimary] = is_array($data['locales'][$oldPrimary] ?? null)
                    ? $data['locales'][$oldPrimary]
                    : [];
            }
            if (!isset($data['locales'][$oldPrimary]) || !is_array($data['locales'][$oldPrimary])) {
                $data['locales'][$oldPrimary] = [];
            }

            $oldSlug = $inverseRename[$slug] ?? $slug;
            $data['locales'][$newPrimary]['slug'] = $slug;
            if (trim((string)($data['locales'][$oldPrimary]['slug'] ?? '')) === '') {
                $data['locales'][$oldPrimary]['slug'] = $oldSlug;
            }

            $data['url'] = '/blog/' . $slug;
            $data['modified'] = date('Y-m-d H:i:s');
            write_json_file($file, $data);
        }
    }

    /**
     * @param array<string,string> $renameMap
     */
    private function migrateHistoryDirs(array $renameMap): int
    {
        $moved = 0;
        if (empty($renameMap) || !is_dir($this->historyDir)) {
            return $moved;
        }

        foreach ($renameMap as $oldSlug => $newSlug) {
            $oldDir = $this->historyDir . '/' . $oldSlug;
            $newDir = $this->historyDir . '/' . $newSlug;
            if (!is_dir($oldDir)) {
                continue;
            }

            if (!is_dir($newDir)) {
                if (@rename($oldDir, $newDir)) {
                    $moved++;
                }
                continue;
            }

            foreach (glob($oldDir . '/*.php') ?: [] as $historyFile) {
                $target = $newDir . '/' . basename($historyFile);
                if (!file_exists($target)) {
                    @rename($historyFile, $target);
                }
            }
            @rmdir($oldDir);
            $moved++;
        }

        return $moved;
    }

    private function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, 0755, true);
    }

    private function backupScope(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        if (!$this->ensureDir($targetDir)) {
            throw new RuntimeException('Failed to create backup target: ' . $targetDir);
        }

        foreach (glob($sourceDir . '/*.php') ?: [] as $file) {
            $targetFile = $targetDir . '/' . basename($file);
            if (!copy($file, $targetFile)) {
                throw new RuntimeException('Failed to backup file: ' . $file);
            }
        }
    }

    private function restoreScope(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        foreach (glob($targetDir . '/*.php') ?: [] as $existingFile) {
            @unlink($existingFile);
        }

        foreach (glob($sourceDir . '/*.php') ?: [] as $backupFile) {
            $targetFile = $targetDir . '/' . basename($backupFile);
            if (!copy($backupFile, $targetFile)) {
                throw new RuntimeException('Failed to restore backup file: ' . $backupFile);
            }
        }
    }

    private function backupDirectorySnapshot(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        if (!$this->ensureDir($targetDir)) {
            throw new RuntimeException('Failed to create backup snapshot target: ' . $targetDir);
        }

        $this->copyDirectoryRecursive($sourceDir, $targetDir);
    }

    private function restoreDirectorySnapshot(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        $this->deleteDirectoryRecursive($targetDir);
        if (!$this->ensureDir($targetDir)) {
            throw new RuntimeException('Failed to recreate restore target: ' . $targetDir);
        }

        $this->copyDirectoryRecursive($sourceDir, $targetDir);
    }

    private function copyDirectoryRecursive(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        if (!$this->ensureDir($targetDir)) {
            throw new RuntimeException('Failed to create target directory: ' . $targetDir);
        }

        foreach (scandir($sourceDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $sourceDir . '/' . $entry;
            $targetPath = $targetDir . '/' . $entry;

            if (is_dir($sourcePath)) {
                $this->copyDirectoryRecursive($sourcePath, $targetPath);
                continue;
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException('Failed to copy file: ' . $sourcePath);
            }
        }
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * @param array<int,string> $warnings
     * @param array<string,int> $stats
     * @param array<string,mixed> $plan
     * @return array{success:bool,dry_run:bool,changed:bool,report_path:?string,error:?string,warnings:array<int,string>,stats:array<string,int>,plan:array<string,mixed>}
     */
    private function result(
        bool $success,
        bool $dryRun,
        bool $changed,
        ?string $reportPath,
        ?string $error,
        array $warnings = [],
        array $stats = [],
        array $plan = []
    ): array {
        return [
            'success' => $success,
            'dry_run' => $dryRun,
            'changed' => $changed,
            'report_path' => $reportPath,
            'error' => $error,
            'warnings' => $warnings,
            'stats' => $stats,
            'plan' => $plan,
        ];
    }
}
