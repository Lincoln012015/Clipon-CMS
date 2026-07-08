<?php
require_once __DIR__ . '/../lib/Auth.php';
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

$analyticsService = clipon_pro_analytics_service();
$stats = $analyticsService->getAnalyticsViewData($from, $to);

function getWidth($count, $total) {
    if ($total == 0) return 0;
    return round(($count / $total) * 100, 1);
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('analytics_title') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin-analytics.css">
    <script src="<?= C_ASSETS_URL ?>/vendor/chartjs/chart.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/partials/analytics_content.php'; ?>
</body>
</html>
