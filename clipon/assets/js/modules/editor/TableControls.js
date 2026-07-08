import { TIPTAP_ICONS } from './icons.js';
import { appendSvgIcon } from './dom.js';

export class EditorTableControls {
    constructor(wrapper, floatingMenuEl) {
        this.wrapper = wrapper;
        this.floatingMenuEl = floatingMenuEl;
        this.editor = null;
        
        this.tableHandle = document.createElement('button');
        this.tableHandle.className = 'tiptap-table-handle';
        this.tableHandle.setAttribute('data-tiptap-ui', 'true');
        this.tableHandle.type = 'button';
        this.tableHandle.setAttribute('aria-label', 'Table options');
        this.tableHandle.setAttribute('aria-haspopup', 'true');
        this.tableHandle.setAttribute('aria-expanded', 'false');
        appendSvgIcon(this.tableHandle, '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M12 5v14" stroke-linecap="round" stroke-linejoin="round"/></svg>');
        
        this.cellToolbar = document.createElement('div');
        this.cellToolbar.className = 'tiptap-table-cell-toolbar';
        this.cellToolbar.setAttribute('data-tiptap-ui', 'true');
        this.cellToolbar.setAttribute('role', 'toolbar');
        this.cellToolbar.setAttribute('aria-label', 'Table cell formatting');
        
        this.tableGroupEl = document.createElement('div');
        this.tableGroupEl.className = 'tiptap-toolbar-group';
        this.tableGroupEl.style.display = 'none';
        
        this.handleHover = false;
        this.handleHideTimeout = null;
        this.cellToolbarOpen = false;
        this.cellToolbarCloseTimeout = null;
        this.selectedCellEl = null;
        
        this.tableButtons = [];
        this.cellButtons = [];
        this.eventListeners = [];
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

    init(editor, getHoveredBlockEl) {
        this.editor = editor;
        this.getHoveredBlockEl = getHoveredBlockEl;
        
        this.floatingMenuEl.appendChild(this.tableGroupEl);
        
        this.setupEvents();
        this.renderControls();
    }

    setupEvents() {
        this.addEventListener(this.tableHandle, 'mouseenter', () => {
            this.handleHover = true;
            if (this.handleHideTimeout) { clearTimeout(this.handleHideTimeout); this.handleHideTimeout = null; }
            this.stopCellToolbarCloseTimer();
        });
        
        this.addEventListener(this.tableHandle, 'mouseleave', () => {
            this.handleHover = false;
            if (this.handleHideTimeout) clearTimeout(this.handleHideTimeout);
            this.handleHideTimeout = setTimeout(() => { this.clearHandle(); }, 300);
            this.startCellToolbarCloseTimer();
        });

        this.addEventListener(this.cellToolbar, 'mouseenter', () => this.stopCellToolbarCloseTimer());
        this.addEventListener(this.cellToolbar, 'mouseleave', () => this.startCellToolbarCloseTimer());

        this.addEventListener(this.tableHandle, 'click', (e) => {
            e.stopPropagation();
            this.cellToolbarOpen = !this.cellToolbarOpen;
            this.updateCellToolbarVisibility();
            if (this.cellToolbarOpen) this.stopCellToolbarCloseTimer();
        });

        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.addEventListener(document, 'click', this.handleDocumentClick);
    }

    handleDocumentClick(e) {
        if (!this.tableHandle.contains(e.target) && !this.cellToolbar.contains(e.target) && !e.target.closest('td,th')) {
            this.cellToolbarOpen = false;
            this.updateCellToolbarVisibility();
        }
    }

    startCellToolbarCloseTimer() {
        if (this.cellToolbarCloseTimeout) clearTimeout(this.cellToolbarCloseTimeout);
        this.cellToolbarCloseTimeout = setTimeout(() => {
            if (!this.cellToolbarOpen) return;
            this.cellToolbarOpen = false;
            this.updateCellToolbarVisibility();
        }, 1000);
    }

    stopCellToolbarCloseTimer() {
        if (this.cellToolbarCloseTimeout) clearTimeout(this.cellToolbarCloseTimeout);
    }

    makeBtn(container, icon, title, action, canCheck, collection) {
        const btn = document.createElement('button');
        btn.className = 'tiptap-btn'; 
        if (!appendSvgIcon(btn, icon)) {
            btn.textContent = title || '';
        }
        btn.title = title; 
        btn.type = 'button';
        btn.setAttribute('aria-label', title);
        
        this.addEventListener(btn, 'click', (e) => { 
            e.preventDefault(); e.stopPropagation(); 
            action(); 
            setTimeout(() => {
                this.editor.commands.focus();
            }, 10);
            this.stopCellToolbarCloseTimer(); 
        });
        container.appendChild(btn);
        collection.push({ btn, canCheck });
        return { btn, canCheck };
    }

    renderControls() {
        this.makeBtn(this.tableGroupEl, TIPTAP_ICONS.clear, 'Delete table', () => this.editor.chain().focus().deleteTable().run(), () => this.editor.can().deleteTable(), this.tableButtons);

        this.mergeBtn = this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableMerge, 'Merge cells', () => this.editor.chain().focus().mergeCells().run(), () => this.editor.can().mergeCells(), this.cellButtons);
        this.splitBtn = this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableSplit, 'Split cell', () => this.editor.chain().focus().splitCell().run(), () => this.editor.can().splitCell(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableHdrRow, 'Toggle header row', () => this.editor.chain().focus().toggleHeaderRow().run(), () => this.editor.can().toggleHeaderRow(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableHdrCol, 'Toggle header column', () => this.editor.chain().focus().toggleHeaderColumn().run(), () => this.editor.can().toggleHeaderColumn(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableAddRow, 'Add row to end', () => this.appendRowToTable(), () => this.editor.can().addRowAfter(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableAddCol, 'Add column to end', () => this.appendColToTable(), () => this.editor.can().addColumnAfter(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableDelRow, 'Delete row', () => this.editor.chain().focus().deleteRow().run(), () => this.editor.can().deleteRow(), this.cellButtons);
        this.makeBtn(this.cellToolbar, TIPTAP_ICONS.tableDelCol, 'Delete column', () => this.editor.chain().focus().deleteColumn().run(), () => this.editor.can().deleteColumn(), this.cellButtons);
    }

    getLastCellInnerPosFromSelection() {
        try {
            const {$from} = this.editor.state.selection;
            for (let d = $from.depth; d > 0; d--) {
                const node = $from.node(d);
                if (node.type.name === 'table') {
                    const tableStart = $from.start(d);
                    let lastCell = null; let lastCellRelPos = null;
                    node.descendants((n, pos) => { if ((n.type.name === 'tableCell' || n.type.name === 'tableHeader')) { lastCell = n; lastCellRelPos = pos; } });
                    if (!lastCell) return null;
                    let innerRelPos = null;
                    lastCell.descendants((n, pos) => {
                        if (n.isTextblock && innerRelPos == null) innerRelPos = pos;
                    });
                    if (innerRelPos == null) return tableStart + lastCellRelPos + 1;
                    return tableStart + lastCellRelPos + innerRelPos + 1;
                }
            }
        } catch (e) { return null; }
        return null;
    }

    getLastCellInnerPosFromHovered() {
        try {
            const hoveredBlockEl = this.getHoveredBlockEl();
            if (!hoveredBlockEl) return null;
            const tableEl = hoveredBlockEl.closest && hoveredBlockEl.closest('table');
            if (!tableEl) return null;
            const cells = tableEl.querySelectorAll('td,th');
            if (!cells.length) return null;
            const last = cells[cells.length - 1];
            const rect = last.getBoundingClientRect();
            const posAt = this.editor.view.posAtCoords({ left: rect.left + 8, top: rect.top + rect.height/2 });
            if (posAt && typeof posAt.pos === 'number') {
                const $p = this.editor.state.doc.resolve(posAt.pos);
                const cellNode = (function() {
                    for (let d = $p.depth; d > 0; d--) {
                        const n = $p.node(d);
                        if (n.type.name === 'tableCell' || n.type.name === 'tableHeader') return { node: n, depth: d, start: $p.start(d) };
                    }
                    return null;
                })();
                if (cellNode) {
                    let innerRel = null;
                    cellNode.node.descendants((n, pos) => { if (n.isTextblock && innerRel == null) innerRel = pos; });
                    if (innerRel != null) return cellNode.start + innerRel + 1;
                }
                return posAt.pos;
            }
        } catch (e) {}
        return null;
    }

    appendRowToTable() {
        const pos = this.getLastCellInnerPosFromHovered() || this.getLastCellInnerPosFromSelection();
        if (pos != null) { this.editor.chain().focus().setTextSelection(pos).addRowAfter().run(); }
    }

    appendColToTable() {
        const pos = this.getLastCellInnerPosFromHovered() || this.getLastCellInnerPosFromSelection();
        if (pos != null) { this.editor.chain().focus().setTextSelection(pos).addColumnAfter().run(); }
    }

    update() {
        const hoveredBlockEl = this.getHoveredBlockEl();
        const inTableContext = this.editor.isActive('table') || (hoveredBlockEl && hoveredBlockEl.closest && hoveredBlockEl.closest('table'));
        this.tableGroupEl.style.display = inTableContext ? 'flex' : 'none';
        this.tableButtons.forEach(t => {
            try { t.btn.disabled = !(t.canCheck && t.canCheck()); } catch (e) { t.btn.disabled = false; }
        });
        this.updateCellToolbarVisibility();
    }

    updateCellToolbarVisibility() {
        if (!this.cellToolbarOpen) {
            this.cellToolbar.classList.remove('is-visible');
            return;
        }

        let cellEl = this.selectedCellEl;
        if (!cellEl) {
            try {
                const { from } = this.editor.state.selection;
                let node = this.editor.view.domAtPos(from).node;
                if (node.nodeType === 3) node = node.parentElement;
                cellEl = (node && node.closest) ? node.closest('td,th') : null;
            } catch (e) { cellEl = null; }
        }

        if (!cellEl) {
            this.cellToolbarOpen = false;
            this.cellToolbar.classList.remove('is-visible');
            return;
        }

        this.cellButtons.forEach(x => {
            try {
                const can = x.canCheck ? x.canCheck() : true;
                x.btn.disabled = !can;
                if (x === this.mergeBtn || x === this.splitBtn) {
                    x.btn.style.display = can ? 'inline-flex' : 'none';
                }
            } catch (e) { x.btn.disabled = false; }
        });

        this.cellToolbar.classList.add('is-visible');
        
        const { computePosition, flip, shift, offset } = window.CliponTiptap;
        
        computePosition(this.tableHandle, this.cellToolbar, {
            placement: 'bottom-start',
            strategy: 'fixed',
            middleware: [
                offset(6),
                flip(),
                shift({ padding: 10 })
            ],
        }).then(({ x, y }) => {
            Object.assign(this.cellToolbar.style, {
                left: `${x}px`,
                top: `${y}px`,
                position: 'fixed'
            });
        });
    }

    positionHandleForCell(cell) {
        if (!cell) return;
        try {
            this.tableHandle._targetCell = cell;
            this.tableHandle.classList.add('is-visible');
            
            const { computePosition, offset } = window.CliponTiptap;
            
            computePosition(cell, this.tableHandle, {
                placement: 'left-start',
                strategy: 'fixed',
                middleware: [
                    offset(10) // Distance from the cell
                ],
            }).then(({ x, y }) => {
                Object.assign(this.tableHandle.style, {
                    left: `${x}px`,
                    top: `${y}px`,
                    position: 'fixed'
                });
            });
        } catch (e) {}
    }

    clearHandle(force = false) {
        if (!force && (this.handleHover || this.selectedCellEl)) return;
        try { this.tableHandle.classList.remove('is-visible'); delete this.tableHandle._targetCell; } catch (e) {}
    }

    updateSelectedCellHandle() {
        try {
            const { from } = this.editor.state.selection;
            let node = this.editor.view.domAtPos(from).node;
            if (node.nodeType === 3) node = node.parentElement;
            const cell = node && node.closest ? node.closest('td,th') : null;
            if (cell) {
                this.selectedCellEl = cell;
                if (this.handleHideTimeout) { clearTimeout(this.handleHideTimeout); this.handleHideTimeout = null; }
                this.positionHandleForCell(cell);
            } else {
                this.selectedCellEl = null;
                this.clearHandle();
            }
        } catch (e) { this.selectedCellEl = null; this.clearHandle(); }
    }

    destroy() {
        this.removeAllEventListeners();
        this.tableHandle.remove();
        this.cellToolbar.remove();
        this.tableGroupEl.remove();
    }
}