<section class="settings-panel" data-tab="general">
    <h2><?php echo __('settings_general_category'); ?></h2>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="site_name"><?= __('settings_site_name') ?></label>
            <input type="text" name="site_name" id="site_name"
                   value="<?= htmlspecialchars($settings['site_name'] ?? 'Clipon CMS') ?>" class="form-control">
        </div>
        <div class="form-group">
            <label for="site_description"><?= __('settings_site_description') ?></label>
            <textarea name="site_description" id="site_description" class="form-control" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="site_email"><?= __('settings_site_email') ?></label>
            <input type="email" name="site_email" id="site_email"
                   value="<?= htmlspecialchars($settings['site_email'] ?? '') ?>" class="form-control">
        </div>
        <div class="form-group">
            <label for="site_url"><?= __('settings_site_url') ?></label>
            <input type="url" name="site_url" id="site_url"
                   placeholder="https://example.com"
                   value="<?= htmlspecialchars($settings['site_url'] ?? '') ?>" class="form-control">
        </div>
        <div class="form-actions">
            <button type="submit" name="save_general" value="1" class="btn btn-primary"><?= __('save') ?></button>
        </div>
    </form>
</section>
