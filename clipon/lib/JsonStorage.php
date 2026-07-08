<?php
/**
 * Небольшая утилита для безопасной записи/чтения JSON файлов с блокировкой.
 * Файлы сохраняются как .php с заглушкой <?php die(); ?> для безопасности.
 */

const PHP_GUARD = "<?php die(); ?>\n";

function write_json_file(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    // Добавляем заглушку для безопасности, если расширение .php
    $content = (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') ? PHP_GUARD . $json : $json;

    // Атомарная запись через временный файл для предотвращения Race Condition
    $tmpFile = $filePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
    
    if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
        return false;
    }

    // Атомарная замена файла. На Unix-системах (macOS) rename() является атомарным.
    if (!rename($tmpFile, $filePath)) {
        if (file_exists($tmpFile)) unlink($tmpFile);
        return false;
    }

    // Устанавливаем права доступа
    chmod($filePath, 0644);
    return true;
}

function read_json_file(string $filePath, bool $throwOnError = false): array {
    if (!file_exists($filePath)) return [];
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        if ($throwOnError) {
            throw new RuntimeException("Failed to read JSON file: {$filePath}");
        }
        return [];
    }
    
    // Удаляем заглушку, если она есть
    if (strpos($contents, PHP_GUARD) === 0) {
        $contents = substr($contents, strlen(PHP_GUARD));
    }
    
    $data = json_decode($contents, true);
    if (!is_array($data)) {
        if ($throwOnError) {
            $jsonError = json_last_error_msg();
            throw new RuntimeException("Invalid JSON in file {$filePath}: {$jsonError}");
        }
        return [];
    }

    return $data;
}

?>
