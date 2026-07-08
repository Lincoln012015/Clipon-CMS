<div class="settings-container">
    <div class="settings-tabs">
        <button type="button" class="settings-tab active" data-tab="general"><?php echo __('settings_tab_general'); ?></button>
        <button type="button" class="settings-tab" data-tab="license_updates"><?php echo __('settings_tab_license_updates'); ?></button>
        <button type="button" class="settings-tab" data-tab="languages"><?php echo __('settings_tab_languages'); ?></button>
        <button type="button" class="settings-tab" data-tab="markup_settings"><?php echo __('settings_tab_markup'); ?></button>
        <button type="button" class="settings-tab" data-tab="blog_settings"><?php echo __('settings_tab_blog'); ?></button>
        <button type="button" class="settings-tab" data-tab="analytics_settings"><?php echo __('settings_tab_analytics'); ?></button>
        <button type="button" class="settings-tab" data-tab="history_settings"><?php echo __('settings_tab_history'); ?></button>
    </div>

    <div class="settings-panels">
        <?php include __DIR__ . '/settings_panel_general.php'; ?>
        <?php include __DIR__ . '/settings_panel_license.php'; ?>
        <?php include __DIR__ . '/settings_panel_languages.php'; ?>
        <?php include __DIR__ . '/settings_panel_markup.php'; ?>
        <?php include __DIR__ . '/settings_panel_blog.php'; ?>
        <?php include __DIR__ . '/settings_panel_analytics.php'; ?>
        <?php include __DIR__ . '/settings_panel_history.php'; ?>
    </div>
</div>
