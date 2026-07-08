<?php

class BlogDirectoryService
{
    private string $directoriesFile;
    private string $blogDir;

    public function __construct(string $directoriesFile, string $blogDir)
    {
        $this->directoriesFile = $directoriesFile;
        $this->blogDir = rtrim($blogDir, '/') . '/';
    }

    public function getDirectories(): array
    {
        if (!file_exists($this->directoriesFile)) {
            return [];
        }

        $data = read_json_file($this->directoriesFile);
        return $data['directories'] ?? [];
    }

    public function saveDirectories(array $directories): void
    {
        write_json_file($this->directoriesFile, ['directories' => array_values($directories)]);
    }

    public function addDirectory(string $name, ?string $parent): void
    {
        if ($name === '') {
            return;
        }

        $directories = $this->getDirectories();
        $directories[] = [
            'id' => uniqid('dir_'),
            'name' => $name,
            'parent' => $parent ?: null,
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
            if (($dir['id'] ?? '') !== $id) {
                continue;
            }

            $dir['name'] = $name;
            if ($parent !== $id) {
                $dir['parent'] = $parent;
            }
            break;
        }
        unset($dir);

        $this->saveDirectories($directories);
        return true;
    }

    public function deleteDirectoryWithPosts(string $id): array
    {
        $directories = $this->getDirectories();
        $directoriesBefore = $directories;
        $allPostsToDelete = [];
        $allDirsToDelete = [$id];

        $collect = function (string $dirId) use (&$collect, &$allPostsToDelete, &$allDirsToDelete, $directories): void {
            foreach ($directories as $dir) {
                if (($dir['parent'] ?? null) !== $dirId) {
                    continue;
                }
                $childId = (string)($dir['id'] ?? '');
                $allDirsToDelete[] = $childId;
                $collect($childId);
            }

            foreach (glob($this->blogDir . '*.php') ?: [] as $file) {
                $data = read_json_file($file);
                if (($data['directory_id'] ?? null) === $dirId) {
                    $allPostsToDelete[] = basename($file, '.php');
                }
            }
        };

        $collect($id);

        $postsBackup = [];
        foreach ($allPostsToDelete as $slug) {
            $jsonFile = $this->blogDir . $slug . '.php';
            if (file_exists($jsonFile)) {
                $postsBackup[$slug] = (string)file_get_contents($jsonFile);
                @unlink($jsonFile);
            }
        }

        $directories = array_filter($directories, static function ($dir) use ($allDirsToDelete) {
            return !in_array($dir['id'] ?? '', $allDirsToDelete, true);
        });

        $this->saveDirectories($directories);

        return [
            'deleted_posts' => $allPostsToDelete,
            'deleted_dirs' => $allDirsToDelete,
            'directories_before' => $directoriesBefore,
            'posts_backup' => $postsBackup,
        ];
    }

    public function restoreDirectoryDeletion(array $snapshot): void
    {
        if (isset($snapshot['directories_before']) && is_array($snapshot['directories_before'])) {
            $this->saveDirectories($snapshot['directories_before']);
        }

        $postsBackup = $snapshot['posts_backup'] ?? [];
        if (!is_array($postsBackup)) {
            return;
        }

        if (!is_dir($this->blogDir)) {
            @mkdir($this->blogDir, 0755, true);
        }

        foreach ($postsBackup as $slug => $contents) {
            if (!is_string($slug) || !is_string($contents)) {
                continue;
            }
            file_put_contents($this->blogDir . $slug . '.php', $contents, LOCK_EX);
        }
    }

    public function reorder(array $items): void
    {
        $directories = $this->getDirectories();
        $dirMap = [];

        foreach ($directories as $k => $dir) {
            $dirMap[$dir['id'] ?? ''] = $k;
        }

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'dir') {
                continue;
            }

            $dirId = (string)($item['id'] ?? '');
            if (!isset($dirMap[$dirId])) {
                continue;
            }

            $directories[$dirMap[$dirId]]['parent'] = $item['parent'] ?? null;
            $directories[$dirMap[$dirId]]['order'] = (int)($item['order'] ?? 0);
        }

        $this->saveDirectories($directories);
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
            foreach ($directories as $dir) {
                if (($dir['id'] ?? '') !== $current) {
                    continue;
                }
                $current = $dir['parent'] ?? null;
                break;
            }
        }

        return false;
    }
}
