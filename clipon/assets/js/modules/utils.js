/**
 * Utils Module
 * Helper functions for HTML normalization and other common tasks.
 */

function parseHtmlDocument(html) {
    const parser = new DOMParser();
    return parser.parseFromString(String(html || ''), 'text/html');
}

function serializeBodyContent(doc) {
    if (!doc || !doc.body) return '';

    const serializer = new XMLSerializer();
    let out = '';
    doc.body.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            out += node.nodeValue || '';
            return;
        }
        out += serializer.serializeToString(node);
    });
    return out;
}

export function decodeHtmlEntities(value) {
    const textarea = document.createElement('textarea');
    let decoded = String(value || '');

    for (let i = 0; i < 3; i++) {
        textarea.innerHTML = decoded;
        const next = textarea.value;
        if (next === decoded) break;
        decoded = next;
    }

    return decoded;
}

export function cliponNormalizeContentHtml(html) {
    if (!html || typeof html !== 'string') return html;
    if (html.indexOf('ql-align-') === -1) return html;

    const doc = parseHtmlDocument(html);
    const root = doc.body;

    const alignMap = {
        'ql-align-center': 'center',
        'ql-align-right': 'right',
        'ql-align-justify': 'justify',
        'ql-align-left': 'left'
    };

    Object.keys(alignMap).forEach(cls => {
        const nodes = root.querySelectorAll('.' + cls);
        nodes.forEach(node => {
            const align = alignMap[cls];
            const tag = node.tagName ? node.tagName.toLowerCase() : '';

            if (tag === 'iframe' || tag === 'video' || tag === 'img') {
                node.style.display = 'block';
                if (align === 'center') {
                    node.style.marginLeft = 'auto';
                    node.style.marginRight = 'auto';
                } else if (align === 'right') {
                    node.style.marginLeft = 'auto';
                    node.style.marginRight = '0';
                } else {
                    node.style.marginLeft = '0';
                    node.style.marginRight = 'auto';
                }
            } else {
                node.style.textAlign = align;
            }
        });
    });

    return serializeBodyContent(doc);
}

export function cliponEnhanceMarkdownHtml(html) {
    if (!html || typeof html !== 'string') return html;

    const doc = parseHtmlDocument(html);
    const root = doc.body;

    // Tables
    root.querySelectorAll('table').forEach(table => {
        if (!table.style.borderCollapse) table.style.borderCollapse = 'collapse';
        if (!table.style.width) table.style.width = '100%';
    });

    root.querySelectorAll('th, td').forEach(cell => {
        if (!cell.style.border) cell.style.border = '1px solid #ddd';
        if (!cell.style.padding) cell.style.padding = '4px 6px';
    });

    // Task lists
    root.querySelectorAll('ul.contains-task-list').forEach(ul => {
        if (!ul.style.listStyle) ul.style.listStyle = 'none';
        if (!ul.style.paddingLeft) ul.style.paddingLeft = '0';
    });

    root.querySelectorAll('li.task-list-item').forEach(li => {
        if (!li.style.listStyle) li.style.listStyle = 'none';
        const cb = li.querySelector('input[type="checkbox"]');
        if (cb && !cb.style.marginRight) cb.style.marginRight = '8px';
    });

    return serializeBodyContent(doc);
}

export function getVideoEmbedUrl(url) {
    try {
        const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
        if (ytMatch && ytMatch[1]) return `https://www.youtube.com/embed/${ytMatch[1]}`;

        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (vimeoMatch && vimeoMatch[1]) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    } catch (e) {
        console.error('getVideoEmbedUrl error', e);
    }
    return null;
}

export function loadScriptOnce(src, globalName) {
    return new Promise((resolve, reject) => {
        if (globalName && window[globalName]) return resolve();
        const existing = Array.from(document.getElementsByTagName('script')).find(s => s.src === src);
        if (existing) return resolve();
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => resolve();
        s.onerror = (e) => reject(e);
        document.head.appendChild(s);
    });
}

export function loadStyleOnce(href) {
    if (document.querySelector(`link[href="${href}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

export function ensureStyleLoaded(href, options = {}) {
    const {
        id = '',
        fallbackCss = '',
        timeoutMs = 2500
    } = options;

    return new Promise((resolve) => {
        const existingByHref = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
            .find((l) => l.href === href);
        if (existingByHref) {
            resolve({ status: 'already' });
            return;
        }

        const existingById = id ? document.getElementById(id) : null;
        if (existingById) {
            resolve({ status: 'already' });
            return;
        }

        let settled = false;

        const finish = (status) => {
            if (settled) return;
            settled = true;
            resolve({ status });
        };

        const injectFallback = () => {
            if (!fallbackCss || !id) {
                finish('no-fallback');
                return;
            }

            const fallbackId = `${id}-fallback`;
            if (document.getElementById(fallbackId)) {
                finish('fallback-already');
                return;
            }

            const style = document.createElement('style');
            style.id = fallbackId;
            style.textContent = fallbackCss;
            document.head.appendChild(style);
            finish('fallback');
        };

        const link = document.createElement('link');
        if (id) link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = () => finish('loaded');
        link.onerror = injectFallback;
        document.head.appendChild(link);

        window.setTimeout(() => {
            if (!settled) injectFallback();
        }, timeoutMs);
    });
}
