<?php
require_once __DIR__ . '/JsonStorage.php';

class Scripts {
    private static $configFile = null;

    private static function getConfigFile() {
        if (self::$configFile === null) {
            self::$configFile = C_CONFIG_PATH . '/scripts.php';
        }
        return self::$configFile;
    }

    public static function load() {
        $file = self::getConfigFile();
        if (!file_exists($file)) {
            return [];
        }
        $data = read_json_file($file);
        return is_array($data) ? $data : [];
    }

    public static function save($scripts) {
        if (!is_array($scripts)) {
            return false;
        }
        return write_json_file(self::getConfigFile(), $scripts);
    }

    public static function add($script) {
        $scripts = self::load();
        $script['id'] = uniqid();
        $scripts[] = $script;
        return self::save($scripts);
    }

    public static function update($id, $updatedScript) {
        $scripts = self::load();
        foreach ($scripts as &$script) {
            if ($script['id'] === $id) {
                $updatedScript['id'] = $id;
                $script = $updatedScript;
                break;
            }
        }
        return self::save($scripts);
    }

    public static function delete($id) {
        $scripts = self::load();
        $scripts = array_filter($scripts, function($s) use ($id) {
            return $s['id'] !== $id;
        });
        return self::save(array_values($scripts));
    }

    public static function getActiveScripts($pageSlug = null) {
        $scripts = self::load();
        $active = [];
        foreach ($scripts as $script) {
            if (empty($script['active'])) continue;
            
            // Check page targeting
            if (!empty($script['pages']) && is_array($script['pages'])) {
                if (!in_array($pageSlug, $script['pages'])) {
                    continue;
                }
            }
            
            $active[] = $script;
        }
        return $active;
    }
}
