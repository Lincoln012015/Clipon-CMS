<?php

class PageDirectoryService
{
    private string $directoriesFile;

    public function __construct(string $directoriesFile)
    {
        $this->directoriesFile = $directoriesFile;
    }

    public function getDirectories(): array
    {
        if (!file_exists($this->directoriesFile)) {
            return [];
        }

        $data = read_json_file($this->directoriesFile);
        return is_array($data['directories'] ?? null) ? $data['directories'] : [];
    }

    public function saveDirectories(array $directories): void
    {
        write_json_file($this->directoriesFile, ['directories' => array_values($directories)]);
    }

    public function wouldCreateCycle(string $dirId, ?string $newParentId, array $directories): bool
    {
        if (!$newParentId || $dirId === $newParentId) {
            return false;
        }

        $visited = [];
        $current = $newParentId;

        while ($current && !in_array($current, $visited, true)) {
            if ($current === $dirId) {
                return true;
            }

            $visited[] = $current;
            $found = false;
            foreach ($directories as $dir) {
                if (($dir['id'] ?? null) === $current) {
                    $current = $dir['parent'] ?? null;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $current = null;
            }
        }

        return false;
    }

    public function addDirectory(string $name, ?string $parent): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $directories = $this->getDirectories();
        $directories[] = [
            'id' => uniqid('dir_'),
            'name' => $name,
            'parent' => $parent,
            'order' => 9999,
        ];

        $this->saveDirectories($directories);
    }

    public function editDirectory(string $id, string $name, ?string $parent): bool
    {
        $directories = $this->getDirectories();

        if ($this->wouldCreateCycle($id, $parent, $directories)) {
            return false;
        }

        foreach ($directories as &$dir) {
            if (($dir['id'] ?? null) === $id) {
                $dir['name'] = trim($name);
                if ($parent !== $id) {
                    $dir['parent'] = $parent;
                }
                break;
            }
        }
        unset($dir);

        $this->saveDirectories($directories);
        return true;
    }

    public function deleteDirectoryWithPages(string $id, string $pagesDir): array
    {
        $directories = $this->getDirectories();
        $directoriesBefore = $directories;
        $allPagesToDelete = [];
        $allDirsToDelete = [$id];

        $this->collectToDelete($id, $allPagesToDelete, $allDirsToDelete, $directories, $pagesDir);

        $pagesBackup = [];
        foreach ($allPagesToDelete as $slug) {
            $jsonFile = rtrim($pagesDir, '/') . '/' . $slug . '.php';
            if (file_exists($jsonFile)) {
                $pagesBackup[$slug] = (string)file_get_contents($jsonFile);
                @unlink($jsonFile);
            }
        }

        $directories = array_values(array_filter($directories, static function ($dir) use ($allDirsToDelete) {
            return !in_array($dir['id'] ?? null, $allDirsToDelete, true);
        }));

        $this->saveDirectories($directories);

        return [
            'deleted_pages' => $allPagesToDelete,
            'deleted_dirs' => $allDirsToDelete,
            'directories_before' => $directoriesBefore,
            'pages_backup' => $pagesBackup,
        ];
    }

    public function restoreDirectoryDeletion(array $snapshot, string $pagesDir): void
    {
        if (isset($snapshot['directories_before']) && is_array($snapshot['directories_before'])) {
            $this->saveDirectories($snapshot['directories_before']);
        }

        $pagesBackup = $snapshot['pages_backup'] ?? [];
        if (!is_array($pagesBackup)) {
            return;
        }

        $pagesDir = rtrim($pagesDir, '/') . '/';
        if (!is_dir($pagesDir)) {
            @mkdir($pagesDir, 0755, true);
        }

        foreach ($pagesBackup as $slug => $contents) {
            if (!is_string($slug) || !is_string($contents)) {
                continue;
            }
            file_put_contents($pagesDir . $slug . '.php', $contents, LOCK_EX);
        }
    }

    public function reorderDirectories(array $directories, array $items): array
    {
        $dirMap = [];
        foreach ($directories as $k => $dir) {
            if (!empty($dir['id'])) {
                $dirMap[$dir['id']] = $k;
            }
        }

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'dir') {
                continue;
            }

            $id = (string)($item['id'] ?? '');
            if ($id === '' || !isset($dirMap[$id])) {
                continue;
            }

            $directories[$dirMap[$id]]['parent'] = $item['parent'] ?? null;
            $directories[$dirMap[$id]]['order'] = (int)($item['order'] ?? 0);
        }

        return $directories;
    }

    private function collectToDelete(string $dirId, array &$pages, array &$dirs, array $directories, string $pagesDir): void
    {
        foreach ($directories as $dir) {
            if (($dir['parent'] ?? null) === $dirId) {
                $childId = (string)$dir['id'];
                $dirs[] = $childId;
                $this->collectToDelete($childId, $pages, $dirs, $directories, $pagesDir);
            }
        }

        foreach (glob(rtrim($pagesDir, '/') . '/*.php') ?: [] as $file) {
            $data = read_json_file($file);
            if (($data['directory_id'] ?? null) === $dirId) {
                $pages[] = basename($file, '.php');
            }
        }
    }
}
