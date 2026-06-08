// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side image processor.
 *
 * Resizes and re-encodes images in the browser before upload using the
 * native Canvas / createImageBitmap APIs. No external dependencies.
 *
 * @module     local_imageblog/image_processor
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import Templates from 'core/templates';
import Log from 'core/log';

const resizeToBlob = (bitmap, maxWidth, maxHeight, quality, mimeType) => new Promise((resolve) => {
    const ratio = Math.min(maxWidth / bitmap.width, maxHeight / bitmap.height, 1);
    const dstW = Math.round(bitmap.width * ratio);
    const dstH = Math.round(bitmap.height * ratio);
    const canvas = document.createElement('canvas');
    canvas.width = dstW;
    canvas.height = dstH;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, dstW, dstH);
    canvas.toBlob(resolve, mimeType, quality);
});

const formatBytes = (bytes) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1048576) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / 1048576).toFixed(1)} MB`;
};

const showStatus = async(input, message, state) => {
    const {html} = await Templates.renderForPromise('local_imageblog/image_status', {
        message,
        processing: state === 'processing',
        done: state === 'done',
        error: state === 'error',
    });
    const existing = input.parentElement.querySelector('.local-imageblog-image-status');
    if (existing) {
        existing.outerHTML = html;
    } else {
        input.insertAdjacentHTML('afterend', html);
    }
};

const processFile = async(file, config) => {
    const originalSize = file.size;
    const outputMime = config.mode === 'featured' ? 'image/jpeg' : 'image/png';
    const outputExt = outputMime === 'image/jpeg' ? '.jpg' : '.png';
    const outputName = file.name.replace(/\.[^.]+$/, outputExt);

    const bitmap = await createImageBitmap(file);
    const blob = await resizeToBlob(bitmap, config.maxWidth, config.maxHeight, config.quality, outputMime);
    const processed = new File([blob], outputName, {type: outputMime, lastModified: Date.now()});
    processed._originalSize = originalSize;
    return processed;
};

const attachToInput = (input, config) => {
    input.addEventListener('change', async(e) => {
        const files = Array.from(e.target.files);
        if (!files.length) {
            return;
        }
        const file = files[0];

        // Browsers cannot decode TIFF natively; let it upload unchanged.
        if (!file.type.startsWith('image/') || file.type === 'image/tiff') {
            return;
        }

        try {
            const processingMsg = await getString('imageprocessing', 'local_imageblog');
            await showStatus(input, processingMsg, 'processing');

            const processed = await processFile(file, config);
            const fromStr = formatBytes(processed._originalSize);
            const toStr = formatBytes(processed.size);

            const dt = new DataTransfer();
            dt.items.add(processed);
            input.files = dt.files;

            const doneMsg = await getString('imageoptimised', 'local_imageblog',
                {from: fromStr, to: toStr});
            await showStatus(input, doneMsg, 'done');
        } catch (err) {
            const errorMsg = await getString('imageprocesserror', 'local_imageblog');
            await showStatus(input, errorMsg, 'error');
            Log.debug('local_imageblog/image_processor failed: ' + err);
        }
    });
};

export const init = (config) => {
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

export {processFile};
