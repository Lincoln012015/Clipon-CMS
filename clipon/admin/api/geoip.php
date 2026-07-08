<?php
require_once __DIR__ . '/../../lib/Auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!$session->has('user')) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission('manage_settings')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$updater = new AnalyticsGeoIpUpdater();

if (!$request->isPost()) {
    echo json_encode(['status' => 'success', 'geoip' => $updater->status()]);
    exit;
}

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => __('error_invalid_csrf')]);
    exit;
}

$geoIpStatus = $updater->update(true);
$state = (string)($geoIpStatus['status'] ?? '');

if ($state === 'installed') {
    echo json_encode([
        'status' => 'success',
        'message' => __('settings_geoip_update_success'),
        'geoip' => $geoIpStatus,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$error = (string)($geoIpStatus['error'] ?? '');
$localizedError = $error;
if ($error === 'GeoIP download failed') {
    $localizedError = __('settings_geoip_error_download_failed');
} elseif (strpos($error, 'GeoIP download failed:') === 0) {
    $detail = trim(substr($error, strlen('GeoIP download failed:')));
    $localizedError = sprintf(__('settings_geoip_error_download_failed_with_detail'), $detail);
} elseif ($error === 'Failed to write GeoIP database') {
    $localizedError = __('settings_geoip_error_write_failed');
}

echo json_encode([
    'status' => 'error',
    'message' => $localizedError !== '' ? sprintf(__('settings_geoip_update_failed'), $localizedError) : __('settings_geoip_update_failed_generic'),
    'geoip' => $geoIpStatus,
], JSON_UNESCAPED_UNICODE);
