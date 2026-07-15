<?php

const INTEGRATION_MAX_BODY = 5242880;

function integration_response(array $payload, int $status, array $headers = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    foreach ($headers as $name => $value) header($name . ': ' . $value);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['POST', 'DELETE'], true)) {
    header('Allow: POST, DELETE');
    integration_response(['error' => 'method_not_allowed', 'message' => 'Only POST and DELETE are supported.'], 405);
}
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > INTEGRATION_MAX_BODY) integration_response(['error' => 'payload_too_large', 'message' => 'Maximum request size is 5 MB.'], 413);
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if ($method === 'POST' && !preg_match('#^(application/json|[^;]+\+json)(?:;|$)#', $contentType)) {
    integration_response(['error' => 'unsupported_media_type', 'message' => 'Content-Type must be application/json.'], 415);
}

define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
require_once dirname(__DIR__) . '/bootstrap.php';

$started = microtime(true);
$integrationRequest = new IntegrationRequest($request);
$dispatcher = new IntegrationDispatcher(registry(), new IntegrationRateLimiter(C_DATA_PATH . '/integration_rate_limits'));
$result = $dispatcher->dispatch($integrationRequest);
$provider = $integrationRequest->query('provider', 'unknown');
$externalId = $method === 'DELETE' ? $integrationRequest->query('id') : (string)(($integrationRequest->json() ?? [])['external_id'] ?? '');
IntegrationLogger::write($provider, strtolower($method), $result->status, $externalId, (int)((microtime(true) - $started) * 1000));
$headers = $result->status === 429 ? ['Retry-After' => (string)($result->payload['retry_after'] ?? 60)] : [];
integration_response($result->payload, $result->status, $headers);
