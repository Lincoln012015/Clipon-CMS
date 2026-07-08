<?php
require_once __DIR__ . '/../lib/Auth.php';

if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($request->isPost()) {
    $csrfToken = (string)$request->post('csrf_token', '');
    if (Csrf::validate($csrfToken)) {
        session_destroy();
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        header('Location: login.php');
        exit;
    }

    $error = __('error_invalid_csrf') ?: 'Invalid CSRF token';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(__('logout') ?: 'Logout', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
</head>
<body class="login-body">
    <div class="login-container">
        <h1><?php echo htmlspecialchars(__('logout') ?: 'Logout', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php echo Csrf::inputField(); ?>
            <button type="submit"><?php echo htmlspecialchars(__('logout') ?: 'Logout', ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <p><a href="index.php"><?php echo htmlspecialchars(__('cancel') ?: 'Cancel', ENT_QUOTES, 'UTF-8'); ?></a></p>
    </div>
</body>
</html>
