(function () {
    const cfg = window.BLOG_ADMIN_CONFIG || {};
    const TEXT = cfg.text || {};
    const BLOG_API_URL = cfg.apiUrl || 'api/blog.php';
    const CSRF_TOKEN = cfg.csrfToken || '';

    function clearNode(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function appendIconMarkup(target, markup) {
        if (!target) return false;

        const raw = String(markup || '').trim();
        if (!raw) {
            return false;
        }

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(raw, 'text/html');
            const svg = doc.querySelector('svg');
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

    function tagKey(name) {
        const map = {
            'а':'a','б':'b','в':'v','г':'h','ґ':'g','д':'d','е':'e','є':'ie','ж':'zh','з':'z','и':'y','і':'i','ї':'i','й':'j',
            'к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts','ч':'ch',
            'ш':'sh','щ':'shch','ь':'','ю':'iu','я':'ia'
        };
        let value = String(name || '').trim().toLowerCase();
        value = value.split('').map(ch => map[ch] !== undefined ? map[ch] : ch).join('');
        value = value.normalize('NFKD').replace(/\p{Diacritic}/gu, '');
        value = value.replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/(^-|-$)/g, '');
        return value || 'tag';
    }

    function initTagPicker(root) {
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';

        const hidden = root.querySelector('input[type="hidden"]');
        const selectedBox = root.querySelector('[data-tag-selected]');
        const optionsBox = root.querySelector('[data-tag-options]');
        const input = root.querySelector('[data-tag-input]');
        const addButton = root.querySelector('[data-tag-add]');
        let allTags = JSON.parse(root.dataset.tags || '[]');
        let selected = JSON.parse(root.dataset.selected || '[]').map(v => String(v || '').trim()).filter(Boolean);

        function tagLabel(tag) {
            return String((tag && (tag.label || tag.name || tag.id)) || '').trim();
        }

        function tagId(tag) {
            return String((tag && (tag.id || tag.name)) || '').trim();
        }

        function findTag(value) {
            const raw = String(value || '').trim();
            const key = tagKey(raw);
            return allTags.find(tag => {
                const id = tagId(tag);
                const label = tagLabel(tag);
                return id === raw || tagKey(id) === key || tagKey(label) === key;
            }) || null;
        }

        function displayValue(value) {
            const tag = findTag(value);
            return tag ? tagLabel(tag) : String(value || '').trim();
        }

        function syncHidden() {
            hidden.value = selected.join(',');
        }

        function hasTag(name) {
            const tag = findTag(name);
            const id = tag ? tagId(tag) : String(name || '').trim();
            const key = tagKey(id);
            return selected.some(item => item === id || tagKey(item) === key || tagKey(displayValue(item)) === tagKey(name));
        }

        function addTag(name) {
            name = String(name || '').trim().replace(/\s+/g, ' ');
            if (!name) return;
            if (hasTag(name)) {
                input.value = '';
                return;
            }
            const tag = findTag(name);
            selected.push(tag ? tagId(tag) : name);
            input.value = '';
            render();
        }

        function removeTag(name) {
            const key = tagKey(name);
            selected = selected.filter(tag => tag !== name && tagKey(tag) !== key && tagKey(displayValue(tag)) !== key);
            render();
        }

        function renderSelected() {
            clearNode(selectedBox);
            if (!selected.length) {
                const empty = document.createElement('span');
                empty.className = 'blog-tag-picker-empty';
                empty.textContent = TEXT.tag_none_selected || 'No tags selected';
                selectedBox.appendChild(empty);
                return;
            }

            selected.forEach(tag => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'blog-tag-chip is-selected';
                chip.textContent = displayValue(tag) + ' ×';
                chip.addEventListener('click', () => removeTag(tag));
                selectedBox.appendChild(chip);
            });
        }

        function renderOptions() {
            clearNode(optionsBox);
            const query = input.value.trim().toLowerCase();
            allTags
                .filter(tag => tag && tagId(tag) && !hasTag(tagId(tag)))
                .filter(tag => !query || tagLabel(tag).toLowerCase().includes(query) || tagId(tag).toLowerCase().includes(query))
                .slice(0, 20)
                .forEach(tag => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'blog-tag-chip';
                    const countLabel = TEXT.tag_posts_count || '%d posts';
                    option.textContent = tagLabel(tag) + (tag.count ? ' (' + countLabel.replace('%d', tag.count) + ')' : '');
                    option.addEventListener('click', () => addTag(tagId(tag)));
                    optionsBox.appendChild(option);
                });
        }

        function render() {
            renderSelected();
            renderOptions();
            syncHidden();
        }

        addButton.addEventListener('click', () => addTag(input.value));
        input.addEventListener('input', renderOptions);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addTag(input.value);
            }
        });

        root.resetTags = function() {
            selected = [];
            render();
        };

        root.refreshTags = function(tags) {
            allTags = Array.isArray(tags) ? tags : [];
            root.dataset.tags = JSON.stringify(allTags);
            renderOptions();
        };

        render();
    }

    function initTagPickers(scope) {
        (scope || document).querySelectorAll('[data-blog-tag-picker]').forEach(initTagPicker);
    }

    function postForm(formData, headers) {
        if (CSRF_TOKEN && !formData.has('csrf_token')) {
            formData.append('csrf_token', CSRF_TOKEN);
        }
        return fetch(BLOG_API_URL, {
            method: 'POST',
            body: formData,
            headers: headers || {}
        }).then(r => r.json());
    }

    function tagCountText(count) {
        const countLabel = TEXT.tag_posts_count || '%d posts';
        return countLabel.replace('%d', Number(count || 0));
    }

    function localizedTagLabel(tag) {
        const lang = cfg.currentLang || '';
        return String((tag && tag.labels && lang && tag.labels[lang]) || (tag && (tag.label || tag.name || tag.id)) || '').trim();
    }

    function upsertTagConfig(tag) {
        if (!tag || !tag.id) return;
        if (!Array.isArray(cfg.tags)) {
            cfg.tags = [];
        }

        const index = cfg.tags.findIndex(item => item && item.id === tag.id);
        if (index >= 0) {
            const next = Object.assign({}, cfg.tags[index], tag);
            if ((!tag.posts || !tag.posts.length) && cfg.tags[index].posts) {
                next.posts = cfg.tags[index].posts;
            }
            cfg.tags[index] = next;
        } else {
            cfg.tags.push(tag);
        }

        cfg.tags.sort((a, b) => String(a.label || a.name || a.id || '').localeCompare(String(b.label || b.name || b.id || ''), undefined, {numeric: true, sensitivity: 'base'}));
        document.querySelectorAll('[data-blog-tag-picker]').forEach(picker => {
            if (typeof picker.refreshTags === 'function') {
                picker.refreshTags(cfg.tags);
            } else {
                picker.dataset.tags = JSON.stringify(cfg.tags);
            }
        });
    }

    function appendTagManagerRow(tag) {
        if (!tag || !tag.id) return;

        const list = document.querySelector('.blog-tag-manager-list');
        if (!list) return;

        const existing = Array.from(list.querySelectorAll('[data-tag-row]'))
            .find(row => row.dataset.id === tag.id);
        if (existing) {
            const count = existing.querySelector('.blog-tag-count');
            const id = existing.querySelector('.blog-tag-summary-id');
            const label = existing.querySelector('.blog-tag-summary-label');
            existing.querySelectorAll('[data-tag-label]').forEach(input => {
                const lang = input.dataset.tagLabel;
                if (tag.labels && Object.prototype.hasOwnProperty.call(tag.labels, lang)) {
                    input.value = tag.labels[lang] || '';
                }
            });
            if (count && Object.prototype.hasOwnProperty.call(tag, 'count')) count.textContent = tagCountText(tag.count);
            if (id) id.textContent = tag.id || '';
            if (label) label.textContent = localizedTagLabel(tag);
            return;
        }

        const empty = list.querySelector('.blog-tag-empty');
        if (empty) {
            empty.remove();
        }

        const row = document.createElement('details');
        row.className = 'blog-tag-accordion';
        row.dataset.tagRow = '';
        row.dataset.id = tag.id;

        const summary = document.createElement('summary');
        summary.className = 'blog-tag-summary';

        const toggle = document.createElement('span');
        toggle.className = 'dir-toggle';
        toggle.innerHTML = '<svg width="12" height="12" viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><polyline points="96 48 176 128 96 208"></polyline></svg>';

        const summaryMain = document.createElement('span');
        summaryMain.className = 'blog-tag-summary-main';
        const summaryId = document.createElement('code');
        summaryId.className = 'blog-tag-summary-id';
        summaryId.textContent = tag.id || '';
        const summaryLabel = document.createElement('span');
        summaryLabel.className = 'blog-tag-summary-label';
        summaryLabel.textContent = localizedTagLabel(tag);
        summaryMain.append(summaryId, summaryLabel);

        const count = document.createElement('span');
        count.className = 'blog-tag-count';
        count.textContent = tagCountText(tag.count);

        const actions = document.createElement('span');
        actions.className = 'blog-tag-actions';

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'icon-btn edit-btn';
        edit.dataset.tagEdit = '';
        if (!appendIconMarkup(edit, cfg.iconPencil)) edit.textContent = TEXT.edit || 'Edit';

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'icon-btn delete-btn';
        del.dataset.tagDelete = '';
        if (!appendIconMarkup(del, cfg.iconTrash)) del.textContent = TEXT.delete || 'Delete';

        actions.append(edit, del);
        summary.append(toggle, summaryMain, count, actions);

        const panel = document.createElement('div');
        panel.className = 'blog-tag-panel';

        const main = document.createElement('div');
        main.className = 'blog-tag-manager-main';
        const languages = Array.isArray(cfg.languages) && cfg.languages.length ? cfg.languages : [{code: cfg.currentLang || 'en', name: 'Default'}];
        languages.forEach(lang => {
            const langCode = String(lang.code || '').trim();
            if (!langCode) return;
            const localeRow = document.createElement('div');
            localeRow.className = 'blog-tag-locale-row';

            const badge = document.createElement('span');
            badge.className = 'lang-code';
            badge.textContent = langCode.toUpperCase();

            const label = document.createElement('input');
            label.type = 'text';
            label.className = 'form-control';
            label.value = (tag.labels && tag.labels[langCode]) || (langCode === (cfg.currentLang || '') ? (tag.label || tag.name || '') : '');
            label.dataset.tagLabel = langCode;
            label.disabled = true;

            localeRow.append(badge, label);
            main.appendChild(localeRow);
        });

        const panelActions = document.createElement('div');
        panelActions.className = 'blog-tag-panel-actions';

        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'btn btn-secondary';
        save.dataset.tagRename = '';
        save.disabled = true;
        if (appendIconMarkup(save, cfg.iconSave)) {
            save.appendChild(document.createTextNode(' ' + (TEXT.save || 'Save')));
        } else {
            save.textContent = TEXT.save || 'Save';
        }
        panelActions.appendChild(save);

        const posts = document.createElement('div');
        posts.className = 'blog-tag-posts';
        const postsTitle = document.createElement('strong');
        postsTitle.textContent = TEXT.posts || 'Posts';
        posts.appendChild(postsTitle);

        if (Array.isArray(tag.posts) && tag.posts.length) {
            const ul = document.createElement('ul');
            tag.posts.forEach(post => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = post.url || '#';
                a.target = '_blank';
                a.textContent = post.title || post.slug || '';
                li.appendChild(a);
                ul.appendChild(li);
            });
            posts.appendChild(ul);
        } else {
            const emptyPosts = document.createElement('p');
            emptyPosts.className = 'blog-tag-empty';
            emptyPosts.textContent = TEXT.tag_no_posts || 'No posts.';
            posts.appendChild(emptyPosts);
        }

        panel.append(main, panelActions, posts);
        row.append(summary, panel);
        list.appendChild(row);
    }

    function setTagRowEditing(row, enabled) {
        if (!row) return;
        row.open = true;
        row.classList.toggle('is-editing', enabled);
        row.querySelectorAll('[data-tag-label]').forEach(input => {
            input.disabled = !enabled;
        });
        const save = row.querySelector('[data-tag-rename]');
        if (save) save.disabled = !enabled;
        if (enabled) {
            const firstLabel = row.querySelector('[data-tag-label]');
            if (firstLabel) firstLabel.focus();
        }
    }

    function openDirModal(action, id = '', name = '', parent = '') {
        const modal = document.getElementById('dirModal');
        document.getElementById('dirAction').value = action;
        document.getElementById('dirId').value = id;
        document.getElementById('dirName').value = name;
        document.getElementById('dirParent').value = parent;

        const titleEl = modal.querySelector('h3');
        if (titleEl) {
            titleEl.textContent = action === 'add_dir' ? (TEXT.add_category || 'Add category') : (TEXT.edit_category || 'Edit category');
        }

        const options = document.getElementById('dirParent').options;
        for (let i = 0; i < options.length; i++) {
            options[i].disabled = (options[i].value === id && id !== '');
        }
        openModal('dirModal');
    }

    function openPostModal() {
        initTagPickers(document.getElementById('postModal'));
        openModal('postModal');
    }

    function openTagManagerModal() {
        openModal('tagManagerModal');
    }

    window.openPostModal = openPostModal;
    window.openDirModal = openDirModal;
    window.openTagManagerModal = openTagManagerModal;

    function openMediaSelector(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        if (window.CliponMediaPicker && typeof window.CliponMediaPicker.open === 'function') {
            window.CliponMediaPicker.open({
                onSelect: function(item) {
                    if (item && item.path) {
                        input.value = item.path;
                    }
                }
            });
            return;
        }

        const url = prompt(TEXT.enter_image_url || 'Enter image URL:', input.value || '/assets/uploads/');
        if (url !== null) {
            input.value = url;
        }
    }

    function closePostModal() {
        closeModal('postModal');
        document.getElementById('createPostForm').reset();
        document.querySelectorAll('#createPostForm [data-blog-tag-picker]').forEach(picker => {
            if (typeof picker.resetTags === 'function') picker.resetTags();
        });
        document.getElementById('postModalError').style.display = 'none';
    }

    (function() {
        const titleInput = document.querySelector('#postModal input[name="title"]');
        const slugInput = document.querySelector('#postModal input[name="slug"]');
        if (!titleInput || !slugInput) return;

        const translitMap = {
            'а':'a','б':'b','в':'v','г':'h','ґ':'g','д':'d','е':'e','є':'ie','ж':'zh','з':'z','и':'y','і':'i','ї':'i','й':'j',
            'к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts','ч':'ch',
            'ш':'sh','щ':'shch','ь':'','ю':'iu','я':'ia'
        };

        function slugify(s) {
            s = (s || '').toString().trim().toLowerCase();
            if (!s) return 'post-' + Date.now();
            s = s.split('').map(ch => translitMap[ch] !== undefined ? translitMap[ch] : ch).join('');
            s = s.normalize('NFKD').replace(/\p{Diacritic}/gu, '');
            s = s.replace(/[^a-z0-9\-_]+/g, '-').replace(/-+/g, '-').replace(/(^-|-$)/g, '');
            return s || 'post-' + Date.now();
        }

        let lastAuto = '';
        let manual = false;

        slugInput.addEventListener('input', function(){
            if (this.value !== lastAuto) manual = this.value !== '';
            if (this.value === '') manual = false;
        });

        titleInput.addEventListener('input', function(){
            if (manual) return;
            const s = slugify(this.value || 'post');
            lastAuto = s;
            slugInput.value = s;
        });

        const origOpen = openPostModal;
        window.openPostModal = function(){
            manual = false;
            lastAuto = '';
            origOpen();
            setTimeout(() => {
                const t = titleInput.value.trim();
                if (t) {
                    const s = slugify(t);
                    lastAuto = s;
                    slugInput.value = s;
                } else {
                    slugInput.value = '';
                }
            }, 10);
        };
    })();

    function copyToClipboard(arg1, arg2) {
        let text = '';
        if (typeof arg1 === 'string') {
            text = arg1;
        } else if (arg2 && arg2.dataset && arg2.dataset.url) {
            if (arg1 && typeof arg1.preventDefault === 'function') {
                arg1.preventDefault();
            }
            text = arg2.dataset.url;
        }

        if (!text) {
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
            cms_alert((TEXT.copied || 'Copied:') + ' ' + text);
        });
    }

    document.getElementById('createPostForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        postForm(formData)
            .then(data => {
                if (data.status === 'success') {
                    cms_alert(data.message);
                    closePostModal();
                    location.reload();
                } else {
                    document.getElementById('postModalError').textContent = data.message;
                    document.getElementById('postModalError').style.display = 'block';
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

                cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
            });
        });
    }

    const tagCreateForm = document.getElementById('blogTagCreateForm');
    if (tagCreateForm) {
        tagCreateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(tagCreateForm);
            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    appendTagManagerRow(data.tag);
                    upsertTagConfig(data.tag);
                    tagCreateForm.reset();
                    const input = tagCreateForm.querySelector('input[name="name"]');
                    if (input) input.focus();
                    return;
                }
                cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
            });
        });
    }

    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('[data-tag-edit]');
        if (editBtn) {
            e.preventDefault();
            e.stopPropagation();
            setTagRowEditing(editBtn.closest('[data-tag-row]'), true);
            return;
        }

        const renameBtn = e.target.closest('[data-tag-rename]');
        if (renameBtn) {
            e.preventDefault();
            e.stopPropagation();
            const row = renameBtn.closest('[data-tag-row]');
            const formData = new FormData();
            formData.append('action', 'update_tag_locale');
            formData.append('id', row.dataset.id || '');
            row.querySelectorAll('[data-tag-label]').forEach(input => {
                formData.append('labels[' + input.dataset.tagLabel + ']', input.value || '');
            });
            postForm(formData).then((data) => {
                if (data.status === 'success') {
                    if (data.tag) {
                        upsertTagConfig(data.tag);
                        appendTagManagerRow(data.tag);
                    }
                    setTagRowEditing(row, false);
                    return;
                }
                cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
            });
            return;
        }

        const deleteBtn = e.target.closest('[data-tag-delete]');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const row = deleteBtn.closest('[data-tag-row]');
            cms_confirm(TEXT.tag_delete_confirm || 'Delete tag from all posts?', () => {
                const formData = new FormData();
                formData.append('action', 'delete_tag');
                formData.append('id', row.dataset.id || '');
                postForm(formData).then((data) => {
                    if (data.status === 'success') {
                        location.reload();
                        return;
                    }
                    cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
                });
            });
        }
    });

    window.onclick = function(event) {
        if (event.target === document.getElementById('dirModal')) closeModal();
        if (event.target === document.getElementById('postModal')) closePostModal();
    };

    function deleteDir(id, posts) {
        cms_confirm((TEXT.delete_category_confirm || 'Delete category?') + (posts ? '\n' + (TEXT.delete_category_with_posts || '') : ''), () => {
            const formData = new FormData();
            formData.append('action', 'delete_dir');
            formData.append('id', id);

            postForm(formData).then(data => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
            });
        });
    }

    function deletePost(slug, title) {
        cms_confirm((TEXT.delete_post_confirm || 'Delete post?').replace('%s', title), () => {
            const formData = new FormData();
            formData.append('action', 'delete_post');
            formData.append('slug', slug);

            postForm(formData).then(data => {
                if (data.status === 'success') {
                    location.reload();
                    return;
                }

                cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
            });
        });
    }

    function duplicatePost(slug, title) {
        const formData = new FormData();
        formData.append('action', 'duplicate_post');
        formData.append('slug', slug);

        postForm(formData).then(data => {
            if (data.status === 'success') {
                cms_alert(data.message || (TEXT.post_duplicated || 'Post duplicated successfully'));
                location.reload();
                return;
            }

            cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
        });
    }

    function initPostActionButtons(root) {
        const scope = root || document;
        scope.addEventListener('click', function(event) {
            const button = event.target.closest('[data-blog-action]');
            if (!button) return;

            event.preventDefault();
            event.stopPropagation();

            const action = button.dataset.blogAction;
            const slug = button.dataset.slug || '';
            const title = button.dataset.title || '';
            const url = button.dataset.url || '';

            if (action === 'open-post-modal') {
                openPostModal();
                return;
            }

            if (action === 'open-dir-modal') {
                openDirModal(button.dataset.dirAction || 'add_dir');
                return;
            }

            if (action === 'open-tag-manager') {
                openTagManagerModal();
                return;
            }

            if (action === 'edit-dir') {
                openDirModal('edit_dir', button.dataset.dirId || '', button.dataset.dirName || '', button.dataset.dirParent || '');
                return;
            }

            if (action === 'delete-dir') {
                deleteDir(button.dataset.dirId || '', button.dataset.dirPosts || '');
                return;
            }

            if (action === 'open-media-selector') {
                openMediaSelector(button.dataset.inputId || '');
                return;
            }

            if (action === 'copy-url') {
                copyToClipboard(button.dataset.url || '');
                return;
            }

            if (action === 'view' && url) {
                window.open(url, '_blank');
                return;
            }

            if (action === 'duplicate' && slug) {
                duplicatePost(slug, title);
                return;
            }

            if (action === 'toggle-active' && slug) {
                toggleActive(slug);
                return;
            }

            if (action === 'edit' && url) {
                window.location.href = url;
                return;
            }

            if (action === 'delete' && slug) {
                deletePost(slug, title);
            }
        });
    }

    function toggleActive(slug) {
        const bodyParts = [
            'action=toggle_active',
            'slug=' + encodeURIComponent(slug)
        ];
        if (CSRF_TOKEN) {
            bodyParts.push('csrf_token=' + encodeURIComponent(CSRF_TOKEN));
        }
        fetch(BLOG_API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: bodyParts.join('&')
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                const escapedSlug = window.CSS && typeof window.CSS.escape === 'function'
                    ? window.CSS.escape(slug)
                    : String(slug).replace(/["\\]/g, '\\$&');
                const btn = document.querySelector('.active-btn[data-slug="' + escapedSlug + '"]');
                if (!btn) return;
                if (data.active) {
                    btn.classList.remove('inactive');
                    btn.classList.add('active');
                    appendIconMarkup(btn, cfg.iconToggleActive);
                } else {
                    btn.classList.remove('active');
                    btn.classList.add('inactive');
                    appendIconMarkup(btn, cfg.iconToggleInactive);
                }
            }
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
                    onEnd: function () { saveOrder(); }
                });
            }
        });
    }

    function saveOrder() {
        const items = [];
        document.querySelectorAll('.tree-item').forEach((el) => {
            const id = el.getAttribute('data-id');
            const type = el.getAttribute('data-type');
            const parentUl = el.closest('ul');
            const parentId = parentUl.getAttribute('data-parent-id') || null;
            const index = Array.from(parentUl.children).indexOf(el);
            items.push({ id: id, type: type, parent: parentId, order: index });
        });

        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('data', JSON.stringify(items));
        postForm(formData);
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

    function switchEditLanguage(el) {
        const lang = el.value;
        const container = el.closest('.page-edit-form');
        const form = container.querySelector('form');
        const locales = JSON.parse(form.dataset.locales || '{}');
        const primary = JSON.parse(form.dataset.primary || '{}');
        const primaryLang = form.dataset.primaryLang;

        const data = (lang === primaryLang) ? primary : (locales[lang] || { title: '', excerpt: '', slug: '', seo: {} });

        form.querySelectorAll('.lang-code').forEach(span => {
            span.textContent = lang.toUpperCase();
        });

        form.querySelector('[name="title"]').value = data.title || '';
        const excerptInput = form.querySelector('[name="excerpt"]');
        if (excerptInput) excerptInput.value = data.excerpt || '';
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
            if (lang === primaryLang) {
                path = '/blog/' + primary.slug;
            } else {
                path = '/' + lang + '/blog/' + (data.slug || primary.slug);
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

    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('page-edit-form-ajax')) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;

            btn.disabled = true;
            btn.textContent = '...';

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
                        excerpt: formData.get('excerpt') || '',
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
                    cms_alert((TEXT.error_prefix || 'Error: ') + (data.message || TEXT.unknown_error || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                cms_alert(TEXT.network_error || 'Network error');
                btn.disabled = false;
                btn.textContent = originalText;
            });
        }
    });

    initDirectoryStatePersistence(document.querySelector('.tree-view'), 'clipon-admin-blog-dir-state');
    initSortable();
    initTagPickers(document);
    initPostActionButtons(document);
    document.querySelectorAll('details').forEach(el => {
        el.addEventListener('toggle', () => { if (el.open) setTimeout(initSortable, 10); });
    });

    if (window.AdminUI && typeof window.AdminUI.initFlashToasts === 'function') {
        window.AdminUI.initFlashToasts(4000);
    }

    window.openDirModal = openDirModal;
    window.openPostModal = openPostModal;
    window.openTagManagerModal = openTagManagerModal;
    window.openMediaSelector = openMediaSelector;
    window.copyToClipboard = copyToClipboard;
    window.deleteDir = deleteDir;
    window.deletePost = deletePost;
    window.toggleActive = toggleActive;
    window.switchEditLanguage = switchEditLanguage;
    window.switchListingEditLang = switchListingEditLang;
})();
