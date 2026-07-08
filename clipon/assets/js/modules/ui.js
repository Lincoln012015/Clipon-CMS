/**
 * UI / Notifications Module
 * Handles visual indicators, modals, and user feedback.
 */

import { loadStyleOnce } from './utils.js';
import { t } from './i18n.js';

export function showSaveIndicator() {
    let el = document.querySelector('.save-indicator');
    if (!el) {
        el = document.createElement('div');
        el.className = 'save-indicator';
        el.textContent = t('saved', 'Saved');
        document.body.appendChild(el);
    }
    el.classList.remove('error');
    el.textContent = t('saved', 'Saved');
    el.style.display = 'block';
    setTimeout(() => {
        el.style.display = 'none';
    }, 2000);
}

export function showSaveError(message) {
    let el = document.querySelector('.save-indicator');
    if (!el) {
        el = document.createElement('div');
        el.className = 'save-indicator';
        document.body.appendChild(el);
    }
    el.classList.add('error');
    el.textContent = message || t('saveError', 'Save error');
    el.style.display = 'block';
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

export function initBaseUI(pageSlug, pageType, onBackToAdmin) {
    document.body.classList.add('clipon-inline-edit-mode');

    // Save indicator
    if (!document.querySelector('.save-indicator')) {
        const indicator = document.createElement('div');
        indicator.className = 'save-indicator';
        indicator.textContent = t('saved', 'Saved');
        document.body.appendChild(indicator);
    }

    // Back button
    const backBtn = document.createElement('button');
    backBtn.className = 'admin-back-btn';
    backBtn.textContent = '← ' + t('backToAdmin', 'To admin');
    backBtn.onclick = onBackToAdmin;
    document.body.appendChild(backBtn);

    // Language Info/Switcher
    if (window.CLIPON_LANGS && window.CLIPON_LANGS.length > 1) {
        const langContainer = document.createElement('div');
        langContainer.className = 'cms-lang-switcher';
        
        const currentLang = document.querySelector('meta[name="cms-lang"]')?.content || 'uk';
        
        window.CLIPON_LANGS.forEach(l => {
            const lBtn = document.createElement('button');
            lBtn.textContent = l.code.toUpperCase();
            lBtn.className = 'cms-lang-switcher-btn' + (l.code === currentLang ? ' is-active' : '');
            lBtn.onclick = () => {
                if (l.code === currentLang) return;
                const newUrl = getEditingLangUrl(l.code);
                window.location.href = newUrl;
            };
            langContainer.appendChild(lBtn);
        });
        
        document.body.appendChild(langContainer);
    }
}

function getEditingLangUrl(langCode) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('edit', '1');
    currentUrl.searchParams.set('edit_lang', langCode);
    return currentUrl.pathname + currentUrl.search;
}

export function initMediaModal(onUpload) {
    if (document.getElementById('media-modal')) return;

    const base = (window.CMS_BASE_PATH || '').replace(/\/$/, '');
    loadStyleOnce(base + '/clipon/assets/css/admin-media.css');

    const modalHtml = `
        <div id="media-modal" class="clipon-media-modal">
            <div class="clipon-media-modal-dialog">
                <div class="media-picker-overlay">
                    <div class="media-picker-header">
                        <div class="media-picker-header-title">
                            <h3>${t('mediaTitle', 'Select media')}</h3>
                        </div>
                        <div class="media-picker-header-actions">
                            <button id="modal-upload-btn" class="media-picker-btn" type="button">
                                <svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="128" cy="128" r="96"></circle>
                                    <line x1="128" y1="88" x2="128" y2="168"></line>
                                    <line x1="88" y1="128" x2="168" y2="128"></line>
                                </svg>
                                ${t('upload', 'Upload')}
                            </button>
                            <button id="media-modal-close" class="media-picker-close" aria-label="${t('close', 'Close')}">×</button>
                        </div>
                        <input type="file" id="modal-upload-input" style="display:none;">
                    </div>
                    <div id="media-breadcrumb-container" class="media-picker-breadcrumb-container"></div>
                    <div id="media-list-container" class="media-picker-browser">
                        <div id="media-list" class="media-grid media-picker-grid">
                            <!-- Images will be here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const mediaModal = document.getElementById('media-modal');
    const mediaDialog = mediaModal.querySelector('.clipon-media-modal-dialog');
    const closeBtn = document.getElementById('media-modal-close');

    mediaModal.setAttribute('aria-modal', 'true');
    mediaModal.setAttribute('role', 'dialog');
    // Keep aria-hidden off while mounted so focus is not blocked.

    const closePicker = () => {
        mediaModal.classList.remove('open');
        mediaModal.setAttribute('aria-hidden', 'true');
    };

    closeBtn.onclick = closePicker;

    // Close when click outside the dialog
    mediaModal.onclick = (e) => {
        if (e.target === mediaModal) {
            closePicker();
        }
    };

    // Prevent clicks inside content from closing
    mediaDialog.onclick = (e) => {
        e.stopPropagation();
    };

    // Close on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mediaModal.classList.contains('open')) {
            closePicker();
        }
    });

    const uploadBtn = document.getElementById('modal-upload-btn');
    const uploadInput = document.getElementById('modal-upload-input');
    
    uploadBtn.onclick = () => {
        uploadInput.click();
    };
    
    uploadInput.onchange = onUpload;
}
