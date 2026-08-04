<?php
if (!defined('CLIPON_SESSIONLESS_BOOTSTRAP')) define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../lib/AnalyticsTokenService.php';
require_once __DIR__ . '/../../lib/AnalyticsBotFilter.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$settings = Settings::load();
if ($request->isPost()) {
    try {
        $data = $request->json();
        if (!is_array($data)) throw new InvalidArgumentException('invalid_token');
        $issued = (new AnalyticsTokenService($settings))->renew(
            is_string($data['token'] ?? null) ? $data['token'] : '',
            is_string($data['pageview_id'] ?? null) ? $data['pageview_id'] : '',
            is_string($data['path'] ?? null) ? $data['path'] : '/'
        );
        echo json_encode(['pageview_id' => $issued['pageview_id'], 'token' => $issued['token'], 'path' => $issued['payload']['path']], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['status' => 'ignored']);
    }
    exit;
}
$path = AnalyticsTokenService::normalizePath((string)$request->query('path', '/'));
$filter = (new AnalyticsBotFilter($settings))->evaluate($request, $path);
if (empty($filter['allowed'])) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored']);
    exit;
}
$mode = (new CookieConsentPolicy($settings, $request))->analyticsModeForRequest();
$issued = (new AnalyticsTokenService($settings))->issue($path, $mode);
echo json_encode(['pageview_id' => $issued['pageview_id'], 'token' => $issued['token'], 'path' => $path], JSON_UNESCAPED_SLASHES);
