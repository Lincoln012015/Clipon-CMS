<?php
$stats = $stats ?? [];
$isAnalyticsEntitled = class_exists('License')
    && License::isValid()
    && License::hasModule('pro_analytics');
$isMissingModule = $isAnalyticsEntitled
    && (!empty($stats['is_missing_module']) || (($stats['mode'] ?? '') === 'install_required'));
$noticeMessage = trim((string)($stats['stub_message'] ?? ''));

if (!$isMissingModule && $noticeMessage === '') {
    return;
}

if (!$isMissingModule) {
    return;
}

if ($noticeMessage === '' && $isMissingModule) {
    $noticeMessage = ProAnalyticsMessages::statusMessage('install_required');
}

$installPath = ProAnalyticsMessages::moduleInstallPath();
?>

<div class="analytics-card analytics-pro-notice" style="margin-bottom: 1.25rem; padding: 1.25rem 1.5rem; border-left: 4px solid var(--warning, #f59e0b); background: #fffbeb;">
    <h2 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
        <?= Icons::warning() ?>
        <?= htmlspecialchars(__('pro_analytics_missing_title'), ENT_QUOTES, 'UTF-8') ?>
    </h2>
    <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
        <?= htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">
        <?= htmlspecialchars(__('pro_analytics_missing_path'), ENT_QUOTES, 'UTF-8') ?>
        <code style="background: #f3f4f6; padding: 0.15rem 0.4rem; border-radius: 4px;"><?= htmlspecialchars($installPath, ENT_QUOTES, 'UTF-8') ?></code>
    </p>
    <a class="btn btn-secondary btn-sm" href="https://clipon-cms.com/pro" target="_blank" rel="noopener noreferrer">
        <?= htmlspecialchars(__('pro_missing_cta'), ENT_QUOTES, 'UTF-8') ?>
    </a>
</div>
