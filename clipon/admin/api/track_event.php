<?php
if (!defined('CLIPON_SESSIONLESS_BOOTSTRAP')) define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../lib/AnalyticsEventRequest.php';
require_once __DIR__ . '/../../lib/AnalyticsEventIngestor.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respond = static function(int $status): never {
    http_response_code($status);
    echo json_encode(['status' => $status < 400 ? 'ok' : 'ignored']);
    exit;
};

if (!$request->isPost()) $respond(405);
$contentType = strtolower((string)$request->server('CONTENT_TYPE', ''));
if (!str_starts_with($contentType, 'application/json')) $respond(415);
$length = (int)$request->server('CONTENT_LENGTH', 0);
if ($length > 8192) $respond(413);

try {
    $data = $request->json();
    if (!is_array($data)) $respond(400);
    $event = AnalyticsEventRequest::fromArray($data);
    $result = (new AnalyticsEventIngestor(C_DATA_PATH . '/analytics'))->ingest($event, $_SERVER);
    if (!empty($result['accepted'])) $respond(202);
    $reason = (string)($result['reason'] ?? '');
    $respond(in_array($reason, ['event_rate_limit', 'network_rate_limit'], true) ? 429 : 400);
} catch (Throwable $e) {
    $respond(400);
}
