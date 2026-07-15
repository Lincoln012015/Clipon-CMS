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
                    <button type="button" class="btn btn-secondary" data-open-conversion-types><?= __('settings_conversion_types_manage') ?></button>
                </div>

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
                ?>
                <div class="settings-conversion-types-summary">
                    <strong><?= sprintf(__('settings_conversion_types_count'), count($conversionTypes)) ?></strong>
                    <span><?= htmlspecialchars(implode(', ', array_slice(array_map(function ($item) use ($labels) {
                        $key = (string)($item['key'] ?? '');
                        return !empty($item['label']) ? (string)$item['label'] : ($labels[$key] ?? ucfirst(str_replace('_', ' ', $key)));
                    }, $conversionTypes), 0, 4)), ENT_QUOTES, 'UTF-8') ?><?= count($conversionTypes) > 4 ? '…' : '' ?></span>
                </div>
            </section>

            <div id="conversionTypesManagerModal" class="modal cms-modal analytics-manager-modal" role="dialog" aria-modal="true" aria-labelledby="conversionTypesManagerTitle">
                <div class="modal-content cms-modal-content analytics-manager-shell">
                    <div class="modal-header cms-modal-header analytics-manager-header">
                        <div>
                            <h3 id="conversionTypesManagerTitle"><?= __('settings_conversion_types_manage_title') ?></h3>
                            <p><?= __('settings_conversion_types_manage_desc') ?></p>
                        </div>
                        <button type="button" class="close-modal cms-modal-close" data-close-conversion-types aria-label="<?= __('cancel') ?>">&times;</button>
                    </div>
                    <div class="modal-body cms-modal-body">
                        <div class="analytics-manager-toolbar">
                            <label class="analytics-manager-search">
                                <span class="sr-only"><?= __('search') ?></span>
                                <input type="search" class="form-control" placeholder="<?= __('settings_conversion_types_search') ?>" data-filter-conversion-types>
                            </label>
                            <span class="analytics-manager-count" data-conversion-types-count><?= sprintf(__('settings_conversion_types_count'), count($conversionTypes)) ?></span>
                            <button type="button" class="btn btn-primary" data-add-conversion-type><?= __('settings_conversion_types_add') ?></button>
                        </div>
                        <div class="settings-conversion-types" data-conversion-types>
                        <?php foreach ($conversionTypes as $index => $item):
                            $key = $item['key'] ?? '';
                            if (!$key) {
                                continue;
                            }
                            $enabled = !empty($item['enabled']);
                            $custom = !empty($item['custom']);
                            $label = $custom && !empty($item['label']) ? (string)$item['label'] : ($labels[$key] ?? ucfirst(str_replace('_', ' ', $key)));
                    ?>
                        <div class="settings-conversion-type-row analytics-manager-item" data-conversion-type-row data-search-value="<?= htmlspecialchars(mb_strtolower($label . ' ' . $key), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="conversion_type_items[<?= $index ?>][key]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="analytics-manager-identity">
                                <?php if ($custom): ?>
                                    <input type="text" name="conversion_type_items[<?= $index ?>][label]" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" maxlength="80" required>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                                <div><code><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></code><span class="analytics-manager-badge"><?= $custom ? __('settings_conversion_types_custom') : __('settings_conversion_types_system') ?></span></div>
                            </div>
                            <label class="settings-inline-checkbox analytics-manager-toggle">
                                <input type="checkbox" name="conversion_type_items[<?= $index ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>>
                                <span><?= __('enabled') ?></span>
                            </label>
                            <button type="button" class="analytics-manager-delete" data-remove-conversion-type aria-label="<?= __('delete') ?>">&times;</button>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <p class="settings-modal-error" data-conversion-types-error role="alert" hidden></p>
                    </div>
                    <div class="modal-footer cms-modal-footer">
                        <button type="button" class="btn btn-primary" data-close-conversion-types><?= __('done') ?></button>
                    </div>
                </div>
            </div>

            <div id="conversionTypeModal" class="modal cms-modal analytics-manager-modal" role="dialog" aria-modal="true" aria-labelledby="conversionTypeModalTitle">
                <div class="modal-content cms-modal-content analytics-create-shell">
                    <div class="modal-header cms-modal-header analytics-manager-header">
                        <div><h3 id="conversionTypeModalTitle"><?= __('settings_conversion_types_modal_title') ?></h3><p><?= __('settings_conversion_types_modal_desc') ?></p></div>
                        <button type="button" class="close-modal cms-modal-close" data-close-conversion-type-modal aria-label="<?= __('cancel') ?>">&times;</button>
                    </div>
                    <div class="modal-body cms-modal-body">
                        <div class="settings-modal-fields">
                            <label>
                                <span><?= __('settings_conversion_types_name') ?></span>
                                <input type="text" class="form-control" maxlength="80" autocomplete="off" data-conversion-type-label>
                            </label>
                            <label>
                                <span><?= __('settings_conversion_types_key') ?></span>
                                <input type="text" class="form-control" maxlength="48" pattern="[a-z0-9_-]+" autocomplete="off" data-conversion-type-key>
                                <small class="help-text"><?= __('settings_conversion_types_key_hint') ?></small>
                            </label>
                            <p class="settings-modal-error" data-conversion-type-error role="alert" hidden></p>
                        </div>
                    </div>
                    <div class="modal-footer cms-modal-footer">
                        <button type="button" class="btn btn-secondary" data-close-conversion-type-modal><?= __('cancel') ?></button>
                        <button type="button" class="btn btn-primary" data-create-conversion-type><?= __('settings_conversion_types_create') ?></button>
                    </div>
                </div>
            </div>

            <section class="settings-section">
                <div class="settings-section-header">
                    <div>
                        <h3><?= __('settings_custom_conversion_events_title') ?></h3>
                        <p><?= __('settings_custom_conversion_events_hint') ?></p>
                    </div>
                    <?php if ($proAnalyticsAvailable): ?>
                        <button type="button" class="btn btn-secondary" data-open-conversion-events><?= __('settings_custom_conversion_events_manage') ?></button>
                    <?php endif; ?>
                </div>

                <?php if (!$proAnalyticsAvailable): ?>
                    <p class="text-muted"><?= __('settings_custom_conversion_events_pro') ?></p>
                <?php else: ?>
                    <div class="settings-conversion-types-summary">
                        <strong><?= sprintf(__('settings_custom_conversion_events_count'), count($customConversionEvents)) ?></strong>
                        <code>window.cliponAnalytics.trackConversion('event_key')</code>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($proAnalyticsAvailable): ?>
            <div id="customConversionEventsManagerModal" class="modal cms-modal analytics-manager-modal" role="dialog" aria-modal="true" aria-labelledby="customConversionEventsManagerTitle">
                <div class="modal-content cms-modal-content analytics-manager-shell analytics-events-shell">
                    <div class="modal-header cms-modal-header analytics-manager-header">
                        <div><h3 id="customConversionEventsManagerTitle"><?= __('settings_custom_conversion_events_manage_title') ?></h3><p><?= __('settings_custom_conversion_events_manage_desc') ?></p></div>
                        <button type="button" class="close-modal cms-modal-close" data-close-conversion-events aria-label="<?= __('cancel') ?>">&times;</button>
                    </div>
                    <div class="modal-body cms-modal-body">
                        <div class="analytics-manager-toolbar">
                            <label class="analytics-manager-search"><span class="sr-only"><?= __('search') ?></span><input type="search" class="form-control" placeholder="<?= __('settings_custom_conversion_events_search') ?>" data-filter-conversion-events></label>
                            <span class="analytics-manager-count" data-conversion-events-count><?= sprintf(__('settings_custom_conversion_events_count'), count($customConversionEvents)) ?></span>
                            <button type="button" class="btn btn-primary" data-add-conversion-event><?= __('settings_custom_conversion_events_add') ?></button>
                        </div>
                        <div class="settings-custom-events" data-conversion-events>
                        <?php foreach ($customConversionEvents as $index => $event): ?>
                            <div class="settings-custom-event-row analytics-manager-item" data-conversion-event-row>
                                <input type="text" name="custom_conversion_events[<?= $index ?>][name]" value="<?= htmlspecialchars((string)($event['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= __('settings_custom_conversion_events_name') ?>" maxlength="80" required>
                                <input type="text" name="custom_conversion_events[<?= $index ?>][key]" value="<?= htmlspecialchars((string)($event['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= __('settings_custom_conversion_events_key') ?>" maxlength="48" pattern="[a-z0-9_-]+" required>
                                <select name="custom_conversion_events[<?= $index ?>][type]">
                                    <?php foreach ($conversionTypes as $conversionType): $typeKey = (string)($conversionType['key'] ?? ''); if ($typeKey === '') continue; ?>
                                        <option value="<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>" <?= ($event['type'] ?? '') === $typeKey ? 'selected' : '' ?>><?= htmlspecialchars(!empty($conversionType['label']) ? (string)$conversionType['label'] : ($labels[$typeKey] ?? ucfirst(str_replace('_', ' ', $typeKey))), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="settings-inline-checkbox"><input type="checkbox" name="custom_conversion_events[<?= $index ?>][enabled]" value="1" <?= !empty($event['enabled']) ? 'checked' : '' ?>> <span><?= __('enabled') ?></span></label>
                                <button type="button" class="analytics-manager-delete" data-remove-conversion-event aria-label="<?= __('delete') ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="analytics-manager-empty" data-conversion-events-empty <?= empty($customConversionEvents) ? '' : 'hidden' ?>>
                            <strong><?= __('settings_custom_conversion_events_empty_title') ?></strong>
                            <span><?= __('settings_custom_conversion_events_empty_desc') ?></span>
                        </div>
                        <div class="analytics-manager-code"><span><?= __('settings_custom_conversion_events_usage') ?></span><code>window.cliponAnalytics.trackConversion('event_key')</code></div>
                    </div>
                    <div class="modal-footer cms-modal-footer">
                        <button type="button" class="btn btn-primary" data-close-conversion-events><?= __('done') ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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

    const conversionTypes = form.querySelector('[data-conversion-types]');
    const addConversionType = form.querySelector('[data-add-conversion-type]');
    const conversionTypeModal = document.getElementById('conversionTypeModal');
    const conversionTypesManager = document.getElementById('conversionTypesManagerModal');
    const openConversionTypes = form.querySelector('[data-open-conversion-types]');
    if (conversionTypes && addConversionType && conversionTypeModal && conversionTypesManager && openConversionTypes) {
        const labelInput = conversionTypeModal.querySelector('[data-conversion-type-label]');
        const keyInput = conversionTypeModal.querySelector('[data-conversion-type-key]');
        const errorBox = conversionTypeModal.querySelector('[data-conversion-type-error]');
        const managerError = conversionTypesManager.querySelector('[data-conversion-types-error]');
        const managerCount = conversionTypesManager.querySelector('[data-conversion-types-count]');
        const typeFilter = conversionTypesManager.querySelector('[data-filter-conversion-types]');
        const createButton = conversionTypeModal.querySelector('[data-create-conversion-type]');
        const refreshTypeCount = () => {
            managerCount.textContent = <?= json_encode(__('settings_conversion_types_count'), JSON_UNESCAPED_UNICODE) ?>.replace('%d', conversionTypes.querySelectorAll('[data-conversion-type-row]').length);
        };
        const reindexTypes = () => conversionTypes.querySelectorAll('[data-conversion-type-row]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/conversion_type_items\[\d+\]/, `conversion_type_items[${index}]`));
        });
        const slugify = value => value.trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '_').replace(/^[_-]+|[_-]+$/g, '').slice(0, 48);
        const showTypeError = message => {
            errorBox.textContent = message;
            errorBox.hidden = false;
        };
        const closeTypeModal = () => {
            conversionTypeModal.classList.remove('active', 'is-open');
            if (!conversionTypesManager.classList.contains('active')) document.body.classList.remove('modal-open');
        };
        const openTypeModal = () => {
            labelInput.value = '';
            keyInput.value = '';
            keyInput.dataset.edited = '0';
            errorBox.hidden = true;
            conversionTypeModal.classList.add('active', 'is-open');
            document.body.classList.add('modal-open');
            setTimeout(() => labelInput.focus(), 0);
        };
        const createType = () => {
            const label = labelInput.value.trim();
            const key = slugify(keyInput.value);
            if (!label) {
                showTypeError(<?= json_encode(__('settings_conversion_types_name_required'), JSON_UNESCAPED_UNICODE) ?>);
                labelInput.focus();
                return;
            }
            if (!key) {
                showTypeError(<?= json_encode(__('settings_conversion_types_key_required'), JSON_UNESCAPED_UNICODE) ?>);
                keyInput.focus();
                return;
            }
            if ([...conversionTypes.querySelectorAll('input[type="hidden"]')].some(input => input.value === key)) {
                showTypeError(<?= json_encode(__('settings_conversion_types_duplicate'), JSON_UNESCAPED_UNICODE) ?>);
                keyInput.focus();
                return;
            }
            const index = conversionTypes.querySelectorAll('[data-conversion-type-row]').length;
            const row = document.createElement('div');
            row.className = 'settings-conversion-type-row analytics-manager-item';
            row.dataset.conversionTypeRow = '';
            row.dataset.searchValue = `${label} ${key}`.toLowerCase();
            row.innerHTML = `<input type="hidden" name="conversion_type_items[${index}][key]" value="${key}"><div class="analytics-manager-identity"><input type="text" name="conversion_type_items[${index}][label]" maxlength="80" required><div><code></code><span class="analytics-manager-badge"><?= __('settings_conversion_types_custom') ?></span></div></div><label class="settings-inline-checkbox analytics-manager-toggle"><input type="checkbox" name="conversion_type_items[${index}][enabled]" value="1" checked><span><?= __('enabled') ?></span></label><button type="button" class="analytics-manager-delete" data-remove-conversion-type aria-label="<?= __('delete') ?>">&times;</button>`;
            row.querySelector('input[type="text"]').value = label.trim();
            row.querySelector('code').textContent = key;
            conversionTypes.appendChild(row);
            refreshTypeCount();
            form.querySelectorAll('select[name*="custom_conversion_events"][name$="[type]"]').forEach(select => {
                if ([...select.options].some(option => option.value === key)) return;
                select.add(new Option(label.trim(), key));
            });
            if (Array.isArray(window.cliponConversionTypeOptions)) window.cliponConversionTypeOptions.push({ key, label: label.trim() });
            closeTypeModal();
        };
        const closeManager = () => {
            conversionTypesManager.classList.remove('active', 'is-open');
            document.body.classList.remove('modal-open');
        };
        openConversionTypes.addEventListener('click', function () {
            managerError.hidden = true;
            typeFilter.value = '';
            conversionTypes.querySelectorAll('[data-conversion-type-row]').forEach(row => row.hidden = false);
            conversionTypesManager.classList.add('active', 'is-open');
            document.body.classList.add('modal-open');
        });
        conversionTypesManager.querySelectorAll('[data-close-conversion-types]').forEach(button => button.addEventListener('click', closeManager));
        conversionTypesManager.addEventListener('click', event => { if (event.target === conversionTypesManager) closeManager(); });
        typeFilter.addEventListener('input', function () {
            const query = typeFilter.value.trim().toLowerCase();
            conversionTypes.querySelectorAll('[data-conversion-type-row]').forEach(row => {
                row.hidden = query !== '' && !(row.dataset.searchValue || '').includes(query);
            });
        });
        addConversionType.addEventListener('click', openTypeModal);
        labelInput.addEventListener('input', function () {
            errorBox.hidden = true;
            if (keyInput.dataset.edited !== '1') keyInput.value = slugify(labelInput.value);
        });
        keyInput.addEventListener('input', function () {
            keyInput.dataset.edited = '1';
            keyInput.value = slugify(keyInput.value);
            errorBox.hidden = true;
        });
        createButton.addEventListener('click', createType);
        conversionTypeModal.querySelectorAll('[data-close-conversion-type-modal]').forEach(button => button.addEventListener('click', closeTypeModal));
        conversionTypeModal.addEventListener('click', event => { if (event.target === conversionTypeModal) closeTypeModal(); });
        conversionTypeModal.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeTypeModal();
            if (event.key === 'Enter' && event.target.matches('input')) { event.preventDefault(); createType(); }
        });
        conversionTypes.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-conversion-type]');
            if (!button) return;
            if (conversionTypes.querySelectorAll('[data-conversion-type-row]').length <= 1) {
                managerError.textContent = <?= json_encode(__('settings_conversion_types_keep_one'), JSON_UNESCAPED_UNICODE) ?>;
                managerError.hidden = false;
                return;
            }
            const row = button.closest('[data-conversion-type-row]');
            const removedKey = row.querySelector('input[type="hidden"]').value;
            row.remove();
            reindexTypes();
            refreshTypeCount();
            managerError.hidden = true;
            form.querySelectorAll('select[name*="custom_conversion_events"][name$="[type]"]').forEach(select => {
                const option = [...select.options].find(item => item.value === removedKey);
                if (option) option.remove();
            });
            if (Array.isArray(window.cliponConversionTypeOptions)) {
                window.cliponConversionTypeOptions = window.cliponConversionTypeOptions.filter(item => item.key !== removedKey);
            }
        });
    }

    const eventsContainer = form.querySelector('[data-conversion-events]');
    const addEvent = form.querySelector('[data-add-conversion-event]');
    const eventsManager = document.getElementById('customConversionEventsManagerModal');
    const openEventsManager = form.querySelector('[data-open-conversion-events]');
    if (eventsContainer && addEvent && eventsManager && openEventsManager) {
        const eventsCount = eventsManager.querySelector('[data-conversion-events-count]');
        const eventsFilter = eventsManager.querySelector('[data-filter-conversion-events]');
        const eventsEmpty = eventsManager.querySelector('[data-conversion-events-empty]');
        const refreshEvents = () => {
            const rows = eventsContainer.querySelectorAll('[data-conversion-event-row]');
            eventsCount.textContent = <?= json_encode(__('settings_custom_conversion_events_count'), JSON_UNESCAPED_UNICODE) ?>.replace('%d', rows.length);
            eventsEmpty.hidden = rows.length !== 0;
            rows.forEach(row => {
                const values = [...row.querySelectorAll('input, select')].map(input => input.value).join(' ').toLowerCase();
                row.dataset.searchValue = values;
            });
        };
        const closeEventsManager = () => {
            eventsManager.classList.remove('active', 'is-open');
            document.body.classList.remove('modal-open');
        };
        openEventsManager.addEventListener('click', function () {
            refreshEvents();
            eventsFilter.value = '';
            eventsContainer.querySelectorAll('[data-conversion-event-row]').forEach(row => row.hidden = false);
            eventsManager.classList.add('active', 'is-open');
            document.body.classList.add('modal-open');
        });
        eventsManager.querySelectorAll('[data-close-conversion-events]').forEach(button => button.addEventListener('click', closeEventsManager));
        eventsManager.addEventListener('click', event => { if (event.target === eventsManager) closeEventsManager(); });
        eventsFilter.addEventListener('input', function () {
            refreshEvents();
            const query = eventsFilter.value.trim().toLowerCase();
            eventsContainer.querySelectorAll('[data-conversion-event-row]').forEach(row => {
                row.hidden = query !== '' && !(row.dataset.searchValue || '').includes(query);
            });
        });
        window.cliponConversionTypeOptions = <?= json_encode(array_values(array_filter(array_map(function ($item) use ($labels) {
            $key = (string)($item['key'] ?? '');
            return $key === '' ? null : ['key' => $key, 'label' => !empty($item['label']) ? (string)$item['label'] : ($labels[$key] ?? ucfirst(str_replace('_', ' ', $key)))];
        }, $conversionTypes))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const reindexEvents = () => eventsContainer.querySelectorAll('[data-conversion-event-row]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/custom_conversion_events\[\d+\]/, `custom_conversion_events[${index}]`));
        });
        addEvent.addEventListener('click', function () {
            const index = eventsContainer.querySelectorAll('[data-conversion-event-row]').length;
            const row = document.createElement('div');
            row.className = 'settings-custom-event-row analytics-manager-item';
            row.dataset.conversionEventRow = '';
            row.innerHTML = `<input type="text" name="custom_conversion_events[${index}][name]" placeholder="<?= __('settings_custom_conversion_events_name') ?>" maxlength="80" required><input type="text" name="custom_conversion_events[${index}][key]" placeholder="<?= __('settings_custom_conversion_events_key') ?>" maxlength="48" pattern="[a-z0-9_-]+" required><select name="custom_conversion_events[${index}][type]"></select><label class="settings-inline-checkbox analytics-manager-toggle"><input type="checkbox" name="custom_conversion_events[${index}][enabled]" value="1" checked> <span><?= __('enabled') ?></span></label><button type="button" class="analytics-manager-delete" data-remove-conversion-event aria-label="<?= __('delete') ?>">&times;</button>`;
            const select = row.querySelector('select');
            window.cliponConversionTypeOptions.forEach(item => select.add(new Option(item.label, item.key)));
            eventsContainer.appendChild(row);
            refreshEvents();
        });
        eventsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-conversion-event]');
            if (!button) return;
            button.closest('[data-conversion-event-row]').remove();
            reindexEvents();
            refreshEvents();
        });
    }

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
