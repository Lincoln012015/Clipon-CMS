<?php

class AdminResponder
{
    public static function json(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonError(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json(array_merge(['status' => 'error', 'message' => $message], $extra), $statusCode);
    }

    public static function jsonSuccess(array $payload = [], int $statusCode = 200): void
    {
        self::json(array_merge(['status' => 'success'], $payload), $statusCode);
    }

    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        }
        exit;
    }
}
