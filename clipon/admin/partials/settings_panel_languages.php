<section class="settings-panel" data-tab="languages">
    <h2><?php echo __('settings_languages_category'); ?></h2>
    <p class="help-text"><?= __('settings_languages_help') ?></p>

    <?php 
    $isMultilangPro = ModuleManager::isProAvailable('multilang'); 
    $isMultilangLicensed = ModuleManager::isProLicensed('multilang');
    $isMultilangMissing = ModuleManager::isModuleMissing('multilang');
    ?>

    <form method="POST" id="languagesForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="languages_json" id="languagesJson">
        <div id="languagesList" class="languages-list settings-languages-list">
            <?php foreach ($languages as $index => $lang): ?>
                <?php if (!$isMultilangLicensed && $index > 0) continue; ?>
                <div class="lang-item" data-code="<?= htmlspecialchars($lang['code']) ?>" data-name="<?= htmlspecialchars($lang['name']) ?>">
                    <?php if ($isMultilangLicensed): ?>
                        <div class="lang-drag-handle"><?= Icons::drag() ?></div>
                    <?php endif; ?>
                    <div class="lang-info">
                        <strong><?= htmlspecialchars($lang['name']) ?></strong> (<?= htmlspecialchars($lang['code']) ?>)
                    </div>
                    <?php if ($isMultilangLicensed): ?>
                    <div class="lang-actions">
                        <input type="checkbox" class="lang-enabled" <?= !empty($lang['enabled']) ? 'checked' : '' ?> data-tooltip="<?= __('settings_languages_enabled') ?>">
                        <button type="button" class="icon-btn delete-lang" data-tooltip="<?= __('settings_languages_delete') ?>"><?= Icons::delete() ?></button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($isMultilangMissing): ?>
            <div class="info-card pro-missing" style="margin-top: 12px; text-align: center; padding: 20px; border-left: 4px solid var(--warning);">
                <h3><?= Icons::warning() ?> <?= __('pro_missing_title') ?? 'Module files are missing' ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 15px;">
                    <?= __('pro_missing_description') ?? 'Your license includes this module, but the module files are not installed.' ?>
                </p>
                <a href="https://clipon-cms.com/pro" target="_blank" class="btn btn-secondary"><?= __('pro_missing_cta') ?? 'How to install' ?></a>
            </div>
        <?php elseif ($isMultilangLicensed): ?>
            <div class="info-card settings-languages-add-row">
                <input type="text" id="newLangName" placeholder="<?= __('settings_languages_name_placeholder') ?>" class="form-control settings-languages-input-name">
                <input type="text" id="newLangCode" placeholder="<?= __('settings_languages_code_placeholder') ?>" class="form-control settings-languages-input-code">
                <button type="button" id="addLangBtn" class="btn btn-secondary"><?= __('settings_languages_add') ?></button>
            </div>

            <div class="info-card" style="margin-top: 12px;">
                <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="checkbox" name="migrate_primary_content" value="1" checked>
                    <span><?= __('settings_languages_migrate_primary_content') ?></span>
                </label>
            </div>
        <?php else: ?>
            <div class="info-card pro-upsell" style="margin-top: 12px; text-align: center; padding: 20px;">
                <h3><?= Icons::lock() ?> <?= __('pro_locked_title') ?? 'Multilanguage is a PRO feature' ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 15px;"><?= __('pro_upgrade_description') ?? 'Upgrade to Clipon PRO to add and manage multiple languages, translate content, and improve your international SEO.' ?></p>
                <a href="https://clipon-cms.com/pro" target="_blank" class="btn btn-primary"><?= __('pro_upgrade_cta') ?? 'Upgrade to PRO' ?></a>
            </div>
        <?php endif; ?>

        <?php if ($isMultilangLicensed && !$isMultilangMissing): ?>
        <div class="form-actions">
            <button type="submit" name="save_languages" value="1" class="btn btn-primary"><?= __('settings_languages_save') ?></button>
        </div>
        <?php endif; ?>
    </form>
</section>
