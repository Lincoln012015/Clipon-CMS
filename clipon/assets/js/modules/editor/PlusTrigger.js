import { TIPTAP_ICONS } from './icons.js';
import { appendSvgIcon } from './dom.js';
import { t } from '../i18n.js';

export class EditorPlusTrigger {
    constructor(wrapper, editorEl, floatingMenuEl) {
        this.wrapper = wrapper;
        this.editorEl = editorEl;
        this.floatingMenuEl = floatingMenuEl;
        this.editor = null;
        
        this.el = document.createElement('button');
        this.el.type = 'button';
        this.el.className = 'tiptap-plus-trigger';
        this.el.setAttribute('data-tiptap-ui', 'true');
        this.el.setAttribute('aria-label', t('addBlock', 'Add block'));
        this.el.setAttribute('aria-haspopup', 'true');
        this.el.setAttribute('aria-expanded', 'false');
        appendSvgIcon(this.el, TIPTAP_ICONS.plus);
        
        this.hoveredBlockEl = null;

        this.onUpdateToolbar = null;
        this.onPositionFloatingMenu = null;
        this.eventListeners = [];
        this.cleanupAutoUpdate = null;
    }

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

    init(editor, onUpdateToolbar, onPositionFloatingMenu) {
        this.editor = editor;
        this.onUpdateToolbar = onUpdateToolbar;
        this.onPositionFloatingMenu = onPositionFloatingMenu;
        
        document.body.appendChild(this.el);
        this.setupEvents();
        // Initial check
        this.updatePosition();
    }

    setupEvents() {
        this.addEventListener(this.el, 'pointerdown', (e) => { e.preventDefault(); e.stopPropagation(); });
        
        this.addEventListener(this.el, 'click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleFloatingMenu();
        });

        const { autoUpdate, computePosition } = window.CliponTiptap;

        // selectionUpdate ensures we find the RIGHT block when cursor moves
        this.editor.on('selectionUpdate', () => {
            const block = this.findCurrentBlock();
            if (block) {
                this.hoveredBlockEl = block;
                this.el.classList.toggle('is-visible', !block.closest('td,th'));
                
                // Start tracking this specific block
                if (this.cleanupAutoUpdate) this.cleanupAutoUpdate();
                this.cleanupAutoUpdate = autoUpdate(block, this.el, () => {
                    this.updatePlusPositionForElement(block);
                }, { strategy: 'fixed' });
            } else {
                this.hoveredBlockEl = null;
                this.el.classList.remove('is-visible');
                if (this.cleanupAutoUpdate) {
                    this.cleanupAutoUpdate();
                    this.cleanupAutoUpdate = null;
                }
            }
        });

        // Hide if editor loses focus completely
        this.editor.on('blur', () => {
            setTimeout(() => {
                if (!this.editor.isFocused) {
                    this.el.classList.remove('is-visible');
                    if (this.cleanupAutoUpdate) {
                        this.cleanupAutoUpdate();
                        this.cleanupAutoUpdate = null;
                    }
                }
            }, 100);
        });
    }

    findCurrentBlock() {
        if (!this.editor || !this.editor.isFocused) return null;
        const { from } = this.editor.state.selection;
        try {
            const domAtPos = this.editor.view.domAtPos(from);
            let node = domAtPos.node;
            if (node.nodeType === 3) node = node.parentElement;
            return this.findBlockElement(node);
        } catch (e) {
            return null;
        }
    }

    // This method is now legacy but kept for reference or single updates
    updatePosition() {
        const block = this.findCurrentBlock();
        if (block) {
            this.updatePlusPositionForElement(block);
        }
    }

    toggleFloatingMenu(forceOpen) {
        if (forceOpen === true) {
            this.floatingMenuEl.classList.add('is-visible');
            this.el.setAttribute('aria-expanded', 'true');
            // Do not focus editor here, it might steal focus from inputs
            this.onPositionFloatingMenu();
            this.onUpdateToolbar();
            return;
        }
        if (forceOpen === false) {
            this.floatingMenuEl.classList.remove('is-visible');
            this.el.setAttribute('aria-expanded', 'false');
            return;
        }
        const isVisible = this.floatingMenuEl.classList.toggle('is-visible');
        this.el.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
        if (isVisible) {
            this.onPositionFloatingMenu();
            this.onUpdateToolbar();
        }
    }

    findBlockElement(target) {
        if (!this.editorEl.contains(target)) return null;
        return target.closest('p,h1,h2,h3,blockquote,li,div,td,th,figure');
    }

    updatePlusPositionForElement(el) {
        try {
            const { computePosition } = window.CliponTiptap;
            const editorRect = this.editorEl.getBoundingClientRect();
            
            computePosition(el, this.el, {
                strategy: 'fixed',
                placement: 'left',
            }).then(({ y }) => {
                // Stable X: always relative to the editor container's left edge, not the block's left edge.
                // This prevents "jumping" when blocks have different indentation (lists, blockquotes).
                const buttonWidth = this.el.offsetWidth || 36;
                const stableX = editorRect.left - buttonWidth - 8; // 8px margin from editor
                
                Object.assign(this.el.style, {
                    left: `${stableX}px`,
                    top: `${y}px`,
                    position: 'fixed',
                    zIndex: '1000'
                });
            });
        } catch (e) {}
    }

    findBlockDomFromSelection() {
        try {
            const { from } = this.editor.state.selection;
            const pos = from;
            const dom = this.editor.view.domAtPos(pos).node;
            if (!dom) return null;
            return dom.nodeType === 3 ? dom.parentElement : dom;
        } catch (e) { return null; }
    }

    showNewBlockFeedback() {
        this.el.classList.add('is-action');
        setTimeout(() => this.el.classList.remove('is-action'), 220);

        setTimeout(() => {
            const blockEl = this.findBlockDomFromSelection();
            if (blockEl) {
                blockEl.classList.add('tiptap-new-block-anim');
                setTimeout(() => blockEl.classList.remove('tiptap-new-block-anim'), 900);
            }

            let tooltip = document.querySelector('.tiptap-plus-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'tiptap-plus-tooltip';
                tooltip.textContent = t('added', 'Added');
                document.body.appendChild(tooltip);
            }
            
            tooltip.classList.add('is-visible');
            
            const { computePosition, offset } = window.CliponTiptap;
            computePosition(this.el, tooltip, {
                strategy: 'fixed',
                placement: 'top',
                middleware: [offset(8)],
            }).then(({ x, y }) => {
                Object.assign(tooltip.style, {
                    left: `${x}px`,
                    top: `${y}px`,
                    position: 'fixed'
                });
            });
            
            setTimeout(() => tooltip.classList.remove('is-visible'), 900);
        }, 40);
    }

    destroy() {
        this.removeAllEventListeners();
        if (this.hoverRaf) cancelAnimationFrame(this.hoverRaf);
        if (this.hoverLeaveTimeout) clearTimeout(this.hoverLeaveTimeout);
        this.el.remove();
    }
}
