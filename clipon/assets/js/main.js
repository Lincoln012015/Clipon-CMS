/**
 * Main Entry Point
 * Coordinates the system startup, configuration injection, and module initialization.
 */

import { initBaseUI, initMediaModal } from './modules/ui.js';
import { setupEditors } from './modules/editor.js?v=entities-decode-20260609';
import { createVersion } from './modules/api.js';
import { handleUpload } from './modules/media.js';
import { ensureStyleLoaded, loadScriptOnce } from './modules/utils.js?v=entities-decode-20260609';
import { t } from './modules/i18n.js';

const INLINE_EDITOR_FALLBACK_CSS = `
body.clipon-inline-edit-mode .clipon { cursor:pointer; outline:2px dashed rgba(0,123,255,0.4); outline-offset:2px; transition:all 0.2s; min-height:1em; }
body.clipon-inline-edit-mode .clipon-editing { outline:2px solid #28a745 !important; outline-offset:2px; cursor:text; }
body.clipon-inline-edit-mode .save-indicator { position:fixed; bottom:20px; right:20px; background:#28a745; color:#fff; padding:10px 20px; border-radius:5px; display:none; z-index:9999; }
body.clipon-inline-edit-mode .admin-back-btn { position:fixed; bottom:20px; left:20px; background:#333; color:#fff; padding:10px 20px; border-radius:5px; border:none; cursor:pointer; z-index:9999; }
body.clipon-inline-edit-mode .clipon-link-popover { position:absolute; z-index:10002; display:flex; flex-direction:column; gap:8px; padding:12px; background:#fff; color:#1f2937; border:1px solid #d7dee8; border-radius:8px; box-shadow:0 16px 40px rgba(15,23,42,0.18); box-sizing:border-box; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
body.clipon-inline-edit-mode .clipon-link-popover-field { display:block; min-width:0; }
body.clipon-inline-edit-mode .clipon-link-popover-label { display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; }
body.clipon-inline-edit-mode .clipon-link-popover-input { width:100%; height:36px; padding:7px 9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font:inherit; font-size:14px; }
body.clipon-inline-edit-mode .clipon-link-popover-actions { display:flex; justify-content:flex-end; gap:8px; }
body.clipon-inline-edit-mode .clipon-link-popover-btn { min-height:32px; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#1f2937; font:inherit; font-size:13px; font-weight:600; cursor:pointer; }
body.clipon-inline-edit-mode .clipon-link-popover-btn.primary { border-color:#007bff; background:#007bff; color:#fff; }
`;

const TIPTAP_FALLBACK_CSS = `
.tiptap-wrapper.mode-full { position:relative; }
.ProseMirror { min-height:1em; min-width:1ch; }
.tiptap-wrapper.is-empty .ProseMirror { min-height:1.2em; min-width:2ch; }
.tiptap-wrapper.mode-base.is-empty .ProseMirror > p:only-child { display:block; min-height:1.2em; margin:0; }
.tiptap-wrapper.mode-base .ProseMirror { font:inherit; color:inherit; letter-spacing:inherit; line-height:inherit; text-align:inherit; }
.tiptap-wrapper.mode-base .ProseMirror p:only-child { display:inline; margin:0; padding:0; font:inherit; color:inherit; letter-spacing:inherit; line-height:inherit; text-align:inherit; }
.tiptap-bubble-menu, .tiptap-floating-menu { background:#fff; border:1px solid #e2e8f0; border-radius:8px; z-index:10001; }
.tiptap-btn { border:none; background:transparent; width:28px; height:28px; border-radius:4px; }
.tiptap-btn:hover { background:#f1f5f9; }
`;

document.addEventListener('DOMContentLoaded', async function() {
    const urlParams = new URLSearchParams(window.location.search);
    const pathname = window.location.pathname;
    
    // Determine page slug and type
    let pageSlug = urlParams.get('page');
    let pageType = 'page';

    const metaType = document.querySelector('meta[name="cms-type"]');
    if (metaType) pageType = metaType.content;

    const metaSlug = document.querySelector('meta[name="page-slug"]');
    if (metaSlug && metaSlug.content) {
        pageSlug = metaSlug.content;
    }

    if (!pageSlug) {
        const pathParts = pathname.split('/').filter(p => p);
        if (pathParts.length === 0 || (pathParts.length === 1 && pathParts[0] === '')) {
            pageSlug = 'index';
        } else {
            pageSlug = pathParts[pathParts.length - 1].replace('.php', '').replace('.html', '');
        }
    }

    // Check edit mode
    if (urlParams.get('edit') !== null) {
        try {
            const base = window.CMS_BASE_PATH || '';
            const response = await fetch(base + '/clipon/check_edit.php?edit=1');
            const data = await response.json();
            if (data.edit) {
                initCMS(pageSlug, pageType);
            }
        } catch (error) {
            console.error('Error checking edit mode:', error);
        }
    }
});

async function initCMS(pageSlug, pageType) {
    const base = (window.CMS_BASE_PATH || '').replace(/\/$/, '');

    const cacheBuster = new Date().getTime();
    await Promise.all([
        ensureStyleLoaded(base + '/clipon/assets/css/inline-editor-ui.css?v=' + cacheBuster, {
            id: 'clipon-inline-editor-ui-style',
            fallbackCss: INLINE_EDITOR_FALLBACK_CSS,
            timeoutMs: 2500
        }),
        ensureStyleLoaded(base + '/clipon/assets/css/tiptap.css?v=' + cacheBuster, {
            id: 'clipon-tiptap-style',
            fallbackCss: TIPTAP_FALLBACK_CSS,
            timeoutMs: 2500
        })
    ]);

    // Initialize UI and Modal
    initBaseUI(pageSlug, pageType, async (e) => {
        e.preventDefault();
        const btn = e.target;
        btn.textContent = t('saving', 'Saving...');
        btn.disabled = true;
        
        try {
            await createVersion(pageSlug, pageType);
            const redirectPath = (pageType === 'blog') ? '/clipon/admin/blog.php' : '/clipon/admin/pages.php';
            window.location.href = base + redirectPath;
        } catch (error) {
            console.error('Error saving version:', error);
            const fallbackPath = (pageType === 'blog') ? '/clipon/admin/blog.php' : '/clipon/admin/pages.php';
            window.location.href = base + fallbackPath;
        }
    });

    initMediaModal(handleUpload);

    try {
        const cacheBuster = new Date().getTime();
        const scripts = [
            loadScriptOnce(base + '/clipon/assets/js/dist/tiptap-bundle.iife.js?v=' + cacheBuster, 'CliponTiptap'),
            loadScriptOnce(base + '/clipon/assets/vendor/marked/marked.min.js?v=' + cacheBuster, 'marked'),
            loadScriptOnce(base + '/clipon/assets/vendor/turndown/turndown.js?v=' + cacheBuster, 'TurndownService'),
            loadScriptOnce(base + '/clipon/assets/vendor/turndown-plugin-gfm/turndown-plugin-gfm.js?v=' + cacheBuster, 'turndownPluginGfm')
        ];

        await Promise.all(scripts);

        // Configure marked and turndown
        if (window.marked && typeof window.marked.setOptions === 'function') {
            window.marked.setOptions({ gfm: true, breaks: true, tables: true });
        }
        if (window.TurndownService && !window.cliponTurndown) {
            window.cliponTurndown = new TurndownService({ headingStyle: 'atx', bulletListMarker: '-' });
        }

        setupEditors(pageSlug, pageType);
    } catch (err) {
        console.error('Error loading editor scripts:', err);
        setupEditors(pageSlug, pageType); // Fallback to basic
    }
}
