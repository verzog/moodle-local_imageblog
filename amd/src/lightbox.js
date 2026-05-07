// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Initialise GLightbox for clinical image zoom on the post view page.
 *
 * @module     local_scca_blog/lightbox
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const GLIGHTBOX_JS  = 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js';
const GLIGHTBOX_CSS = 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css';

/**
 * Dynamically load GLightbox JS (CSS is loaded server-side in view.php).
 *
 * @returns {Promise<object>} GLightbox constructor
 */
const loadGLightbox = () => new Promise((resolve, reject) => {
    if (window.GLightbox) {
        resolve(window.GLightbox);
        return;
    }
    const script = document.createElement('script');
    script.src = GLIGHTBOX_JS;
    script.onload = () => resolve(window.GLightbox);
    script.onerror = reject;
    document.head.appendChild(script);
});

/**
 * Wrap all clinical images in the post body with lightbox anchor tags.
 * Images must have the class `scca-blog-image` and optionally a
 * `data-href` attribute pointing to a higher-res version.
 */
const wrapImages = () => {
    document.querySelectorAll('.scca-blog-post-body img.scca-blog-image').forEach((img) => {
        if (img.closest('a')) {
            return; // Already wrapped.
        }
        const href = img.dataset.href || img.src;
        const a    = document.createElement('a');
        a.href          = href;
        a.className     = 'glightbox';
        a.dataset.type  = 'image';
        a.dataset.title = img.alt || '';
        img.parentNode.insertBefore(a, img);
        a.appendChild(img);
    });
};

/**
 * Initialise lightbox on the post view page.
 * Called from view.php via $PAGE->requires->js_call_amd().
 */
export const init = async() => {
    try {
        wrapImages();
        const GLightbox = await loadGLightbox();
        GLightbox({
            selector:        '.glightbox',
            touchNavigation: true,
            loop:            false,
            zoomable:        true,
            openEffect:      'fade',
            closeEffect:     'fade',
        });
    } catch (err) {
        // Non-fatal — images still display inline without lightbox.
        // eslint-disable-next-line no-console
        console.warn('[scca_blog] lightbox init failed:', err);
    }
};
