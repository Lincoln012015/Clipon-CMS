<?php

require_once __DIR__ . '/AnalyticsTokenService.php';

final class AnalyticsEventRequest {
    public int $version;
    public string $type;
    public string $pageviewId;
    public string $path;
    public string $token;
    public int $sequence;
    public array $data;

    public static function fromArray(array $input): self {
        $event = new self();
        $event->version = $input['version'] ?? 0;
        $event->type = is_string($input['type'] ?? null) ? $input['type'] : '';
        $event->pageviewId = is_string($input['pageview_id'] ?? null) ? $input['pageview_id'] : '';
        $event->path = is_string($input['path'] ?? null) ? AnalyticsTokenService::normalizePath($input['path']) : '';
        $event->token = is_string($input['token'] ?? null) ? $input['token'] : '';
        $event->sequence = $input['sequence'] ?? 0;
        $event->data = is_array($input['data'] ?? null) ? $input['data'] : [];
        if ($event->version !== 1 || !in_array($event->type, ['page_view', 'engagement', 'scroll', 'conversion'], true)
            || !preg_match('/^[a-f0-9]{32}$/', $event->pageviewId) || $event->path === ''
            || $event->token === '' || !is_int($event->sequence) || $event->sequence < 1 || $event->sequence > 100000) {
            throw new InvalidArgumentException('invalid_event');
        }
        return $event;
    }
}
