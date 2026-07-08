(function () {
    const config = window.PAGE_ADMIN_CONFIG || {};
    const PAGE_TREE = config.pageTree || {};
    const PAGES_API_URL = config.apiUrl || 'api/pages.php';
    const CSRF_TOKEN = config.csrfToken || window.CLIPON_CSRF_TOKEN || '';
    const LANG = config.lang || {};
    const ICONS = config.icons || {};

    function clearNode(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function appendIconMarkup(target, markup) {
        if (!target) return false;
        const raw = String(markup || '').trim();
        if (!raw) {
            clearNode(target);
            return true;
        }

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(raw, 'image/svg+xml');
            const svg = doc.documentElement;
            if (!svg || String(svg.nodeName).toLowerCase() !== 'svg') {
                return false;
            }

            clearNode(target);
            target.appendChild(document.importNode(svg, true));
            return true;
        } catch (e) {
            return false;
        }
    }

    function appendCheckIcon(target) {
        if (!target) return;
        const svgNs = 'http://www.w3.org/2000/svg';
        clearNode(target);

        const svg = document.createElementNS(svgNs, 'svg');
        svg.setAttribute('width', '14');
        svg.setAttribute('height', '14');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');

        const polyline = document.createElementNS(svgNs, 'polyline');
        polyline.setAttribute('points', '20 6 9 17 4 12');
        svg.appendChild(polyline);

        target.appendChild(svg);
    }

    function setStatusMessage(container, text, options) {
        if (!container) return;
        const tagName = (options && options.tag) || 'small';
        const styleText = (options && options.style) || '';

        clearNode(container);
        const node = document.createElement(tagName);
        if (styleText) {
            node.style.cssText = styleText;
        }
        node.textContent = String(text || '');
        container.appendChild(node);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJsSingleQuoteString(value) {
        return String(value == null ? '' : value)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'");
    }

    function postForm(formData, headers) {
        if (CSRF_TOKEN && !formData.has('csrf_token')) {
            formData.append('csrf_token', CSRF_TOKEN);
        }
        return fetch(PAGES_API_URL, {
            method: 'POST',
            body: formData,
            headers: headers || {}
        }).then((response) => response.json());
    }

    function buildTreeFromJson() {
        const treeView = document.getElementById('page-tree-root');
        const templateSource = document.getElementById('tree-templates-source');
        if (!treeView || !templateSource || !PAGE_TREE || typeof PAGE_TREE !== 'object') {
            return;
        }

        const pageTemplates = new Map();
        const dirTemplates = new Map();

        templateSource.querySelectorAll('.tree-item').forEach((item) => {
            const id = item.getAttribute('data-id') || '';
            const type = item.getAttribute('data-type') || '';
            if (!id || !type) {
                return;
            }

            if (type === 'page') {
                pageTemplates.set(id, item.cloneNode(true));
            } else if (type === 'dir') {
                dirTemplates.set(id, item.cloneNode(true));
            }
        });

        const cleanDirNode = (node) => {
            const details = node.querySelector('details');
            if (!details) {
                return node;
            }
            details.querySelectorAll('ul.sortable-list').forEach((ul) => ul.remove());
            return node;
        };

        const createFallbackPage = (page) => {
            const li = document.createElement('li');
            li.className = 'tree-item';
            li.setAttribute('data-id', page.slug || '');
            li.setAttribute('data-type', 'page');

            const details = document.createElement('details');
            details.className = 'page-details';
            const summary = document.createElement('summary');
            summary.className = 'tree-content';

            const dragHandle = document.createElement('span');
            dragHandle.className = 'drag-handle';
            dragHandle.textContent = '::';

            const title = document.createElement('span');
            title.className = 'title';
            title.textContent = String(page.slug || '');

            summary.appendChild(dragHandle);
            summary.appendChild(title);
            details.appendChild(summary);
            li.appendChild(details);
            return li;
        };

        const createFallbackDir = (dir) => {
            const li = document.createElement('li');
            li.className = 'tree-item';
            li.setAttribute('data-id', dir.id || '');
            li.setAttribute('data-type', 'dir');

            const details = document.createElement('details');
            details.open = true;
            const summary = document.createElement('summary');
            summary.className = 'tree-content';

            const dragHandle = document.createElement('span');
            dragHandle.className = 'drag-handle';
            dragHandle.textContent = '::';

            const title = document.createElement('span');
            title.className = 'title';
            title.textContent = String(dir.name || '');

            summary.appendChild(dragHandle);
            summary.appendChild(title);
            details.appendChild(summary);
            li.appendChild(details);
            return li;
        };

        const buildList = (dirs, pages, parentId = '') => {
            const ul = document.createElement('ul');
            ul.className = 'sortable-list';
            ul.setAttribute('data-parent-id', parentId);

            (dirs || []).forEach((dir) => {
                const template = dirTemplates.get(dir.id);
                const dirNode = template ? cleanDirNode(template.cloneNode(true)) : createFallbackDir(dir);
                dirNode.setAttribute('data-id', dir.id || '');
                dirNode.setAttribute('data-type', 'dir');

                const details = dirNode.querySelector('details');
                if (details) {
                    details.appendChild(buildList(dir.children || [], dir.pages || [], dir.id || ''));
                }

                ul.appendChild(dirNode);
            });

            (pages || []).forEach((page) => {
                const template = pageTemplates.get(page.slug);
                const pageNode = template ? template.cloneNode(true) : createFallbackPage(page);
                pageNode.setAttribute('data-id', page.slug || '');
                pageNode.setAttribute('data-type', 'page');
                ul.appendChild(pageNode);
            });

            return ul;
        };

        const root = buildList(PAGE_TREE.root_dirs || [], PAGE_TREE.root_pages || [], '');
        clearNode(treeView);
        treeView.appendChild(root);
    }

    function initDirectoryStatePersistence(root, storageKey) {
        const treeRoot = root || document;
        let savedState = {};

        try {
            savedState = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            savedState = {};
        }

        function saveState() {
            const state = {};
            treeRoot.querySelectorAll('.tree-item[data-type="dir"]').forEach((item) => {
                const id = item.getAttribute('data-id');
                const details = item.firstElementChild && item.firstElementChild.tagName === 'DETAILS'
                    ? item.firstElementChild
                    : null;
                if (id && details) {
                    state[id] = !!details.open;
                }
            });

            try {
                localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) {}
        }

        treeRoot.querySelectorAll('.tree-item[data-type="dir"]').forEach((item) => {
            const id = item.getAttribute('data-id');
            const details = item.firstElementChild && item.firstElementChild.tagName === 'DETAILS'
                ? item.firstElementChild
                : null;
            if (!id || !details) return;

            if (Object.prototype.hasOwnProperty.call(savedState, id)) {
                details.open = !!savedState[id];
            }

            details.addEventListener('toggle', saveState);
        });
    }

    function openDirModal(action, id = '', name = '', parent = '') {
        const modal = document.getElementById('dirModal');
        document.getElementById('dirAction').value = action;
        document.getElementById('dirId').value = id;
        document.getElementById('dirName').value = name;
        document.getElementById('dirParent').value = parent;

        const titleEl = modal.querySelector('h3');
        if (titleEl) {
            titleEl.textContent = action === 'add_dir' ? LANG.add_dir : LANG.edit_dir;
        }

        const options = document.getElementById('dirParent').options;
        for (let i = 0; i < options.length; i++) {
            options[i].disabled = (options[i].value === id && id !== '');
        }
        openModal('dirModal');
    }

    function openPageModal() {
        openModal('pageModal');
    }

    function closePageModal() {
        closeModal('pageModal');
        document.getElementById('createPageForm').reset();
        document.getElementById('pageModalError').style.display = 'none';
    }

    function deleteDir(id, pages) {
        let msg = LANG.delete_directory_confirm;
        if (pages) {
            msg += '\n' + LANG.delete_directory_with_pages.replace('%s', pages);
        }
        cms_confirm(msg, () => {
            const formData = new FormData();
            formData.append('action', 'delete_dir');
            formData.append('id', id);

            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert(LANG.error_prefix + (data.message || LANG.system_error));
            });
        });
    }

    function deletePage(slug, title) {
        cms_confirm(LANG.delete_page_confirm.replace('%s', title), () => {
            const formData = new FormData();
            formData.append('action', 'delete_page');
            formData.append('slug', slug);

            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert(LANG.error_prefix + (data.message || LANG.system_error));
            });
        });
    }

    function copyPage(slug, title) {
        cms_confirm(LANG.copy_page_confirm.replace('%s', title), () => {
            const formData = new FormData();
            formData.append('action', 'copy_page');
            formData.append('slug', slug);

            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert(LANG.error_prefix + (data.message || LANG.system_error));
            });
        });
    }

    function toggleActive(slug) {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('slug', slug);

        postForm(formData)
        .then(data => {
            if (data.status === 'success') {
                const btn = document.querySelector('.active-btn[data-slug="' + slug + '"]');
                if (!btn) return;
                if (data.active) {
                    btn.classList.remove('inactive');
                    btn.classList.add('active');
                    btn.setAttribute('data-tooltip', LANG.active_tooltip);
                    btn.removeAttribute('title');
                    appendIconMarkup(btn, ICONS.toggleActive || '');
                } else {
                    btn.classList.remove('active');
                    btn.classList.add('inactive');
                    btn.setAttribute('data-tooltip', LANG.inactive_tooltip);
                    btn.removeAttribute('title');
                    appendIconMarkup(btn, ICONS.toggleInactive || '');
                }
            }
        });
    }

    function setHomePage(slug) {
        cms_confirm(LANG.set_homepage_confirm, () => {
            const formData = new FormData();
            formData.append('action', 'set_homepage');
            formData.append('slug', slug);

            postForm(formData)
            .then(data => {
                if (data.status === 'success') {
                    document.querySelectorAll('.home-btn').forEach(btn => {
                        btn.classList.remove('active');
                        appendIconMarkup(btn, ICONS.starInactive || '');
                    });
                    const btn = document.querySelector('.home-btn[data-slug="' + slug + '"]');
                    if (!btn) return;
                    btn.classList.add('active');
                    appendIconMarkup(btn, ICONS.starActive || '');
                    cms_alert(LANG.homepage_changed);
                }
            });
        });
    }

    function initSortable() {
        document.querySelectorAll('.sortable-list').forEach(el => {
            if (!el.sortableInstance) {
                el.sortableInstance = new Sortable(el, {
                    group: 'nested',
                    handle: '.drag-handle',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    onEnd: function () {
                        saveOrder();
                    }
                });
            }
        });
    }

    function copyToClipboard(e, btn) {
        e.preventDefault();
        const url = btn.getAttribute('data-url');
        if (!url) return;
        const originalNodes = Array.from(btn.childNodes).map((node) => node.cloneNode(true));
        const restoreOriginal = () => {
            clearNode(btn);
            originalNodes.forEach((node) => btn.appendChild(node.cloneNode(true)));
            btn.style.color = '';
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                appendCheckIcon(btn);
                btn.style.color = '#10b981';
                setTimeout(() => {
                    restoreOriginal();
                }, 1200);
            }).catch(() => fallbackCopy(url, btn, restoreOriginal));
        } else {
            fallbackCopy(url, btn, restoreOriginal);
        }
    }

    function fallbackCopy(text, btn, restoreOriginal) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            appendCheckIcon(btn);
            btn.style.color = '#10b981';
            setTimeout(() => {
                restoreOriginal();
            }, 1200);
        } catch (err) {
            cms_alert(LANG.error_copy_url);
        }
        document.body.removeChild(ta);
    }

    function saveOrder() {
        const items = [];
        const treeRoot = document.getElementById('page-tree-root');
        if (!treeRoot) {
            return;
        }

        treeRoot.querySelectorAll('.tree-item').forEach((el) => {
            const id = el.getAttribute('data-id');
            const type = el.getAttribute('data-type');
            const parentUl = el.closest('ul');
            if (!parentUl) {
                return;
            }
            const parentId = parentUl.getAttribute('data-parent-id') || null;
            const index = Array.from(parentUl.children).indexOf(el);

            items.push({id: id, type: type, parent: parentId, order: index});
        });

        if (items.length === 0) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('data', JSON.stringify(items));

        postForm(formData)
        .then(data => {
            if (data.status !== 'success') {
                console.error('Error saving order');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function loadHistory(slug, container) {
        const formData = new FormData();
        formData.append('action', 'get_history');
        formData.append('slug', slug);

        postForm(formData)
        .then(data => {
            if (data.status === 'success') {
                renderHistory(data.history, container, !!data.can_restore, !!data.can_view);
            } else {
                setStatusMessage(container, LANG.error_loading, { tag: 'small', style: 'color: red;' });
            }
        })
        .catch(error => {
            console.error('Error loading history:', error);
            setStatusMessage(container, LANG.error_network, { tag: 'small', style: 'color: red;' });
        });
    }

    function renderHistory(history, container, canRestore, canView) {
        if (!history || history.length === 0) {
            setStatusMessage(container, LANG.history_empty, {
                tag: 'p',
                style: 'color: #6b7280; font-size: 0.875rem; text-align: center; padding: 1rem 0;'
            });
            return;
        }

        const slug = container.dataset.slug;

        const labelAlias = (raw) => {
            const m = {
                'заголовок': LANG.title,
                'title': LANG.title,
                'зображення': LANG.image,
                'image': LANG.image,
                'img': LANG.image,
                'url': LANG.url,
                'slug': LANG.url,
                'шаблон': LANG.template,
                'template': LANG.template,
                'батьківську сторінку': LANG.parent_page,
                'parent page': LANG.parent_page,
            };
            const k = String(raw || '').trim().toLowerCase();
            return m[k] || raw;
        };

        const codeMap = {
            changed_title: LANG.changed_title,
            changed_url: LANG.changed_url,
            changed_template: LANG.changed_template,
            changed_parent_page: LANG.changed_parent_page,
            page_activated: LANG.page_activated,
            page_deactivated: LANG.page_deactivated,
            set_homepage: LANG.set_homepage,
            unset_homepage: LANG.unset_homepage,
            changed_seo_title: LANG.changed_seo_title,
            changed_seo_desc: LANG.changed_seo_desc,
            initial_version: LANG.initial_version,
            no_changes: LANG.no_changes,
        };

        const safeSlug = escapeJsSingleQuoteString(slug);

        let html = '<div class="history-items" style="width: 100%;">';
        history.forEach((item) => {
            let changesHtml = '';
            if (Array.isArray(item.change_codes) && item.change_codes.length > 0) {
                const msgs = [];
                item.change_codes.forEach(entry => {
                    const code = entry && entry.code ? String(entry.code) : '';
                    const field = labelAlias(entry && entry.field ? entry.field : '');

                    if (code === 'content_added') {
                        msgs.push(LANG.added + field);
                        return;
                    }
                    if (code === 'content_changed') {
                        msgs.push(LANG.changed + field);
                        return;
                    }
                    if (code === 'content_deleted') {
                        msgs.push(LANG.deleted + field);
                        return;
                    }

                    msgs.push(codeMap[code] || code);
                });

                if (msgs.length) {
                    changesHtml = '<ul style="list-style: disc; margin: 0.5rem 0 0 1.25rem; padding: 0; font-size: 0.75rem; color: #6b7280;">';
                    msgs.forEach(m => { changesHtml += '<li>' + escapeHtml(m) + '</li>'; });
                    changesHtml += '</ul>';
                }
            }

            const safeDate = escapeHtml(item && item.date ? item.date : '');
            const safeAuthor = escapeHtml(item && item.author ? item.author : '');
            const safeCurrentVersion = escapeHtml(LANG.current_version || '');
            const safeNoDetails = escapeHtml(LANG.no_details || '');
            const safePreview = escapeHtml(LANG.preview || '');
            const safeRestore = escapeHtml(LANG.restore || '');
            const safeSlugForUrl = encodeURIComponent(String(slug || ''));
            const timestamp = Number(item && item.timestamp ? item.timestamp : 0) || 0;

            html += '\
                <div class="history-version" style="border-bottom: 1px solid #e5e7eb; padding: 0.75rem 0; width: 100%; box-sizing: border-box;">\
                    <details style="cursor: pointer; width: 100%;">\
                        <summary style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; width: 100%;">\
                            <div>\
                                <div style="font-weight: 600; color: #111827; margin-bottom: 0.125rem; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">\
                                    <span>' + safeDate + '</span>\
                                    ' + (item.is_current ? '<span style="display:inline-flex; align-items:center; padding:0.08rem 0.45rem; border-radius:9999px; background:#dcfce7; color:#166534; font-size:0.66rem; font-weight:700; letter-spacing:0.01em;">' + safeCurrentVersion + '</span>' : '') + '\
                                </div>\
                                <div style="color: #6b7280; font-size: 0.75rem;">' + safeAuthor + '</div>\
                            </div>\
                            <svg style="width: 1rem; height: 1rem; color: #6b7280; transition: transform 0.2s;" class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">\
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>\
                            </svg>\
                        </summary>\
                        <div style="margin-top: 0.5rem; padding: 0.5rem; background: #f9fafb; border-radius: 0.375rem; width: 100%;">\
                            ' + (changesHtml || '<p style="font-size: 0.75rem; color: #6b7280; margin: 0;">' + safeNoDetails + '</p>') + '\
                            <div style="display:flex; gap:6px; margin-top:0.5rem; flex-wrap:wrap;">\
                                ' + (canView ? '<a class="btn btn-secondary" style="padding:0.35rem 0.75rem; font-size:0.75rem; box-shadow:none;" href="/clipon/admin/preview_version.php?slug=' + safeSlugForUrl + '&timestamp=' + timestamp + '" target="_blank">' + safePreview + '</a>' : '') + '\
                                ' + ((canRestore && !item.is_current) ? '<button type="button" class="btn" style="padding:0.35rem 0.75rem; font-size:0.75rem; box-shadow:none;" onclick="restoreVersion(\'' + safeSlug + '\', ' + timestamp + ')">' + safeRestore + '</button>' : '') + '\
                            </div>\
                        </div>\
                    </details>\
                </div>\
            ';
        });
        html += '</div>';

        clearNode(container);
        const fragment = document.createRange().createContextualFragment(html);
        container.appendChild(fragment);

        container.querySelectorAll('details').forEach(details => {
            details.addEventListener('toggle', function() {
                const chevron = this.querySelector('.chevron');
                if (this.open) {
                    chevron.style.transform = 'rotate(180deg)';
                } else {
                    chevron.style.transform = 'rotate(0deg)';
                }
            });
        });
    }

    function restoreVersion(slug, timestamp) {
        cms_confirm(LANG.restore_confirm, () => {
            const formData = new FormData();
            formData.append('action', 'restore_version');
            formData.append('slug', slug);
            formData.append('timestamp', timestamp);

            postForm(formData)
            .then(data => {
                if (data.status === 'success') {
                    cms_alert(LANG.restore_success);
                    location.reload();
                } else {
                    cms_alert(LANG.error_prefix + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                cms_alert(LANG.error_network);
            });
        });
    }

    function switchEditLanguage(el) {
        const lang = el.value;
        const container = el.closest('.page-edit-form');
        const form = container.querySelector('form');
        const locales = JSON.parse(form.dataset.locales || '{}');
        const primary = JSON.parse(form.dataset.primary || '{}');
        const primaryLang = form.dataset.primaryLang;

        const data = (lang === primaryLang) ? primary : (locales[lang] || { title: '', slug: '', seo: {} });

        form.querySelectorAll('.lang-code').forEach(span => {
            span.textContent = lang.toUpperCase();
        });

        form.querySelector('[name="title"]').value = data.title || '';
        form.querySelector('[name="new_slug"]').value = data.slug || primary.slug || '';
        form.querySelector('[name="meta_title"]').value = data.seo && data.seo.meta_title ? data.seo.meta_title : '';
        form.querySelector('[name="meta_description"]').value = data.seo && data.seo.meta_description ? data.seo.meta_description : '';
        form.querySelector('[name="editing_lang"]').value = lang;

        const link = form.querySelector('.page-full-url');
        const copyBtn = form.querySelector('.copy-url-btn');
        if (link) {
            const protocol = window.location.protocol + '//';
            const host = window.location.host;
            let path = '';
            if (typeof data.url === 'string' && data.url !== '') {
                path = data.url;
            } else if (lang === primaryLang) {
                path = primary.url || '/' + primary.slug;
            } else {
                path = '/' + lang + '/' + (data.slug || primary.slug);
            }
            path = path.replace(/\/+/g, '/');
            const fullUrl = protocol + host + path;
            link.href = fullUrl;
            link.textContent = fullUrl;
            if (copyBtn) copyBtn.dataset.url = fullUrl;
        }
    }

    function switchListingEditLang(lang) {
        const params = new URLSearchParams(location.search);
        params.set('edit_lang', lang);
        location.search = params.toString();
    }

    // Global functions for inline onclick handlers.
    window.openDirModal = openDirModal;
    window.openPageModal = openPageModal;
    window.deleteDir = deleteDir;
    window.deletePage = deletePage;
    window.copyPage = copyPage;
    window.toggleActive = toggleActive;
    window.setHomePage = setHomePage;
    window.copyToClipboard = copyToClipboard;
    window.restoreVersion = restoreVersion;
    window.switchListingEditLang = switchListingEditLang;
    window.switchEditLanguage = switchEditLanguage;

    buildTreeFromJson();
    initDirectoryStatePersistence(document.getElementById('page-tree-root'), 'clipon-admin-pages-dir-state');

    (function () {
        const form = document.getElementById('createPageForm');
        if (!form) return;
        const titleInput = form.querySelector('[name="title"]');
        const slugInput = form.querySelector('[name="slug"]');
        if (!titleInput || !slugInput) return;

        const translitMap = {
            'а':'a','б':'b','в':'v','г':'h','ґ':'g','д':'d','е':'e','є':'ie','ж':'zh','з':'z','и':'y','і':'i','ї':'i','й':'j',
            'к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts','ч':'ch',
            'ш':'sh','щ':'shch','ь':'','ю':'iu','я':'ia'
        };

        function slugify(s) {
            s = (s || '').toString().trim().toLowerCase();
            if (!s) return 'page-' + Date.now();
            s = s.split('').map(ch => translitMap[ch] !== undefined ? translitMap[ch] : ch).join('');
            s = s.normalize('NFKD').replace(/\p{Diacritic}/gu, '');
            s = s.replace(/[^a-z0-9\-_]+/g, '-').replace(/-+/g, '-').replace(/(^-|-$)/g, '');
            return s || 'page-' + Date.now();
        }

        let lastAuto = '';
        let manual = false;

        slugInput.addEventListener('input', function() {
            if (this.value !== lastAuto) manual = this.value !== '';
            if (this.value === '') manual = false;
        });

        titleInput.addEventListener('input', function() {
            if (manual) return;
            const s = slugify(this.value);
            lastAuto = s;
            slugInput.value = s;
        });
    })();

    document.getElementById('createPageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        postForm(formData)
        .then(data => {
            if (data.status === 'success') {
                cms_alert(data.message);
                closePageModal();
                location.reload();
            } else {
                document.getElementById('pageModalError').textContent = data.message;
                document.getElementById('pageModalError').style.display = 'block';
            }
        });
    });

    const dirForm = document.getElementById('dirForm');
    if (dirForm) {
        dirForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(dirForm);

            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert(LANG.error_prefix + (data.message || LANG.system_error));
            });
        });
    }

    window.onclick = function(event) {
        if (event.target === document.getElementById('dirModal')) {
            closeModal();
        }
        if (event.target === document.getElementById('pageModal')) {
            closePageModal();
        }
    };

    document.querySelectorAll('details').forEach((el) => {
        const summary = el.querySelector('summary');
        if (!summary) return;
        const toggle = summary.querySelector('.dir-toggle');

        if (toggle) {
            el.addEventListener('toggle', () => {
                toggle.textContent = el.open ? '▼' : '▶';
            });
            toggle.textContent = el.open ? '▼' : '▶';
        }
    });

    initSortable();
    document.querySelectorAll('details').forEach(el => {
        el.addEventListener('toggle', () => {
            if (el.open) {
                setTimeout(initSortable, 10);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const detailsElements = document.querySelectorAll('.page-details');
        detailsElements.forEach(details => {
            details.addEventListener('toggle', function() {
                if (this.open) {
                    const historyList = this.querySelector('.history-list');
                    if (historyList && historyList.textContent.includes(LANG.loading)) {
                        const slug = historyList.dataset.slug;
                        loadHistory(slug, historyList);
                    }
                }
            });
        });

        if (window.AdminUI && typeof window.AdminUI.initFlashToasts === 'function') {
            window.AdminUI.initFlashToasts(4000);
        }
    });

    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('page-edit-form-ajax')) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;

            btn.disabled = true;
            btn.textContent = LANG.loading;

            const formData = new FormData(form);

            postForm(formData, {
                'X-Requested-With': 'XMLHttpRequest'
            })
            .then(data => {
                if (data.status === 'success') {
                    btn.style.background = '#28a745';
                    btn.textContent = '✓';
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        btn.style.background = '';
                    }, 2000);

                    const lang = formData.get('editing_lang');
                    const primaryLang = form.dataset.primaryLang;
                    const locales = JSON.parse(form.dataset.locales || '{}');

                    const currentData = {
                        title: formData.get('title'),
                        slug: formData.get('new_slug'),
                        seo: {
                            meta_title: formData.get('meta_title'),
                            meta_description: formData.get('meta_description')
                        }
                    };

                    if (lang === primaryLang) {
                        form.dataset.primary = JSON.stringify(currentData);
                    } else {
                        locales[lang] = currentData;
                        form.dataset.locales = JSON.stringify(locales);
                    }
                } else {
                    cms_alert(LANG.error_prefix + (data.message || LANG.system_error));
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                cms_alert(LANG.error_network);
                btn.disabled = false;
                btn.textContent = originalText;
            });
        }
    });
})();
