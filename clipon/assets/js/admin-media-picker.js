/**
 * Admin Media Picker Bridge
 * Reuses the frontend media picker for admin inputs.
 */

import { initMediaModal } from './modules/ui.js';
import { loadMediaLibrary, handleUpload, mediaState } from './modules/media.js';

function openMediaPicker(opts = {}) {
    mediaState.currentImage = null;
    mediaState.cliponCurrentTiptapEditor = null;
    mediaState.onSelect = typeof opts.onSelect === 'function' ? opts.onSelect : null;

    initMediaModal(handleUpload);
    loadMediaLibrary(opts.dir || '');

    const modal = document.getElementById('media-modal');
    if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    }
}

function closeMediaPicker() {
    const modal = document.getElementById('media-modal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

window.CliponMediaPicker = {
    open: openMediaPicker,
    close: closeMediaPicker
};
