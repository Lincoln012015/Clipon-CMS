(function () {
    /**
     * Unified modal helpers for admin UI
     */
    function getModal(idOrElement) {
        if (!idOrElement) return document.querySelector('.modal.active, .cms-modal.active, .cms-modal.is-open');
        if (typeof idOrElement === 'string') return document.getElementById(idOrElement);
        return idOrElement;
    }

    function openModal(idOrElement) {
        const modal = getModal(idOrElement);
        if (modal) {
            modal.classList.add('active');
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden'; // Запрет скролла
        }
    }

    function closeModal(idOrElement) {
        const modal = getModal(idOrElement);
        if (modal) {
            modal.classList.remove('active');
            modal.classList.remove('is-open');
            if (!document.querySelector('.modal.active, .cms-modal.active, .cms-modal.is-open')) {
                document.body.style.overflow = '';
            }
        }
    }

    function getUiConfig() {
        return window.AdminUIConfig || {};
    }

    function ensureDialog(id, maxWidth, bodyType) {
        let modal = document.getElementById(id);
        if (modal) return modal;

        const config = getUiConfig();
        const cancelLabel = String(config.cancelLabel || 'Cancel');
        const okLabel = String(config.okLabel || 'OK');

        modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal cms-modal';

        const content = document.createElement('div');
        content.className = 'modal-content cms-modal-content';
        content.style.maxWidth = String(maxWidth);

        const header = document.createElement('div');
        header.className = 'modal-header cms-modal-header';

        const titleNode = document.createElement('h3');
        titleNode.id = id + '_title';

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'close-modal cms-modal-close';
        closeButton.setAttribute('data-close-modal', id);
        closeButton.textContent = '×';

        header.appendChild(titleNode);
        header.appendChild(closeButton);

        const body = document.createElement('div');
        body.className = 'modal-body cms-modal-body';

        const bodyText = document.createElement(bodyType === 'prompt' ? 'p' : 'div');
        bodyText.id = id + '_body';
        if (bodyType === 'prompt') {
            bodyText.style.marginBottom = '0.75rem';
        }
        body.appendChild(bodyText);

        if (bodyType === 'prompt') {
            const input = document.createElement('input');
            input.type = 'text';
            input.id = id + '_input';
            input.className = 'form-control';
            input.style.width = '100%';
            input.style.margin = '0';
            body.appendChild(input);
        }

        const footer = document.createElement('div');
        footer.className = 'modal-footer cms-modal-footer';

        if (bodyType !== 'alert') {
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-secondary';
            cancelButton.id = id + '_cancel';
            cancelButton.textContent = cancelLabel;
            footer.appendChild(cancelButton);
        }

        const okButton = document.createElement('button');
        okButton.type = 'button';
        okButton.className = 'btn btn-primary';
        okButton.id = id + '_ok';
        okButton.textContent = okLabel;
        footer.appendChild(okButton);

        content.appendChild(header);
        content.appendChild(body);
        content.appendChild(footer);
        modal.appendChild(content);

        document.body.appendChild(modal);
        return modal;
    }

    function cmsAlert(message, title) {
        const modal = ensureDialog('cms_alert_modal', '400px', 'alert');
        const id = modal.id;

        document.getElementById(id + '_title').textContent = title || 'Clipon CMS';
        document.getElementById(id + '_body').textContent = message;

        const okBtn = document.getElementById(id + '_ok');
        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);
        newOkBtn.addEventListener('click', function () { closeModal(id); });

        openModal(id);
    }

    function cmsConfirm(message, onConfirm, title) {
        const modal = ensureDialog('cms_confirm_modal', '400px', 'confirm');
        const id = modal.id;

        document.getElementById(id + '_title').textContent = title || 'Clipon CMS';
        document.getElementById(id + '_body').textContent = message;

        const cancelBtn = document.getElementById(id + '_cancel');
        const okBtn = document.getElementById(id + '_ok');

        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        newCancelBtn.addEventListener('click', function () { closeModal(id); });

        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);
        newOkBtn.addEventListener('click', function () {
            closeModal(id);
            if (typeof onConfirm === 'function') onConfirm();
        });

        openModal(id);
    }

    function cmsPrompt(message, onCallback, defaultValue, title) {
        const modal = ensureDialog('cms_prompt_modal', '420px', 'prompt');
        const id = modal.id;
        const inputEl = document.getElementById(id + '_input');

        document.getElementById(id + '_title').textContent = title || 'Clipon CMS';
        document.getElementById(id + '_body').textContent = message;
        inputEl.value = defaultValue || '';

        const cancelBtn = document.getElementById(id + '_cancel');
        const okBtn = document.getElementById(id + '_ok');

        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        newCancelBtn.addEventListener('click', function () { closeModal(id); });

        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);

        const handleConfirm = function () {
            closeModal(id);
            if (typeof onCallback === 'function') onCallback(inputEl.value);
        };

        newOkBtn.addEventListener('click', handleConfirm);
        inputEl.onkeydown = function (e) {
            if (e.key === 'Enter') handleConfirm();
        };

        openModal(id);
        setTimeout(function () {
            inputEl.focus();
            inputEl.select();
        }, 120);
    }

    function initAccordions() {
        const items = document.querySelectorAll('.cms-accordion > summary .cms-accordion-arrow');
        items.forEach(function (arrow) {
            const details = arrow.closest('details');
            if (!details) return;
            const sync = function () {
                arrow.setAttribute('aria-expanded', details.open ? 'true' : 'false');
            };
            details.addEventListener('toggle', sync);
            sync();
        });
    }

    function initFlashToasts(timeoutMs) {
        var timeout = typeof timeoutMs === 'number' ? timeoutMs : 4000;
        var toasts = document.querySelectorAll('.toast');
        if (!toasts || toasts.length === 0) return;

        toasts.forEach(function (toast) {
            var closer = toast.querySelector('.toast-close');
            var remove = function () {
                if (toast && toast.parentNode) toast.remove();
            };
            if (closer) closer.addEventListener('click', remove);
            setTimeout(remove, timeout);
        });
    }

    function initPasswordToggles() {
        const buttons = document.querySelectorAll('.password-toggle');
        if (!buttons || buttons.length === 0) return;

        buttons.forEach(function (button) {
            if (button.dataset.passwordToggleInit === '1') return;

            button.dataset.passwordToggleInit = '1';
            button.setAttribute('aria-label', 'Toggle password visibility');

            button.addEventListener('click', function () {
                const wrapper = button.closest('.password-wrapper');
                if (!wrapper) return;

                const input = wrapper.querySelector('input[type="password"], input[type="text"]');
                if (!input) return;

                const nextType = input.type === 'password' ? 'text' : 'password';
                input.type = nextType;
                button.classList.toggle('active', nextType === 'text');
            });
        });
    }

    function createTooltipState() {
        return {
            element: null,
            activeTarget: null,
            touchTimer: null,
            touchHideTimer: null,
            lastTouchTs: 0
        };
    }

    function ensureTooltipElement(state) {
        if (state.element && state.element.isConnected) return state.element;

        const el = document.createElement('div');
        el.className = 'admin-tooltip';
        el.setAttribute('role', 'tooltip');
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);

        state.element = el;
        return el;
    }

    function resolveTooltipTarget(startNode) {
        if (!startNode || !startNode.closest) return null;
        const target = startNode.closest('[data-tooltip]');
        if (!target) return null;

        const tooltipText = target.getAttribute('data-tooltip') || '';
        if (!tooltipText.trim()) return null;

        return target;
    }

    function clearTouchTimers(state) {
        if (state.touchTimer) {
            clearTimeout(state.touchTimer);
            state.touchTimer = null;
        }
        if (state.touchHideTimer) {
            clearTimeout(state.touchHideTimer);
            state.touchHideTimer = null;
        }
    }

    function resolveTooltipText(target) {
        return (target.getAttribute('data-tooltip') || '').trim();
    }

    function resolveTooltipPlacement(target) {
        const raw = (target.getAttribute('data-tooltip-placement') || 'top').toLowerCase();
        return raw === 'bottom' ? 'bottom' : 'top';
    }

    function positionTooltip(state) {
        const target = state.activeTarget;
        const tooltip = state.element;
        if (!target || !tooltip) return;

        const gap = 10;
        const viewportPadding = 8;
        const rect = target.getBoundingClientRect();

        tooltip.style.left = '0px';
        tooltip.style.top = '0px';

        const tipRect = tooltip.getBoundingClientRect();
        let placement = resolveTooltipPlacement(target);

        if (placement === 'top' && rect.top < tipRect.height + gap + viewportPadding) {
            placement = 'bottom';
        } else if (placement === 'bottom' && (window.innerHeight - rect.bottom) < tipRect.height + gap + viewportPadding) {
            placement = 'top';
        }

        let top = placement === 'top'
            ? rect.top - tipRect.height - gap
            : rect.bottom + gap;

        const centeredLeft = rect.left + (rect.width / 2) - (tipRect.width / 2);
        const minLeft = viewportPadding;
        const maxLeft = window.innerWidth - tipRect.width - viewportPadding;
        const left = Math.min(Math.max(centeredLeft, minLeft), Math.max(minLeft, maxLeft));

        tooltip.dataset.placement = placement;
        tooltip.style.left = `${Math.round(left)}px`;
        tooltip.style.top = `${Math.round(top)}px`;
    }

    function showTooltip(state, target) {
        if (!target) return;

        const text = resolveTooltipText(target);
        if (!text) return;

        const tooltip = ensureTooltipElement(state);

        tooltip.textContent = text;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');

        state.activeTarget = target;
        positionTooltip(state);
    }

    function hideTooltip(state) {
        const tooltip = state.element;
        if (!tooltip) return;

        tooltip.classList.remove('is-visible');
        tooltip.setAttribute('aria-hidden', 'true');
        state.activeTarget = null;
    }

    function initTooltips() {
        if (window.AdminUI && window.AdminUI.__tooltipsInitialized) return;

        const state = createTooltipState();

        document.addEventListener('mouseover', function (event) {
            if (Date.now() - state.lastTouchTs < 800) return;

            const target = resolveTooltipTarget(event.target);
            if (!target) {
                if (!state.activeTarget) return;
                if (state.activeTarget.contains(event.relatedTarget)) return;
                hideTooltip(state);
                return;
            }

            if (state.activeTarget === target) {
                positionTooltip(state);
                return;
            }

            showTooltip(state, target);
        });

        document.addEventListener('mouseout', function (event) {
            if (Date.now() - state.lastTouchTs < 800) return;

            if (!state.activeTarget) return;
            if (state.activeTarget.contains(event.relatedTarget)) return;
            hideTooltip(state);
        });

        document.addEventListener('focusin', function (event) {
            const target = resolveTooltipTarget(event.target);
            if (!target) return;
            showTooltip(state, target);
        });

        document.addEventListener('focusout', function () {
            hideTooltip(state);
        });

        document.addEventListener('touchstart', function (event) {
            state.lastTouchTs = Date.now();
            clearTouchTimers(state);

            const target = resolveTooltipTarget(event.target);
            if (!target) {
                hideTooltip(state);
                return;
            }

            state.touchTimer = setTimeout(function () {
                showTooltip(state, target);
            }, 420);
        }, { passive: true });

        document.addEventListener('touchmove', function () {
            clearTouchTimers(state);
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (state.touchTimer) {
                clearTimeout(state.touchTimer);
                state.touchTimer = null;
                return;
            }

            if (!state.activeTarget) return;

            clearTouchTimers(state);
            state.touchHideTimer = setTimeout(function () {
                hideTooltip(state);
            }, 900);
        }, { passive: true });

        document.addEventListener('touchcancel', function () {
            clearTouchTimers(state);
            hideTooltip(state);
        }, { passive: true });

        window.addEventListener('scroll', function () {
            if (state.activeTarget) positionTooltip(state);
        }, true);

        window.addEventListener('resize', function () {
            if (state.activeTarget) positionTooltip(state);
        });

        window.AdminUI.__tooltipsInitialized = true;
        window.AdminUI.refreshTooltips = function () {
            if (state.activeTarget) positionTooltip(state);
        };
    }

    /**
     * Show a toast message
     * @param {string} message 
     * @param {string} type 'success' or 'error'
     */
    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const body = document.createElement('div');
        body.className = 'toast-body';
        body.style.fontWeight = '600';
        body.textContent = String(message || '');

        const closeButton = document.createElement('button');
        closeButton.className = 'toast-close';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.textContent = 'x';

        toast.appendChild(body);
        toast.appendChild(closeButton);

        container.appendChild(toast);

        const closer = closeButton;
        const remove = () => {
            toast.style.opacity = '0';
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 300);
        };

        if (closer) closer.addEventListener('click', remove);
        setTimeout(remove, 4000);
    }

    function createAdminSession() {
        const config = getUiConfig().session || {};
        const statusUrl = config.statusUrl || '';
        const refreshUrl = config.refreshUrl || statusUrl;
        const csrfToken = config.csrfToken || '';
        const loginUrl = config.loginUrl || 'login.php';
        const logoutUrl = config.logoutUrl || 'logout.php';
        const warningBefore = Number(config.warningBefore || 300);
        const activityRefreshInterval = Number(config.activityRefreshInterval || 300);
        const labels = config.labels || {};
        const state = {
            warningTimer: null,
            expireTimer: null,
            lastRefreshAt: 0,
            refreshInFlight: false,
            modalVisible: false,
            handlingExpired: false
        };

        function hasConfig() {
            return !!statusUrl && !!refreshUrl;
        }

        function clearTimers() {
            if (state.warningTimer) clearTimeout(state.warningTimer);
            if (state.expireTimer) clearTimeout(state.expireTimer);
            state.warningTimer = null;
            state.expireTimer = null;
        }

        function redirectToLogin() {
            window.location.href = loginUrl.indexOf('?') === -1
                ? loginUrl + '?timeout=1'
                : loginUrl + '&timeout=1';
        }

        async function logout() {
            try {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                await window.fetch(logoutUrl, {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
            } catch (error) {
                // The next navigation is the reliable UX fallback.
            }

            window.location.href = loginUrl;
        }

        function ensureSessionModal() {
            const modal = ensureDialog('cms_session_warning_modal', '420px', 'confirm');
            const id = modal.id;
            const title = document.getElementById(id + '_title');
            const body = document.getElementById(id + '_body');
            const cancelBtn = document.getElementById(id + '_cancel');
            const okBtn = document.getElementById(id + '_ok');

            if (title) title.textContent = labels.warningTitle || 'Session expires soon';
            if (body) body.textContent = labels.warningText || 'Your session is about to expire due to inactivity.';
            if (cancelBtn) cancelBtn.textContent = labels.loginAgain || labels.logout || 'Logout';
            if (okBtn) okBtn.textContent = labels.extend || 'Continue session';

            return modal;
        }

        function hideWarning() {
            state.modalVisible = false;
            closeModal('cms_session_warning_modal');
        }

        function showWarning() {
            if (state.modalVisible) return;
            state.modalVisible = true;

            const modal = ensureSessionModal();
            const id = modal.id;
            const cancelBtn = document.getElementById(id + '_cancel');
            const okBtn = document.getElementById(id + '_ok');

            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            newCancelBtn.addEventListener('click', function () {
                logout();
            });

            const newOkBtn = okBtn.cloneNode(true);
            okBtn.parentNode.replaceChild(newOkBtn, okBtn);
            newOkBtn.addEventListener('click', function () {
                refreshSession(true);
            });

            openModal(modal);
        }

        function scheduleFromState(payload) {
            if (!payload || !payload.authenticated) return;

            clearTimers();
            const secondsRemaining = Number(payload.seconds_remaining || 0);
            const warningSeconds = Number(payload.warning_before || warningBefore);

            if (secondsRemaining <= 0) {
                handleExpired();
                return;
            }

            const warnIn = Math.max(0, secondsRemaining - warningSeconds);
            state.warningTimer = setTimeout(showWarning, warnIn * 1000);
            state.expireTimer = setTimeout(handleExpired, secondsRemaining * 1000);
        }

        async function fetchStatus() {
            if (!hasConfig()) return null;
            try {
                const response = await window.fetch(statusUrl, {
                    method: 'GET',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                const payload = await response.json();
                if (response.status === 401 || payload.code === 'session_expired') {
                    handleExpired();
                    return null;
                }
                scheduleFromState(payload);
                return payload;
            } catch (error) {
                return null;
            }
        }

        async function refreshSession(force) {
            if (!hasConfig() || state.refreshInFlight) return null;

            const now = Date.now();
            if (!force && (now - state.lastRefreshAt) < activityRefreshInterval * 1000) {
                return null;
            }

            state.refreshInFlight = true;
            try {
                const formData = new FormData();
                formData.append('action', 'refresh');
                formData.append('csrf_token', csrfToken);

                const response = await window.fetch(refreshUrl, {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const payload = await response.json();

                if (response.status === 401 || payload.code === 'session_expired') {
                    handleExpired();
                    return null;
                }

                state.lastRefreshAt = Date.now();
                hideWarning();
                scheduleFromState(payload);
                return payload;
            } catch (error) {
                return null;
            } finally {
                state.refreshInFlight = false;
            }
        }

        function handleActivity() {
            refreshSession(false);
        }

        function handleExpired() {
            if (state.handlingExpired) return;
            state.handlingExpired = true;
            clearTimers();
            showToast(labels.expiredToast || labels.warningText || 'Session expired.', 'error');
            setTimeout(redirectToLogin, 900);
        }

        function bindActivity() {
            ['click', 'keydown', 'input', 'pointerdown'].forEach(function (eventName) {
                document.addEventListener(eventName, handleActivity, { passive: true });
            });
        }

        function init() {
            if (!hasConfig() || state.initialized) return;
            state.initialized = true;
            bindActivity();
            fetchStatus();
        }

        return {
            init: init,
            refresh: refreshSession,
            status: fetchStatus,
            handleExpired: handleExpired
        };
    }

    function installFetchSessionHandler(adminSession) {
        if (!window.fetch || window.fetch.__cliponSessionWrapped) return;

        const nativeFetch = window.fetch.bind(window);
        const wrappedFetch = async function () {
            const response = await nativeFetch.apply(null, arguments);

            if (response && response.status === 401) {
                const clone = response.clone();
                const contentType = clone.headers.get('Content-Type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    try {
                        const payload = await clone.json();
                        if (payload && (payload.code === 'session_expired' || payload.code === 'unauthorized')) {
                            adminSession.handleExpired();
                        }
                    } catch (error) {
                        // Keep original fetch behavior for callers.
                    }
                }
            }

            return response;
        };

        wrappedFetch.__cliponSessionWrapped = true;
        window.fetch = wrappedFetch;
    }

    // Экспорт в глобальный объект AdminUI
    window.AdminUI = window.AdminUI || {};
    const adminSession = createAdminSession();
    window.AdminUI.openModal = openModal;
    window.AdminUI.closeModal = closeModal;
    window.AdminUI.showToast = showToast;
    window.AdminUI.initFlashToasts = initFlashToasts;
    window.AdminUI.alert = cmsAlert;
    window.AdminUI.confirm = cmsConfirm;
    window.AdminUI.prompt = cmsPrompt;
    window.AdminUI.initAccordions = initAccordions;
    window.AdminUI.initTooltips = initTooltips;
    window.AdminUI.initPasswordToggles = initPasswordToggles;
    window.AdminUI.session = adminSession;
    installFetchSessionHandler(adminSession);

    // Глобальные алиасы для обратной совместимости
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.showToast = showToast;
    window.cms_alert = cmsAlert;
    window.cms_confirm = cmsConfirm;
    window.cms_prompt = cmsPrompt;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    document.addEventListener('mousedown', function (e) {
        if (e.target.classList.contains('modal') || e.target.classList.contains('cms-modal')) {
            closeModal(e.target);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initAccordions();
        initTooltips();
        initPasswordToggles();
        adminSession.init();
    });
})();
