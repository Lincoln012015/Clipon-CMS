<?php
require_once __DIR__ . '/JsonStorage.php';

class Funnels {
    private static string $file = C_CONFIG_PATH . '/funnels.php';

    public static function load(): array {
        $data = read_json_file(self::$file);
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        return [
            'items' => $items,
            'updated_at' => $data['updated_at'] ?? null
        ];
    }

    public static function get(string $id): ?array {
        $data = self::load();
        return $data['items'][$id] ?? null;
    }

    public static function add(string $name, array $steps, bool $ordered = false): ?string {
        $name = trim($name);
        $steps = self::normalizeSteps($steps);
        if ($name === '' || empty($steps)) return null;

        $data = self::load();
        $items = $data['items'];

        $id = self::makeId($name, $items);
        $items[$id] = [
            'id' => $id,
            'name' => $name,
            'steps' => $steps,
            'ordered' => $ordered ? 1 : 0,
            'created_at' => time()
        ];

        $data['items'] = $items;
        $data['updated_at'] = time();

        return self::save($data) ? $id : null;
    }

    public static function delete(string $id): bool {
        $id = trim($id);
        if ($id === '') return false;

        $data = self::load();
        if (!isset($data['items'][$id])) return false;

        unset($data['items'][$id]);
        $data['updated_at'] = time();
        return self::save($data);
    }

    private static function normalizeSteps(array $steps): array {
        $clean = [];
        foreach ($steps as $step) {
            $step = trim((string)$step);
            if ($step === '') continue;
            $clean[] = $step;
            if (count($clean) >= 20) break;
        }

        return array_values(array_unique($clean));
    }

    private static function makeId(string $name, array $items): string {
        $base = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'funnel';
        }

        $id = $base;
        $suffix = 1;
        while (isset($items[$id])) {
            $id = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 50) {
                try {
                    $rand = bin2hex(random_bytes(4));
                } catch (Exception $e) {
                    $rand = str_replace('.', '', uniqid('', true));
                }
                $id = $base . '-' . substr($rand, 0, 6);
                break;
            }
        }

        return $id;
    }

    private static function save(array $data): bool {
        $ok = write_json_file(self::$file, $data);
        if (!$ok) {
            error_log('[Funnels] Failed to write funnels config');
        }
        return $ok;
    }
}
