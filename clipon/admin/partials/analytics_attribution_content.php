<?php
$stats = $stats ?? [];
$attr = $stats['attribution'] ?? ['first_touch' => [], 'last_touch' => [], 'recent' => []];

function getWidthAt($count, $total) {
    if ($total == 0) return 0;
    return round(($count / $total) * 100, 1);
}

$first = $attr['first_touch'] ?? [];
$last = $attr['last_touch'] ?? [];
arsort($first);
arsort($last);
$maxFirst = !empty($first) ? max($first) : 0;
$maxLast = !empty($last) ? max($last) : 0;
$recent = $attr['recent'] ?? [];
?>

<div class="analytics-grid">
    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_first_touch') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);">&sum; <?php $analyticsMetric(array_sum($first)); ?></span>
        </div>
        <table class="analytics-table">
            <?php if (empty($first)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach (array_slice($first, 0, 10) as $label => $count): ?>
                <tr>
                    <td style="width: 70%; word-break: break-word;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem;"><?php $analyticsMetric($label); ?></div>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?= getWidthAt($count, $maxFirst) ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 700;"><?php $analyticsMetric(number_format($count)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_last_touch') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);">&sum; <?php $analyticsMetric(array_sum($last)); ?></span>
        </div>
        <table class="analytics-table">
            <?php if (empty($last)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach (array_slice($last, 0, 10) as $label => $count): ?>
                <tr>
                    <td style="width: 70%; word-break: break-word;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem;"><?php $analyticsMetric($label); ?></div>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?= getWidthAt($count, $maxLast) ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 700;"><?php $analyticsMetric(number_format($count)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>

<div class="analytics-card" style="margin-top: 1.5rem;">
    <div class="analytics-card-header">
        <h2><?= __('analytics_recent_conversions') ?></h2>
        <span style="font-size: 0.875rem; color: var(--text-secondary);"><?php $analyticsMetric('max 100'); ?></span>
    </div>
    <table class="analytics-table">
        <?php if (empty($recent)): ?>
            <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
        <?php else: foreach (array_slice(array_reverse($recent), 0, 20) as $row): ?>
            <tr>
                <td>
                    <div style="font-weight: 600; margin-bottom: 0.35rem;">
                        <?php $analyticsMetric($row['uri'] ?? ''); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                        <?php $analyticsMetric(($row['type'] ?? '') . ' - ' . ($row['first'] ?? '') . ' > ' . ($row['last'] ?? '') . ' - ' . date('Y-m-d H:i', $row['ts'] ?? time())); ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
</div>
