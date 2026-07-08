<?php

class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;
    private array $cookies;
    private string $rawBody;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->rawBody = (string)(file_get_contents('php://input') ?: '');

        $jsonData = $this->decodeJsonBody();
        if (is_array($jsonData)) {
            foreach ($jsonData as $key => $value) {
                if (!array_key_exists($key, $this->post)) {
                    $this->post[$key] = $value;
                }
            }
        }
    }

    private function isJsonContentType(): bool
    {
        $contentType = strtolower((string)($this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? ''));

        if ($contentType === '') {
            return false;
        }

        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        return preg_match('/\+json(?:\s*;|\s*$)/', $contentType) === 1;
    }

    private function decodeJsonBody()
    {
        if ($this->rawBody === '') {
            return null;
        }

        $looksLikeJson = false;
        if ($this->isJsonContentType()) {
            $looksLikeJson = true;
        } elseif (empty($this->post)) {
            $trimmed = ltrim($this->rawBody);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $looksLikeJson = true;
            }
        }

        if (!$looksLikeJson) {
            return null;
        }

        $decoded = json_decode($this->rawBody, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) return $this->get;
        return $this->get[$key] ?? $default;
    }

    public function post(?string $key = null, $default = null)
    {
        if ($key === null) return $this->post;
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    public function server(?string $key = null, $default = null)
    {
        if ($key === null) return $this->server;
        return $this->server[$key] ?? $default;
    }

    public function hasServer(string $key): bool
    {
        return isset($this->server[$key]);
    }

    public function cookie(?string $key = null, $default = null)
    {
        if ($key === null) return $this->cookies;
        return $this->cookies[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $val = $this->input($key, $default);
        return is_numeric($val) ? (int)$val : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->input($key, $default);
        if ($val === 'true' || $val === '1' || $val === 1 || $val === true) return true;
        if ($val === 'false' || $val === '0' || $val === 0 || $val === false) return false;
        return $default;
    }

    public function string(string $key, string $default = '', bool $trim = true): string
    {
        $val = $this->input($key, $default);
        if (!is_string($val)) return $default;
        return $trim ? trim($val) : $val;
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->get[$key]);
    }

    public function isPost(): bool
    {
        return ($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) && 
               strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function json(bool $assoc = true)
    {
        if ($this->rawBody === '') {
            return null;
        }

        if (!$this->isJsonContentType()) {
            return null;
        }

        $decoded = json_decode($this->rawBody, $assoc);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
