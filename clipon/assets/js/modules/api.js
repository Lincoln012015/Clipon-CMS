/**
 * API Client Module
 * Handles asynchronous requests with CSRF protection and error handling.
 */

import { t } from './i18n.js';

export async function fetchAPI(url, options = {}) {
    const defaultOptions = {
        method: 'POST',
        headers: {},
        keepalive: true // Ensure request completes even if page unloads
    };

    const finalOptions = { ...defaultOptions, ...options };

    // Automatically append CSRF token to FormData if it exists
    if (finalOptions.body instanceof FormData && window.CLIPON_CSRF_TOKEN) {
        if (!finalOptions.body.has('csrf_token')) {
            finalOptions.body.append('csrf_token', window.CLIPON_CSRF_TOKEN);
        }
    }

    try {
        const response = await fetch(url, finalOptions);
        
        if (!response.ok) {
            if (response.status === 403) {
                throw new Error(t('forbiddenSession', 'Access denied (CSRF error or session expired)'));
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response;
    } catch (error) {
        console.error('API Request failed:', error);
        throw error;
    }
}

export async function saveContent(pageSlug, key, content, pageType, normalizeFn, options = {}) {
    if (typeof normalizeFn === 'function') {
        content = normalizeFn(content);
    }

    const formData = new FormData();
    formData.append('page', pageSlug);
    formData.append('key', key);
    formData.append('content', content);
    formData.append('type', pageType || 'page');
    if (options.contentKind) {
        formData.append('content_kind', options.contentKind);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const editLang = (urlParams.get('edit_lang') || '').trim();
    const metaLang = (document.querySelector('meta[name="cms-lang"]')?.content || '').trim();
    const langToSave = editLang || metaLang;
    if (langToSave) {
        formData.append('lang', langToSave);
    }

    const base = window.CMS_BASE_PATH || '';
    return fetchAPI(base + '/clipon/save.php', {
        body: formData
    });
}

export async function createVersion(pageSlug, pageType) {
    const formData = new FormData();
    formData.append('action', 'create_version');
    formData.append('page', pageSlug);
    formData.append('type', pageType);

    const base = window.CMS_BASE_PATH || '';
    return fetchAPI(base + '/clipon/save.php', {
        body: formData
    });
}

export async function getMediaList(dir = '') {
    const base = window.CMS_BASE_PATH || '';
    const url = `${base}/clipon/admin/api/media_list.php?dir=${encodeURIComponent(dir)}`;
    const response = await fetch(url);
    if (!response.ok) throw new Error('Failed to fetch media list');
    return response.json();
}

export async function uploadMedia(file, dir = '') {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('dir', dir);
    
    const base = window.CMS_BASE_PATH || '';
    const response = await fetchAPI(base + '/clipon/admin/upload_handler.php', {
        body: formData
    });
    return response.json();
}
