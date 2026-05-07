// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Client-side image processor.
 *
 * Intercepts file inputs, resizes/compresses images in the browser
 * before they are uploaded. Supports JPEG, PNG, WebP, and TIFF
 * (via UTIF.js loaded on demand).
 *
 * @module     local_scca_blog/image_processor
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const UTIF_CDN = 'https://cdn.jsdelivr.net/npm/utif2@3.1.0/UTIF.js';

/**
 * Load UTIF.js on demand — only pays the 80kb cost when a TIFF is selected.
 *
 * @returns {Promise<object>} UTIF global
 */
const loadUTIF = () => new Promise((resolve, reject) => {
    if (window.UTIF) {
        resolve(window.UTIF);
        return;
    }
    const script = document.createElement('script');
    script.src = UTIF_CDN;
    script.onload = () => resolve(window.UTIF);
    script.onerror = reject;
    document.head.appendChild(script);
});

/**
 * Decode a TIFF ArrayBuffer to an ImageData via UTIF.
 *
 * @param {ArrayBuffer} buffer
 * @returns {Promise<ImageData>}
 */
const decodeTiff = async(buffer) => {
    const UTIF = await loadUTIF();
    const ifds = UTIF.decode(buffer);
    UTIF.decodeImage(buffer, ifds[0]);
    const rgba = UTIF.toRGBA8(ifds[0]);
    return new ImageData(
        new Uint8ClampedArray(rgba.buffer),
        ifds[0].width,
        ifds[0].height
    );
};

/**
 * Resize an ImageBitmap or ImageData to fit within maxWidth × maxHeight,
 * preserving aspect ratio. Returns a Blob.
 *
 * @param {ImageBitmap|ImageData} source
 * @param {number} maxWidth
 * @param {number} maxHeight
 * @param {number} quality   0–1, used for JPEG output
 * @param {string} mimeType  'image/jpeg' or 'image/png'
 * @returns {Promise<Blob>}
 */
const resizeToBlob = (source, maxWidth, maxHeight, quality, mimeType) => new Promise((resolve) => {
    const srcW = source.width;
    const srcH = source.height;

    const ratio = Math.min(maxWidth / srcW, maxHeight / srcH, 1); // never upscale
    const dstW  = Math.round(srcW * ratio);
    const dstH  = Math.round(srcH * ratio);

    const canvas  = document.createElement('canvas');
    canvas.width  = dstW;
    canvas.height = dstH;
    const ctx = canvas.getContext('2d');

    if (source instanceof ImageData) {
        // Paint raw TIFF pixel data via an offscreen canvas first.
        const tmp   = document.createElement('canvas');
        tmp.width   = srcW;
        tmp.height  = srcH;
        tmp.getContext('2d').putImageData(source, 0, 0);
        ctx.drawImage(tmp, 0, 0, dstW, dstH);
    } else {
        ctx.drawImage(source, 0, 0, dstW, dstH);
    }

    canvas.toBlob(resolve, mimeType, quality);
});

/**
 * Format a byte count as a human-readable string (e.g. "2.4 MB").
 *
 * @param {number} bytes
 * @returns {string}
 */
const formatBytes = (bytes) => {
    if (bytes < 1024)       { return bytes + ' B'; }
    if (bytes < 1048576)    { return (bytes / 1024).toFixed(1) + ' KB'; }
    return (bytes / 1048576).toFixed(1) + ' MB';
};

/**
 * Show inline status text next to the file input.
 *
 * @param {HTMLElement} input
 * @param {string}      message
 * @param {string}      [cls]  CSS class suffix (processing|done|error)
 */
const showStatus = (input, message, cls = 'processing') => {
    let el = input.parentElement.querySelector('.scca-image-status');
    if (!el) {
        el = document.createElement('small');
        el.className = 'scca-image-status form-text';
        input.insertAdjacentElement('afterend', el);
    }
    el.textContent = message;
    el.dataset.state = cls;
};

/**
 * Process a single File through the pipeline.
 *
 * @param {File}   file
 * @param {object} config  { maxWidth, maxHeight, quality, mode }
 * @returns {Promise<File>}  Processed File ready for upload
 */
const processFile = async(file, config) => {
    const isTiff = /\.tiff?$/i.test(file.name) || file.type === 'image/tiff';
    const originalSize = file.size;

    // Output format: TIFF → PNG (lossless), others → JPEG for featured, PNG for body.
    const outputMime = (config.mode === 'featured') ? 'image/jpeg' : 'image/png';
    const outputExt  = (outputMime === 'image/jpeg') ? '.jpg' : '.png';
    const outputName = file.name.replace(/\.[^.]+$/, outputExt);

    let source;

    if (isTiff) {
        const buffer = await file.arrayBuffer();
        source = await decodeTiff(buffer);
    } else {
        const bitmap = await createImageBitmap(file);
        source = bitmap;
    }

    const blob = await resizeToBlob(source, config.maxWidth, config.maxHeight, config.quality, outputMime);
    const processed = new File([blob], outputName, {type: outputMime, lastModified: Date.now()});
    processed._originalSize = originalSize;

    return processed;
};

/**
 * Attach the processor to a file input element.
 *
 * @param {HTMLElement} input   The <input type="file"> element
 * @param {object}      config
 */
const attachToInput = (input, config) => {
    input.addEventListener('change', async(e) => {
        const files = Array.from(e.target.files);
        if (!files.length) { return; }

        const file = files[0];

        // Skip non-image files gracefully.
        if (!file.type.startsWith('image/') && !/\.tiff?$/i.test(file.name)) {
            return;
        }

        showStatus(input, M.str.local_scca_blog.imageprocessing, 'processing');

        try {
            const processed = await processFile(file, config);
            const fromStr   = formatBytes(processed._originalSize);
            const toStr     = formatBytes(processed.size);

            // Replace the FileList on the input via DataTransfer.
            const dt = new DataTransfer();
            dt.items.add(processed);
            input.files = dt.files;

            showStatus(
                input,
                `✓ ${M.str.local_scca_blog.imageoptimised
                    .replace('{$a->from}', fromStr)
                    .replace('{$a->to}', toStr)}`,
                'done'
            );
        } catch (err) {
            // On failure, leave the original file in place — server-side maxbytes acts as backstop.
            showStatus(input, '⚠ Could not process image — uploading original', 'error');
            // eslint-disable-next-line no-console
            console.warn('[scca_blog] image_processor error:', err);
        }
    });
};

/**
 * Initialise the image processor.
 *
 * Called from edit.php via $PAGE->requires->js_call_amd().
 *
 * @param {object} config
 * @param {string} config.featuredSelector  CSS selector for featured image input
 * @param {number} config.maxWidth
 * @param {number} config.maxHeight
 * @param {number} config.quality
 * @param {string} config.mode              'featured' | 'body'
 */
export const init = (config) => {
    // Wait for DOM ready.
    const attach = () => {
        const input = document.querySelector(config.featuredSelector);
        if (input) {
            attachToInput(input, config);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }
};

/**
 * Exported for use by the TinyMCE editor init module.
 *
 * @param {File}   file
 * @param {object} config
 * @returns {Promise<File>}
 */
export {processFile};
