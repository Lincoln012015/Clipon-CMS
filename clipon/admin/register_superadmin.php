<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Translation.php';
require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/JsonStorage.php';
Translation::init();
// session is started in Auth.php
Csrf::init();

require_once __DIR__ . '/../bootstrap.php';
$userService = new UserService();
$hasAdmin = $userService->hasAdmin();

if ($hasAdmin) {
    header('Location: login.php');
    exit;
}

// Обробка форми
if ($request->isPost()) {
    if (!Csrf::validate($request->post('csrf_token', ''))) {
        $error = __('error_invalid_csrf');
    } else {
        $login = $request->string('login');
        $password = $request->post('password', '');
        $confirmPassword = $request->post('confirm_password', '');
        $name = $request->string('name');

        if (empty($login) || empty($password)) {
            $error = __('fill_all_fields');
        } elseif (!UserService::isValidLogin($login)) {
            $error = __('invalid_login_format');
        } elseif (!UserService::isValidPassword($password)) {
            $error = __('password_min_length');
        } elseif ($password !== $confirmPassword) {
            $error = __('passwords_dont_match');
        } else {
            if ($userService->createAdmin($login, $password, $name)) {
                header('Location: login.php?setup=success');
                exit;
            }

            $error = $userService->hasNormalizationConflict() ? __('user_login_conflict') : __('user_exists');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo Translation::getLang(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo __('create_admin_title'); ?></title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="lang-switcher-login">
            <select onchange="window.location.href='switch_lang.php?lang=' + this.value">
                <?php foreach (Translation::getInterfaceLangs() as $l): ?>
                    <option value="<?= $l ?>" <?= Translation::getLang() == $l ? 'selected' : '' ?>>
                        <?= strtoupper($l) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <h1><?php echo __('create_first_admin_h1'); ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?php echo __('system_not_setup'); ?></p>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <?php echo Csrf::inputField(); ?>
            <div class="form-group">
                <input type="text" name="name" placeholder="<?php echo __('name_label'); ?>">
            </div>
            <div class="form-group">
                <input type="text" name="login" placeholder="<?php echo __('login_placeholder'); ?>" required>
            </div>
            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
                    <button type="button" class="password-toggle">
                        <span class="eye-open"><?= Icons::eye(18) ?></span>
                        <span class="eye-closed"><?= Icons::eyeClosed(18) ?></span>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" placeholder="<?php echo __('confirm_password'); ?>" required>
                    <button type="button" class="password-toggle">
                        <span class="eye-open"><?= Icons::eye(18) ?></span>
                        <span class="eye-closed"><?= Icons::eyeClosed(18) ?></span>
                    </button>
                </div>
            </div>
            <button type="submit"><?php echo __('create_admin_btn'); ?></button>
        </form>
    </div>

    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=3"></script>
</body>
</html>
