<?php

require_once __DIR__ . '/../../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

if (!hasPermission('manage_redirects')) {
    AdminResponder::jsonError(__('error_no_permission') ?: 'Access denied.');
}

$redirectService = new RedirectService();
$action = (string)$request->post('action', '');
$router = new AdminActionRouter();

$router->on('get_list', function () use ($redirectService): void {
    $redirects = $redirectService->getAllRedirects();
    AdminResponder::json([
        'status' => 'success',
        'redirects' => $redirects,
        'routes_count' => count($redirectService->getAllRoutes())
    ]);
});

$router->on('add_redirect', function () use ($request, $redirectService): void {
    $oldUrl = trim((string)$request->post('old_url', ''));
    $newUrl = trim((string)$request->post('new_url', ''));
    $code = (int)$request->post('code', 301);

    if (empty($oldUrl) || empty($newUrl)) {
        AdminResponder::jsonError(__('fill_both_fields'));
    }

    $success = $redirectService->addRedirect($oldUrl, $newUrl, $code);
    if ($success) {
        AdminResponder::jsonSuccess(['message' => sprintf(__('redirect_added'), $oldUrl, $newUrl)]);
    } else {
        AdminResponder::jsonError(__('error_add_redirect') ?: 'Could not add redirect. Possible loop.');
    }
});

$router->on('delete_redirect', function () use ($request, $redirectService): void {
    $oldUrl = (string)$request->post('old_url', '');
    if (empty($oldUrl)) {
        AdminResponder::jsonError('URL is required.');
    }

    $redirectService->removeRedirect($oldUrl);
    AdminResponder::jsonSuccess(['message' => sprintf(__('redirect_deleted'), $oldUrl)]);
});

$router->dispatch($action);
