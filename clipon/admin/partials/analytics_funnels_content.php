<?php
$stats = $stats ?? [];
$funnels = $stats['funnels'] ?? ['completed' => [], 'recent' => []];

function getWidthAt($count, $total) {
    if ($total == 0) return 0;
    return round(($count / $total) * 100, 1);
}

$completed = $funnels['completed'] ?? [];
arsort($completed);
$maxPath = !empty($completed) ? max($completed) : 0;
$recent = $funnels['recent'] ?? [];
$totalCompleted = array_sum($completed);

$transitions = [];
$lengths = [];
$stepSum = 0;

foreach ($completed as $pathKey => $count) {
    $steps = array_values(array_filter(array_map('trim', explode(' > ', $pathKey))));
    $len = count($steps);
    if ($len > 0) {
        $lengths[$len] = ($lengths[$len] ?? 0) + $count;
        $stepSum += $len * $count;
    }
    if ($len > 1) {
        for ($i = 0; $i < $len - 1; $i++) {
            $edge = $steps[$i] . ' -> ' . $steps[$i + 1];
            $transitions[$edge] = ($transitions[$edge] ?? 0) + $count;
        }
    }
}
arsort($transitions);
$maxTrans = !empty($transitions) ? max($transitions) : 0;
ksort($lengths);
$avgSteps = ($totalCompleted > 0 && $stepSum > 0) ? round($stepSum / $totalCompleted, 2) : 0;
?>

<div class="analytics-grid" style="margin-bottom: 1.25rem;">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <div class="analytics-card-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h2><?= __('analytics_saved_funnels') ?></h2>
                <span style="font-size: 0.875rem; color: var(--text-secondary);">&sum; <?php $analyticsMetric(count($funnelsList)); ?></span>
            </div>
            <?php if ($analyticsLockedButton(__('analytics_add_funnel'), ['class' => 'btn btn-sm'])): ?>
            <?php else: ?>
            <button type="button" class="btn btn-sm" onclick="document.getElementById('add-funnel-modal').classList.add('active')">
                <?= Icons::plus(16) ?> <?= __('analytics_add_funnel') ?>
            </button>
            <?php endif; ?>
        </div>
        <div style="padding: 1rem 1.5rem;">
            <?php if (empty($funnelsList)): ?>
                <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                    <?= __('analytics_funnel_empty') ?>
                </p>
            <?php else: ?>
                <table class="analytics-table">
                    <?php foreach ($funnelsList as $funnel): $fid = $funnel['id'] ?? ''; ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; margin-bottom: 0.35rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    <span><?php $analyticsMetric($funnel['name'] ?? ''); ?></span>
                                    <?php if (!empty($funnel['ordered'])): ?>
                                        <span style="font-size: 0.75rem; background: var(--primary-light); color: var(--primary-color); padding: 0.2rem 0.45rem; border-radius: var(--radius-sm);">
                                            <?= __('analytics_funnel_ordered_badge') ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($selectedFunnelId === $fid): ?>
                                        <span style="font-size: 0.75rem; background: #ecfdf3; color: #15803d; padding: 0.2rem 0.45rem; border-radius: var(--radius-sm);">
                                            <?= __('analytics_funnel_active') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); word-break: break-word;">
                                    <?php $analyticsMetric(implode(' -> ', $funnel['steps'] ?? [])); ?>
                                </div>
                            </td>
                            <td style="width: 220px; text-align: right; white-space: nowrap;">
                                <?php if ($analyticsLockedButton(__('analytics_funnel_apply'), ['class' => 'btn btn-secondary btn-sm', 'style' => 'margin-right: 0.35rem;'])): ?>
                                <?php else: ?>
                                <a class="btn btn-secondary btn-sm" href="?from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>&funnel_id=<?= htmlspecialchars($fid) ?>" style="margin-right: 0.35rem;">
                                    <?= __('analytics_funnel_apply') ?>
                                </a>
                                <?php endif; ?>
                                <?php if ($analyticsLockedButton(__('delete'), ['class' => 'btn btn-danger btn-sm'])): ?>
                                <?php else: ?>
                                <form method="POST" style="display: inline-block; margin: 0;">
                                    <?= Csrf::inputField(); ?>
                                    <input type="hidden" name="action" value="delete_funnel">
                                    <input type="hidden" name="funnel_id" value="<?= htmlspecialchars($fid) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return cmsConfirmSubmit(event, '<?= __('analytics_funnel_delete_confirm') ?>');">
                                        <?= __('delete') ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="analytics-grid">
    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_funnel_paths') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);">&sum; <?php $analyticsMetric($totalCompleted); ?></span>
        </div>
        <table class="analytics-table">
            <?php if (empty($completed)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach (array_slice($completed, 0, 10) as $path => $count): ?>
                <tr>
                    <td style="width: 70%; word-break: break-word;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem;"><?php $analyticsMetric($path); ?></div>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?= getWidthAt($count, $maxPath) ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 700;"><?php $analyticsMetric(number_format($count)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_recent_paths') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);"><?php $analyticsMetric('max 50'); ?></span>
        </div>
        <table class="analytics-table">
            <?php if (empty($recent)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach (array_slice(array_reverse($recent), 0, 15) as $row): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600; margin-bottom: 0.35rem;">
                            <?php $analyticsMetric(implode(' > ', $row['path'] ?? [])); ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?php $analyticsMetric(($row['type'] ?? '') . ' - ' . date('Y-m-d H:i', $row['ts'] ?? time())); ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>

<div class="analytics-grid" style="margin-top: 1.25rem;">
    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_step_transitions') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);"><?php $analyticsMetric('max 10'); ?></span>
        </div>
        <table class="analytics-table">
            <?php if (empty($transitions)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach (array_slice($transitions, 0, 10) as $edge => $count): ?>
                <tr>
                    <td style="width: 70%; word-break: break-word;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem;"><?php $analyticsMetric($edge); ?></div>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?= getWidthAt($count, $maxTrans) ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 700;"><?php $analyticsMetric(number_format($count)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header">
            <h2><?= __('analytics_path_length') ?></h2>
            <span style="font-size: 0.875rem; color: var(--text-secondary);">
                <?php $analyticsMetric(sprintf(__('analytics_path_length_avg'), $avgSteps)); ?>
            </span>
        </div>
        <table class="analytics-table">
            <?php if (empty($lengths)): ?>
                <tr><td style="text-align: center; padding: 2rem; color: var(--text-muted);"><?= __('analytics_no_data') ?></td></tr>
            <?php else: foreach ($lengths as $len => $count): ?>
                <tr>
                    <td style="width: 70%; word-break: break-word;">
                        <div style="font-weight: 600; margin-bottom: 0.35rem;">
                            <?php $analyticsMetric(sprintf(__('analytics_steps_label'), (int)$len)); ?>
                        </div>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?= getWidthAt($count, max($lengths)) ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 700;"><?php $analyticsMetric(number_format($count)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
