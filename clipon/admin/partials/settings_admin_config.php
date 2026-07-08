<script>
    window.SETTINGS_ADMIN_CONFIG = {
        langSettings: <?= json_encode([
            'error_empty' => __('settings_languages_error_empty'),
            'error_format' => __('settings_languages_error_format'),
            'error_duplicate' => __('settings_languages_error_duplicate') !== 'settings_languages_error_duplicate' ? __('settings_languages_error_duplicate') : 'Language code already exists',
            'enabled' => __('settings_languages_enabled'),
            'delete' => __('settings_languages_delete'),
            'error_last' => __('settings_languages_error_last')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        csrfToken: <?= json_encode(getCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        geoIpApiUrl: <?= json_encode(C_ADMIN_URL . '/api/geoip.php', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        langGeoIp: <?= json_encode([
            'status_installed' => __('settings_geoip_status_installed'),
            'status_missing' => __('settings_geoip_status_missing'),
            'status_outdated' => __('settings_geoip_status_outdated'),
            'status_error' => __('settings_geoip_status_error'),
            'updating' => __('settings_geoip_updating'),
            'network_error' => __('settings_geoip_network_error'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        langIconDrag: <?= json_encode(Icons::drag(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        langIconDelete: <?= json_encode(Icons::delete(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        updatesApiUrl: <?= json_encode(C_ADMIN_URL . '/api/updates.php', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        langUpdates: <?= json_encode([
            'checking' => __('settings_updates_checking'),
            'error_default' => __('settings_updates_error_default'),
            'core_new_prefix' => __('settings_updates_core_new_prefix'),
            'core_none' => __('settings_updates_core_none'),
            'changelog_title' => __('settings_updates_changelog_title'),
            'go_to_update' => __('settings_license_go_to_update'),
            'pro_synced' => __('settings_updates_pro_synced'),
            'pro_skipped' => __('settings_updates_pro_skipped'),
            'pro_revoked' => __('settings_updates_pro_revoked'),
            'pro_error_prefix' => __('settings_updates_pro_error_prefix'),
            'network_error' => __('settings_updates_network_error')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        langLicense: <?= json_encode([
            'unlock_hint' => __('settings_license_unlock_hint') !== 'settings_license_unlock_hint' ? __('settings_license_unlock_hint') : 'Unlock field for editing',
            'lock_hint' => __('settings_license_lock_hint') !== 'settings_license_lock_hint' ? __('settings_license_lock_hint') : 'Lock field'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };

    window.SETTINGS_UPDATE_INFO = <?= json_encode(class_exists('CoreUpdater') ? CoreUpdater::getUpdateInfo() : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.SETTINGS_LAST_CHECK_STATUS = <?= json_encode(class_exists('CoreUpdater') ? CoreUpdater::getLastCheckStatus() : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
