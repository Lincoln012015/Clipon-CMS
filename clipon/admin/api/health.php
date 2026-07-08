<?php

/**
 * Authenticated async health/sync endpoint.
 * Fetched from the admin UI to keep license/update state fresh without blocking pages.
 */

require_once __DIR__ . '/../../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

// Output minimal response quickly
header("Content-Type: application/json");
header("Connection: close");
ob_start();
echo json_encode(["status" => "processing"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
header("Content-Length: " . ob_get_length());
ob_end_flush();
ob_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Now perform the blocking, slow check
if (class_exists('License')) {
    License::syncInternalState();
}

if (class_exists('CoreUpdater')) {
    CoreUpdater::syncInternalState();
}
exit;
