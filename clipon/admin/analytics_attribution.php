<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/Flash.php';
require_once __DIR__ . '/lib/analytics_service.php';

if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

if (!hasPermission('view_analytics')) {
    header('Location: index.php');
    exit;
}

$user = is_string($session->get('user')) ? $session->get('user') : 'unknown';

$to = $request->query('to', date('Y-m-d'));
$from = $request->query('from', date('Y-m-d', strtotime('-30 days')));

$settings = Settings::load();
$attrEnabled = !empty($settings['enable_attribution']);

Csrf::init();

$flash = Flash::pull('attr');

$stats = clipon_pro_analytics_service()->getAttributionViewData($from, $to);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('analytics_attribution_tab') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin-analytics.css">
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <?php $gateState = AdminUI::proGateStart('pro_analytics'); ?>
            <?php include __DIR__ . '/partials/analytics_pro_helpers.php'; ?>
            <header class="header">
                <div class="header-title">
                    <h1><?= __('analytics_attribution_tab') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($user) ?></p>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="toast-container" id="toast-container">
                    <div class="toast <?= $flash['type'] === 'success' ? 'toast-success' : 'toast-error' ?>" role="status">
                        <div class="toast-body" style="font-weight: 600;">
                            <?= htmlspecialchars($flash['message'] ?? '') ?>
                        </div>
                        <button class="toast-close" type="button" aria-label="Close">x</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/partials/analytics_pro_notice.php'; ?>
            <?php $analyticsProDataHidden = !empty($stats['is_missing_module']); ?>

            <?php if (!$analyticsProDataHidden): ?>
            <?php if ($attrEnabled): ?>
                <?php include __DIR__ . '/partials/analytics_filter.php'; ?>
            <?php endif; ?>

            <?php if (!$attrEnabled): ?>
                <div class="analytics-card" style="padding: 1.25rem; border: 1px dashed var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <h2 style="margin: 0 0 0.35rem 0; font-size: 1rem; font-weight: 600;"><?= __('analytics_feature_disabled') ?></h2>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                                <?= __('settings_funnels_desc') ?>
                            </p>
                        </div>
                        <?php if ($analyticsLockedButton(__('analytics_enable_in_settings'), ['class' => 'btn', 'style' => 'white-space: nowrap;'])): ?>
                        <?php else: ?>
                            <a class="btn" href="settings.php" style="white-space: nowrap;"><?= __('analytics_enable_in_settings') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php include __DIR__ . '/partials/analytics_attribution_content.php'; ?>
            <?php endif; ?>
            <?php endif; ?>
            <?php AdminUI::proGateEnd($gateState ?? []); ?>
        </main>
    </div>
    <script>
    (function() {
        const toast = document.querySelector('.toast');
        if (toast) {
            const closer = toast.querySelector('.toast-close');
            const remove = () => toast.remove();
            if (closer) closer.addEventListener('click', remove);
            setTimeout(remove, 4000);
        }
    })();
    </script>
</body>
</html>
