import { TIPTAP_ICONS } from './icons.js';
import { appendSvgIcon } from './dom.js';
import { mediaState, loadMediaLibrary } from '../media.js';
import { getVideoEmbedUrl } from '../utils.js';
import { EditorInputPopup } from './InputPopup.js';

export class EditorToolbar {
    constructor(mode, wrapper) {
        this.mode = mode;
        this.wrapper = wrapper;
        this.bubbleMenuEl = document.createElement('div');
        this.bubbleMenuEl.className = 'tiptap-bubble-menu';
        this.bubbleMenuEl.setAttribute('data-tiptap-ui', 'true');
        this.bubbleMenuEl.setAttribute('role', 'toolbar');
        this.bubbleMenuEl.setAttribute('aria-label', 'Text formatting');
        
        this.floatingMenuEl = document.createElement('div');
        this.floatingMenuEl.className = 'tiptap-floating-menu';
        this.floatingMenuEl.setAttribute('data-tiptap-ui', 'true');
        this.floatingMenuEl.setAttribute('role', 'toolbar');
        this.floatingMenuEl.setAttribute('aria-label', 'Block formatting');
        
        this.inputPopup = new EditorInputPopup(wrapper);
        
        this.toolbarButtons = [];
        this.textColorInput = null;
        this.editor = null;
        this.eventListeners = [];
        this.cleanupFloatingMenuAutoUpdate = null;
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

    init(editor) {
        this.editor = editor;
        this.renderMenus();
        
        const { autoUpdate } = window.CliponTiptap;
        
        // Use autoUpdate for floating menu if visible
        this.editor.on('selectionUpdate', () => {
            const isVisible = this.floatingMenuEl.classList.contains('is-visible');
            if (isVisible) {
                this.startTrackingFloatingMenu();
            }
        });
    }

    startTrackingFloatingMenu() {
        if (this.cleanupFloatingMenuAutoUpdate) this.cleanupFloatingMenuAutoUpdate();
        const { autoUpdate } = window.CliponTiptap;
        
        // We use a virtual element based on current cursor
        const virtualEl = this.createVirtualCursorElement();
        if (!virtualEl) return;

        this.cleanupFloatingMenuAutoUpdate = autoUpdate(virtualEl, this.floatingMenuEl, () => {
            this.positionFloatingMenu();
        }, { strategy: 'fixed' });
    }

    createVirtualCursorElement() {
        if (!this.editor || !this.editor.isFocused) return null;
        try {
            const { from } = this.editor.state.selection;
            const coords = this.editor.view.coordsAtPos(from);
            return {
                getBoundingClientRect() {
                    return {
                        width: 0,
                        height: coords.bottom - coords.top,
                        x: coords.left,
                        y: coords.top,
                        top: coords.top,
                        left: coords.left,
                        right: coords.left,
                        bottom: coords.bottom,
                    };
                },
            };
        } catch (e) {
            return null;
        }
    }

    rgbToHex(rgb) {
        if (!rgb || rgb.startsWith('#')) return rgb || '#000000';
        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) return '#000000';
        return "#" + ((1 << 24) + (parseInt(match[1]) << 16) + (parseInt(match[2]) << 8) + parseInt(match[3])).toString(16).slice(1);
    }

    renderGroup(group, container) {
        const groupEl = document.createElement('div');
        groupEl.className = 'tiptap-toolbar-group';
        container.appendChild(groupEl);

        group.forEach(btnDef => {
            if (btnDef.type === 'dropdown') {
                const select = document.createElement('select');
                select.className = 'tiptap-select';
                select.setAttribute('aria-label', btnDef.title || 'Select option');
                btnDef.options.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt.value; o.textContent = opt.label;
                    select.appendChild(o);
                });
                this.addEventListener(select, 'change', () => {
                    const opt = btnDef.options.find(o => o.value === select.value);
                    if (opt) opt.action();
                });
                groupEl.appendChild(select);
                this.toolbarButtons.push({ element: select, update: () => {
                    const active = btnDef.options.find(o => o.isActive());
                    if (active) select.value = active.value;
                }});
                return;
            }

            if (btnDef.type === 'color') {
                const input = document.createElement('input');
                input.type = 'color'; input.className = 'tiptap-color-input';
                input.setAttribute('aria-label', btnDef.title || 'Choose color');
                this.addEventListener(input, 'input', () => btnDef.action(input.value));
                groupEl.appendChild(input);
                this.textColorInput = input;
                return;
            }

            const btn = document.createElement('button');
            btn.className = 'tiptap-btn'; 
            if (!appendSvgIcon(btn, btnDef.icon)) {
                btn.textContent = btnDef.title || '';
            }
            btn.title = btnDef.title; 
            btn.type = 'button';
            btn.setAttribute('aria-label', btnDef.title);
            btn.setAttribute('aria-pressed', 'false');
            
            this.addEventListener(btn, 'mousedown', (e) => { 
                e.preventDefault(); 
                btnDef.action(btn); 
                setTimeout(() => this.update(), 10); 
            });
            groupEl.appendChild(btn);
            if (btnDef.isActive) {
                this.toolbarButtons.push({ element: btn, update: () => {
                    const isActive = btnDef.isActive();
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    if (isActive) btn.classList.add('is-active');
                    else btn.classList.remove('is-active');
                }});
            }
        });
    }

    getBubbleGroups() {
        const editor = this.editor;
        const alignment = [
            { icon: TIPTAP_ICONS.alignLeft, title: 'Left', action: () => editor.chain().focus().setTextAlign('left').run(), isActive: () => editor.isActive({ textAlign: 'left' }) },
            { icon: TIPTAP_ICONS.alignCenter, title: 'Center', action: () => editor.chain().focus().setTextAlign('center').run(), isActive: () => editor.isActive({ textAlign: 'center' }) },
            { icon: TIPTAP_ICONS.alignRight, title: 'Right', action: () => editor.chain().focus().setTextAlign('right').run(), isActive: () => editor.isActive({ textAlign: 'right' }) }
        ];

        const textFormatting = [
            { icon: TIPTAP_ICONS.bold, title: 'Bold', action: () => editor.chain().focus().toggleBold().run(), isActive: () => editor.isActive('bold') },
            { icon: TIPTAP_ICONS.italic, title: 'Italic', action: () => editor.chain().focus().toggleItalic().run(), isActive: () => editor.isActive('italic') },
            { icon: TIPTAP_ICONS.underline, title: 'Underline', action: () => editor.chain().focus().toggleUnderline().run(), isActive: () => editor.isActive('underline') },
            { icon: TIPTAP_ICONS.strike, title: 'Strike', action: () => editor.chain().focus().toggleStrike().run(), isActive: () => editor.isActive('strike') }
        ];

        const basicGroups = [
            textFormatting,
            alignment,
            [
                { type: 'color', title: 'Color', action: (c) => editor.chain().focus().setColor(c).run() },
                { icon: TIPTAP_ICONS.link, title: 'Link', action: (btn) => {
                    const currentUrl = editor.getAttributes('link').href;
                    this.inputPopup.show(btn, {
                        placeholder: 'Enter URL...',
                        defaultValue: currentUrl,
                        onSubmit: (url) => {
                            if (url) editor.chain().focus().setLink({ href: url }).run();
                            else if (url === '') editor.chain().focus().unsetLink().run();
                        }
                    });
                }, isActive: () => editor.isActive('link') },
                { icon: TIPTAP_ICONS.clear, title: 'Clear', action: () => editor.chain().focus().unsetAllMarks().run() }
            ]
        ];

        if (this.mode === 'full') {
            return [
                [{
                    type: 'dropdown',
                    title: 'Type',
                    options: [
                        { label: 'Text', value: 'paragraph', action: () => editor.chain().focus().setParagraph().run(), isActive: () => editor.isActive('paragraph') },
                        { label: 'H1', value: 'h1', action: () => editor.chain().focus().toggleHeading({ level: 1 }).run(), isActive: () => editor.isActive('heading', { level: 1 }) },
                        { label: 'H2', value: 'h2', action: () => editor.chain().focus().toggleHeading({ level: 2 }).run(), isActive: () => editor.isActive('heading', { level: 2 }) },
                        { label: 'H3', value: 'h3', action: () => editor.chain().focus().toggleHeading({ level: 3 }).run(), isActive: () => editor.isActive('heading', { level: 3 }) }
                    ]
                }],
                textFormatting,
                [
                    { type: 'color', title: 'Color', action: (c) => editor.chain().focus().setColor(c).run() },
                    { icon: TIPTAP_ICONS.link, title: 'Link', action: (btn) => {
                        const currentUrl = editor.getAttributes('link').href;
                        this.inputPopup.show(btn, {
                            placeholder: 'Enter URL...',
                            defaultValue: currentUrl,
                            onSubmit: (url) => {
                                if (url) editor.chain().focus().setLink({ href: url }).run();
                                else if (url === '') editor.chain().focus().unsetLink().run();
                            }
                        });
                    }, isActive: () => editor.isActive('link') }
                ],
                [
                    { icon: TIPTAP_ICONS.bulletList, title: 'Bullets', action: () => editor.chain().focus().toggleBulletList().run(), isActive: () => editor.isActive('bulletList') },
                    { icon: TIPTAP_ICONS.orderedList, title: 'Numbers', action: () => editor.chain().focus().toggleOrderedList().run(), isActive: () => editor.isActive('orderedList') },
                    { icon: TIPTAP_ICONS.quote, title: 'Quote', action: () => editor.chain().focus().toggleBlockquote().run(), isActive: () => editor.isActive('blockquote') },
                    { icon: TIPTAP_ICONS.code, title: 'Code', action: () => editor.chain().focus().toggleCodeBlock().run(), isActive: () => editor.isActive('codeBlock') }
                ],
                alignment,
                [
                    { icon: TIPTAP_ICONS.undo, title: 'Undo', action: () => editor.chain().focus().undo().run() },
                    { icon: TIPTAP_ICONS.redo, title: 'Redo', action: () => editor.chain().focus().redo().run() },
                    { icon: TIPTAP_ICONS.clear, title: 'Clear', action: () => editor.chain().focus().unsetAllMarks().run() }
                ]
            ];
        }

        return basicGroups;
    }

    getFloatingGroups() {
        const editor = this.editor;
        const headingAndBlocks = [
            { icon: TIPTAP_ICONS.h1, title: 'H1', action: () => editor.chain().focus().toggleHeading({ level: 1 }).run() },
            { icon: TIPTAP_ICONS.h2, title: 'H2', action: () => editor.chain().focus().toggleHeading({ level: 2 }).run() },
            { icon: TIPTAP_ICONS.h3, title: 'H3', action: () => editor.chain().focus().toggleHeading({ level: 3 }).run() },
            { icon: TIPTAP_ICONS.bulletList, title: 'Bulleted list', action: () => editor.chain().focus().toggleBulletList().run() },
            { icon: TIPTAP_ICONS.orderedList, title: 'Numbered list', action: () => editor.chain().focus().toggleOrderedList().run() },
            { icon: TIPTAP_ICONS.quote, title: 'Quote', action: () => editor.chain().focus().toggleBlockquote().run() },
            { icon: TIPTAP_ICONS.code, title: 'Code block', action: () => editor.chain().focus().toggleCodeBlock().run() }
        ];

        const mediaAndTable = [];
        if (this.mode === 'full') {
            mediaAndTable.push({ icon: TIPTAP_ICONS.table, title: 'Table', action: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() });
        }
        mediaAndTable.push(
            { icon: TIPTAP_ICONS.image, title: 'Image', action: () => {
                const modal = document.getElementById('media-modal');
                if (modal) {
                    mediaState.cliponCurrentTiptapEditor = editor;
                    loadMediaLibrary();
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }
            }},
            { icon: TIPTAP_ICONS.video, title: 'Video', action: (btn) => {
                this.inputPopup.show(btn, {
                    placeholder: 'Enter Video URL (YouTube, Vimeo, etc)...',
                    onSubmit: (url) => {
                        if (url) {
                            const embedUrl = getVideoEmbedUrl(url);
                            if (embedUrl) {
                                editor.chain().focus().insertContent({
                                    type: 'iframe',
                                    attrs: { src: embedUrl }
                                }).run();
                            } else if (url.includes('iframe') || url.includes('http')) {
                                const src = url.match(/src=["'](.*?)["']/) ? url.match(/src=["'](.*?)["']/)[1] : url;
                                editor.chain().focus().insertContent({
                                    type: 'iframe',
                                    attrs: { src: src }
                                }).run();
                            }
                        }
                    }
                });
            }},
            { icon: TIPTAP_ICONS.divider, title: 'Divider', action: () => editor.chain().focus().setHorizontalRule().run() }
        );

        return [headingAndBlocks, mediaAndTable].filter(group => group.length);
    }

    renderMenus() {
        const bubbleGroups = this.getBubbleGroups();
        bubbleGroups.forEach(g => this.renderGroup(g, this.bubbleMenuEl));

        const floatingGroups = this.getFloatingGroups();
        floatingGroups.forEach(g => this.renderGroup(g, this.floatingMenuEl));
    }

    update() {
        this.toolbarButtons.forEach(btn => btn.update && btn.update());
        if (this.textColorInput && this.editor) {
            let color = this.editor.getAttributes('textStyle').color;
            if (!color) {
                try {
                    const { from } = this.editor.state.selection;
                    const node = this.editor.view.domAtPos(from).node;
                    const element = node.nodeType === 3 ? node.parentElement : node;
                    color = window.getComputedStyle(element).color;
                } catch (e) {}
            }
            if (color) this.textColorInput.value = this.rgbToHex(color);
        }
    }

    positionFloatingMenu() {
        if (!this.editor || !this.floatingMenuEl.classList.contains('is-visible')) return;
        
        const virtualEl = this.createVirtualCursorElement();
        if (!virtualEl) return;

        try {
            const { computePosition, flip, shift, offset } = window.CliponTiptap;
            
            computePosition(virtualEl, this.floatingMenuEl, {
                strategy: 'fixed',
                placement: 'right-start',
                middleware: [
                    offset({ mainAxis: 10, crossAxis: -20 }),
                    flip(),
                    shift({ padding: 5 })
                ],
            }).then(({ x, y }) => {
                Object.assign(this.floatingMenuEl.style, {
                    left: `${x}px`,
                    top: `${y}px`,
                    position: 'fixed'
                });
            });
        } catch (e) {}
    }

    destroy() {
        this.removeAllEventListeners();
        if (this.cleanupFloatingMenuAutoUpdate) {
            this.cleanupFloatingMenuAutoUpdate();
            this.cleanupFloatingMenuAutoUpdate = null;
        }
        this.bubbleMenuEl.remove();
        this.floatingMenuEl.remove();
        if (this.inputPopup) this.inputPopup.destroy();
    }
}