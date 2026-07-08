import { getEditorExtensions, getEditorProps } from './config.js';
import { EditorToolbar } from './Toolbar.js';
import { EditorTableControls } from './TableControls.js';
import { EditorPlusTrigger } from './PlusTrigger.js';
import { saveContent } from '../api.js';
import { cliponNormalizeContentHtml } from '../utils.js';
import { showSaveIndicator, showSaveError } from '../ui.js';
import { t } from '../i18n.js';

function readElementHtml(el) {
    const serializer = new XMLSerializer();
    return Array.from(el.childNodes).map((node) => {
        if (node.nodeType === Node.PROCESSING_INSTRUCTION_NODE || node.nodeType === Node.DOCUMENT_TYPE_NODE) {
            return '';
        }
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue;
        return serializer.serializeToString(node);
    }).join('');
}

function setElementHtml(el, html) {
    const parsed = new DOMParser().parseFromString(html, 'text/html');
    const fragment = document.createDocumentFragment();

    while (parsed.body.firstChild) {
        fragment.appendChild(parsed.body.firstChild);
    }

    el.replaceChildren(fragment);
}

function isTextContainer(el) {
    return [
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'P', 'LI', 'SPAN', 'A', 'STRONG', 'EM', 'B', 'I', 'U', 'S',
        'SMALL', 'LABEL', 'BUTTON'
    ].includes(el.tagName);
}

function copyEditableAttributes(source, target) {
    Array.from(source.attributes).forEach((attr) => {
        if (attr.name === 'class' || attr.name === 'style') return;
        target.setAttribute(attr.name, attr.value);
    });
}

function unwrapSingleParagraph(html, targetEl, force = false) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    if (doc.body.children.length !== 1 || doc.body.firstElementChild.tagName !== 'P') {
        return html;
    }

    const p = doc.body.firstElementChild;
    if (force || p.attributes.length === 0 || isTextContainer(targetEl)) {
        return p.innerHTML;
    }

    return html;
}

export class CliponEditor {
    constructor(el, pageSlug, key, pageType, mode) {
        this.el = el;
        this.pageSlug = pageSlug;
        this.key = key;
        this.pageType = pageType;
        this.mode = mode;
        this.originalContent = readElementHtml(el);
        
        this.wrapper = null;
        this.editorEl = null;
        this.editor = null;
        this.originalParent = null;
        
        this.toolbar = null;
        this.tables = null;
        this.plusTrigger = null;
        
        this.eventListeners = []; // Event registry for cleanup.
        this.handleOutsideClick = this.handleOutsideClick.bind(this);
    }

    // Helper for registering events safely.
    addEventListener(target, type, listener, options) {
        target.addEventListener(type, listener, options);
        this.eventListeners.push({ target, type, listener, options });
    }

    removeAllEventListeners() {
        this.eventListeners.forEach(({ target, type, listener, options }) => {
            target.removeEventListener(type, listener, options);
        });
        this.eventListeners = [];
    }

    init(triggerEvent = null) {
        this.el.classList.add('clipon-editing');
        
        if (!window.CliponTiptap) {
            console.error('Tiptap not loaded');
            return;
        }

        this.createDOM();
        
        const { Editor } = window.CliponTiptap;
        
        this.editor = new Editor({
            element: this.editorEl,
            extensions: getEditorExtensions(this.mode, this.toolbar.bubbleMenuEl),
            content: this.originalContent,
            injectCSS: false,
            editorProps: getEditorProps(() => this.editor),
            onSelectionUpdate: () => this.onSelectionUpdate(),
            onTransaction: () => this.onTransaction(),
            onDestroy: () => this.onDestroy()
        });

        this.toolbar.init(this.editor);
        this.updateEmptyState();
        if (this.mode === 'full') {
            try {
                this.tables.init(this.editor, () => this.plusTrigger ? this.plusTrigger.hoveredBlockEl : null);
            } catch (e) {
                console.error('Failed to initialize table controls:', e);
                this.tables = null;
            }

            try {
                this.plusTrigger.init(
                    this.editor,
                    () => this.safeToolbarUpdate(),
                    () => this.safePositionFloatingMenu()
                );
            } catch (e) {
                console.error('Failed to initialize block menu trigger:', e);
                this.plusTrigger = null;
            }
        }

        this.addEventListener(document, 'mousedown', this.handleOutsideClick);
        this.addEventListener(window, 'beforeunload', (e) => {
            if (this.editor && this.editor.isFocused && this.wrapper.parentNode) {
                e.preventDefault();
                e.returnValue = ''; // Standard way to trigger a "Changes may not be saved" dialog
            }
        });
        
        this.focusEditor(triggerEvent);
        this.updateUI();
        window.requestAnimationFrame(() => this.updateUI());
    }

    createDOM() {
        this.originalParent = this.el.parentNode;
        const computedStyle = window.getComputedStyle(this.el);

        this.editorEl = document.createElement(this.el.tagName);
        copyEditableAttributes(this.el, this.editorEl);
        const classes = Array.from(this.el.classList).filter(c => c !== 'clipon-editing');
        classes.push('tiptap-wrapper', `mode-${this.mode}`);
        this.editorEl.className = classes.join(' ');
        const elStyle = this.el.getAttribute('style');
        if (elStyle) this.editorEl.setAttribute('style', elStyle);

        if (this.mode === 'base') {
            this.wrapper = this.editorEl;
            this.originalParent.replaceChild(this.editorEl, this.el);
        } else {
            this.wrapper = document.createElement('div');
            this.wrapper.className = `tiptap-wrapper mode-${this.mode}`;
            this.wrapper.style.display = computedStyle.display === 'inline' ? 'inline-block' : computedStyle.display;
            this.wrapper.style.position = 'relative';

            this.el.style.display = 'none';
            this.originalParent.insertBefore(this.wrapper, this.el);
            this.wrapper.append(this.editorEl);
        }

        this.toolbar = new EditorToolbar(this.mode, this.wrapper);
        
        if (this.mode === 'full') {
            this.plusTrigger = new EditorPlusTrigger(this.wrapper, this.editorEl, this.toolbar.floatingMenuEl);
            this.tables = new EditorTableControls(this.wrapper, this.toolbar.floatingMenuEl);
        }

        document.body.append(this.toolbar.bubbleMenuEl, this.toolbar.floatingMenuEl);
    }

    onSelectionUpdate() {
        this.updateUI();
    }

    onTransaction() {
        this.updateEmptyState();
        this.updateUI();
    }

    updateEmptyState() {
        if (!this.wrapper || !this.editor) return;
        this.wrapper.classList.toggle('is-empty', this.editor.isEmpty);
    }

    updateUI() {
        this.safeToolbarUpdate();
        this.safePositionFloatingMenu();
        if (this.mode === 'full') {
            if (this.tables) {
                try {
                    this.tables.update();
                    this.tables.updateSelectedCellHandle();
                } catch (e) {
                    console.error('Failed to update table controls:', e);
                }
            }
        }
    }

    focusEditor(triggerEvent = null) {
        if (!this.editor) return;

        if (triggerEvent && Number.isFinite(triggerEvent.clientX) && Number.isFinite(triggerEvent.clientY)) {
            try {
                const result = this.editor.view.posAtCoords({
                    left: triggerEvent.clientX,
                    top: triggerEvent.clientY
                });

                if (result && typeof result.pos === 'number') {
                    this.editor.chain().focus().setTextSelection(result.pos).run();
                    return;
                }
            } catch (e) {
                console.error('Failed to place editor cursor from click:', e);
            }
        }

        this.editor.commands.focus();
    }

    safeToolbarUpdate() {
        if (!this.toolbar) return;
        try {
            this.toolbar.update();
        } catch (e) {
            console.error('Failed to update editor toolbar:', e);
        }
    }

    safePositionFloatingMenu() {
        if (!this.toolbar) return;
        try {
            this.toolbar.positionFloatingMenu();
        } catch (e) {
            console.error('Failed to position editor floating menu:', e);
        }
    }

    handleOutsideClick(e) {
        if (this.wrapper.contains(e.target)) {
            if (!this.toolbar.floatingMenuEl.contains(e.target) && 
                (!this.plusTrigger || !this.plusTrigger.el.contains(e.target))) {
                this.toolbar.floatingMenuEl.classList.remove('is-visible');
            }
            return;
        }

        // Ignore clicks on floating UI elements tagged with data-tiptap-ui
        if (e.target.closest('[data-tiptap-ui="true"]')) {
            return;
        }

        const mediaModal = document.getElementById('media-modal');
        if (mediaModal && mediaModal.contains(e.target)) return;

        this.finishEditing();
    }

    finishEditing() {
        if (!this.editor || !this.wrapper.parentNode) return;
        
        let html = this.editor.getHTML().trim();
        
        // Clean up: unwrap single paragraph to match the original semantic context.
        // This is a common pattern in CMS to prevent breaking layouts that expect 
        // raw text or inline elements inside block containers (like headers).
        html = unwrapSingleParagraph(html, this.el);

        this.wrapper.classList.add('is-saving');
        
        saveContent(this.pageSlug, this.key, html, this.pageType, cliponNormalizeContentHtml)
            .then(() => {
                showSaveIndicator();
                this.finalizeFinish(html);
            })
            .catch((err) => {
                console.error('Failed to auto-save:', err);
                showSaveError(t('contentSaveError', 'Save error. Try again or copy the text.'));
                this.wrapper.classList.remove('is-saving');
                this.editor.commands.focus();
            });
    }

    finalizeFinish(html) {
        this.removeAllEventListeners();
        this.editor.destroy(); 
        setElementHtml(this.el, unwrapSingleParagraph(html, this.el, true));

        if (this.mode === 'base') {
            this.editorEl.replaceWith(this.el);
        } else {
            this.wrapper.remove();
            this.el.style.display = '';
        }

        this.el.classList.remove('clipon-editing');
    }

    onDestroy() {
        if (this.tables) this.tables.destroy();
        if (this.plusTrigger) this.plusTrigger.destroy();
        if (this.toolbar) this.toolbar.destroy();
    }
}
