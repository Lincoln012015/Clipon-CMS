<?php

require_once __DIR__ . '/../../lib/Auth.php';

header('Content-Type: application/json');

if (!$session->has('user')) {
    http_response_code(401);
    echo json_encode(SessionManager::expiredPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($request->isPost()) {
    $action = (string)$request->post('action', '');
    $csrfToken = (string)$request->post('csrf_token', '');

    if ($action !== 'refresh') {
        AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Invalid parameters', 400);
    }

    if (!Csrf::validate($csrfToken)) {
        AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
    }

    if (!SessionManager::refreshActivity()) {
        http_response_code(401);
        echo json_encode(SessionManager::expiredPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

echo json_encode(SessionManager::state(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
