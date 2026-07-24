<?php
if (!defined('CLIPON_SESSIONLESS_BOOTSTRAP')) define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../lib/AnalyticsTokenService.php';
require_once __DIR__ . '/../../lib/AnalyticsBotFilter.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($request->isPost()) {
    http_response_code(405);
    echo json_encode(['status' => 'ignored']);
    exit;
}
$path = AnalyticsTokenService::normalizePath((string)$request->query('path', '/'));
$settings = Settings::load();
$filter = (new AnalyticsBotFilter($settings))->evaluate($request, $path);
if (empty($filter['allowed'])) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored']);
    exit;
}
$mode = (new CookieConsentPolicy($settings, $request))->analyticsModeForRequest();
$issued = (new AnalyticsTokenService($settings))->issue($path, $mode);
echo json_encode(['pageview_id' => $issued['pageview_id'], 'token' => $issued['token'], 'path' => $path], JSON_UNESCAPED_SLASHES);
