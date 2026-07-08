<?php

require_once __DIR__ . '/../../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminAccess::requirePost($request);

$csrfToken = (string)$request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    AdminResponder::jsonError(__('error_invalid_csrf') ?: 'Invalid CSRF token', 403);
}

$currentUser = UserService::normalizeLogin((string)$session->get('user'));
$role = (string)$session->get('role');

$userService = (class_exists('ProUsersService') && class_exists('ModuleManager') && ModuleManager::isProAvailable('pro_users'))
    ? new ProUsersService()
    : new UserService();

$action = (string)$request->post('action', '');
$router = new AdminActionRouter();

/**
 * Basic Profile Updates (Self-service, available to all users)
 */
$router->on('update_profile', function () use ($request, $userService, $currentUser): void {
    $username = UserService::normalizeLogin((string)$request->post('username', ''));
    $name = (string)$request->post('name', '');

    if (!$userService->canManageUser($currentUser, $username)) {
        AdminResponder::jsonError(__('error_forbidden'), 403);
    }

    if ($userService->updateProfile($username, $name)) {
        AdminResponder::jsonSuccess(['message' => __('settings_saved')]);
    } else {
        AdminResponder::jsonError('Could not update profile.');
    }
});

$router->on('update_password', function () use ($request, $userService, $currentUser, $role): void {
    $username = UserService::normalizeLogin((string)$request->post('username', ''));
    $currentPassword = (string)$request->post('current_password', '');
    $newPassword = (string)$request->post('new_password', '');
    $confirmPassword = (string)$request->post('confirm_password', '');
    $isSelfChange = $currentUser === $username;

    if (!$userService->canManageUser($currentUser, $username)) {
        AdminResponder::jsonError(__('error_forbidden'), 403);
    }

    if (empty($newPassword) || !UserService::isValidPassword($newPassword)) {
        AdminResponder::jsonError(__('password_min_length'));
    }

    if ($newPassword !== $confirmPassword) {
        AdminResponder::jsonError(__('passwords_dont_match'));
    }

    if ($isSelfChange && $currentPassword === '') {
        AdminResponder::jsonError(__('current_password_required') ?: 'Current password is required.');
    }

    $requiredCurrentPassword = $isSelfChange ? $currentPassword : null;
    if ($userService->updatePassword($username, $newPassword, $requiredCurrentPassword)) {
        AdminResponder::jsonSuccess(['message' => __('password_updated')]);
    } else {
        AdminResponder::jsonError($isSelfChange ? __('current_password_invalid') : 'Could not update password.');
    }
});

if (class_exists('Hooks')) {
    Hooks::doAction('admin_users_api_routes', $router, $request, $currentUser, $role);
}

$router->dispatch($action, static function (): void {
    AdminResponder::jsonError(__('error_invalid_parameters') ?: 'Invalid parameters', 400);
});
