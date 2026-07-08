export function clearNode(node) {
    while (node && node.firstChild) {
        node.removeChild(node.firstChild);
    }
}

export function appendSvgIcon(target, svgMarkup) {
    if (!target) return false;

    const raw = String(svgMarkup || '').trim();
    if (!raw) {
        clearNode(target);
        return true;
    }

    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(raw, 'text/html');
        const svg = doc.querySelector('svg');
        if (!svg) {
            return false;
        }

        clearNode(target);
        target.appendChild(svg);
        return true;
    } catch (e) {
        return false;
    }
}
