<?php

require_once __DIR__ . '/../../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

$action = (string)$request->post('action', '');
$manualKey = (string)$request->post('license_key', '');

if ($action !== 'check_all_updates') {
    AdminResponder::jsonError('Unsupported action', 400);
}

if (!class_exists('CoreUpdater')) {
    AdminResponder::jsonError('Core updater unavailable', 500);
}

$coreResult = CoreUpdater::checkForCoreUpdatesManual();

$manualKey = trim($manualKey);
$hasManualKey = $manualKey !== '';
$hasStoredKey = class_exists('License') && License::hasConfiguredKey();
$shouldSyncPro = class_exists('License') && ($hasManualKey || $hasStoredKey);

$proResult = [
    'success' => false,
    'skipped' => true,
    'error' => 'No license key configured',
];

if ($shouldSyncPro) {
    $proResult = License::syncLicenseManual($hasManualKey ? $manualKey : null);

    if (isset($proResult['error']) && in_array((string)$proResult['error'], ['invalid_key', 'domain_mismatch'], true)) {
        $proResult['license_revoked'] = true;
    }
}

if (empty($coreResult['success']) && !empty($proResult['success']) === false && empty($proResult['skipped'])) {
    AdminResponder::jsonError('Core and PRO checks failed', 502, [
        'core' => $coreResult,
        'pro' => $proResult,
    ]);
}

AdminResponder::jsonSuccess([
    'core' => $coreResult,
    'pro' => $proResult,
    'update_info' => $coreResult['update_info'] ?? null,
    'changelog' => (string)($coreResult['changelog'] ?? (($coreResult['update_info']['changelog'] ?? ''))),
    'module_updates' => isset($proResult['module_updates']) && is_array($proResult['module_updates'])
        ? $proResult['module_updates']
        : [],
]);
