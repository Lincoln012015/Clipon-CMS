<?php

class AdminAccess
{
    public static function requireUser(Session $session, string $redirectUrl = 'login.php'): void
    {
        if (!$session->has('user')) {
            AdminResponder::redirect($redirectUrl);
        }

        self::refreshUserPermissions($session);
    }

    public static function requireUserApi(Session $session): void
    {
        if (!$session->has('user')) {
            AdminResponder::jsonError(__('error_unauthorized') ?: 'Unauthorized', 401, ['code' => 'unauthorized']);
        }

        self::refreshUserPermissions($session);
    }

    public static function requirePost(Request $request): void
    {
        if (!$request->isPost()) {
            AdminResponder::jsonError('Method not allowed', 405);
        }
    }

    public static function requirePermissionJson(string $permission, string $message, int $statusCode = 403): void
    {
        if (!hasPermission($permission)) {
            AdminResponder::jsonError($message, $statusCode);
        }
    }

    private static function refreshUserPermissions(Session $session): void
    {
        if (!class_exists('Auth')) {
            return;
        }

        $username = trim((string)$session->get('user', ''));
        if ($username === '') {
            return;
        }

        (new Auth())->loadUserPermissions($username);
    }
}
