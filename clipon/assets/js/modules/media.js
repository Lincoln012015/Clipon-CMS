/**
 * Media Manager Module
 * Handles media library loading, selection and image/video resizing inside editors.
 */

import { getMediaList, uploadMedia, saveContent } from './api.js';
import { cliponNormalizeContentHtml } from './utils.js';
import { showSaveIndicator, showSaveError } from './ui.js';
import { t } from './i18n.js';

export let currentMediaDir = '';
export let mediaState = {
    currentImage: null,
    pageSlug: '',
    pageType: '',
    cliponCurrentTiptapEditor: null,
    cliponCurrentTiptapMediaType: null,
    onSelect: null
};

function clearNode(node) {
    while (node && node.firstChild) {
        node.removeChild(node.firstChild);
    }
}

function createSvgIcon(pathDOrPoints, kind) {
    const svgNs = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNs, 'svg');
    svg.setAttribute('viewBox', '0 0 256 256');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', kind === 'chevron' ? '24' : '16');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');

    if (kind === 'dashboard') {
        [['40', '40'], ['144', '40'], ['40', '144'], ['144', '144']].forEach((coords) => {
            const rect = document.createElementNS(svgNs, 'rect');
            rect.setAttribute('x', coords[0]);
            rect.setAttribute('y', coords[1]);
            rect.setAttribute('width', '72');
            rect.setAttribute('height', '72');
            rect.setAttribute('rx', '8');
            svg.appendChild(rect);
        });
        return svg;
    }

    if (kind === 'chevron') {
        const polyline = document.createElementNS(svgNs, 'polyline');
        polyline.setAttribute('points', '96 48 176 128 96 208');
        svg.appendChild(polyline);
        return svg;
    }

    if (kind === 'folder') {
        const path = document.createElementNS(svgNs, 'path');
        path.setAttribute('d', 'M32,80V192a8,8,0,0,0,8,8H216a8,8,0,0,0,8-8V96a8,8,0,0,0-8-8H120L96,56H40a8,8,0,0,0-8,8Z');
        svg.appendChild(path);
        return svg;
    }

    const path = document.createElementNS(svgNs, 'path');
    path.setAttribute('d', pathDOrPoints || '');
    svg.appendChild(path);
    return svg;
}

function createFileIcon() {
    const svgNs = 'http://www.w3.org/2000/svg';
    const svg = createSvgIcon('M48,32H160l48,48V224a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40A8,8,0,0,1,48,32Z', 'path');
    [['152 32 152 88 208 88', 'polyline'], ['80', '128', '176', '128', 'line'], ['80', '160', '176', '160', 'line'], ['80', '192', '136', '192', 'line']].forEach((entry) => {
        if (entry[1] === 'polyline') {
            const poly = document.createElementNS(svgNs, 'polyline');
            poly.setAttribute('points', entry[0]);
            svg.appendChild(poly);
        } else {
            const line = document.createElementNS(svgNs, 'line');
            line.setAttribute('x1', entry[0]);
            line.setAttribute('y1', entry[1]);
            line.setAttribute('x2', entry[2]);
            line.setAttribute('y2', entry[3]);
            svg.appendChild(line);
        }
    });
    return svg;
}

function setStatusNode(container, text, isError) {
    clearNode(container);
    const status = document.createElement('div');
    status.className = isError
        ? 'media-picker-status media-picker-status-error'
        : 'media-picker-status';
    status.textContent = String(text || '');
    container.appendChild(status);
}

export async function loadMediaLibrary(dir = '') {
    currentMediaDir = dir || '';
    const list = document.getElementById('media-list');
    const breadcrumbContainer = document.getElementById('media-breadcrumb-container');

    if (!list) {
        return;
    }

    setStatusNode(list, t('loading', 'Loading...'), false);
    if (breadcrumbContainer) {
        clearNode(breadcrumbContainer);
    }
    
    try {
        const data = await getMediaList(currentMediaDir);
        clearNode(list);

        if (breadcrumbContainer) {
            const parts = currentMediaDir ? currentMediaDir.split('/').filter(Boolean) : [];
            const breadcrumb = document.createElement('div');
            breadcrumb.className = 'breadcrumb';

            const homeLink = document.createElement('a');
            homeLink.href = '#';
            homeLink.className = 'media-nav-home';
            const homeIconWrap = document.createElement('span');
            homeIconWrap.className = 'media-icon';
            homeIconWrap.setAttribute('aria-hidden', 'true');
            homeIconWrap.appendChild(createSvgIcon('', 'dashboard'));
            homeLink.appendChild(homeIconWrap);
            homeLink.appendChild(document.createTextNode(t('home', 'Home')));
            breadcrumb.appendChild(homeLink);

            let path = '';
            parts.forEach((part) => {
                path += (path ? '/' : '') + part;

                const iconWrap = document.createElement('span');
                iconWrap.className = 'breadcrumb-icon';
                iconWrap.setAttribute('aria-hidden', 'true');
                iconWrap.appendChild(createSvgIcon('', 'chevron'));
                breadcrumb.appendChild(iconWrap);

                const dirLink = document.createElement('a');
                dirLink.href = '#';
                dirLink.className = 'media-nav-dir';
                dirLink.dataset.dir = path;
                dirLink.textContent = String(part);
                breadcrumb.appendChild(dirLink);
            });
            breadcrumbContainer.appendChild(breadcrumb);

            if (homeLink) {
                homeLink.onclick = (e) => {
                    e.preventDefault();
                    loadMediaLibrary('');
                };
            }

            breadcrumb.querySelectorAll('.media-nav-dir').forEach((link) => {
                link.onclick = (e) => {
                    e.preventDefault();
                    loadMediaLibrary(e.currentTarget.dataset.dir || '');
                };
            });
        }
        
        if (data.items.length === 0) {
            setStatusNode(list, t('emptyFolder', 'Folder is empty'), false);
        }
        
        data.items.forEach(item => {
            const div = document.createElement('div');
            
            if (item.type === 'folder') {
                div.className = 'media-item folder-item media-picker-item';

                const preview = document.createElement('div');
                preview.className = 'media-preview';
                const folderIconWrap = document.createElement('div');
                folderIconWrap.className = 'folder-preview-icon';
                folderIconWrap.setAttribute('aria-hidden', 'true');
                folderIconWrap.appendChild(createSvgIcon('', 'folder'));
                preview.appendChild(folderIconWrap);

                const nameNode = document.createElement('div');
                nameNode.className = 'media-name';
                nameNode.setAttribute('data-tooltip', String(item.name || ''));
                nameNode.textContent = String(item.name || '');

                div.appendChild(preview);
                div.appendChild(nameNode);
                div.onclick = () => loadMediaLibrary(item.dir || item.name);
            } else {
                div.className = 'media-item media-picker-item';
                const preview = document.createElement('div');
                preview.className = 'media-preview';
                const nameNode = document.createElement('div');
                nameNode.className = 'media-name';
                nameNode.setAttribute('data-tooltip', String(item.name || ''));
                nameNode.textContent = String(item.name || '');

                if (item.is_image) {
                    const img = document.createElement('img');
                    img.src = String(item.path || '');
                    img.loading = 'lazy';
                    img.alt = String(item.name || '');
                    preview.appendChild(img);
                } else if (item.is_video) {
                    const video = document.createElement('video');
                    video.src = String(item.path || '');
                    video.muted = true;
                    video.preload = 'metadata';
                    preview.appendChild(video);
                } else {
                    const fileIconWrap = document.createElement('div');
                    fileIconWrap.className = 'media-icon media-icon-file';
                    fileIconWrap.setAttribute('aria-hidden', 'true');
                    fileIconWrap.appendChild(createFileIcon());
                    preview.appendChild(fileIconWrap);
                }

                div.appendChild(preview);
                div.appendChild(nameNode);
                div.onclick = () => selectMedia(item);
            }
            list.appendChild(div);
        });
    } catch (err) {
        const errorText = 'Error: ' + (err && err.message ? err.message : 'Unknown error');
        setStatusNode(list, errorText, true);
    }
}

export async function selectMedia(item) {
    const path = item.path;

    if (typeof mediaState.onSelect === 'function') {
        const cb = mediaState.onSelect;
        mediaState.onSelect = null;
        cb(item);
        const modal = document.getElementById('media-modal');
        modal?.classList.remove('open');
        modal?.setAttribute('aria-hidden', 'true');
        return;
    }

    if (mediaState.currentImage) {
        const image = mediaState.currentImage;
        const previousSrc = image.getAttribute('src') || '';
        const key = image.id;
        image.src = path;

        try {
            await saveContent(mediaState.pageSlug, key, path, mediaState.pageType, undefined, {
                contentKind: 'image_src'
            });
            showSaveIndicator();
            const modal = document.getElementById('media-modal');
            modal?.classList.remove('open');
            modal?.setAttribute('aria-hidden', 'true');
        } catch (err) {
            console.error('Failed to save image source:', err);
            image.src = previousSrc;
            showSaveError(t('imageSaveError', 'Failed to save image. Try again.'));
        } finally {
            mediaState.currentImage = null;
        }
        return;
    }

    if (mediaState.cliponCurrentTiptapEditor) {
        const editor = mediaState.cliponCurrentTiptapEditor;
        if (item.is_video) {
            editor.chain().focus().insertContent(`<video src="${path}" controls playsinline preload="metadata"></video>`).run();
        } else {
            editor.chain().focus().setImage({ src: path }).run();
        }
        const modal = document.getElementById('media-modal');
        modal?.classList.remove('open');
        modal?.setAttribute('aria-hidden', 'true');
        mediaState.cliponCurrentTiptapEditor = null;
    }
}

export async function handleUpload() {
    const input = document.getElementById('modal-upload-input');
    if (!input.files[0]) return;
    
    const btn = document.getElementById('modal-upload-btn');
    const originalText = btn.innerText;
    btn.innerText = t('loading', 'Loading...');
    btn.disabled = true;

    try {
        const res = await uploadMedia(input.files[0], currentMediaDir);
        if (res.success) {
            loadMediaLibrary(currentMediaDir);
            input.value = '';
        } else {
            alert('Error: ' + (res.error || 'Unknown error'));
        }
    } catch (e) {
        alert(t('uploadFailed', 'Upload failed'));
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}
