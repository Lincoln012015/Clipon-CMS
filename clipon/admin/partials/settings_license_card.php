<div class="dashboard-grid">
    <div class="dashboard-card" style="grid-column: span 3; background: #fff; border-left: 5px solid #000;">
        <h3><?= __('settings_license_title') ?></h3>
        <?php
            $is_valid = License::isValid();
            $lic_conf = C_CONFIG_PATH . '/license.php';
            $lic_data = file_exists($lic_conf) ? require $lic_conf : [];
            $licenseStatusText = $is_valid ? __('settings_license_status_active') : __('settings_license_status_inactive');
            $licenseStatusColor = $is_valid ? 'green' : 'red';
        ?>
        <p><?= __('settings_license_status_label') ?>: <b><span style="color:<?= $licenseStatusColor ?>;"><?= htmlspecialchars($licenseStatusText, ENT_QUOTES, 'UTF-8') ?></span></b></p>

        <form method="POST" style="display: flex; gap: 10px; align-items: center; margin-top: 15px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="license_key" placeholder="<?= __('settings_license_key_placeholder') ?>" value="<?= htmlspecialchars($lic_data['key'] ?? '') ?>" style="flex-grow: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" name="activate_license" class="btn btn-primary"><?= __('settings_license_activate_btn') ?></button>
            <button type="button" id="manualCheckUpdatesBtn" class="btn btn-secondary"><?= __('settings_license_check_updates_btn') ?></button>
        </form>
        <p id="manualCheckUpdatesStatus" style="font-size: 12px; color: #666; margin-top: 10px; display: none;"></p>
        <?php
            $updateInfo = class_exists('CoreUpdater') ? CoreUpdater::getUpdateInfo() : null;
            if (is_array($updateInfo) && !empty($updateInfo['version'])):
        ?>
            <p style="margin-top: 10px; color: #8a5a00;">
                <?= __('settings_license_update_available') ?>: <b><?= htmlspecialchars((string)$updateInfo['version']) ?></b>
                <?php if (!empty($updateInfo['url'])): ?>
                    - <a href="<?= htmlspecialchars((string)$updateInfo['url']) ?>" target="_blank" rel="noopener"><?= __('settings_license_go_to_update') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($lic_data['last_verified'])): ?>
            <p style="font-size: 12px; color: #666; margin-top: 10px;"><?= __('settings_license_last_check') ?>: <?= $lic_data['last_verified'] ?></p>
        <?php endif; ?>
    </div>
</div>
