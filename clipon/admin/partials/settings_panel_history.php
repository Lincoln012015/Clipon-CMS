<section class="settings-panel" data-tab="history_settings">
    <h2><?php echo __('settings_history_category'); ?></h2>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="history_retention_days"><?= __('settings_history_retention') ?></label>
            <select name="history_retention_days" id="history_retention_days" class="form-control settings-history-retention-select">
                <?php
                    $retentionOptions = [
                        0 => __('settings_history_retention_never'),
                        7 => sprintf(__('settings_history_retention_days'), 7),
                        14 => sprintf(__('settings_history_retention_days'), 14),
                        30 => sprintf(__('settings_history_retention_days'), 30),
                        60 => sprintf(__('settings_history_retention_days'), 60),
                        90 => sprintf(__('settings_history_retention_days'), 90),
                        180 => sprintf(__('settings_history_retention_days'), 180),
                        365 => sprintf(__('settings_history_retention_days'), 365),
                    ];
                    foreach ($retentionOptions as $days => $label):
                        $selected = ($historyRetentionDays === (int)$days) ? ' selected' : '';
                ?>
                    <option value="<?= (int)$days ?>"<?= $selected ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help-text"><?= __('settings_history_retention_help') ?></p>
        </div>

        <div class="form-group settings-group-spaced">
            <label><?= __('settings_history_manual_cleanup_title') ?></label>
            <p class="help-text settings-help-compact"><?= __('settings_history_manual_cleanup_help') ?></p>
            <button type="button"
                    class="btn btn-secondary btn-danger-outline"
                    onclick="cms_confirm('<?= addslashes(__('settings_history_clear_confirm')) ?>', () => {
                        const form = this.closest('form');
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'clear_history_versions';
                        hidden.value = '1';
                        form.appendChild(hidden);
                        form.submit();
                    })">
                <?= __('settings_history_clear') ?>
            </button>
        </div>

        <div class="form-actions settings-actions-spaced">
            <button type="submit" name="save_history" value="1" class="btn btn-primary"><?= __('save') ?></button>
        </div>
    </form>
</section>
