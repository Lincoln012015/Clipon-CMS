<?php
require_once __DIR__ . '/../lib/Auth.php';

// Перевірка авторизації
AdminAccess::requireUser($session);

// Перевірка доступу до сторінки користувачів
if (function_exists('hasPermission') && !hasPermission('view_pro_users') && !Gate::isAdmin()) {
    header('Location: index.php');
    exit;
}

$currentUser = UserService::normalizeLogin((string)$session->get('user', 'unknown'));
$role = (string)$session->get('role', 'unknown');

$userService = new UserService();
$currentUserData = $userService->getUser($currentUser) ?? [
    'name' => '',
    'role' => $role,
    'created' => ''
];

$isProUsersAvailable = class_exists('ModuleManager') && ModuleManager::isProAvailable('pro_users');
$isProUsersMissing = class_exists('ModuleManager') && ModuleManager::isModuleMissing('pro_users');

?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('users_title') ?></title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=17">
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('users_h1') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($currentUser) ?></p>
                </div>
                <div class="header-actions">
                    <?php Hooks::doAction('admin_users_header_actions', $currentUser, $role); ?>
                    <?php if ($role === 'admin' && !$isProUsersAvailable && !$isProUsersMissing): ?>
                        <?php AdminUI::proLockedButton(__('create_moderator_btn'), [
                            'class' => 'btn btn-primary',
                            'data-tooltip' => __('pro_users_locked_tooltip')
                        ]); ?>
                    <?php endif; ?>
                </div>
            </header>

            <?php
            $flash = Flash::pull('main');
            include __DIR__ . '/partials/admin_flash.php';
            ?>

            <?php if ($role === 'admin' && $isProUsersMissing): ?>
                <?php AdminUI::proGateStart('pro_users'); ?>
            <?php endif; ?>
            
            <div class="user-list">
                <?php
                $summary = '
                    <div class="user-info-inline">
                        <span class="user-name">' . htmlspecialchars($currentUser) .
                        (!empty($currentUserData['name']) ? ' <small class="user-name-meta">(' . htmlspecialchars($currentUserData['name']) . ')</small>' : '') . '
                        </span>
                        <span class="badge ' . (($currentUserData['role'] ?? $role) === 'admin' ? 'badge-success' : 'badge-info') . '">' .
                        strtoupper((string)($currentUserData['role'] ?? $role)) . '</span>
                        <span class="user-date">' . __('created_at') . ': ' . htmlspecialchars($currentUserData['created'] ?? '—') . '</span>
                    </div>
                ';

                AdminUI::accordionStart($summary, false, [], true);
                ?>
                    <div class="user-item-content">
                        <?php AdminUI::formGridStart(2); ?>
                            <div>
                                <form class="admin-form user-form-section" data-user-type="profile">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($currentUser) ?>">
                                    <?php echo Csrf::inputField(); ?>
                                    <h3><?= __('profile_settings') ?></h3>
                                    <div class="form-group">
                                        <label><?= __('display_name') ?></label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentUserData['name'] ?? '') ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?= __('save_btn') ?></button>
                                </form>
                            </div>

                            <div>
                                <form class="admin-form user-form-section" data-user-type="password">
                                    <input type="hidden" name="action" value="update_password">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($currentUser) ?>">
                                    <?php echo Csrf::inputField(); ?>
                                    <h3><?= __('change_password') ?></h3>
                                    <div class="form-group">
                                        <label><?= __('current_password') ?></label>
                                        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                                    </div>
                                    <div class="form-group">
                                        <label><?= __('new_password') ?></label>
                                        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                                    </div>
                                    <div class="form-group">
                                        <label><?= __('confirm_password') ?></label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?= __('change_password_btn') ?></button>
                                </form>
                            </div>
                        <?php AdminUI::formGridEnd(); ?>
                    </div>
                <?php AdminUI::accordionEnd(); ?>
                <?php Hooks::doAction('admin_users_after_self_profile', $currentUser, $role); ?>
            </div>

            <?php Hooks::doAction('admin_users_modals', $currentUser, $role); ?>
        </main>
    </div>

    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script>
        window.USER_ADMIN_CONFIG = {
            csrfToken: <?= json_encode(getCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
        window.USER_ADMIN_LANG = {
            delete_user_confirm: '<?= addslashes(__('delete_user_confirm')) ?>',
            api_error: '<?= addslashes(__('system_error')) ?>'
        };
    </script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-users.js?v=2"></script>
</body>
</html>
