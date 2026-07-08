export class EditorInputPopup {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.el = document.createElement('div');
        this.el.className = 'tiptap-input-popup';
        this.el.setAttribute('data-tiptap-ui', 'true');
        this.el.setAttribute('role', 'dialog');
        this.el.setAttribute('aria-label', 'Input popup');
        this.el.style.display = 'none';
        
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'tiptap-popup-input';
        this.input.setAttribute('aria-label', 'Input value');
        
        const actions = document.createElement('div');
        actions.className = 'tiptap-popup-actions';
        
        this.submitBtn = document.createElement('button');
        this.submitBtn.type = 'button';
        this.submitBtn.className = 'tiptap-popup-btn primary';
        this.submitBtn.textContent = 'Save';
        this.submitBtn.setAttribute('aria-label', 'Save');
        
        this.cancelBtn = document.createElement('button');
        this.cancelBtn.type = 'button';
        this.cancelBtn.className = 'tiptap-popup-btn';
        this.cancelBtn.textContent = 'Cancel';
        this.cancelBtn.setAttribute('aria-label', 'Cancel');
        
        actions.append(this.cancelBtn, this.submitBtn);
        this.el.append(this.input, actions);
        document.body.appendChild(this.el);
        
        this.onSubmit = null;
        this.onCancel = null;
        this.eventListeners = [];
        this.cleanupAutoUpdate = null;
        
        this.setupEvents();
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

    setupEvents() {
        this.addEventListener(this.submitBtn, 'click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.submit();
        });
        
        this.addEventListener(this.cancelBtn, 'click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.close();
        });
        
        this.addEventListener(this.input, 'keydown', (e) => {
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                this.submit(); 
            }
            if (e.key === 'Escape') { 
                e.preventDefault(); 
                this.close(); 
            }
        });

        // Prevent clicks inside popup from closing it
        this.addEventListener(this.el, 'mousedown', (e) => {
            e.stopPropagation();
        });
    }
    
    show(referenceEl, options) {
        this.input.placeholder = options.placeholder || '';
        this.input.value = options.defaultValue || '';
        this.onSubmit = options.onSubmit;
        this.onCancel = options.onCancel;
        
        this.el.style.display = 'flex';
        this.input.focus();
        
        if (this.cleanupAutoUpdate) this.cleanupAutoUpdate();
        const { computePosition, offset, flip, shift, autoUpdate } = window.CliponTiptap;
        
        this.cleanupAutoUpdate = autoUpdate(referenceEl, this.el, () => {
            computePosition(referenceEl, this.el, {
                strategy: 'fixed',
                placement: 'bottom',
                middleware: [
                    offset(8), 
                    flip(), 
                    shift({ padding: 8 })
                ]
            }).then(({ x, y }) => {
                Object.assign(this.el.style, { 
                    left: `${x}px`, 
                    top: `${y}px`,
                    position: 'fixed'
                });
            });
        }, { strategy: 'fixed' });
    }
    
    submit() {
        if (this.onSubmit) this.onSubmit(this.input.value);
        this.close();
    }
    
    close() {
        this.el.style.display = 'none';
        this.input.value = '';
        if (this.onCancel) this.onCancel();
        if (this.cleanupAutoUpdate) {
            this.cleanupAutoUpdate();
            this.cleanupAutoUpdate = null;
        }
    }
    
    destroy() {
        this.removeAllEventListeners();
        if (this.cleanupAutoUpdate) {
            this.cleanupAutoUpdate();
            this.cleanupAutoUpdate = null;
        }
        this.el.remove();
    }
}
