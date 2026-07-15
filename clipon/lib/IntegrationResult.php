<?php

final class IntegrationResult
{
    public function __construct(
        public int $status,
        public array $payload
    ) {}

    public static function success(array $payload = [], int $status = 200): self
    {
        return new self($status, $payload);
    }

    public static function error(string $code, string $message, int $status, array $extra = []): self
    {
        return new self($status, array_merge(['error' => $code, 'message' => $message], $extra));
    }
}
