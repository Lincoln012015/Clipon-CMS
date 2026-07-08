<section class="settings-panel" data-tab="analytics_settings">
    <div class="settings-panel-heading">
        <h2><?php echo __('settings_analytics_category'); ?></h2>
    </div>

    <form method="POST" class="admin-form settings-analytics-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="settings-analytics-layout">
            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_analytics_data_title') ?></h3>
                        <p><?= __('settings_analytics_data_desc') ?></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="analytics_retention"><?= __('settings_analytics_retention') ?></label>
                    <div class="settings-analytics-retention-row">
                        <input type="number" name="analytics_retention" id="analytics_retention"
                               value="<?= (int)($settings['analytics_retention'] ?? 36) ?>"
                               min="1" max="120" class="form-control settings-analytics-retention-input">

                        <button type="button"
                            class="btn btn-secondary btn-danger-outline btn-compact"
                                onclick="cms_confirm('<?= addslashes(__('settings_analytics_clear_confirm')) ?>', () => {
                                    const form = this.closest('form');
                                    const hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = 'clear_analytics';
                                    hidden.value = '1';
                                    form.appendChild(hidden);
                                    form.submit();
                                })">
                            <?= __('settings_analytics_clear') ?>
                        </button>
                    </div>
                    <p class="help-text"><?= __('settings_analytics_retention_help') ?></p>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_bot_filter_title') ?></h3>
                        <p><?= __('settings_bot_filter_desc') ?></p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-compact" data-open-bot-filter-log>
                        <?= __('settings_bot_filter_view_log') ?>
                    </button>
                </div>

                <div class="settings-cookie-grid">
                    <label>
                        <span><?= __('settings_bot_filter_allowlist') ?></span>
                        <textarea name="analytics_bot_allowlist" class="form-control" rows="4"><?= htmlspecialchars(implode("\n", (array)($settings['analytics_bot_allowlist'] ?? [])), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="help-text"><?= __('settings_bot_filter_allowlist_help') ?></small>
                    </label>
                    <label>
                        <span><?= __('settings_bot_filter_denylist') ?></span>
                        <textarea name="analytics_bot_denylist" class="form-control" rows="4"><?= htmlspecialchars(implode("\n", (array)($settings['analytics_bot_denylist'] ?? [])), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="help-text"><?= __('settings_bot_filter_denylist_help') ?></small>
                    </label>
                </div>

                <label class="settings-inline-checkbox">
                    <input type="checkbox" name="analytics_bot_filter_debug" value="1" <?= !empty($settings['analytics_bot_filter_debug']) ? 'checked' : '' ?>>
                    <span><?= __('settings_bot_filter_debug') ?></span>
                </label>
                <p class="help-text settings-help-compact"><?= __('settings_bot_filter_debug_help') ?></p>
            </section>

            <?php
                $geoIpStatus = is_array($geoIpStatus ?? null) ? $geoIpStatus : [];
                $geoIpState = (string)($geoIpStatus['status'] ?? 'missing');
                $geoIpStateLabel = __('settings_geoip_status_' . $geoIpState);
                $geoIpUpdatedAt = (int)($geoIpStatus['last_updated'] ?? 0);
                $geoIpNextUpdate = (int)($geoIpStatus['next_update'] ?? 0);
            ?>
            <section class="settings-section" data-geoip-panel>
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_geoip_title') ?></h3>
                        <p><?= __('settings_geoip_desc') ?></p>
                    </div>
                    <button type="submit" name="update_geoip" value="1" class="btn btn-secondary btn-compact" formnovalidate data-geoip-update-button data-loading-text="<?= htmlspecialchars(__('settings_geoip_updating'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= __('settings_geoip_update_now') ?>
                    </button>
                </div>

                <div class="settings-meta-grid">
                    <div>
                        <span><?= __('settings_geoip_status') ?></span>
                        <strong data-geoip-field="status"><?= htmlspecialchars($geoIpStateLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div>
                        <span><?= __('settings_geoip_ranges') ?></span>
                        <strong data-geoip-field="ranges_count"><?= number_format((int)($geoIpStatus['ranges_count'] ?? 0)) ?></strong>
                    </div>
                    <div>
                        <span><?= __('settings_geoip_last_updated') ?></span>
                        <strong data-geoip-field="last_updated"><?= $geoIpUpdatedAt > 0 ? htmlspecialchars(date('Y-m-d H:i', $geoIpUpdatedAt), ENT_QUOTES, 'UTF-8') : '-' ?></strong>
                    </div>
                    <div>
                        <span><?= __('settings_geoip_next_update') ?></span>
                        <strong data-geoip-field="next_update"><?= $geoIpNextUpdate > 0 ? htmlspecialchars(date('Y-m-d H:i', $geoIpNextUpdate), ENT_QUOTES, 'UTF-8') : '-' ?></strong>
                    </div>
                    <div class="settings-meta-source">
                        <span><?= __('settings_geoip_source') ?></span>
                        <strong data-geoip-field="source"><?= htmlspecialchars((string)($geoIpStatus['source'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div data-geoip-error-row style="<?= empty($geoIpStatus['error']) ? 'display:none;' : '' ?>">
                        <span><?= __('settings_geoip_error') ?></span>
                        <strong data-geoip-field="error"><?= htmlspecialchars((string)($geoIpStatus['error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
                <p class="help-text settings-help-compact" data-geoip-message style="display:none;"></p>
            </section>

            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_funnels_title') ?></h3>
                        <p><?= __('settings_funnels_desc') ?></p>
                    </div>
                </div>

                <div class="settings-stack-sm">
                    <?php if (empty($proAnalyticsAvailable)): ?>
                        <?php AdminUI::proLockedSwitch(__('settings_enable_funnels'), true); ?>
                        <?php AdminUI::proLockedSwitch(__('settings_enable_attribution'), true); ?>
                    <?php else: ?>
                        <label class="settings-inline-checkbox">
                            <input type="checkbox" name="enable_funnels" value="1" <?= $enableFunnels ? 'checked' : '' ?>>
                            <span><?= __('settings_enable_funnels') ?></span>
                        </label>
                        <label class="settings-inline-checkbox">
                            <input type="checkbox" name="enable_attribution" value="1" <?= $enableAttribution ? 'checked' : '' ?>>
                            <span><?= __('settings_enable_attribution') ?></span>
                        </label>
                    <?php endif; ?>
                </div>
            </section>

            <?php
                $cookieColors = is_array($settings['cookie_banner_colors'] ?? null) ? $settings['cookie_banner_colors'] : [];
                $cookieDefaults = Settings::getDefaultCookieBannerSettings();
                $cookieColors = array_merge($cookieDefaults['cookie_banner_colors'], $cookieColors);
            ?>
            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_cookie_consent_title') ?></h3>
                        <p><?= __('settings_cookie_consent_desc') ?></p>
                    </div>
                </div>

                <div class="settings-stack-sm">
                    <label class="settings-inline-checkbox">
                        <input type="checkbox" name="cookie_banner_enabled" value="1" <?= !empty($settings['cookie_banner_enabled']) ? 'checked' : '' ?>>
                        <span><?= __('settings_cookie_banner_enabled') ?></span>
                    </label>
                </div>

                <div class="settings-cookie-grid">
                    <label>
                        <span><?= __('settings_analytics_mode') ?></span>
                        <select name="analytics_mode" class="form-control">
                            <option value="privacy_basic" <?= (($settings['analytics_mode'] ?? 'privacy_basic') === 'privacy_basic') ? 'selected' : '' ?>><?= __('settings_analytics_mode_basic') ?></option>
                            <option value="full_with_consent" <?= (($settings['analytics_mode'] ?? '') === 'full_with_consent') ? 'selected' : '' ?>><?= __('settings_analytics_mode_full') ?></option>
                        </select>
                        <small class="help-text"><?= __('settings_analytics_mode_help') ?></small>
                    </label>
                    <label>
                        <span><?= __('settings_cookie_banner_position') ?></span>
                        <select name="cookie_banner_position" class="form-control" data-cookie-preview-field>
                            <option value="bottom_bar" <?= (($settings['cookie_banner_position'] ?? 'bottom_bar') === 'bottom_bar') ? 'selected' : '' ?>><?= __('settings_cookie_position_bottom_bar') ?></option>
                            <option value="bottom_right" <?= (($settings['cookie_banner_position'] ?? '') === 'bottom_right') ? 'selected' : '' ?>><?= __('settings_cookie_position_bottom_right') ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?= __('settings_cookie_banner_theme') ?></span>
                        <select name="cookie_banner_theme" class="form-control">
                            <option value="auto" <?= (($settings['cookie_banner_theme'] ?? 'auto') === 'auto') ? 'selected' : '' ?>>Auto</option>
                            <option value="light" <?= (($settings['cookie_banner_theme'] ?? '') === 'light') ? 'selected' : '' ?>>Light</option>
                            <option value="dark" <?= (($settings['cookie_banner_theme'] ?? '') === 'dark') ? 'selected' : '' ?>>Dark</option>
                        </select>
                    </label>
                    <label>
                        <span><?= __('settings_cookie_banner_radius') ?></span>
                        <input type="text" name="cookie_banner_radius" class="form-control" value="<?= htmlspecialchars((string)($settings['cookie_banner_radius'] ?? '10px'), ENT_QUOTES, 'UTF-8') ?>" data-cookie-preview-field>
                    </label>
                </div>

                <div class="settings-cookie-grid">
                    <label><span><?= __('settings_cookie_banner_title') ?></span><input type="text" name="cookie_banner_title" class="form-control" value="<?= htmlspecialchars((string)($settings['cookie_banner_title'] ?: __('cookie_banner_default_title')), ENT_QUOTES, 'UTF-8') ?>" data-cookie-preview-field></label>
                    <label><span><?= __('settings_cookie_accept_text') ?></span><input type="text" name="cookie_accept_text" class="form-control" value="<?= htmlspecialchars((string)($settings['cookie_accept_text'] ?: __('cookie_banner_accept')), ENT_QUOTES, 'UTF-8') ?>" data-cookie-preview-field></label>
                    <label><span><?= __('settings_cookie_reject_text') ?></span><input type="text" name="cookie_reject_text" class="form-control" value="<?= htmlspecialchars((string)($settings['cookie_reject_text'] ?: __('cookie_banner_reject')), ENT_QUOTES, 'UTF-8') ?>" data-cookie-preview-field></label>
                    <label><span><?= __('settings_cookie_policy_url') ?></span><input type="url" name="cookie_policy_url" class="form-control" value="<?= htmlspecialchars((string)($settings['cookie_policy_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                </div>

                <label class="settings-field-block">
                    <span><?= __('settings_cookie_banner_text') ?></span>
                    <textarea name="cookie_banner_text" class="form-control" rows="3" data-cookie-preview-field><?= htmlspecialchars((string)($settings['cookie_banner_text'] ?: __('cookie_banner_default_text')), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>

                <div class="settings-cookie-colors">
                    <?php foreach (['background', 'text', 'muted', 'accent', 'border'] as $colorKey): ?>
                        <label>
                            <span><?= __('settings_cookie_color_' . $colorKey) ?></span>
                            <input type="color" name="cookie_banner_color_<?= htmlspecialchars($colorKey) ?>" value="<?= htmlspecialchars($cookieColors[$colorKey], ENT_QUOTES, 'UTF-8') ?>" data-cookie-preview-field>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="settings-field-block">
                    <span><?= __('settings_cookie_custom_css') ?></span>
                    <textarea name="cookie_banner_custom_css" class="form-control" rows="5" placeholder=".clipon-cookie-banner { ... }"><?= htmlspecialchars((string)($settings['cookie_banner_custom_css'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>

                <div class="settings-cookie-preview-wrap">
                    <div class="settings-cookie-preview" id="cookiePreview">
                        <div>
                            <strong id="cookiePreviewTitle"></strong>
                            <p id="cookiePreviewText"></p>
                        </div>
                        <div class="settings-cookie-preview-actions">
                            <button type="button" id="cookiePreviewReject"></button>
                            <button type="button" id="cookiePreviewAccept"></button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_conversion_types_title') ?></h3>
                        <p><?= __('settings_conversion_types_hint') ?></p>
                    </div>
                </div>

                <div class="settings-conversion-grid">
                    <?php
                        $labels = [
                            'conversion' => __('conversion_type_generic'),
                            'lead' => __('conversion_type_lead'),
                            'registration' => __('conversion_type_registration'),
                            'purchase' => __('conversion_type_purchase'),
                            'add_to_cart' => __('conversion_type_add_to_cart'),
                            'begin_checkout' => __('conversion_type_begin_checkout'),
                            'subscribe' => __('conversion_type_subscribe'),
                            'contact' => __('conversion_type_contact'),
                            'sign_up' => __('conversion_type_sign_up'),
                        ];
                        foreach ($conversionTypes as $item):
                            $key = $item['key'] ?? '';
                            if (!$key) {
                                continue;
                            }
                            $enabled = !empty($item['enabled']);
                            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                    ?>
                        <label class="settings-inline-checkbox">
                            <input type="checkbox" name="conversion_types[<?= htmlspecialchars($key) ?>]" value="1" <?= $enabled ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="form-actions settings-actions-spaced">
            <button type="submit" name="save_analytics" value="1" class="btn btn-primary"><?= __('save') ?></button>
        </div>
    </form>

    <?php
        $botFilterLog = is_array($botFilterLog ?? null) ? $botFilterLog : ['total' => 0, 'reasons' => [], 'rows' => [], 'days_with_data' => 0, 'limit_days' => 30];
        $botReasonLabels = [
            'method' => __('settings_bot_filter_reason_method'),
            'non_html' => __('settings_bot_filter_reason_non_html'),
            'empty_ua' => __('settings_bot_filter_reason_empty_ua'),
            'bot_ua' => __('settings_bot_filter_reason_bot_ua'),
            'denylist' => __('settings_bot_filter_reason_denylist'),
            'probe_path' => __('settings_bot_filter_reason_probe_path'),
            'browser_headers' => __('settings_bot_filter_reason_browser_headers'),
            'unknown' => __('settings_bot_filter_reason_unknown'),
        ];
    ?>
    <div id="botFilterLogModal" class="modal cms-modal" role="dialog" aria-modal="true" aria-labelledby="botFilterLogTitle">
        <div class="modal-content cms-modal-content" style="max-width: 760px;">
            <div class="modal-header cms-modal-header">
                <h3 id="botFilterLogTitle"><?= __('settings_bot_filter_log_title') ?></h3>
                <button type="button" class="close-modal cms-modal-close" data-close-bot-filter-log aria-label="Close">&times;</button>
            </div>
            <div class="modal-body cms-modal-body">
                <p class="help-text settings-help-compact"><?= sprintf(__('settings_bot_filter_log_desc'), (int)$botFilterLog['limit_days']) ?></p>

                <div class="settings-meta-grid" style="margin-bottom: 1rem;">
                    <div>
                        <span><?= __('settings_bot_filter_log_total') ?></span>
                        <strong><?= number_format((int)$botFilterLog['total']) ?></strong>
                    </div>
                    <div>
                        <span><?= __('settings_bot_filter_log_days') ?></span>
                        <strong><?= number_format((int)$botFilterLog['days_with_data']) ?></strong>
                    </div>
                </div>

                <?php if (empty($botFilterLog['rows'])): ?>
                    <p class="help-text"><?= __('settings_bot_filter_log_empty') ?></p>
                <?php else: ?>
                    <div class="data-table-wrapper" style="margin-bottom: 1rem; overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><?= __('settings_bot_filter_log_reason') ?></th>
                                    <th style="text-align: right;"><?= __('settings_bot_filter_log_count') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($botFilterLog['reasons'] as $reason => $count): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($botReasonLabels[$reason] ?? (string)$reason, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td style="text-align: right;"><?= number_format((int)$count) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="data-table-wrapper" style="max-height: 360px; overflow: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><?= __('settings_bot_filter_log_date') ?></th>
                                    <th><?= __('settings_bot_filter_log_reason') ?></th>
                                    <th style="text-align: right;"><?= __('settings_bot_filter_log_count') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($botFilterLog['rows'] as $row): ?>
                                    <?php $reason = (string)($row['reason'] ?? 'unknown'); ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($botReasonLabels[$reason] ?? $reason, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td style="text-align: right;"><?= number_format((int)($row['count'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-tab="analytics_settings"] form');
    const preview = document.getElementById('cookiePreview');
    if (!form || !preview) return;
    const q = name => form.querySelector(`[name="${name}"]`);
    function value(name, fallback) {
        const el = q(name);
        return el && el.value ? el.value : fallback;
    }
    function updatePreview() {
        preview.style.background = value('cookie_banner_color_background', '#ffffff');
        preview.style.color = value('cookie_banner_color_text', '#111827');
        preview.style.borderColor = value('cookie_banner_color_border', '#e5e7eb');
        preview.style.borderRadius = value('cookie_banner_radius', '10px');
        preview.classList.toggle('is-card', value('cookie_banner_position', 'bottom_bar') === 'bottom_right');
        document.getElementById('cookiePreviewTitle').textContent = value('cookie_banner_title', 'Cookies');
        const text = document.getElementById('cookiePreviewText');
        text.textContent = value('cookie_banner_text', '');
        text.style.color = value('cookie_banner_color_muted', '#4b5563');
        document.getElementById('cookiePreviewReject').textContent = value('cookie_reject_text', 'Reject');
        const accept = document.getElementById('cookiePreviewAccept');
        accept.textContent = value('cookie_accept_text', 'Accept');
        accept.style.background = value('cookie_banner_color_accent', '#2563eb');
        accept.style.borderColor = value('cookie_banner_color_accent', '#2563eb');
    }
    form.querySelectorAll('[data-cookie-preview-field]').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    updatePreview();

    const logModal = document.getElementById('botFilterLogModal');
    const openLog = document.querySelector('[data-open-bot-filter-log]');
    const closeLog = document.querySelector('[data-close-bot-filter-log]');
    if (logModal && openLog) {
        openLog.addEventListener('click', function () {
            logModal.classList.add('active', 'is-open');
            document.body.classList.add('modal-open');
        });
        logModal.addEventListener('click', function (event) {
            if (event.target === logModal) {
                logModal.classList.remove('active', 'is-open');
                document.body.classList.remove('modal-open');
            }
        });
    }
    if (logModal && closeLog) {
        closeLog.addEventListener('click', function () {
            logModal.classList.remove('active', 'is-open');
            document.body.classList.remove('modal-open');
        });
    }
});
</script>
