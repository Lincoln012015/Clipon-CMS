<section class="settings-panel" data-tab="license_updates">
    <div class="license-panel-grid">
        <div class="license-card">
            <?php
                $is_valid = License::isValid();
                $lic_conf = C_CONFIG_PATH . '/license.php';
                $lic_data = file_exists($lic_conf) ? require $lic_conf : [];
                $licenseStatusText = $is_valid ? __('settings_license_status_active') : __('settings_license_status_inactive');
            ?>

            <div class="license-card-header">
                <h2 class="license-card-title"><?= __('settings_license_title') ?></h2>
                <span class="badge <?= $is_valid ? 'badge-success' : 'badge-error' ?>"><?= htmlspecialchars($licenseStatusText, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <form method="POST" class="license-form license-main-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label" for="license_key"><?= __('settings_license_key_placeholder') ?></label>
                <div class="license-form-row">
                    <div class="license-input-wrapper">
                        <input type="text" id="license_key" name="license_key" class="form-control" placeholder="<?= __('settings_license_key_placeholder') ?>" value="<?= htmlspecialchars($lic_data['key'] ?? '') ?>" <?= $is_valid ? 'readonly' : '' ?>>
                        <?php
                            $unlockHint = __('settings_license_unlock_hint');
                            if ($unlockHint === 'settings_license_unlock_hint') {
                                $unlockHint = 'Unlock field for editing';
                            }
                            $lockHint = __('settings_license_lock_hint');
                            if ($lockHint === 'settings_license_lock_hint') {
                                $lockHint = 'Lock field';
                            }
                            $lockBtnLabel = $is_valid ? $unlockHint : $lockHint;
                        ?>
                        <button
                            type="button"
                            id="licenseLockBtn"
                            class="license-lock-btn"
                            data-tooltip="<?= htmlspecialchars($lockBtnLabel, ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($lockBtnLabel, ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="<?= htmlspecialchars($lockBtnLabel, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <svg class="license-lock-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <circle cx="12" cy="16" r="1"></circle>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </button>
                    </div>
                    <button type="submit" name="activate_license" class="btn btn-primary"><?= __('settings_license_activate_btn') ?></button>
                </div>
            </form>

            <?php
                $poweredByTheme = (string)($settings['powered_by_theme'] ?? 'light');
                if (!in_array($poweredByTheme, ['light', 'dark'], true)) {
                    $poweredByTheme = 'light';
                }
                $poweredByHidden = !empty($settings['powered_by_hidden']) && $is_valid;
                $poweredByDesign = $poweredByHidden ? 'disabled' : $poweredByTheme;
            ?>
            <form method="POST" class="license-form powered-by-settings-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="powered-by-settings-head">
                    <h3><?= __('settings_powered_by_title') ?></h3>
                    <p><?= __('settings_powered_by_description') ?></p>
                </div>

                <div class="powered-by-theme-options" role="radiogroup" aria-label="<?= htmlspecialchars(__('settings_powered_by_theme'), ENT_QUOTES, 'UTF-8') ?>">
                    <label class="powered-by-theme-option <?= $poweredByDesign === 'light' ? 'active' : '' ?>">
                        <input type="radio" name="powered_by_design" value="light" <?= $poweredByDesign === 'light' ? 'checked' : '' ?>>
                        <span class="powered-by-preview powered-by-preview-light">Powered by <strong>Clipon CMS</strong></span>
                        <span class="powered-by-theme-label"><?= __('settings_powered_by_theme_light') ?></span>
                    </label>
                    <label class="powered-by-theme-option <?= $poweredByDesign === 'dark' ? 'active' : '' ?>">
                        <input type="radio" name="powered_by_design" value="dark" <?= $poweredByDesign === 'dark' ? 'checked' : '' ?>>
                        <span class="powered-by-preview powered-by-preview-dark">Powered by <strong>Clipon CMS</strong></span>
                        <span class="powered-by-theme-label"><?= __('settings_powered_by_theme_dark') ?></span>
                    </label>
                    <label
                        class="powered-by-theme-option powered-by-theme-option-disabled <?= $poweredByDesign === 'disabled' ? 'active' : '' ?> <?= !$is_valid ? 'powered-by-theme-option-locked' : '' ?>"
                        <?= !$is_valid ? 'data-tooltip="' . htmlspecialchars(__('settings_powered_by_disable_locked'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                    >
                        <input type="radio" name="powered_by_design" value="disabled" <?= $poweredByDesign === 'disabled' ? 'checked' : '' ?> <?= !$is_valid ? 'disabled' : '' ?>>
                        <?php if (!$is_valid): ?>
                            <span class="pro-lock-chip" aria-hidden="true">PRO</span>
                        <?php endif; ?>
                        <span class="powered-by-theme-label"><?= __('settings_powered_by_theme_disabled') ?></span>
                    </label>
                </div>

                <div class="license-actions-row">
                    <button type="submit" name="save_powered_by" value="1" class="btn btn-primary"><?= __('save') ?></button>
                </div>
            </form>

        </div>

        <div class="license-card license-update-card">
            <div class="license-update-panel">
                <div class="license-update-header">
                    <div class="license-update-header-row">
                        <h3><?= __('settings_updates_panel_title') ?? __('settings_license_check_updates_btn') ?></h3>
                        <?php
                            $updateInfo = class_exists('CoreUpdater') ? CoreUpdater::getUpdateInfo() : null;
                            if (is_array($updateInfo) && !empty($updateInfo['version'])): ?>
                            <div class="license-update-note badge badge-warning">
                                <?= __('settings_license_update_available') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p><?= __('settings_license_update_help') ?? __('settings_updates_checking') ?></p>
                </div>

                <div class="license-update-actions">
                    <button type="button" id="manualCheckUpdatesBtn" class="btn btn-secondary"><?= __('settings_license_check_updates_btn') ?></button>
                    <?php if (!empty($lic_data['last_verified'])): ?>
                        <span class="license-update-caption"><?= __('settings_license_last_check') ?> <?= htmlspecialchars($lic_data['last_verified'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <p id="manualCheckUpdatesStatus" class="license-status-text"></p>
                <div id="manualCheckChangelogContainer" class="license-changelog-container"></div>
            </div>
        </div>
    </div>
</section>
