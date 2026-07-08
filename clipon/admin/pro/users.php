<?php
define('C_PRO_PROXY', true);

require_once __DIR__ . '/../../lib/Auth.php';

if (!$session->has('user')) {
    header('Location: ../login.php');
    exit;
}

if (function_exists('hasPermission') && !hasPermission('view_pro_users')) {
    header('Location: ../index.php');
    exit;
}

if (class_exists('ModuleManager') && ModuleManager::isProAvailable('pro_users')) {
    header('Location: ../users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <title><?= htmlspecialchars(__('users_title'), ENT_QUOTES, 'UTF-8') ?> - PRO</title>
</head>
<body class="admin-body">
    <div class="admin-container">
        <?php include __DIR__ . '/../nav.php'; ?>
        <main class="main-content" style="position: relative;">
            <?php AdminUI::proGateStart('pro_users'); ?>
        </main>
    </div>
</body>
</html>
