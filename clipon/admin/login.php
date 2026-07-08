<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Translation.php';
require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/LoginThrottle.php';
require_once __DIR__ . '/../lib/JsonStorage.php';
Translation::init();
Csrf::init();

// Якщо вже залогінений, перенаправити до адмінки
if ($session->has('user')) {
    header('Location: index.php');
    exit;
}

// Обробка форми логіну
if ($request->isPost()) {
    if (!Csrf::validate($request->post('csrf_token') ?? '')) {
        $error = __('error_invalid_csrf');
    } else {
        $login = $request->string('login');
        $password = $request->post('password') ?? '';
        
        // Throttle: перевіряємо блокування по логіну або IP
        $ip = $request->server('REMOTE_ADDR', 'unknown');
        $th = throttle_is_locked($login, $ip);
        if (!empty($th['locked'])) {
            $remaining = (int)($th['remaining'] ?? 0);
            $mins = ceil($remaining / 60);
            $error = __('error_too_many_attempts');
            if ($mins > 0) $error .= ' ' . sprintf(__('try_again_minutes'), $mins);
        } else {
            // Завантаження користувачів
            $auth = new Auth();

            if ($auth->attemptLogin($login, $password)) {
                throttle_reset($login, $ip);
                header('Location: index.php');
                exit;
            } else {
                $delay = throttle_register_failure($login, $ip);
                // базова затримка для сповільнення брутфорсу
                sleep($delay);
                $session->set('login_attempts', $session->get('login_attempts', 0) + 1);
                $error = __('invalid_login');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo __('login_title'); ?></title>
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
        <h1><?php echo __('login_h1'); ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($request->query('timeout') === '1'): ?>
            <div class="error"><?php echo htmlspecialchars(__('session_expired')); ?></div>
        <?php endif; ?>

        <?php if ($request->query('setup') === 'success'): ?>
            <div class="success"><?php echo __('admin_created_success'); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php echo Csrf::inputField(); ?>
            <input type="text" name="login" placeholder="<?php echo __('login_placeholder'); ?>" required>
            <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
            <button type="submit"><?php echo __('login_btn'); ?></button>
        </form>

        <?php
        $repo = new UserRepository();
        $users = $repo->getAll();
        $hasAdmin = false;
        foreach ($users as $u) {
            if (($u['role'] ?? '') === 'admin') {
                $hasAdmin = true;
                break;
            }
        }
        if (!$hasAdmin): ?>
            <p><a href="register_superadmin.php"><?php echo __('create_first_admin'); ?></a></p>
        <?php endif; ?>
    </div>
</body>
</html>
