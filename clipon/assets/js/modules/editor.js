/**
 * Editor Core Module
 * Unified Tiptap implementation for pages and blog.
 */

import { mediaState, loadMediaLibrary } from './media.js';
import { CliponEditor } from './editor/CliponEditor.js';
import { saveContent } from './api.js';
import { showSaveError, showSaveIndicator } from './ui.js';
import { decodeHtmlEntities } from './utils.js?v=entities-decode-20260609';
import { t } from './i18n.js';

let activeLinkHrefEditor = null;

function isTextContainer(el) {
    return [
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'P', 'LI', 'SPAN', 'STRONG', 'EM', 'B', 'I', 'U', 'S',
        'SMALL', 'LABEL', 'BUTTON'
    ].includes(el.tagName);
}

function sanitizeHrefForPreview(value) {
    const href = String(value || '').trim();
    if (!href) return '';
    if (/[\u0000-\u001F\u007F<>"'`]/u.test(href)) return '';
    if (href.startsWith('//')) return '';

    const schemeMatch = href.match(/^([a-z][a-z0-9+.-]*):/i);
    if (schemeMatch) {
        const scheme = schemeMatch[1].toLowerCase();
        if (!['http', 'https', 'mailto', 'tel'].includes(scheme)) return '';
    }

    return href;
}

class LinkHrefEditor {
    constructor(el, pageSlug, key, pageType) {
        this.el = el;
        this.pageSlug = pageSlug;
        this.key = key;
        this.pageType = pageType;
        this.popover = null;
        this.textInput = null;
        this.hrefInput = null;
        this.handleDocumentMouseDown = this.handleDocumentMouseDown.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
    }

    open(event) {
        if (activeLinkHrefEditor && activeLinkHrefEditor !== this) {
            activeLinkHrefEditor.close();
        }
        activeLinkHrefEditor = this;

        this.close(false);
        this.popover = document.createElement('div');
        this.popover.className = 'clipon-link-popover';
        this.popover.setAttribute('data-clipon-ui', 'true');
        this.popover.setAttribute('role', 'dialog');
        this.popover.setAttribute('aria-label', t('linkDialog', 'Edit button'));

        const textField = this.createField(
            t('linkTextLabel', 'Button text'),
            decodeHtmlEntities(this.el.textContent || ''),
            t('linkTextPlaceholder', 'Text')
        );
        this.textInput = textField.input;

        const hrefField = this.createField(
            t('linkHrefLabel', 'Link'),
            this.el.getAttribute('href') || '',
            t('linkHrefPlaceholder', '/contacts or https://...')
        );
        this.hrefInput = hrefField.input;

        const actions = document.createElement('div');
        actions.className = 'clipon-link-popover-actions';

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'clipon-link-popover-btn primary';
        saveBtn.textContent = t('save', 'Save');
        saveBtn.addEventListener('click', () => this.save());

        actions.append(saveBtn);
        this.popover.append(textField.wrapper, hrefField.wrapper, actions);
        document.body.appendChild(this.popover);
        this.position();

        this.popover.addEventListener('mousedown', (e) => e.stopPropagation());
        document.addEventListener('mousedown', this.handleDocumentMouseDown);
        document.addEventListener('keydown', this.handleKeyDown);

        this.textInput.focus();
        this.textInput.select();
    }

    createField(labelText, value, placeholder) {
        const wrapper = document.createElement('label');
        wrapper.className = 'clipon-link-popover-field';

        const label = document.createElement('span');
        label.className = 'clipon-link-popover-label';
        label.textContent = labelText;

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'clipon-link-popover-input';
        input.value = value;
        input.placeholder = placeholder;
        input.setAttribute('aria-label', labelText);

        wrapper.append(label, input);
        return { wrapper, input };
    }

    position() {
        if (!this.popover) return;

        const rect = this.el.getBoundingClientRect();
        const width = Math.min(360, window.innerWidth - 24);
        this.popover.style.width = `${width}px`;

        const popoverRect = this.popover.getBoundingClientRect();
        let left = rect.left + window.scrollX;
        let top = rect.bottom + window.scrollY + 8;

        left = Math.max(12 + window.scrollX, Math.min(left, window.scrollX + window.innerWidth - popoverRect.width - 12));
        if (top + popoverRect.height > window.scrollY + window.innerHeight - 12) {
            top = Math.max(window.scrollY + 12, rect.top + window.scrollY - popoverRect.height - 8);
        }

        this.popover.style.left = `${left}px`;
        this.popover.style.top = `${top}px`;
    }

    async save() {
        if (!this.textInput || !this.hrefInput) return;

        const text = this.textInput.value.trim();
        const rawHref = this.hrefInput.value;
        const href = sanitizeHrefForPreview(rawHref);
        if (rawHref.trim() !== '' && href === '') {
            showSaveError(t('invalidLink', 'Invalid link'));
            this.hrefInput.focus();
            return;
        }

        this.popover.classList.add('is-saving');
        try {
            await Promise.all([
                saveContent(this.pageSlug, this.key, text, this.pageType),
                saveContent(this.pageSlug, `${this.key}_href`, href, this.pageType, undefined, {
                    contentKind: 'link_href'
                })
            ]);

            this.el.textContent = text;
            if (href) {
                this.el.setAttribute('href', href);
            } else {
                this.el.removeAttribute('href');
            }
            showSaveIndicator();
            this.close();
        } catch (error) {
            console.error('Failed to save link href:', error);
            showSaveError(t('saveLinkError', 'Failed to save link'));
            if (this.popover) this.popover.classList.remove('is-saving');
        }
    }

    handleDocumentMouseDown(event) {
        if (this.popover && this.popover.contains(event.target)) return;
        if (this.el.contains(event.target)) return;
        this.close();
    }

    handleKeyDown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }
        if (event.key === 'Enter' && (document.activeElement === this.textInput || document.activeElement === this.hrefInput)) {
            event.preventDefault();
            this.save();
        }
    }

    close(clearActive = true) {
        document.removeEventListener('mousedown', this.handleDocumentMouseDown);
        document.removeEventListener('keydown', this.handleKeyDown);
        if (this.popover) {
            this.popover.remove();
            this.popover = null;
        }
        if (clearActive && activeLinkHrefEditor === this) {
            activeLinkHrefEditor = null;
        }
    }
}

export function setupEditors(pageSlug, pageType) {
    document.querySelectorAll('.clipon').forEach((el) => {
        if (el.dataset.cliponInit) return;
        el.dataset.cliponInit = 'true';

        const key = el.dataset.key || el.id;
        if (!key) return;

        if (el.tagName === 'IMG') {
            el.addEventListener('click', () => {
                const modal = document.getElementById('media-modal');
                if (modal) {
                    mediaState.currentImage = el;
                    mediaState.pageSlug = pageSlug;
                    mediaState.pageType = pageType;
                    loadMediaLibrary();
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
            return;
        }

        if (el.tagName === 'A') {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (this.classList.contains('clipon-editing')) return;

                const linkEditor = new LinkHrefEditor(this, pageSlug, key, pageType);
                linkEditor.open(e);
            });
            return;
        }

        el.addEventListener('click', function(e) {
            if (this.classList.contains('clipon-editing')) return;
            
            const mode = (String(pageType).toLowerCase() === 'blog' && !isTextContainer(this)) ? 'full' : 'base';
            const editor = new CliponEditor(this, pageSlug, key, pageType, mode);
            editor.init(e);
        });
    });
}
