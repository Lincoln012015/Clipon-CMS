<?php
require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/Settings.php';

class History {
    private $historyDir;

    public function __construct() {
        $this->historyDir = C_CONTENT_PATH . '/history/';
        if (!is_dir($this->historyDir)) {
            mkdir($this->historyDir, 0755, true);
        }
    }

    private function normalizeTimestamp($value) {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', (string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function extractTimestampFromFilename($filePath) {
        $fileTimestamp = $this->normalizeTimestamp(basename($filePath, '.php'));
        return $fileTimestamp === null ? 0 : (int)$fileTimestamp;
    }

    private function buildVersionFilename($pageHistoryDir, $timestamp) {
        return $pageHistoryDir . $timestamp . '.php';
    }

    private function getRetentionDays() {
        $settings = Settings::load();
        $days = isset($settings['history_retention_days']) ? (int)$settings['history_retention_days'] : 90;
        return $days < 0 ? 0 : $days;
    }

    private function getHistoryDirectories($slug = null) {
        if ($slug !== null && $slug !== '') {
            $dir = $this->historyDir . $slug . '/';
            return is_dir($dir) ? [$dir] : [];
        }

        $dirs = glob($this->historyDir . '*', GLOB_ONLYDIR);
        return is_array($dirs) ? $dirs : [];
    }

    private function getVersionFiles($dirPath) {
        $files = glob(rtrim($dirPath, '/') . '/*.php');
        if (!is_array($files)) {
            return [];
        }

        usort($files, function($a, $b) {
            return $this->extractTimestampFromFilename($b) - $this->extractTimestampFromFilename($a);
        });

        return $files;
    }

    private function getCurrentPageHashForSlug($slug) {
        $pageFile = C_CONTENT_PATH . '/pages/' . $slug . '.php';
        if (!file_exists($pageFile)) {
            return '';
        }

        $pageData = read_json_file($pageFile);
        return $this->computeDataHash($pageData);
    }

    private function findCurrentVersionFileToKeep($files, $currentHash) {
        if ($currentHash === '') {
            return null;
        }

        foreach ($files as $file) {
            $data = read_json_file($file);
            $versionHash = $this->computeDataHash($data['data'] ?? []);
            if ($versionHash !== '' && hash_equals($versionHash, $currentHash)) {
                return $file;
            }
        }

        return null;
    }

    private function removeDirIfEmpty($dirPath) {
        $remaining = glob(rtrim($dirPath, '/') . '/*.php');
        if (is_array($remaining) && count($remaining) === 0) {
            @rmdir($dirPath);
        }
    }

    private function isAssocArray($value) {
        if (!is_array($value)) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function normalizeForHash($value) {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isAssocArray($value)) {
            ksort($value);
            foreach ($value as $k => $v) {
                $value[$k] = $this->normalizeForHash($v);
            }
            return $value;
        }

        foreach ($value as $k => $v) {
            $value[$k] = $this->normalizeForHash($v);
        }

        return $value;
    }

    public function computeDataHash($data) {
        $normalized = $this->normalizeForHash(is_array($data) ? $data : []);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return hash('sha256', $json);
    }

    private function resolveVersionFilePath($slug, $timestamp) {
        $pageHistoryDir = $this->historyDir . $slug . '/';
        if (!is_dir($pageHistoryDir)) {
            return null;
        }

        $normalizedTimestamp = $this->normalizeTimestamp($timestamp);
        if ($normalizedTimestamp === null) {
            return null;
        }

        $directFilename = $pageHistoryDir . $normalizedTimestamp . '.php';
        if (file_exists($directFilename)) {
            return $directFilename;
        }

        // Backward compatibility: older records may have mismatched internal timestamp.
        $files = glob($pageHistoryDir . '*.php');
        foreach ($files as $file) {
            $data = read_json_file($file);
            $fileTimestamp = $this->normalizeTimestamp(basename($file, '.php'));
            $metaTimestamp = $this->normalizeTimestamp($data['timestamp'] ?? null);

            if ($metaTimestamp === $normalizedTimestamp || $fileTimestamp === $normalizedTimestamp) {
                return $file;
            }
        }

        return null;
    }

    private function calculateDiffBundle($old, $new) {
        $codes = [];

        $push = function($code, $field = null) use (&$codes) {
            $entry = ['code' => $code];
            if ($field !== null && $field !== '') {
                $entry['field'] = (string)$field;
            }
            $codes[] = $entry;
        };

        // Basic fields with readable messages
        if (($old['title'] ?? null) !== ($new['title'] ?? null)) {
            $push('changed_title');
        }
        $oldSlug = $old['slug'] ?? null;
        $newSlug = $new['slug'] ?? null;
        $oldUrl = $old['url'] ?? null;
        $newUrl = $new['url'] ?? null;
        if ($oldSlug !== $newSlug || $oldUrl !== $newUrl) {
            $push('changed_url');
        }
        if (($old['template'] ?? null) !== ($new['template'] ?? null)) {
            $push('changed_template');
        }
        if (($old['parent_id'] ?? null) !== ($new['parent_id'] ?? null)) {
            $push('changed_parent_page');
        }

        // Active / is_home booleans - show explicit actions
        if (isset($old['active']) || isset($new['active'])) {
            $oldAct = (bool)($old['active'] ?? true);
            $newAct = (bool)($new['active'] ?? true);
            if ($oldAct !== $newAct) {
                if ($newAct) {
                    $push('page_activated');
                } else {
                    $push('page_deactivated');
                }
            }
        }
        if (isset($old['is_home']) || isset($new['is_home'])) {
            $oldHome = (bool)($old['is_home'] ?? false);
            $newHome = (bool)($new['is_home'] ?? false);
            if ($oldHome !== $newHome) {
                if ($newHome) {
                    $push('set_homepage');
                } else {
                    $push('unset_homepage');
                }
            }
        }

        // SEO
        $oldSeo = $old['seo'] ?? [];
        $newSeo = $new['seo'] ?? [];
        if (($oldSeo['meta_title'] ?? '') !== ($newSeo['meta_title'] ?? '')) {
            $push('changed_seo_title');
        }
        if (($oldSeo['meta_description'] ?? '') !== ($newSeo['meta_description'] ?? '')) {
            $push('changed_seo_desc');
        }

        // Content changes (added/modified/removed blocks)
        $oldContent = $old['content'] ?? [];
        $newContent = $new['content'] ?? [];

        foreach ($newContent as $k => $v) {
            if (!array_key_exists($k, $oldContent)) {
                $push('content_added', $k);
            } elseif ($oldContent[$k] !== $v) {
                $push('content_changed', $k);
            }
        }
        foreach ($oldContent as $k => $v) {
            if (!array_key_exists($k, $newContent)) {
                $push('content_deleted', $k);
            }
        }

        if (empty($codes)) {
            $push('no_changes');
        }

        return $codes;
    }

    public function save($slug, $data, $onlyIfChanged = false) {
        $this->cleanupExpiredVersions($slug);

        $pageHistoryDir = $this->historyDir . $slug . '/';
        if (!is_dir($pageHistoryDir)) {
            mkdir($pageHistoryDir, 0755, true);
        }

        // Get previous version for diff
        $files = glob($pageHistoryDir . '*.php');
        $changeCodes = [];
        if (!empty($files)) {
            // Compare against logically latest version by timestamp in file name.
            $lastFile = null;
            $lastTimestamp = -1;
            foreach ($files as $file) {
                $ts = $this->extractTimestampFromFilename($file);
                if ($ts > $lastTimestamp) {
                    $lastTimestamp = $ts;
                    $lastFile = $file;
                }
            }

            if ($lastFile === null) {
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $lastFile = $files[0];
            }

            $lastData = read_json_file($lastFile);
            $changeCodes = $this->calculateDiffBundle($lastData['data'] ?? [], $data);
        } else {
            $changeCodes = [['code' => 'initial_version']];
        }

        if ($onlyIfChanged && count($changeCodes) === 1 && (($changeCodes[0]['code'] ?? '') === 'no_changes')) {
            return false;
        }

        $timestamp = time();
        $filename = $this->buildVersionFilename($pageHistoryDir, $timestamp);

        // Prevent overwrite when multiple saves happen in the same second.
        while (file_exists($filename)) {
            $timestamp++;
            $filename = $this->buildVersionFilename($pageHistoryDir, $timestamp);
        }
        
        // Add metadata about the version
        $session = new Session();
        $versionData = [
            'timestamp' => $timestamp,
            'author' => $session->get('user', 'unknown'),
            'change_codes' => $changeCodes,
            'data' => $data
        ];

        write_json_file($filename, $versionData);
        return true;
    }

    public function cleanupExpiredVersions($slug = null) {
        $days = $this->getRetentionDays();
        if ($days === 0) {
            return 0;
        }

        $cutoff = time() - ($days * 86400);
        $deleted = 0;
        $dirs = $this->getHistoryDirectories($slug);

        foreach ($dirs as $dirPath) {
            $slugName = basename(rtrim($dirPath, '/'));
            $files = $this->getVersionFiles($dirPath);
            if (empty($files)) {
                $this->removeDirIfEmpty($dirPath);
                continue;
            }

            $currentHash = $this->getCurrentPageHashForSlug($slugName);
            $keepCurrentFile = $this->findCurrentVersionFileToKeep($files, $currentHash);

            foreach ($files as $file) {
                $timestamp = $this->extractTimestampFromFilename($file);
                if ($timestamp >= $cutoff) {
                    continue;
                }
                if ($keepCurrentFile !== null && $file === $keepCurrentFile) {
                    continue;
                }
                if (@unlink($file)) {
                    $deleted++;
                }
            }

            $this->removeDirIfEmpty($dirPath);
        }

        return $deleted;
    }

    public function clearAllExceptCurrent() {
        $deleted = 0;
        $dirs = $this->getHistoryDirectories();

        foreach ($dirs as $dirPath) {
            $slugName = basename(rtrim($dirPath, '/'));
            $files = $this->getVersionFiles($dirPath);
            if (empty($files)) {
                $this->removeDirIfEmpty($dirPath);
                continue;
            }

            $currentHash = $this->getCurrentPageHashForSlug($slugName);
            $keepCurrentFile = $this->findCurrentVersionFileToKeep($files, $currentHash);

            foreach ($files as $file) {
                if ($keepCurrentFile !== null && $file === $keepCurrentFile) {
                    continue;
                }
                if (@unlink($file)) {
                    $deleted++;
                }
            }

            $this->removeDirIfEmpty($dirPath);
        }

        return $deleted;
    }

    public function get($slug) {
        $pageHistoryDir = $this->historyDir . $slug . '/';
        if (!is_dir($pageHistoryDir)) {
            return [];
        }

        $files = glob($pageHistoryDir . '*.php');
        $history = [];

        foreach ($files as $file) {
            $data = read_json_file($file);
            $fileTimestamp = (int) basename($file, '.php');
            $metaTimestamp = isset($data['timestamp']) && is_numeric($data['timestamp'])
                ? (int) $data['timestamp']
                : 0;
            $timestamp = $fileTimestamp > 0 ? $fileTimestamp : $metaTimestamp;

            if ($timestamp <= 0) {
                continue;
            }

            $history[] = [
                'timestamp' => $timestamp,
                'author' => $data['author'] ?? 'unknown',
                'change_codes' => is_array($data['change_codes'] ?? null) ? $data['change_codes'] : [],
                'date' => date('Y-m-d H:i:s', $timestamp),
                'data_hash' => $this->computeDataHash($data['data'] ?? []),
            ];
        }

        // Sort by timestamp desc
        usort($history, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $history;
    }

    public function getVersionEntry($slug, $timestamp) {
        $filename = $this->resolveVersionFilePath($slug, $timestamp);
        if (!$filename) {
            return null;
        }

        $versionData = read_json_file($filename);
        $fileTimestamp = (int) basename($filename, '.php');
        $metaTimestamp = isset($versionData['timestamp']) && is_numeric($versionData['timestamp'])
            ? (int) $versionData['timestamp']
            : 0;

        $versionData['timestamp'] = $fileTimestamp > 0 ? $fileTimestamp : $metaTimestamp;

        return $versionData;
    }

    public function getVersion($slug, $timestamp) {
        $versionData = $this->getVersionEntry($slug, $timestamp);
        if (!$versionData) {
            return null;
        }

        return $versionData['data'] ?? null;
    }
}
