<?php

final class IntegrationRequest
{
    public function __construct(private Request $request) {}

    public function method(): string { return strtoupper($this->request->method()); }
    public function json(): ?array
    {
        $value = $this->request->json(true);
        return is_array($value) ? $value : null;
    }
    public function query(string $key, string $default = ''): string
    {
        $value = $this->request->query($key, $default);
        return is_scalar($value) ? trim((string)$value) : $default;
    }
    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (strtolower($name) === 'content-type') $key = 'CONTENT_TYPE';
        $value = trim((string)$this->request->server($key, ''));
        if ($value === '' && strtolower($name) === 'authorization') {
            $value = trim((string)$this->request->server('REDIRECT_HTTP_AUTHORIZATION', ''));
        }
        return $value;
    }
    public function bearerToken(): string
    {
        $header = $this->header('Authorization');
        return preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : '';
    }
    public function clientIp(): string
    {
        $ip = trim((string)$this->request->server('REMOTE_ADDR', 'unknown'));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}
