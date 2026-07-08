<?php
require_once __DIR__ . '/../lib/Auth.php';

// Event: Admin Initialization
Hooks::doAction('admin_init');

// Перевірка авторизації
if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

$user = is_string($session->get('user')) ? $session->get('user') : 'unknown';
$role = is_string($session->get('role')) ? $session->get('role') : 'unknown';

require_once __DIR__ . '/../lib/Analytics.php';

// Фільтрація за датою для базової аналітики
$from = $request->query('from', date('Y-m-d', strtotime('-7 days')));
$to = $request->query('to', date('Y-m-d'));

// Валідація дат
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$stats = Analytics::getBasicStats($from, $to);
?>

<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_title') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin-analytics.css">
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('dashboard') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($user) ?> (<?= htmlspecialchars($role) ?>)</p>
                </div>
                <div class="header-actions">
                    <a href="analytics.php" class="btn btn-primary"><?= __('analytics_view_full') ?? 'Повна аналітика' ?></a>
                </div>
            </header>

            <div class="dashboard-analytics-section">
                <?php include __DIR__ . '/partials/analytics_basic_stats.php'; ?>
            </div>
        </main>
    </div>
</body>
</html>