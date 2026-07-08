(function () {
    function clearNode(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function appendSvgMarkup(target, svgMarkup) {
        if (!target || typeof svgMarkup !== 'string' || svgMarkup.trim() === '') {
            return false;
        }

        try {
            var parser = new DOMParser();
            var doc = parser.parseFromString(svgMarkup, 'image/svg+xml');
            var svg = doc.documentElement;
            if (!svg || String(svg.nodeName).toLowerCase() !== 'svg') {
                return false;
            }

            var imported = document.importNode(svg, true);
            target.appendChild(imported);
            return true;
        } catch (e) {
            return false;
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initializeTabs() {
        var tabs = document.querySelectorAll('.settings-tab');
        var panels = document.querySelectorAll('.settings-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = this.getAttribute('data-tab');

                tabs.forEach(function (t) {
                    t.classList.remove('active');
                });
                this.classList.add('active');

                panels.forEach(function (panel) {
                    panel.style.display = panel.getAttribute('data-tab') === target ? 'block' : 'none';
                });
            });
        });
    }

    function initializeLanguages(config) {
        var list = document.getElementById('languagesList');
        if (!list) {
            return;
        }

        // Only initialize Sortable if drag handles exist (i.e., Multilang PRO is active)
        if (list.querySelector('.lang-drag-handle')) {
            Sortable.create(list, {
                animation: 150,
                handle: '.lang-drag-handle'
            });
        }

        function attachDeleteHandler(btn) {
            btn.addEventListener('click', function () {
                if (list.children.length > 1) {
                    btn.closest('.lang-item').remove();
                } else {
                    cms_alert(config.langSettings.error_last);
                }
            });
        }

        document.querySelectorAll('.delete-lang').forEach(attachDeleteHandler);

        var addBtn = document.getElementById('addLangBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var nameInput = document.getElementById('newLangName');
                var codeInput = document.getElementById('newLangCode');
                var name = nameInput ? nameInput.value.trim() : '';
                var code = codeInput ? codeInput.value.trim().toLowerCase() : '';

                if (!name || !code) {
                    return cms_alert(config.langSettings.error_empty);
                }
                if (!/^[a-z]{2}(-[a-z]{2})?$/.test(code)) {
                    return cms_alert(config.langSettings.error_format);
                }

                var exists = Array.from(list.children).some(function (existing) {
                    return (existing.getAttribute('data-code') || '').toLowerCase() === code;
                });
                if (exists) {
                    return cms_alert(config.langSettings.error_duplicate);
                }

                var item = document.createElement('div');
                item.className = 'lang-item';
                item.setAttribute('data-code', code);
                item.setAttribute('data-name', name);

                var dragHandle = document.createElement('div');
                dragHandle.className = 'lang-drag-handle';
                if (!appendSvgMarkup(dragHandle, config.langIconDrag || '')) {
                    dragHandle.textContent = '::';
                }

                var info = document.createElement('div');
                info.className = 'lang-info';
                var strong = document.createElement('strong');
                strong.textContent = name;
                info.appendChild(strong);
                info.appendChild(document.createTextNode(' (' + code + ')'));

                var actions = document.createElement('div');
                actions.className = 'lang-actions';

                var enabledInput = document.createElement('input');
                enabledInput.type = 'checkbox';
                enabledInput.className = 'lang-enabled';
                enabledInput.checked = true;
                enabledInput.setAttribute('data-tooltip', String(config.langSettings.enabled || ''));

                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'icon-btn delete-lang';
                deleteBtn.setAttribute('data-tooltip', String(config.langSettings.delete || ''));
                if (!appendSvgMarkup(deleteBtn, config.langIconDelete || '')) {
                    deleteBtn.textContent = 'x';
                }

                actions.appendChild(enabledInput);
                actions.appendChild(deleteBtn);

                item.appendChild(dragHandle);
                item.appendChild(info);
                item.appendChild(actions);

                list.appendChild(item);
                nameInput.value = '';
                codeInput.value = '';

                attachDeleteHandler(item.querySelector('.delete-lang'));
            });
        }

        var languageForm = document.getElementById('languagesForm');
        if (languageForm) {
            languageForm.addEventListener('submit', function () {
                var langs = [];
                Array.from(list.children).forEach(function (item) {
                    langs.push({
                        code: item.getAttribute('data-code'),
                        name: item.getAttribute('data-name'),
                        enabled: item.querySelector('.lang-enabled').checked
                    });
                });

                var jsonField = document.getElementById('languagesJson');
                if (jsonField) {
                    jsonField.value = JSON.stringify(langs);
                }
            });
        }
    }

    function initializeLicenseLock(config) {
        var lockBtn = document.getElementById('licenseLockBtn');
        var licenseInput = document.getElementById('license_key');
        if (!lockBtn || !licenseInput) {
            return;
        }

        function updateLockState(isLocked) {
            licenseInput.readOnly = isLocked;
            var inputWrapper = licenseInput.closest('.license-input-wrapper');
            var lockIcon = lockBtn.querySelector('.license-lock-icon');

            var svgNs = 'http://www.w3.org/2000/svg';
            function appendSvgElement(tag, attrs) {
                var node = document.createElementNS(svgNs, tag);
                Object.keys(attrs).forEach(function (key) {
                    node.setAttribute(key, attrs[key]);
                });
                lockIcon.appendChild(node);
            }

            clearNode(lockIcon);
            
            if (isLocked) {
                inputWrapper.classList.add('license-input-disabled');
                appendSvgElement('path', { d: 'M7 11V7a5 5 0 0 1 10 0v4' });
                appendSvgElement('rect', { x: '3', y: '11', width: '18', height: '11', rx: '2', ry: '2' });
                appendSvgElement('circle', { cx: '12', cy: '16', r: '1' });
            } else {
                inputWrapper.classList.remove('license-input-disabled');
                appendSvgElement('rect', { x: '3', y: '11', width: '18', height: '11', rx: '2', ry: '2' });
                appendSvgElement('circle', { cx: '12', cy: '16', r: '1' });
                appendSvgElement('path', { d: 'M7 11V7a5 5 0 0 1 9.9-1' });
            }
            
            var lockLabel = isLocked ? config.langLicense.unlock_hint : config.langLicense.lock_hint;
            lockBtn.setAttribute('data-tooltip', lockLabel);
            lockBtn.setAttribute('title', lockLabel);
            lockBtn.setAttribute('aria-label', lockLabel);
        }

        lockBtn.addEventListener('click', function () {
            var isCurrentlyLocked = licenseInput.readOnly;
            updateLockState(!isCurrentlyLocked);
        });

        // Initialize based on current state
        updateLockState(licenseInput.readOnly);
    }

    function initializePoweredByThemeOptions() {
        var options = document.querySelectorAll('.powered-by-theme-option');
        if (!options.length) {
            return;
        }

        options.forEach(function (option) {
            var input = option.querySelector('input[name="powered_by_design"]');
            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                options.forEach(function (item) {
                    var itemInput = item.querySelector('input[name="powered_by_design"]');
                    item.classList.toggle('active', !!itemInput && itemInput.checked);
                });
            });
        });
    }

    function initializeBlogPaginationPreview() {
        var preview = document.getElementById('blogPaginationPreview');
        if (!preview) {
            return;
        }

        var fields = document.querySelectorAll('[data-blog-pagination-preview-field]');
        var alignMap = {
            left: 'flex-start',
            center: 'center',
            right: 'flex-end'
        };

        function value(name, fallback) {
            var field = document.querySelector('[name="' + name + '"]');
            return field ? String(field.value || fallback) : fallback;
        }

        function updatePreview() {
            var localizedToggle = document.querySelector('[data-blog-pagination-localized-toggle]');
            var localizedEnabled = !!(localizedToggle && localizedToggle.checked);
            preview.style.justifyContent = alignMap[value('blog_pagination_alignment', 'center')] || 'center';
            preview.style.gap = value('blog_pagination_gap', '8px');
            preview.style.setProperty('--preview-blog-pagination-bg', value('blog_pagination_color_background', '#ffffff'));
            preview.style.setProperty('--preview-blog-pagination-text', value('blog_pagination_color_text', '#111827'));
            preview.style.setProperty('--preview-blog-pagination-active-bg', value('blog_pagination_color_active_background', '#2563eb'));
            preview.style.setProperty('--preview-blog-pagination-active-text', value('blog_pagination_color_active_text', '#ffffff'));
            preview.style.setProperty('--preview-blog-pagination-border', value('blog_pagination_color_border', '#d1d5db'));
            preview.style.setProperty('--preview-blog-pagination-disabled', value('blog_pagination_color_disabled', '#9ca3af'));
            preview.style.setProperty('--preview-blog-pagination-radius', value('blog_pagination_radius', '8px'));
            preview.style.setProperty('--preview-blog-pagination-padding', value('blog_pagination_padding', '8px 12px'));
            preview.style.setProperty('--preview-blog-pagination-font-size', value('blog_pagination_font_size', '14px'));

            var prev = preview.querySelector('[data-blog-pagination-preview-prev]');
            var next = preview.querySelector('[data-blog-pagination-preview-next]');
            var prevInput = document.querySelector('[data-blog-pagination-preview-prev-input]');
            var nextInput = document.querySelector('[data-blog-pagination-preview-next-input]');
            var globalPrev = document.querySelector('[data-blog-pagination-global-prev]');
            var globalNext = document.querySelector('[data-blog-pagination-global-next]');
            if (prev) prev.textContent = localizedEnabled && prevInput ? String(prevInput.value || 'Prev') : (globalPrev ? String(globalPrev.value || 'Prev') : value('blog_pagination_prev_text', 'Prev'));
            if (next) next.textContent = localizedEnabled && nextInput ? String(nextInput.value || 'Next') : (globalNext ? String(globalNext.value || 'Next') : value('blog_pagination_next_text', 'Next'));
        }

        function syncLocalizedState() {
            var toggle = document.querySelector('[data-blog-pagination-localized-toggle]');
            var openButton = document.querySelector('[data-open-blog-pagination-labels]');
            var enabled = !!(toggle && toggle.checked);
            if (openButton) {
                openButton.disabled = !enabled;
            }
            updatePreview();
        }

        var toggle = document.querySelector('[data-blog-pagination-localized-toggle]');
        if (toggle) {
            toggle.addEventListener('change', syncLocalizedState);
        }

        var openButton = document.querySelector('[data-open-blog-pagination-labels]');
        if (openButton) {
            openButton.addEventListener('click', function () {
                if (openButton.disabled) return;
                if (typeof window.openModal === 'function') {
                    window.openModal('blogPaginationLabelsModal');
                }
            });
        }

        document.querySelectorAll('[data-close-blog-pagination-labels]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (typeof window.closeModal === 'function') {
                    window.closeModal('blogPaginationLabelsModal');
                }
            });
        });

        fields.forEach(function (field) {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });
        syncLocalizedState();
    }

    function initializeManualUpdateCheck(config) {
        var manualCheckBtn = document.getElementById('manualCheckUpdatesBtn');
        var manualCheckStatus = document.getElementById('manualCheckUpdatesStatus');
        var changelogContainer = document.getElementById('manualCheckChangelogContainer');
        if (!manualCheckBtn || !manualCheckStatus || !changelogContainer) {
            return;
        }

        function clearChangelog() {
            clearNode(changelogContainer);
        }

        function renderChangelog(changelogText) {
            var normalized = String(changelogText || '').trim();
            if (normalized === '') {
                clearChangelog();
                return;
            }

            var container = document.createElement('div');
            container.className = 'license-changelog';

            var header = document.createElement('div');
            header.className = 'license-changelog-header';

            var svgNs = 'http://www.w3.org/2000/svg';
            var icon = document.createElementNS(svgNs, 'svg');
            icon.setAttribute('class', 'license-changelog-icon');
            icon.setAttribute('width', '16');
            icon.setAttribute('height', '16');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('stroke-width', '2');

            var iconPath = document.createElementNS(svgNs, 'path');
            iconPath.setAttribute('d', 'M6 9l6 6 6-6');
            icon.appendChild(iconPath);

            var title = document.createElement('span');
            title.className = 'license-changelog-title';
            title.textContent = String(config.langUpdates.changelog_title || 'Changelog');

            header.appendChild(icon);
            header.appendChild(title);
            header.addEventListener('click', function() {
                var content = container.querySelector('.license-changelog-content');
                var icon = container.querySelector('.license-changelog-icon');
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    content.style.display = 'none';
                    icon.style.transform = 'rotate(-90deg)';
                }
            });

            var content = document.createElement('div');
            content.className = 'license-changelog-content';
            content.style.display = 'none';
            content.textContent = normalized;

            container.appendChild(header);
            container.appendChild(content);

            clearNode(changelogContainer);
            changelogContainer.appendChild(container);
        }

        function renderCheckStatus(statusData, proData) {
            if (!statusData || typeof statusData !== 'object') {
                manualCheckStatus.style.display = 'none';
                return;
            }

            manualCheckStatus.textContent = '';

            function appendLine(buildLine) {
                if (manualCheckStatus.childNodes.length > 0) {
                    manualCheckStatus.appendChild(document.createElement('br'));
                }
                buildLine();
            }

            function appendCoreAvailableLine(version, url) {
                appendLine(function () {
                    manualCheckStatus.appendChild(document.createTextNode(config.langUpdates.core_new_prefix + ' '));

                    var versionNode = document.createElement('b');
                    versionNode.textContent = String(version || '');
                    manualCheckStatus.appendChild(versionNode);

                    if (url) {
                        manualCheckStatus.appendChild(document.createTextNode(' - '));
                        var linkNode = document.createElement('a');
                        linkNode.href = String(url);
                        linkNode.target = '_blank';
                        linkNode.rel = 'noopener';
                        linkNode.textContent = String(config.langUpdates.go_to_update || '');
                        manualCheckStatus.appendChild(linkNode);
                    }
                });
            }

            function appendTextLine(text) {
                appendLine(function () {
                    manualCheckStatus.appendChild(document.createTextNode(String(text || '')));
                });
            }

            if (statusData.core_available && statusData.core_version) {
                appendCoreAvailableLine(statusData.core_version, statusData.core_url || '');
            } else {
                appendTextLine(config.langUpdates.core_none);
            }

            // Add PRO status if available
            if (proData && typeof proData === 'object') {
                if (proData.success) {
                    appendTextLine(config.langUpdates.pro_synced);
                } else if (proData.skipped) {
                    appendTextLine(config.langUpdates.pro_skipped);
                } else if (proData.license_revoked) {
                    appendTextLine(config.langUpdates.pro_revoked);
                } else if (proData.error) {
                    appendTextLine(config.langUpdates.pro_error_prefix + ' ' + String(proData.error));
                }
            }

            manualCheckStatus.style.display = 'block';
            var hasErrors = (proData && (proData.license_revoked || (proData.error && !proData.success && !proData.skipped)));
            manualCheckStatus.style.color = hasErrors ? '#b91c1c' : (statusData.core_available ? '#166534' : '#666');
        }

        // Initialize changelog from stored update info
        var initialUpdateInfo = window.SETTINGS_UPDATE_INFO || null;
        if (initialUpdateInfo && typeof initialUpdateInfo.changelog === 'string') {
            renderChangelog(initialUpdateInfo.changelog);
        }

        // Initialize status from last check
        var lastCheckStatus = window.SETTINGS_LAST_CHECK_STATUS || null;
        renderCheckStatus(lastCheckStatus);

        manualCheckBtn.addEventListener('click', function () {
            var licenseInput = document.querySelector('input[name="license_key"]');
            var enteredLicenseKey = licenseInput ? String(licenseInput.value || '').trim() : '';

            manualCheckBtn.disabled = true;
            manualCheckStatus.style.display = 'block';
            manualCheckStatus.style.color = '#666';
            manualCheckStatus.textContent = config.langUpdates.checking;
            clearChangelog();

            var body = new URLSearchParams();
            body.set('action', 'check_all_updates');
            body.set('csrf_token', config.csrfToken || '');
            if (enteredLicenseKey !== '') {
                body.set('license_key', enteredLicenseKey);
            }

            fetch(config.updatesApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    });
                })
                .then(function (data) {
                    if (!data || data.status !== 'success') {
                        var errorMessage = data && data.message ? data.message : config.langUpdates.error_default;
                        manualCheckStatus.style.color = '#b91c1c';
                        manualCheckStatus.textContent = errorMessage;
                        clearChangelog();
                        return;
                    }

                    var updateInfo = data.update_info || null;
                    var pro = data.pro || null;

                    var changelogText = '';
                    if (typeof data.changelog === 'string') {
                        changelogText = data.changelog;
                    } else if (updateInfo && typeof updateInfo.changelog === 'string') {
                        changelogText = updateInfo.changelog;
                    }

                    renderChangelog(changelogText);

                    // Update displayed status based on current check results
                    var currentStatus = {
                        core_available: !!(updateInfo && updateInfo.version),
                        core_version: updateInfo ? updateInfo.version : null,
                        core_url: updateInfo ? updateInfo.url : null,
                        changelog: changelogText,
                        checked_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    };
                    renderCheckStatus(currentStatus, pro);
                })
                .catch(function () {
                    manualCheckStatus.style.color = '#b91c1c';
                    manualCheckStatus.textContent = config.langUpdates.network_error;
                    clearChangelog();
                    // Keep existing status visible on error
                    var existingStatus = window.SETTINGS_LAST_CHECK_STATUS || null;
                    if (existingStatus) {
                        setTimeout(function() {
                            renderCheckStatus(existingStatus);
                        }, 3000); // Show error for 3 seconds, then restore previous status
                    }
                })
                .finally(function () {
                    manualCheckBtn.disabled = false;
                });
        });
    }

    function initializeGeoIpUpdateButton(config) {
        var btn = document.querySelector('[data-geoip-update-button]');
        if (!btn) {
            return;
        }

        var form = btn.closest('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || document.activeElement;
            if (submitter !== btn) {
                return;
            }

            if (btn.disabled) {
                event.preventDefault();
                return;
            }

            event.preventDefault();

            btn.dataset.originalText = btn.textContent;
            btn.textContent = btn.getAttribute('data-loading-text') || 'Updating...';
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            form.setAttribute('aria-busy', 'true');

            var message = document.querySelector('[data-geoip-message]');
            if (message) {
                message.style.display = 'block';
                message.style.color = '#666';
                message.textContent = (config.langGeoIp && config.langGeoIp.updating) || btn.textContent;
            }

            var body = new URLSearchParams();
            body.set('csrf_token', config.csrfToken || '');

            fetch(config.geoIpApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    });
                })
                .then(function (data) {
                    if (!data) {
                        throw new Error('Invalid response');
                    }

                    updateGeoIpPanel(data.geoip || {}, config);
                    if (message) {
                        message.style.display = 'block';
                        message.style.color = data.status === 'success' ? '#166534' : '#b91c1c';
                        message.textContent = data.message || '';
                    }
                })
                .catch(function () {
                    if (message) {
                        message.style.display = 'block';
                        message.style.color = '#b91c1c';
                        message.textContent = (config.langGeoIp && config.langGeoIp.network_error) || 'Network error.';
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = btn.dataset.originalText || btn.textContent;
                    btn.removeAttribute('aria-busy');
                    form.removeAttribute('aria-busy');
                });
        });
    }

    function updateGeoIpPanel(status, config) {
        var labels = config.langGeoIp || {};
        var statusKey = String(status.status || 'missing');
        var statusLabel = labels['status_' + statusKey] || statusKey;
        var fields = {
            status: statusLabel,
            ranges_count: Number(status.ranges_count || 0).toLocaleString(),
            last_updated: formatUnixTime(status.last_updated),
            next_update: formatUnixTime(status.next_update),
            source: status.source || '-',
            error: status.error || ''
        };

        Object.keys(fields).forEach(function (key) {
            var el = document.querySelector('[data-geoip-field="' + key + '"]');
            if (el) {
                el.textContent = fields[key];
            }
        });

        var errorRow = document.querySelector('[data-geoip-error-row]');
        if (errorRow) {
            errorRow.style.display = fields.error ? '' : 'none';
        }
    }

    function formatUnixTime(value) {
        var ts = Number(value || 0);
        if (!ts) {
            return '-';
        }

        var date = new Date(ts * 1000);
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.SETTINGS_ADMIN_CONFIG || null;
        if (!config) {
            return;
        }

        initializeTabs();
        initializeLanguages(config);
        initializeManualUpdateCheck(config);
        initializeLicenseLock(config);
        initializeGeoIpUpdateButton(config);
        initializePoweredByThemeOptions();
        initializeBlogPaginationPreview();
    });
})();
