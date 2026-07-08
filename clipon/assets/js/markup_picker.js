(function() {
    const config = window.CLIPON_MARKUP_PICKER_CONFIG || {};
    const labels = config.labels || {};
    const blogLabels = config.blogLabels || {};
    const blogTags = Array.isArray(config.blogTags) ? config.blogTags : [];
    const csrfToken = config.csrfToken || '';
    const allTags = Array.isArray(config.tags) ? config.tags : ['h1', 'h2', 'h3', 'p', 'img', 'a', 'span', 'li'];
    const defaultTags = Array.isArray(config.defaultTags) ? config.defaultTags : ['h1', 'h2', 'h3', 'p', 'img', 'a'];
    const defaultExclude = Array.isArray(config.exclude) ? config.exclude : ['header', 'footer', 'nav', 'aside'];

    function resolveScenario(mode) {
        if (mode === 'blog-list') return 'blog-list';
        if (mode === 'blog-post') return 'blog-post';
        return 'page-content';
    }

    function sanitizeId(value) {
        return String(value || 'page').replace(/[^a-zA-Z0-9_-]+/g, '_') || 'page';
    }

    const state = {
        scenario: config.scenario || resolveScenario(config.mode || 'manual'),
        mode: config.initialPanel || config.mode || 'manual',
        pageMode: ['auto', 'manual'].includes(config.initialPanel || config.mode) ? (config.initialPanel || config.mode) : 'manual',
        interactionMode: false,
        activeBlogTool: null,
        perPage: 6,
        pageParam: '',
        selectedBlogTags: [],
        ancestorCandidate: null,
        blogLists: [],
        activeBlogListId: '',
        blogListCounter: 0,
        lastHighlighted: null,
        idCounter: 0,
    };

    const slug = sanitizeId(config.slug || 'page');

    function createBlogListState(seed = {}) {
        const numericIndex = parseInt(seed.index, 10);
        if (numericIndex > state.blogListCounter) state.blogListCounter = numericIndex - 1;
        state.blogListCounter++;
        const id = seed.id || `blog_${state.blogListCounter}`;
        const attrs = seed.attrs && typeof seed.attrs === 'object' ? seed.attrs : {};
        const pageParam = seed.pageParam !== undefined
            ? seed.pageParam
            : (attrs.page_param !== undefined ? attrs.page_param : (state.blogListCounter === 1 ? `${slug}_blog_page` : `${slug}_${id}_page`));
        const tags = Array.isArray(seed.selectedBlogTags)
            ? seed.selectedBlogTags
            : (typeof attrs.tags === 'string' && attrs.tags.indexOf('__CLIPON_PHP_BLOCK_') === -1 ? attrs.tags.split(',') : []);
        return {
            id,
            index: numericIndex || state.blogListCounter,
            name: seed.name || `${blogLabels.list_default_name || 'List'} ${numericIndex || state.blogListCounter}`,
            perPage: parseInt(seed.perPage !== undefined ? seed.perPage : (attrs.per_page !== undefined ? attrs.per_page : 6), 10) || 6,
            pageParam: String(pageParam || ''),
            selectedBlogTags: utils.sanitizeCsvTokens(tags),
            originalAttrs: Object.assign({}, attrs),
            originalAttrsRaw: seed.attrsRaw || '',
            dirty: seed.dirty !== undefined ? !!seed.dirty : true,
            touched: {
                perPage: !!seed.touchedPerPage,
                pageParam: !!seed.touchedPageParam,
                tags: !!seed.touchedTags,
            },
        };
    }

    const listTools = [
        ['container', blogLabels.container || 'List container'],
        ['card', blogLabels.card || 'Post card'],
        ['title', blogLabels.field_title || 'Title'],
        ['image', blogLabels.field_image || 'Image'],
        ['excerpt', blogLabels.field_excerpt || 'Excerpt'],
        ['date', blogLabels.field_date || 'Date'],
        ['author', blogLabels.field_author || 'Author'],
        ['tags', blogLabels.field_tags || 'Tags'],
        ['link', blogLabels.field_link || 'Link'],
        ['pagination', blogLabels.field_pagination || 'Pagination'],
    ];
    const postTools = [
        ['title', blogLabels.field_title || 'Title'],
        ['content', blogLabels.field_content || 'Content'],
        ['thumbnail', blogLabels.field_thumbnail || 'Thumbnail'],
        ['date', blogLabels.field_date || 'Date'],
        ['author', blogLabels.field_author || 'Author'],
        ['tags', blogLabels.field_tags || 'Tags'],
    ];

    function t(key, fallback) {
        return labels[key] || fallback;
    }

    const utils = (function() {
        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[ch]));
        }

        function encodeBase64Utf8(value) {
            return btoa(unescape(encodeURIComponent(String(value))));
        }

        function decodeBase64Utf8(value) {
            try {
                return decodeURIComponent(escape(atob(String(value || ''))));
            } catch (err) {
                return '';
            }
        }

        function decodeBase64Json(value) {
            const json = decodeBase64Utf8(value);
            if (!json) return {};
            try {
                const parsed = JSON.parse(json);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (err) {
                return {};
            }
        }

        function escapeAttr(value) {
            return escapeHtml(value).replace(/`/g, '&#096;');
        }

        function sanitizeAttrToken(value) {
            return String(value || '').trim().replace(/[^a-zA-Z0-9_-]+/g, '_').replace(/^_+|_+$/g, '');
        }

        function sanitizeCsvTokens(values) {
            return values.map(sanitizeAttrToken).filter(Boolean).filter((value, index, list) => list.indexOf(value) === index);
        }

        function addClass(el, className) {
            if (el && !el.classList.contains(className)) el.classList.add(className);
        }

        function removeClass(el, className) {
            if (el) el.classList.remove(className);
        }

        function ensureId(el, tagName) {
            if (el.id) return el.id;
            const tag = sanitizeId(tagName || el.tagName.toLowerCase());
            let id;
            do {
                id = `${slug}_${tag}_${state.idCounter++}`;
            } while (document.getElementById(id));
            el.id = id;
            return id;
        }

        function isPickerNode(el) {
            return !!(el && (el.closest('#clipon-markup-picker-bar') || el.closest('#clipon-markup-ancestor-picker') || el.id === 'clipon-markup-picker-style'));
        }

        function clearHighlight() {
            if (state.lastHighlighted) {
                removeClass(state.lastHighlighted, 'clipon-markup-highlight');
                state.lastHighlighted = null;
            }
        }

        function describeElement(el) {
            if (!el || !el.tagName) return '';
            let label = el.tagName.toLowerCase();
            if (el.id) label += `#${el.id}`;
            const classes = Array.from(el.classList || []).filter((className) => className.indexOf('clipon-markup-') !== 0).slice(0, 3);
            if (classes.length) label += `.${classes.join('.')}`;
            return label;
        }

        return {
            escapeHtml,
            escapeAttr,
            encodeBase64Utf8,
            decodeBase64Utf8,
            decodeBase64Json,
            sanitizeAttrToken,
            sanitizeCsvTokens,
            addClass,
            removeClass,
            ensureId,
            isPickerNode,
            clearHighlight,
            describeElement,
        };
    })();

    const ui = (function() {
        function setStatus(message) {
            const status = document.getElementById('clipon-markup-picker-status');
            if (status) status.textContent = message;
        }

        function createStyle() {
            if (document.getElementById('clipon-markup-picker-style')) return;
            const href = config.styleUrl || './assets/css/markup_picker.css';
            const link = document.createElement('link');
            link.id = 'clipon-markup-picker-style';
            link.rel = 'stylesheet';
            link.href = href;
            (document.head || document.documentElement).appendChild(link);
        }

        function createBar() {
            let bar = document.getElementById('clipon-markup-picker-bar');
            if (bar) return bar;
            bar = document.createElement('div');
            bar.id = 'clipon-markup-picker-bar';
            const blogTagOptions = blogTags.map((tag) => {
                const id = utils.escapeAttr(tag.id || '');
                const label = utils.escapeHtml(tag.label || tag.id || '');
                return `<label><input type="checkbox" data-blog-filter-tag value="${id}"> ${label}</label>`;
            }).join('') || `<div class="tag-empty">${utils.escapeHtml(blogLabels.all_tags || 'All tags')}</div>`;
            bar.innerHTML = `
                <div class="clipon-markup-topbar">
                    <div class="clipon-markup-brand">
                        <button class="btn clipon-markup-sidebar-toggle" type="button" id="clipon-markup-sidebar-toggle" aria-expanded="true" title="${utils.escapeAttr(blogLabels.collapse_sidebar || 'Collapse sidebar')}">‹</button>
                        <div class="clipon-markup-brand-text">
                            <div class="title">${utils.escapeHtml(t('title', 'Clipon Markup Editor'))}</div>
                            <div id="clipon-markup-picker-status">${utils.escapeHtml(t('manual_hint', 'Click elements to toggle editable markers.'))}</div>
                        </div>
                    </div>
                    <div class="clipon-markup-scenarios">
                        <button class="btn" type="button" data-scenario="page-content">${utils.escapeHtml(t('scenario_page_content', 'Page Content'))}</button>
                        <button class="btn" type="button" data-scenario="blog-list">${utils.escapeHtml(t('scenario_blog_list', 'Blog List'))}</button>
                        <button class="btn" type="button" data-scenario="blog-post">${utils.escapeHtml(t('scenario_blog_post', 'Blog Post Template'))}</button>
                    </div>
                    <div class="clipon-markup-actions">
                        <button class="btn" type="button" id="clipon-markup-interact">${utils.escapeHtml(t('interact', 'Interact with page'))}</button>
                        <button class="btn btn-success" type="button" id="clipon-markup-save">${utils.escapeHtml(t('save', 'Save Changes'))}</button>
                        <button class="btn btn-cancel" type="button" id="clipon-markup-close">${utils.escapeHtml(t('cancel', 'Cancel'))}</button>
                    </div>
                </div>
                <aside class="clipon-markup-sidebar">
                    <section class="clipon-markup-panel" data-scenario-panel="page-content">
                        <div class="tool-label">${utils.escapeHtml(t('page_content_tools', 'Page content tools'))}</div>
                        <div class="clipon-markup-segment">
                            <button class="btn" type="button" data-page-mode="auto">${utils.escapeHtml(t('auto', 'Auto'))}</button>
                            <button class="btn" type="button" data-page-mode="manual">${utils.escapeHtml(t('manual', 'Manual'))}</button>
                        </div>
                        <div class="tool-group" data-panel="auto">
                            <div class="clipon-markup-section-title">${utils.escapeHtml(t('tags', 'Tags'))}</div>
                            <div class="clipon-markup-check-grid">
                                ${allTags.map((tag) => `<label><input type="checkbox" data-auto-tag value="${utils.escapeHtml(tag)}" ${defaultTags.includes(tag) ? 'checked' : ''}> &lt;${utils.escapeHtml(tag)}&gt;</label>`).join('')}
                            </div>
                            <div class="clipon-markup-section-title">${utils.escapeHtml(t('exclude', 'Exclude'))}</div>
                            <div class="clipon-markup-check-grid">
                                ${defaultExclude.map((item) => `<label><input type="checkbox" data-auto-exclude value="${utils.escapeHtml(item)}" checked> ${utils.escapeHtml(item)}</label>`).join('')}
                            </div>
                            <button class="btn btn-success" type="button" id="clipon-markup-apply-auto">${utils.escapeHtml(t('apply_auto', 'Apply auto rules'))}</button>
                        </div>
                        <div class="tool-group" data-panel="manual">${utils.escapeHtml(t('manual_hint', 'Click elements to toggle editable markers.'))}</div>
                        ${config.scope === 'template' ? `<button class="btn btn-secondary" type="button" id="clipon-markup-import-template-content">${utils.escapeHtml(t('import_template_content', 'Імпортувати контент з шаблону'))}</button>` : ''}
                    </section>
                    <section class="clipon-markup-panel" data-scenario-panel="blog-list" data-panel="blog-list">
                        <div class="tool-label">${utils.escapeHtml(t('blog_list_tools', 'Blog list tools'))}</div>
                        <label>${utils.escapeHtml(blogLabels.blog_lists || 'Blog lists')} <select id="clipon-markup-blog-list-select"></select></label>
                        <div class="clipon-markup-list-actions">
                            <button class="btn" type="button" id="clipon-markup-blog-list-add">${utils.escapeHtml(blogLabels.add_list || 'Add list')}</button>
                            <button class="btn" type="button" id="clipon-markup-blog-list-rename">${utils.escapeHtml(blogLabels.rename_list || 'Rename')}</button>
                            <button class="btn" type="button" id="clipon-markup-blog-list-remove">${utils.escapeHtml(blogLabels.remove_list || 'Remove')}</button>
                        </div>
                        <label>${utils.escapeHtml(blogLabels.per_page || 'Per page')} <input id="clipon-markup-per-page" type="number" min="1" max="50" step="1" value="6"></label>
                        <label>
                            <span class="clipon-markup-label-row">
                                ${utils.escapeHtml(blogLabels.page_param || 'Page param')}
                                <span class="clipon-markup-help" tabindex="0" data-tooltip-placement="right" data-tooltip="${utils.escapeAttr(blogLabels.page_param_help || 'Unique URL query parameter used by this list pagination, so multiple blog lists on one page do not share the same page number.')}">?</span>
                            </span>
                            <input id="clipon-markup-page-param" type="text" value="${utils.escapeAttr(state.pageParam)}">
                        </label>
                        <details>
                            <summary class="btn" id="clipon-markup-tags-summary">${utils.escapeHtml(blogLabels.filter_tags || 'Filter tags')}: ${utils.escapeHtml(blogLabels.all_tags || 'All tags')}</summary>
                            <div class="tag-menu">
                                ${blogTagOptions}
                            </div>
                        </details>
                        <div class="clipon-markup-section-title">${utils.escapeHtml(blogLabels.select_tool || 'Choose a tool, then click an element.')}</div>
                        <div class="clipon-markup-tool-list">
                            ${listTools.map((tool) => `
                                <button class="btn clipon-markup-tool-row" type="button" data-blog-tool="${utils.escapeHtml(tool[0])}">
                                    <span class="clipon-markup-tool-status" data-tool-status="${utils.escapeHtml(tool[0])}"></span>
                                    <span class="clipon-markup-tool-main">
                                        <span class="clipon-markup-tool-name">${utils.escapeHtml(tool[1])}</span>
                                        <span class="clipon-markup-tool-target" data-tool-target="${utils.escapeHtml(tool[0])}">${utils.escapeHtml(blogLabels.not_selected || 'Not selected')}</span>
                                    </span>
                                </button>
                            `).join('')}
                        </div>
                    </section>
                    <section class="clipon-markup-panel" data-scenario-panel="blog-post" data-panel="blog-post">
                        <div class="tool-label">${utils.escapeHtml(t('blog_post_tools', 'Blog post template tools'))}</div>
                        <div class="clipon-markup-tool-list">
                            ${postTools.map((tool) => `
                                <button class="btn clipon-markup-tool-row" type="button" data-blog-tool="${utils.escapeHtml(tool[0])}">
                                    <span class="clipon-markup-tool-status" data-post-tool-status="${utils.escapeHtml(tool[0])}"></span>
                                    <span class="clipon-markup-tool-main">
                                        <span class="clipon-markup-tool-name">${utils.escapeHtml(tool[1])}</span>
                                        <span class="clipon-markup-tool-target" data-post-tool-target="${utils.escapeHtml(tool[0])}">${utils.escapeHtml(blogLabels.not_selected || 'Not selected')}</span>
                                    </span>
                                </button>
                            `).join('')}
                        </div>
                    </section>
                </aside>
            `;
            document.body.appendChild(bar);
            if (!document.body.hasAttribute('data-clipon-original-padding-top')) {
                document.body.setAttribute('data-clipon-original-padding-top', document.body.style.paddingTop || '');
            }
            if (!document.body.hasAttribute('data-clipon-original-padding-left')) {
                document.body.setAttribute('data-clipon-original-padding-left', document.body.style.paddingLeft || '');
            }
            document.body.style.paddingTop = '64px';
            document.body.style.paddingLeft = '320px';
            return bar;
        }

        function setSidebarCollapsed(collapsed) {
            const bar = document.getElementById('clipon-markup-picker-bar');
            const toggle = document.getElementById('clipon-markup-sidebar-toggle');
            if (!bar) return;

            bar.classList.toggle('is-sidebar-collapsed', collapsed);
            document.body.style.paddingLeft = collapsed ? '0px' : '320px';

            if (toggle) {
                toggle.textContent = collapsed ? '›' : '‹';
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('title', collapsed
                    ? (blogLabels.expand_sidebar || 'Expand sidebar')
                    : (blogLabels.collapse_sidebar || 'Collapse sidebar'));
            }
        }

        function toggleSidebar() {
            const bar = document.getElementById('clipon-markup-picker-bar');
            setSidebarCollapsed(!(bar && bar.classList.contains('is-sidebar-collapsed')));
        }

        function initTooltips() {
            const state = { element: null, activeTarget: null };

            function tooltipElement() {
                if (state.element && state.element.isConnected) return state.element;
                const el = document.createElement('div');
                el.className = 'admin-tooltip clipon-markup-floating-tooltip';
                el.setAttribute('role', 'tooltip');
                el.setAttribute('aria-hidden', 'true');
                document.body.appendChild(el);
                state.element = el;
                return el;
            }

            function resolveTarget(startNode) {
                if (!startNode || !startNode.closest) return null;
                const target = startNode.closest('#clipon-markup-picker-bar [data-tooltip]');
                if (!target) return null;
                return String(target.getAttribute('data-tooltip') || '').trim() ? target : null;
            }

            function position() {
                const target = state.activeTarget;
                const tooltip = state.element;
                if (!target || !tooltip) return;

                const gap = 10;
                const viewportPadding = 8;
                const rect = target.getBoundingClientRect();
                tooltip.style.left = '0px';
                tooltip.style.top = '0px';

                const tipRect = tooltip.getBoundingClientRect();
                let placement = (target.getAttribute('data-tooltip-placement') || 'top').toLowerCase();
                if (!['top', 'bottom', 'right'].includes(placement)) placement = 'top';

                if (placement === 'right' && window.innerWidth - rect.right < tipRect.width + gap + viewportPadding) placement = 'top';
                if (placement === 'top' && rect.top < tipRect.height + gap + viewportPadding) placement = 'bottom';
                if (placement === 'bottom' && window.innerHeight - rect.bottom < tipRect.height + gap + viewportPadding) placement = 'top';

                let top;
                let left;
                if (placement === 'right') {
                    top = rect.top + (rect.height / 2) - (tipRect.height / 2);
                    left = rect.right + gap;
                } else {
                    top = placement === 'top' ? rect.top - tipRect.height - gap : rect.bottom + gap;
                    left = rect.left + (rect.width / 2) - (tipRect.width / 2);
                }

                left = Math.min(Math.max(left, viewportPadding), Math.max(viewportPadding, window.innerWidth - tipRect.width - viewportPadding));
                top = Math.min(Math.max(top, viewportPadding), Math.max(viewportPadding, window.innerHeight - tipRect.height - viewportPadding));

                tooltip.dataset.placement = placement;
                tooltip.style.left = `${Math.round(left)}px`;
                tooltip.style.top = `${Math.round(top)}px`;
            }

            function show(target) {
                const tooltip = tooltipElement();
                tooltip.textContent = String(target.getAttribute('data-tooltip') || '').trim();
                tooltip.classList.add('is-visible');
                tooltip.setAttribute('aria-hidden', 'false');
                state.activeTarget = target;
                position();
            }

            function hide() {
                if (!state.element) return;
                state.element.classList.remove('is-visible');
                state.element.setAttribute('aria-hidden', 'true');
                state.activeTarget = null;
            }

            document.addEventListener('mouseover', (event) => {
                const target = resolveTarget(event.target);
                if (target) show(target);
            });
            document.addEventListener('focusin', (event) => {
                const target = resolveTarget(event.target);
                if (target) show(target);
            });
            document.addEventListener('mouseout', (event) => {
                if (state.activeTarget && (!event.relatedTarget || !state.activeTarget.contains(event.relatedTarget))) hide();
            });
            document.addEventListener('focusout', (event) => {
                if (state.activeTarget && state.activeTarget === event.target) hide();
            });
            window.addEventListener('scroll', position, true);
            window.addEventListener('resize', position);
        }

        function setMode(mode) {
            state.mode = mode;
            if (mode === 'auto' || mode === 'manual') state.pageMode = mode;
            state.interactionMode = false;
            state.activeBlogTool = null;
            utils.clearHighlight();
            document.querySelectorAll('[data-page-mode]').forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-page-mode') === mode);
            });
            document.querySelectorAll('[data-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-panel') !== mode;
            });
            document.querySelectorAll('[data-blog-tool]').forEach((button) => button.classList.remove('active'));

            if (mode === 'auto') setStatus(t('auto_ready', 'Auto rules are ready.'));
            if (mode === 'manual') setStatus(t('manual_hint', 'Click elements to toggle editable markers.'));
            if (mode === 'blog-list' || mode === 'blog-post') setStatus(blogLabels.select_tool || 'Choose a tool, then click an element.');
        }

        function setScenario(scenario) {
            state.scenario = scenario;
            document.querySelectorAll('[data-scenario]').forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-scenario') === scenario);
            });
            document.querySelectorAll('[data-scenario-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-scenario-panel') !== scenario;
            });

            if (scenario === 'page-content') {
                setMode(state.pageMode || 'manual');
            } else {
                setMode(scenario);
            }
        }

        function statusForCurrentMode() {
            if (state.scenario !== 'page-content') {
                return blogLabels.select_tool || 'Choose a tool, then click an element.';
            }

            return state.mode === 'auto'
                ? t('auto_ready', 'Auto rules are ready.')
                : t('manual_hint', 'Click elements to toggle editable markers.');
        }

        return {
            createStyle,
            createBar,
            toggleSidebar,
            initTooltips,
            setStatus,
            setMode,
            setScenario,
            statusForCurrentMode,
        };
    })();

    const pageContent = (function() {
        function isExcluded(el) {
            const checked = Array.from(document.querySelectorAll('[data-auto-exclude]:checked')).map((input) => input.value);
            return checked.some((selector) => !!el.closest(selector));
        }

        function isInsideShortcodeBlock(el) {
            if (!el || !document.body) return false;

            const marker = `clipon-probe-${Date.now()}-${Math.random().toString(36).slice(2)}`;
            const previous = el.getAttribute('data-clipon-shortcode-probe');
            el.setAttribute('data-clipon-shortcode-probe', marker);

            const html = document.body.innerHTML;
            const markerIndex = html.indexOf(`data-clipon-shortcode-probe="${marker}"`);

            if (previous === null) {
                el.removeAttribute('data-clipon-shortcode-probe');
            } else {
                el.setAttribute('data-clipon-shortcode-probe', previous);
            }

            if (markerIndex === -1) return false;

            return ['blog_loop', 'blog_pagination'].some((name) => {
                const openPattern = new RegExp(`\\[${name}(?:\\s[^\\]]*)?\\]`, 'gi');
                const closePattern = new RegExp(`\\[\\/${name}\\]`, 'gi');
                let lastOpen = -1;
                let lastClose = -1;
                let match;

                while ((match = openPattern.exec(html)) && match.index < markerIndex) {
                    lastOpen = match.index;
                }
                while ((match = closePattern.exec(html)) && match.index < markerIndex) {
                    lastClose = match.index;
                }

                return lastOpen > lastClose;
            });
        }

        function applyAutoRules() {
            let count = 0;
            const tags = Array.from(document.querySelectorAll('[data-auto-tag]:checked')).map((input) => input.value);
            tags.forEach((tagName) => {
                Array.from(document.getElementsByTagName(tagName)).forEach((el) => {
                    if (utils.isPickerNode(el) || isExcluded(el) || isInsideShortcodeBlock(el)) return;
                    utils.addClass(el, 'clipon');
                    utils.addClass(el, 'clipon-markup-selected');
                    utils.ensureId(el, tagName);
                    count++;
                });
            });
            ui.setStatus(t('auto_applied', 'Auto rules applied: {count} elements marked.').replace('{count}', count));
        }

        function toggleManualMarker(el) {
            el.classList.toggle('clipon');
            if (el.classList.contains('clipon')) {
                utils.ensureId(el, el.tagName.toLowerCase());
                utils.addClass(el, 'clipon-markup-selected');
                ui.setStatus(`${t('marked', 'Marked')} <${el.tagName.toLowerCase()}> #${el.id}`);
            } else {
                utils.removeClass(el, 'clipon-markup-selected');
                ui.setStatus(t('removed', 'Removed marker'));
            }
        }

        return {
            applyAutoRules,
            toggleManualMarker,
        };
    })();

    const blogList = (function() {
        function activeList() {
            return state.blogLists.find((list) => list.id === state.activeBlogListId) || state.blogLists[0];
        }

        function syncLegacyState(list) {
            state.perPage = list.perPage;
            state.pageParam = list.pageParam;
            state.selectedBlogTags = list.selectedBlogTags.slice();
        }

        function renderListSelector() {
            const select = document.getElementById('clipon-markup-blog-list-select');
            if (!select) return;
            select.innerHTML = state.blogLists.map((list) => (
                `<option value="${utils.escapeAttr(list.id)}"${list.id === state.activeBlogListId ? ' selected' : ''}>${utils.escapeHtml(list.name)}</option>`
            )).join('');

            const remove = document.getElementById('clipon-markup-blog-list-remove');
            if (remove) remove.disabled = state.blogLists.length <= 1;
        }

        function loadActiveControls() {
            const list = activeList();
            if (!list) return;
            syncLegacyState(list);
            renderListSelector();

            const perPage = document.getElementById('clipon-markup-per-page');
            if (perPage) perPage.value = list.perPage;
            const pageParam = document.getElementById('clipon-markup-page-param');
            if (pageParam) pageParam.value = list.pageParam;
            document.querySelectorAll('[data-blog-filter-tag]').forEach((input) => {
                input.checked = list.selectedBlogTags.includes(input.value);
            });
            updateTagsSummary();
            refreshToolStatus();
        }

        function setActiveList(id) {
            const list = state.blogLists.find((item) => item.id === id);
            if (!list) return;
            state.activeBlogListId = list.id;
            loadActiveControls();
            ui.setStatus(`${blogLabels.active_list || 'Active list'}: ${list.name}`);
        }

        function addList() {
            const list = createBlogListState({ touchedPerPage: true, touchedPageParam: true, touchedTags: true });
            state.blogLists.push(list);
            state.activeBlogListId = list.id;
            loadActiveControls();
        }

        function removeActiveList() {
            if (state.blogLists.length <= 1) return;
            const list = activeList();
            if (!list) return;

            document.querySelectorAll(`[data-clipon-blog-container="${list.id}"], [data-clipon-blog-card="${list.id}"], [data-clipon-blog-pagination="${list.id}"], [data-clipon-blog-list="${list.id}"]`).forEach((node) => {
                node.removeAttribute('data-clipon-blog-container');
                node.removeAttribute('data-clipon-blog-card');
                node.removeAttribute('data-clipon-blog-pagination');
                node.removeAttribute('data-clipon-blog-field');
                node.removeAttribute('data-clipon-blog-list');
                utils.removeClass(node, 'clipon-markup-blog-container');
                utils.removeClass(node, 'clipon-markup-blog-card');
                utils.removeClass(node, 'clipon-markup-blog-field');
                utils.removeClass(node, 'clipon-markup-blog-pagination');
            });

            state.blogLists = state.blogLists.filter((item) => item.id !== list.id);
            state.activeBlogListId = state.blogLists[0].id;
            loadActiveControls();
            refreshToolStatus();
        }

        function updatePerPage(value) {
            const list = activeList();
            if (!list) return;
            list.perPage = Math.max(1, Math.min(50, parseInt(value, 10) || 6));
            list.dirty = true;
            list.touched.perPage = true;
            syncLegacyState(list);
        }

        function updateListName(value) {
            const list = activeList();
            if (!list) return;
            const name = String(value || '').trim();
            list.name = name || `${blogLabels.list_default_name || 'List'} ${state.blogLists.indexOf(list) + 1}`;
            renderListSelector();
            loadActiveControls();
        }

        function renameActiveList() {
            const list = activeList();
            if (!list) return;
            const nextName = prompt(blogLabels.rename_list_prompt || 'List name', list.name);
            if (nextName === null) return;
            updateListName(nextName);
        }

        function updatePageParam(value) {
            const list = activeList();
            if (!list) return;
            list.pageParam = value;
            list.dirty = true;
            list.touched.pageParam = true;
            syncLegacyState(list);
        }

        function updateSelectedTags() {
            const list = activeList();
            if (!list) return;
            list.selectedBlogTags = Array.from(document.querySelectorAll('[data-blog-filter-tag]:checked')).map((tag) => tag.value);
            list.dirty = true;
            list.touched.tags = true;
            syncLegacyState(list);
            updateTagsSummary();
        }

        function updateTagsSummary() {
            const list = activeList();
            const tagSummary = document.getElementById('clipon-markup-tags-summary');
            if (!list || !tagSummary) return;
            const count = list.selectedBlogTags.length;
            tagSummary.textContent = count
                ? `${blogLabels.filter_tags || 'Filter tags'}: ${count}`
                : `${blogLabels.filter_tags || 'Filter tags'}: ${blogLabels.all_tags || 'All tags'}`;
        }

        function elementForTool(list, tool) {
            if (!list) return null;
            if (tool === 'container') return document.querySelector(`[data-clipon-blog-container="${list.id}"]`);
            if (tool === 'card') return document.querySelector(`[data-clipon-blog-card="${list.id}"]`);
            if (tool === 'pagination') return document.querySelector(`[data-clipon-blog-pagination="${list.id}"]`);
            return document.querySelector(`[data-clipon-blog-field="${tool}"][data-clipon-blog-list="${list.id}"]`);
        }

        function refreshToolStatus() {
            const list = activeList();
            if (!list) return;
            listTools.forEach((tool) => {
                const id = tool[0];
                const element = elementForTool(list, id);
                const row = document.querySelector(`[data-scenario-panel="blog-list"] [data-blog-tool="${id}"]`);
                const target = document.querySelector(`[data-tool-target="${id}"]`);
                const status = document.querySelector(`[data-tool-status="${id}"]`);
                if (row) row.classList.toggle('is-complete', !!element);
                if (target) target.textContent = element ? utils.describeElement(element) : (blogLabels.not_selected || 'Not selected');
                if (status) status.setAttribute('aria-label', element ? (blogLabels.selected || 'Selected') : (blogLabels.not_selected || 'Not selected'));
            });
        }

        function loopAttrs(list, includePaginationAttrs) {
            const perPage = Math.max(1, Math.min(50, parseInt(list.perPage, 10) || 6));
            const source = Object.assign({}, list.originalAttrs || {});
            source.per_page = String(perPage);
            const pageParam = utils.sanitizeAttrToken(list.pageParam);
            const selectedTags = utils.sanitizeCsvTokens(list.selectedBlogTags || []);

            if (list.touched.pageParam) {
                if (pageParam) source.page_param = pageParam;
                else delete source.page_param;
            }

            if (list.touched.tags) {
                if (selectedTags.length) source.tags = selectedTags.join(',');
                else delete source.tags;
            }

            if (includePaginationAttrs) source.pagination = 'none';
            else delete source.pagination;

            const orderedKeys = ['per_page', 'page_param', 'tags', 'pagination'];
            const keys = orderedKeys.concat(Object.keys(source).filter((key) => !orderedKeys.includes(key)));
            const attrs = [];
            keys.forEach((key) => {
                if (source[key] === undefined || source[key] === null || source[key] === '') return;
                const value = String(source[key]);
                if (/^\d+$/.test(value) && key === 'per_page') attrs.push(`${key}=${value}`);
                else attrs.push(`${key}="${utils.escapeAttr(value)}"`);
            });

            return attrs.join(' ');
        }

        function markElement(el, tool) {
            const list = activeList();
            if (!list) return;
            list.dirty = true;

            if (tool === 'container') {
                document.querySelectorAll(`[data-clipon-blog-container="${list.id}"]`).forEach((node) => {
                    node.removeAttribute('data-clipon-blog-container');
                    node.removeAttribute('data-clipon-blog-list');
                    utils.removeClass(node, 'clipon-markup-blog-container');
                });
                el.setAttribute('data-clipon-blog-container', list.id);
                el.setAttribute('data-clipon-blog-list', list.id);
                utils.addClass(el, 'clipon-markup-blog-container');
                refreshToolStatus();
                return;
            }

            if (tool === 'card') {
                document.querySelectorAll(`[data-clipon-blog-card="${list.id}"]`).forEach((node) => {
                    node.removeAttribute('data-clipon-blog-card');
                    node.removeAttribute('data-clipon-blog-list');
                    utils.removeClass(node, 'clipon-markup-blog-card');
                });
                el.setAttribute('data-clipon-blog-card', list.id);
                el.setAttribute('data-clipon-blog-list', list.id);
                utils.addClass(el, 'clipon-markup-blog-card');
                refreshToolStatus();
                return;
            }

            if (tool === 'pagination') {
                document.querySelectorAll(`[data-clipon-blog-pagination="${list.id}"]`).forEach((node) => {
                    node.removeAttribute('data-clipon-blog-pagination');
                    node.removeAttribute('data-clipon-blog-list');
                    utils.removeClass(node, 'clipon-markup-blog-pagination');
                });
                el.setAttribute('data-clipon-blog-pagination', list.id);
                el.setAttribute('data-clipon-blog-list', list.id);
                utils.addClass(el, 'clipon-markup-blog-pagination');
                refreshToolStatus();
                return;
            }

            document.querySelectorAll(`[data-clipon-blog-field="${tool}"][data-clipon-blog-list="${list.id}"]`).forEach((node) => {
                node.removeAttribute('data-clipon-blog-field');
                node.removeAttribute('data-clipon-blog-list');
                utils.removeClass(node, 'clipon-markup-blog-field');
            });
            el.setAttribute('data-clipon-blog-field', tool);
            el.setAttribute('data-clipon-blog-list', list.id);
            utils.addClass(el, 'clipon-markup-blog-field');
            refreshToolStatus();
        }

        function applyPlaceholder(field, element) {
            if (!element) return;
            const originalPlaceholderKey = utils.sanitizeAttrToken(element.getAttribute('data-clipon-blog-placeholder') || '');
            if (originalPlaceholderKey && field !== 'link') {
                element.innerHTML = `{{${originalPlaceholderKey}}}`;
                return;
            }
            if (field === 'image') {
                const img = element.tagName.toLowerCase() === 'img' ? element : element.querySelector('img');
                if (img) {
                    img.setAttribute('src', '{{thumbnail}}');
                    img.setAttribute('alt', '{{title}}');
                }
                return;
            }
            if (field === 'link') {
                const anchor = element.tagName.toLowerCase() === 'a' ? element : element.closest('a') || element.querySelector('a');
                if (anchor) anchor.setAttribute('href', '{{url}}');
                return;
            }
            const map = {
                title: '{{title}}',
                excerpt: '{{excerpt}}',
                date: '{{date}}',
                author: '{{author}}',
                tags: '{{tags}}',
            };
            if (map[field]) element.innerHTML = map[field];
        }

        function cleanNodeHtml(element) {
            const copy = element.cloneNode(true);
            if (copy.nodeType === Node.ELEMENT_NODE) {
                copy.removeAttribute('data-clipon-blog-container');
                copy.removeAttribute('data-clipon-blog-card');
                copy.removeAttribute('data-clipon-blog-field');
                copy.removeAttribute('data-clipon-blog-list');
                copy.removeAttribute('data-clipon-blog-post-field');
                copy.removeAttribute('data-clipon-blog-pagination');
                copy.removeAttribute('data-clipon-blog-placeholder');
            }
            cleanup.cleanBlogAttributes(copy);
            return copy.outerHTML;
        }

        function paginationTemplateHtml(element) {
            const preview = element.classList.contains('clipon-shortcode-preview-blog-pagination')
                ? element
                : element.closest('.clipon-shortcode-preview-blog-pagination');
            const copy = (preview ? preview : element).cloneNode(true);
            cleanup.cleanBlogAttributes(copy);

            if (preview) {
                copy.removeAttribute('data-clipon-shortcode-preview');
                copy.removeAttribute('data-clipon-shortcode-type');
                copy.removeAttribute('data-clipon-shortcode-attrs');
                copy.removeAttribute('data-clipon-shortcode-index');
                copy.removeAttribute('data-clipon-shortcode-meta');
                copy.classList.remove('clipon-shortcode-preview', 'clipon-shortcode-preview-blog-pagination');
                if (!copy.getAttribute('class')) copy.removeAttribute('class');
                return copy.innerHTML;
            }

            return copy.outerHTML;
        }

        function buildSingleListHtml(clone, list) {
            const container = clone.querySelector(`[data-clipon-blog-container="${list.id}"]`);
            const card = clone.querySelector(`[data-clipon-blog-card="${list.id}"]`);
            if (!container || !card) {
                alert(blogLabels.need_container_card || 'Select a list container and post card before saving.');
                return null;
            }
            if (!container.contains(card)) {
                alert(blogLabels.need_container_card || 'Select a list container and post card before saving.');
                return null;
            }

            const preview = container.classList.contains('clipon-shortcode-preview-blog-loop')
                ? container
                : container.closest('.clipon-shortcode-preview-blog-loop');
            if (preview) {
                if (!list.dirty) return clone;

                if (!preview.contains(card)) {
                    alert(blogLabels.need_container_card || 'Select a list container and post card before saving.');
                    return null;
                }

                const title = card.querySelector(`[data-clipon-blog-field="title"][data-clipon-blog-list="${list.id}"]`);
                if (!title) {
                    alert(blogLabels.need_list_fields || 'Select a title field before saving.');
                    return null;
                }

                card.querySelectorAll(`[data-clipon-blog-field][data-clipon-blog-list="${list.id}"]`).forEach((element) => {
                    applyPlaceholder(element.getAttribute('data-clipon-blog-field'), element);
                });

                const pagination = clone.querySelector(`[data-clipon-blog-pagination="${list.id}"]`);
                const attrs = ' ' + loopAttrs(list, !!pagination);
                const cardHtml = cleanNodeHtml(card);
                preview.setAttribute('data-clipon-shortcode-preview', utils.encodeBase64Utf8(`\n[blog_loop${attrs}]\n${cardHtml}\n[/blog_loop]\n`));

                if (pagination) {
                    const paginationAttrs = loopAttrs(list, false);
                    const paginationShortcode = `\n[blog_pagination ${paginationAttrs}]\n${paginationTemplateHtml(pagination)}\n[/blog_pagination]\n`;
                    const paginationPreview = pagination.classList.contains('clipon-shortcode-preview-blog-pagination')
                        ? pagination
                        : pagination.closest('.clipon-shortcode-preview-blog-pagination');

                    if (paginationPreview) {
                        paginationPreview.setAttribute('data-clipon-shortcode-preview', utils.encodeBase64Utf8(paginationShortcode));
                        cleanup.cleanBlogAttributes(paginationPreview);
                    } else if (preview.contains(pagination)) {
                        pagination.remove();
                        preview.insertAdjacentHTML('afterend', paginationShortcode);
                    } else {
                        pagination.outerHTML = paginationShortcode;
                    }
                }

                cleanup.cleanBlogAttributes(preview);
                return clone;
            }

            const title = clone.querySelector(`[data-clipon-blog-field="title"][data-clipon-blog-list="${list.id}"]`);
            if (!title) {
                alert(blogLabels.need_list_fields || 'Select a title field before saving.');
                return null;
            }
            if (!card.contains(title)) {
                alert(blogLabels.field_card_warning || 'Select fields inside the selected post card.');
                return null;
            }

            const misplacedField = Array.from(clone.querySelectorAll(`[data-clipon-blog-field][data-clipon-blog-list="${list.id}"]`)).find((element) => {
                const field = element.getAttribute('data-clipon-blog-field');
                if (field === 'link') return !card.contains(element) && !element.contains(card);
                return !card.contains(element);
            });
            if (misplacedField) {
                alert(blogLabels.field_card_warning || 'Select fields inside the selected post card.');
                return null;
            }

            clone.querySelectorAll(`[data-clipon-blog-field][data-clipon-blog-list="${list.id}"]`).forEach((element) => {
                applyPlaceholder(element.getAttribute('data-clipon-blog-field'), element);
            });

            const pagination = clone.querySelector(`[data-clipon-blog-pagination="${list.id}"]`);
            const attrs = loopAttrs(list, !!pagination);
            const paginationAttrs = loopAttrs(list, false);
            let paginationShortcode = '';
            let paginationInsideContainer = false;
            if (pagination) {
                paginationInsideContainer = container.contains(pagination);
                cleanup.cleanBlogAttributes(pagination);
                paginationShortcode = `\n[blog_pagination ${paginationAttrs}]\n${pagination.outerHTML}\n[/blog_pagination]\n`;
                if (paginationInsideContainer) pagination.remove();
                else pagination.outerHTML = paginationShortcode;
            }

            const cardHtml = card.outerHTML;
            if (pagination) {
                container.innerHTML = `\n[blog_loop ${attrs}]\n${cardHtml}\n[/blog_loop]\n`;
                if (paginationInsideContainer) container.innerHTML += paginationShortcode;
            } else {
                container.innerHTML = `\n[blog_loop ${attrs}]\n${cardHtml}\n[/blog_loop]\n`;
            }
            cleanup.cleanBlogAttributes(container);
            return clone;
        }

        function markedLists(clone) {
            return state.blogLists.filter((list) => (
                clone.querySelector(`[data-clipon-blog-container="${list.id}"]`) ||
                clone.querySelector(`[data-clipon-blog-card="${list.id}"]`) ||
                clone.querySelector(`[data-clipon-blog-field][data-clipon-blog-list="${list.id}"]`) ||
                clone.querySelector(`[data-clipon-blog-pagination="${list.id}"]`)
            ));
        }

        function buildHtml(clone) {
            const lists = markedLists(clone);
            if (!lists.length) {
                alert(blogLabels.need_container_card || 'Select a list container and post card before saving.');
                return null;
            }

            for (const list of lists) {
                if (!buildSingleListHtml(clone, list)) return null;
            }

            return clone;
        }

        return {
            addList,
            removeActiveList,
            setActiveList,
            updatePerPage,
            updateListName,
            renameActiveList,
            updatePageParam,
            updateSelectedTags,
            loadActiveControls,
            refreshToolStatus,
            markElement,
            buildHtml,
        };
    })();

    const blogPost = (function() {
        function elementForTool(tool) {
            return document.querySelector(`[data-clipon-blog-post-field="${tool}"]`);
        }

        function refreshToolStatus() {
            postTools.forEach((tool) => {
                const id = tool[0];
                const element = elementForTool(id);
                const row = document.querySelector(`[data-scenario-panel="blog-post"] [data-blog-tool="${id}"]`);
                const target = document.querySelector(`[data-post-tool-target="${id}"]`);
                const status = document.querySelector(`[data-post-tool-status="${id}"]`);
                if (row) row.classList.toggle('is-complete', !!element);
                if (target) target.textContent = element ? utils.describeElement(element) : (blogLabels.not_selected || 'Not selected');
                if (status) status.setAttribute('aria-label', element ? (blogLabels.selected || 'Selected') : (blogLabels.not_selected || 'Not selected'));
            });
        }

        function markElement(el, tool) {
            document.querySelectorAll(`[data-clipon-blog-post-field="${tool}"]`).forEach((node) => {
                node.removeAttribute('data-clipon-blog-post-field');
                utils.removeClass(node, 'clipon-markup-blog-field');
            });
            el.setAttribute('data-clipon-blog-post-field', tool);
            utils.addClass(el, 'clipon-markup-blog-field');
            refreshToolStatus();
        }

        function buildHtml(clone) {
            const fields = clone.querySelectorAll('[data-clipon-blog-post-field]');
            if (!fields.length) {
                alert(blogLabels.need_post_field || 'Select at least one post template field before saving.');
                return null;
            }

            const normalizedFields = [];
            for (const element of Array.from(fields)) {
                const field = element.getAttribute('data-clipon-blog-post-field');
                if (field === 'thumbnail') {
                    const img = element.tagName.toLowerCase() === 'img' ? element : element.querySelector('img');
                    if (!img) {
                        alert(blogLabels.need_thumbnail_image || 'Select an image element for thumbnail.');
                        return null;
                    }
                    if (img !== element) {
                        element.removeAttribute('data-clipon-blog-post-field');
                        utils.removeClass(element, 'clipon-markup-blog-field');
                        img.setAttribute('data-clipon-blog-post-field', 'thumbnail');
                    }
                    normalizedFields.push(img);
                    continue;
                }
                normalizedFields.push(element);
            }

            normalizedFields.forEach((element) => {
                const field = element.getAttribute('data-clipon-blog-post-field');
                element.setAttribute('id', field);
                if (['title', 'content', 'thumbnail'].includes(field)) utils.addClass(element, 'clipon');
                else utils.removeClass(element, 'clipon');
            });
            cleanup.cleanBlogAttributes(clone);
            return clone;
        }

        return {
            refreshToolStatus,
            markElement,
            buildHtml,
        };
    })();

    const cleanup = (function() {
        function cleanClone(clone) {
            const bar = clone.querySelector('#clipon-markup-picker-bar');
            if (bar) bar.remove();
            const ancestorPicker = clone.querySelector('#clipon-markup-ancestor-picker');
            if (ancestorPicker) ancestorPicker.remove();
            const tooltip = clone.querySelector('.clipon-markup-floating-tooltip');
            if (tooltip) tooltip.remove();
            const style = clone.querySelector('#clipon-markup-picker-style');
            if (style) style.remove();
            const configTag = clone.querySelector('#clipon-markup-picker-config');
            if (configTag) configTag.remove();
            const base = clone.querySelector('base');
            if (base && base.getAttribute('href')) base.remove();
            const body = clone.querySelector('body');
            if (body) {
                const originalPaddingTop = body.getAttribute('data-clipon-original-padding-top');
                const originalPaddingLeft = body.getAttribute('data-clipon-original-padding-left');
                body.removeAttribute('cz-shortcut-listen');
                body.removeAttribute('data-new-gr-c-s-check-loaded');
                body.removeAttribute('data-gr-ext-installed');
                body.removeAttribute('data-clipon-original-padding-top');
                body.removeAttribute('data-clipon-original-padding-left');
                const styleAttr = body.getAttribute('style') || '';
                const parts = styleAttr.split(';').map((part) => part.trim()).filter((part) => part && !/^padding-top\s*:\s*64px$/i.test(part) && !/^padding-left\s*:\s*(?:320px|0px)$/i.test(part));
                if (originalPaddingTop !== null && originalPaddingTop !== '') {
                    const withoutPadding = parts.filter((part) => !/^padding-top\s*:/i.test(part));
                    withoutPadding.push(`padding-top: ${originalPaddingTop}`);
                    parts.splice(0, parts.length, ...withoutPadding);
                }
                if (originalPaddingLeft !== null && originalPaddingLeft !== '') {
                    const withoutPadding = parts.filter((part) => !/^padding-left\s*:/i.test(part));
                    withoutPadding.push(`padding-left: ${originalPaddingLeft}`);
                    parts.splice(0, parts.length, ...withoutPadding);
                }
                if (parts.length) body.setAttribute('style', parts.join('; '));
                else body.removeAttribute('style');
            }
            clone.querySelectorAll('script').forEach((script) => {
                if (script.src && script.src.includes('markup_picker.js')) script.remove();
            });
            clone.querySelectorAll('[class]').forEach((el) => {
                Array.from(el.classList).forEach((className) => {
                    if (className.indexOf('clipon-markup-') === 0) el.classList.remove(className);
                });
                if (!el.getAttribute('class')) el.removeAttribute('class');
            });
        }

        function cleanBlogAttributes(root) {
            root.querySelectorAll('[data-clipon-blog-container], [data-clipon-blog-card], [data-clipon-blog-field], [data-clipon-blog-list], [data-clipon-blog-post-field], [data-clipon-blog-pagination]').forEach((el) => {
                el.removeAttribute('data-clipon-blog-container');
                el.removeAttribute('data-clipon-blog-card');
                el.removeAttribute('data-clipon-blog-field');
                el.removeAttribute('data-clipon-blog-list');
                el.removeAttribute('data-clipon-blog-post-field');
                el.removeAttribute('data-clipon-blog-pagination');
                el.removeAttribute('data-clipon-blog-placeholder');
            });
        }

        return {
            cleanClone,
            cleanBlogAttributes,
        };
    })();

    const save = (function() {
        function buildHtmlForSave() {
            const clone = document.documentElement.cloneNode(true);
            cleanup.cleanClone(clone);

            let finalClone = clone;
            if (state.mode === 'blog-list') {
                finalClone = blogList.buildHtml(clone);
            } else if (state.mode === 'blog-post') {
                finalClone = blogPost.buildHtml(clone);
            } else {
                cleanup.cleanBlogAttributes(clone);
            }

            if (!finalClone) return null;
            cleanup.cleanBlogAttributes(finalClone);
            return '<!DOCTYPE html>\n' + finalClone.outerHTML;
        }

        function saveHtml(options = {}) {
            const silent = !!options.silent;
            const closeOnSuccess = options.closeOnSuccess !== false;
            const html = buildHtmlForSave();
            if (!html) return Promise.resolve(false);
            ui.setStatus(t('saving', 'Saving...'));

            const params = new URLSearchParams();
            params.append('html', html);
            params.append('action', 'save');
            params.append('csrf_token', csrfToken);

            return fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params,
            }).then((res) => res.json().catch(() => ({ ok: false, error: res.statusText })))
                .then((payload) => {
                    if (payload && payload.ok) {
                        if (!silent) alert(t('saved', 'Saved successfully!'));
                        if (closeOnSuccess) window.close();
                        return true;
                    } else {
                        alert(t('server_error', 'Server error:') + ' ' + ((payload && payload.error) || 'Unknown error'));
                        ui.setStatus('Error saving.');
                        return false;
                    }
                }).catch((err) => {
                    alert(t('request_error', 'Request error:') + ' ' + err);
                    ui.setStatus('Error saving.');
                    return false;
                });
        }

        function importTemplateContent() {
            ui.setStatus(t('saving', 'Saving...'));
            saveHtml({ silent: true, closeOnSuccess: false }).then((saved) => {
                if (!saved) return;

                const params = new URLSearchParams();
                params.append('action', 'import_template_content');
                params.append('csrf_token', csrfToken);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params,
                }).then((res) => res.json().catch(() => ({ ok: false, error: res.statusText })))
                    .then((payload) => {
                        if (!payload || !payload.ok) {
                            alert(t('server_error', 'Server error:') + ' ' + ((payload && payload.error) || 'Unknown error'));
                            ui.setStatus('Error importing content.');
                            return;
                        }

                        const primary = Number(payload.primary_added || 0);
                        const secondary = Number(payload.secondary_added || 0);
                        const pages = Number(payload.pages_updated || 0);
                        const kept = Number(payload.kept || 0);
                        let message = t('import_template_content_done', 'Імпорт завершено. Оновлено сторінок: {pages}. Додано ключів основної мови: {primary}. Додано ключів перекладів: {secondary}. Залишено без змін: {kept}.')
                            .replace('{pages}', String(pages))
                            .replace('{primary}', String(primary))
                            .replace('{secondary}', String(secondary))
                            .replace('{kept}', String(kept));
                        if (primary === 0 && secondary === 0) {
                            message = t('import_template_content_none', 'Нових ключів не додано. Існуючий контент залишено без змін.');
                        }
                        alert(message);
                        ui.setStatus(message);
                    }).catch((err) => {
                        alert(t('request_error', 'Request error:') + ' ' + err);
                        ui.setStatus('Error importing content.');
                    });
            });
        }

        return {
            saveHtml,
            importTemplateContent,
        };
    })();

    const selection = (function() {
        const ancestorTools = ['container', 'card', 'pagination'];

        function clearAncestorCandidate() {
            if (state.ancestorCandidate) {
                utils.removeClass(state.ancestorCandidate, 'clipon-markup-ancestor-candidate');
                state.ancestorCandidate = null;
            }
        }

        function closeAncestorPicker() {
            clearAncestorCandidate();
            const picker = document.getElementById('clipon-markup-ancestor-picker');
            if (picker) picker.remove();
        }

        function ancestorCandidates(el) {
            const candidates = [];
            let current = el;
            while (current && current.nodeType === Node.ELEMENT_NODE && current !== document.documentElement) {
                if (!utils.isPickerNode(current) && current !== document.body) {
                    candidates.push(current);
                }
                current = current.parentElement;
            }
            return candidates.slice(0, 10);
        }

        function setAncestorCandidate(el) {
            clearAncestorCandidate();
            state.ancestorCandidate = el;
            utils.addClass(el, 'clipon-markup-ancestor-candidate');
        }

        function placeAncestorPicker(picker, x, y) {
            document.body.appendChild(picker);
            const rect = picker.getBoundingClientRect();
            const left = Math.max(12, Math.min(x + 12, window.innerWidth - rect.width - 12));
            const top = Math.max(12, Math.min(y + 12, window.innerHeight - rect.height - 12));
            picker.style.left = `${left}px`;
            picker.style.top = `${top}px`;
        }

        function showAncestorPicker(el, tool, event) {
            closeAncestorPicker();
            const candidates = ancestorCandidates(el);
            if (!candidates.length) {
                blogList.markElement(el, tool);
                return;
            }

            const picker = document.createElement('div');
            picker.id = 'clipon-markup-ancestor-picker';
            picker.innerHTML = `
                <div class="ancestor-title">${utils.escapeHtml(blogLabels.choose_parent || 'Choose element')}</div>
                <div class="ancestor-list"></div>
            `;
            const list = picker.querySelector('.ancestor-list');

            candidates.forEach((candidate, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `ancestor-option${index === 0 ? ' active' : ''}`;
                button.textContent = index === 0
                    ? `${utils.describeElement(candidate)} (${blogLabels.clicked_element || 'clicked'})`
                    : `${'↑ '.repeat(Math.min(index, 4))}${utils.describeElement(candidate)}`;
                button.addEventListener('mouseenter', () => {
                    picker.querySelectorAll('.ancestor-option').forEach((item) => item.classList.remove('active'));
                    button.classList.add('active');
                    setAncestorCandidate(candidate);
                });
                button.addEventListener('click', () => {
                    closeAncestorPicker();
                    blogList.markElement(candidate, tool);
                });
                list.appendChild(button);
            });

            setAncestorCandidate(candidates[0]);
            placeAncestorPicker(picker, event.clientX, event.clientY);
            ui.setStatus(blogLabels.choose_parent || 'Choose element');
        }

        function setInteractionMode(enabled) {
            state.interactionMode = enabled;
            utils.clearHighlight();
            closeAncestorPicker();
            const button = document.getElementById('clipon-markup-interact');
            if (button) {
                button.classList.toggle('active', enabled);
                button.textContent = enabled ? t('return_picker', 'Return to editor') : t('interact', 'Interact with page');
            }
            ui.setStatus(enabled ? t('return_picker', 'Return to editor') : ui.statusForCurrentMode());
        }

        function selectBlogTool(tool) {
            state.activeBlogTool = tool;
            closeAncestorPicker();
            document.querySelectorAll(`[data-scenario-panel="${state.scenario}"] [data-blog-tool]`).forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-blog-tool') === tool);
            });
            ui.setStatus(blogLabels.select_tool || 'Choose a tool, then click an element.');
        }

        function markBlogElement(el, event) {
            if (!state.activeBlogTool) {
                ui.setStatus(blogLabels.select_tool || 'Choose a tool, then click an element.');
                return;
            }

            if (state.mode === 'blog-list') {
                if (ancestorTools.includes(state.activeBlogTool)) {
                    showAncestorPicker(el, state.activeBlogTool, event);
                    return;
                }
                blogList.markElement(el, state.activeBlogTool);
            } else if (state.mode === 'blog-post') {
                blogPost.markElement(el, state.activeBlogTool);
            }
        }

        function hydrateExistingMarkers() {
            const listsByIndex = {};
            document.querySelectorAll('.clipon-shortcode-preview-blog-loop').forEach((preview) => {
                const meta = utils.decodeBase64Json(preview.getAttribute('data-clipon-shortcode-meta') || '');
                const index = parseInt(meta.index || preview.getAttribute('data-clipon-shortcode-index') || '0', 10) || (Object.keys(listsByIndex).length + 1);
                if (!listsByIndex[index]) {
                    const attrs = meta.attrs && typeof meta.attrs === 'object' ? meta.attrs : {};
                    const list = createBlogListState({
                        id: `blog_${index}`,
                        index,
                        name: `${blogLabels.list_default_name || 'List'} ${index}`,
                        attrs,
                        attrsRaw: meta.attrs_raw || '',
                        dirty: false,
                    });
                    listsByIndex[index] = list;
                    state.blogLists.push(list);
                }
                const list = listsByIndex[index];
                preview.setAttribute('data-clipon-blog-container', list.id);
                preview.setAttribute('data-clipon-blog-list', list.id);
                preview.querySelectorAll('[data-clipon-blog-card]').forEach((el) => {
                    el.setAttribute('data-clipon-blog-card', list.id);
                    el.setAttribute('data-clipon-blog-list', list.id);
                });
                preview.querySelectorAll('[data-clipon-blog-field]').forEach((el) => {
                    el.setAttribute('data-clipon-blog-list', list.id);
                });
            });

            if (!state.blogLists.length) {
                state.blogLists.push(createBlogListState());
            }
            state.activeBlogListId = state.blogLists[0].id;
            state.perPage = state.blogLists[0].perPage;
            state.pageParam = state.blogLists[0].pageParam;
            state.selectedBlogTags = state.blogLists[0].selectedBlogTags.slice();

            document.querySelectorAll('.clipon').forEach((el) => {
                if (!utils.isPickerNode(el)) utils.addClass(el, 'clipon-markup-selected');
            });
            document.querySelectorAll('[data-clipon-blog-container]').forEach((el) => {
                if (!utils.isPickerNode(el)) utils.addClass(el, 'clipon-markup-blog-container');
            });
            document.querySelectorAll('[data-clipon-blog-card]').forEach((el) => {
                if (!utils.isPickerNode(el)) utils.addClass(el, 'clipon-markup-blog-card');
            });
            document.querySelectorAll('[data-clipon-blog-field], [data-clipon-blog-post-field]').forEach((el) => {
                if (!utils.isPickerNode(el)) utils.addClass(el, 'clipon-markup-blog-field');
            });
            document.querySelectorAll('[data-clipon-blog-pagination]').forEach((el) => {
                const preview = el.classList.contains('clipon-shortcode-preview-blog-pagination')
                    ? el
                    : el.closest('.clipon-shortcode-preview-blog-pagination');
                if (preview) {
                    const meta = utils.decodeBase64Json(preview.getAttribute('data-clipon-shortcode-meta') || '');
                    const index = parseInt(meta.index || preview.getAttribute('data-clipon-shortcode-index') || '0', 10);
                    const list = listsByIndex[index] || state.blogLists[0];
                    if (list) {
                        el.setAttribute('data-clipon-blog-pagination', list.id);
                        el.setAttribute('data-clipon-blog-list', list.id);
                    }
                }
                if (!utils.isPickerNode(el)) utils.addClass(el, 'clipon-markup-blog-pagination');
            });
        }

        function bindEvents() {
            document.querySelectorAll('[data-scenario]').forEach((button) => {
                button.addEventListener('click', () => ui.setScenario(button.getAttribute('data-scenario')));
            });
            document.querySelectorAll('[data-page-mode]').forEach((button) => {
                button.addEventListener('click', () => ui.setMode(button.getAttribute('data-page-mode')));
            });
            document.querySelectorAll('[data-blog-tool]').forEach((button) => {
                button.addEventListener('click', () => selectBlogTool(button.getAttribute('data-blog-tool')));
            });
            const applyAuto = document.getElementById('clipon-markup-apply-auto');
            if (applyAuto) applyAuto.addEventListener('click', pageContent.applyAutoRules);
            const interact = document.getElementById('clipon-markup-interact');
            if (interact) interact.addEventListener('click', () => setInteractionMode(!state.interactionMode));
            const sidebarToggle = document.getElementById('clipon-markup-sidebar-toggle');
            if (sidebarToggle) sidebarToggle.addEventListener('click', ui.toggleSidebar);
            const blogListSelect = document.getElementById('clipon-markup-blog-list-select');
            if (blogListSelect) blogListSelect.addEventListener('change', () => blogList.setActiveList(blogListSelect.value));
            const addBlogList = document.getElementById('clipon-markup-blog-list-add');
            if (addBlogList) addBlogList.addEventListener('click', blogList.addList);
            const removeBlogList = document.getElementById('clipon-markup-blog-list-remove');
            if (removeBlogList) removeBlogList.addEventListener('click', blogList.removeActiveList);
            const renameBlogList = document.getElementById('clipon-markup-blog-list-rename');
            if (renameBlogList) renameBlogList.addEventListener('click', blogList.renameActiveList);
            const perPage = document.getElementById('clipon-markup-per-page');
            if (perPage) perPage.addEventListener('input', () => blogList.updatePerPage(perPage.value));
            const pageParam = document.getElementById('clipon-markup-page-param');
            if (pageParam) pageParam.addEventListener('input', () => blogList.updatePageParam(pageParam.value));
            document.querySelectorAll('[data-blog-filter-tag]').forEach((input) => {
                input.addEventListener('change', blogList.updateSelectedTags);
            });
            const saveButton = document.getElementById('clipon-markup-save');
            if (saveButton) saveButton.addEventListener('click', save.saveHtml);
            const importTemplateContent = document.getElementById('clipon-markup-import-template-content');
            if (importTemplateContent) importTemplateContent.addEventListener('click', save.importTemplateContent);
            const close = document.getElementById('clipon-markup-close');
            if (close) close.addEventListener('click', () => {
                if (confirm(t('close_confirm', 'Close without saving?'))) window.close();
            });

            document.addEventListener('mouseover', (event) => {
                if (state.interactionMode || utils.isPickerNode(event.target)) return;
                utils.clearHighlight();
                utils.addClass(event.target, 'clipon-markup-highlight');
                state.lastHighlighted = event.target;
            }, true);

            document.addEventListener('click', (event) => {
                if (state.interactionMode || utils.isPickerNode(event.target)) return;
                if (!['manual', 'blog-list', 'blog-post'].includes(state.mode)) return;
                event.preventDefault();
                event.stopPropagation();

                const el = event.target;
                if (state.mode === 'manual') {
                    closeAncestorPicker();
                    pageContent.toggleManualMarker(el);
                } else {
                    markBlogElement(el, event);
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeAncestorPicker();
            });
        }

        return {
            hydrateExistingMarkers,
            bindEvents,
        };
    })();

    const app = (function() {
        function init() {
            if (!document.body) {
                setTimeout(init, 50);
                return;
            }
            ui.createStyle();
            ui.createBar();
            ui.initTooltips();
            selection.hydrateExistingMarkers();
            selection.bindEvents();
            blogList.loadActiveControls();
            blogList.refreshToolStatus();
            blogPost.refreshToolStatus();
            ui.setScenario(state.scenario);
        }

        return { init };
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', app.init);
    } else {
        app.init();
    }
})();
