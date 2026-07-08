export function t(key, fallback) {
    return (window.CLIPON_INLINE_EDITOR_I18N && window.CLIPON_INLINE_EDITOR_I18N[key]) || fallback;
}
