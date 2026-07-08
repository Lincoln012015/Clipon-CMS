<?php

class PageTreeRenderer
{
    public function build(array $directories, array $pages): array
    {
        $normalizedDirs = [];
        foreach ($directories as $dir) {
            $dir['order'] = (int)($dir['order'] ?? 0);
            $dir['children'] = [];
            $dir['pages'] = [];
            if (!empty($dir['id'])) {
                $normalizedDirs[$dir['id']] = $dir;
            }
        }

        usort($pages, static function (array $a, array $b): int {
            return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
        });

        $rootPages = [];
        foreach ($pages as $page) {
            $dirId = $page['directory_id'] ?? null;
            if ($dirId && isset($normalizedDirs[$dirId])) {
                $normalizedDirs[$dirId]['pages'][] = $page;
            } else {
                $rootPages[] = $page;
            }
        }

        $rootDirs = $this->buildDirTree($normalizedDirs, null);

        return [
            'root_dirs' => $rootDirs,
            'root_pages' => $rootPages,
        ];
    }

    public function toJson(array $tree): string
    {
        return json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"root_dirs":[],"root_pages":[]}';
    }

    private function buildDirTree(array $dirMap, $parentId): array
    {
        $dirs = [];
        foreach ($dirMap as $id => $dir) {
            $dirParent = $dir['parent'] ?? null;
            if ($dirParent == $parentId) {
                $dir['children'] = $this->buildDirTree($dirMap, $id);
                $dirs[] = $dir;
            }
        }

        usort($dirs, static function (array $a, array $b): int {
            return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
        });

        return $dirs;
    }
}
