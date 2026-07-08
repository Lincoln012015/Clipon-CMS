<form class="filter-bar" method="GET">
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <label style="font-size: 0.875rem; color: var(--text-secondary);"><?= __('analytics_period') ?>:</label>
        <?php if (($analyticsLockedInput ?? null) && $analyticsLockedInput((string)$from, ['type' => 'date', 'style' => 'width: 10.5rem;'])): ?>
        <?php else: ?>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        <?php endif; ?>
        <span>—</span>
        <?php if (($analyticsLockedInput ?? null) && $analyticsLockedInput((string)$to, ['type' => 'date', 'style' => 'width: 10.5rem;'])): ?>
        <?php else: ?>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        <?php endif; ?>
        <?php if (isset($selectedFunnelId) && $selectedFunnelId !== ''): ?>
            <input type="hidden" name="funnelIdHidden" value="<?= htmlspecialchars($selectedFunnelId) ?>">
        <?php endif; ?>
    </div>

    <?php if (isset($funnelsList)): ?>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <label style="font-size: 0.875rem; color: var(--text-secondary);"><?= __('analytics_funnel_filter') ?>:</label>
        <?php
        $selectedFunnelLabel = __('analytics_funnel_all');
        foreach ($funnelsList as $funnel) {
            if (($selectedFunnelId ?? '') === ($funnel['id'] ?? '')) {
                $selectedFunnelLabel = (string)($funnel['name'] ?? $selectedFunnelLabel);
                break;
            }
        }
        ?>
        <?php if (($analyticsLockedInput ?? null) && $analyticsLockedInput($selectedFunnelLabel, ['style' => 'width: 13rem;'])): ?>
        <?php else: ?>
        <select name="funnel_id">
            <option value=""><?= __('analytics_funnel_all') ?></option>
            <?php foreach ($funnelsList as $funnel): $fid = $funnel['id'] ?? ''; ?>
                <option value="<?= htmlspecialchars($fid) ?>" <?= ($selectedFunnelId ?? '') === $fid ? 'selected' : '' ?>>
                    <?= htmlspecialchars($funnel['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($selectedFunnelId ?? ''): ?>
            <?php if (($analyticsLockedButton ?? null) && $analyticsLockedButton(__('analytics_clear_filter'), ['class' => 'bg-secondary-btn'])): ?>
            <?php else: ?>
            <a href="?from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>" class="bg-secondary-btn"><?= __('analytics_clear_filter') ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (($analyticsLockedButton ?? null) && $analyticsLockedButton(__('analytics_apply'), ['class' => 'btn-apply'])): ?>
    <?php else: ?>
    <button type="submit" class="btn-apply"><?= __('analytics_apply') ?></button>
    <?php endif; ?>
    <div style="margin-left: auto; display: flex; gap: 0.5rem;">
        <?php if (($analyticsLockedButton ?? null) && $analyticsLockedButton(__('analytics_today'), ['class' => 'bg-secondary-btn'])): ?>
        <?php else: ?>
        <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?><?= isset($selectedFunnelId) && $selectedFunnelId !== '' ? '&funnel_id=' . urlencode($selectedFunnelId) : '' ?>" class="bg-secondary-btn"><?= __('analytics_today') ?></a>
        <?php endif; ?>
        <?php if (($analyticsLockedButton ?? null) && $analyticsLockedButton(__('analytics_last_30_days'), ['class' => 'bg-secondary-btn'])): ?>
        <?php else: ?>
        <a href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?><?= isset($selectedFunnelId) && $selectedFunnelId !== '' ? '&funnel_id=' . urlencode($selectedFunnelId) : '' ?>" class="bg-secondary-btn"><?= __('analytics_last_30_days') ?></a>
        <?php endif; ?>
    </div>
</form>
