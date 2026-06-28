// Copyright (c) Vernon Apain / Educheckout.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Vernon Apain /
// Educheckout for use with Moodle. It is NOT free software and is NOT
// released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Educheckout. The software is provided "as
// is", without warranty of any kind, express or implied. The bundled
// Pannellum library under thirdparty/ retains its own MIT licence; see
// that directory and thirdpartylibs.xml for details.

/**
 * Native image lightbox for the post view page.
 *
 * Uses the platform <dialog> element to show a full-size version of any
 * image clicked inside the post body, with no external dependencies.
 *
 * @module     local_imageblog/lightbox
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

import Templates from 'core/templates';
import Log from 'core/log';

const SELECTOR = {
    body:    '.local-imageblog-post-body',
    image:   '.local-imageblog-post-body img',
    dialog:  '.local-imageblog-lightbox',
    close:   '[data-action="close"]',
    img:     '.local-imageblog-lightbox img',
    caption: '.local-imageblog-lightbox figcaption',
};

/**
 * Derive a title/caption for the clicked image.
 *
 * Looks, in order, at an enclosing <figure>'s <figcaption>, a Bootstrap
 * card wrapper (as produced by the tiny_bootstrap card group button), and
 * finally the image's own title/alt attributes.
 *
 * @param {HTMLImageElement} img
 * @return {?{title: string, text: string}}
 */
const captionFor = (img) => {
    const figure = img.closest('figure');
    if (figure) {
        const figcaption = figure.querySelector('figcaption');
        const text = figcaption ? figcaption.textContent.trim() : '';
        if (text) {
            return {title: '', text};
        }
    }

    const card = img.closest('.card');
    if (card) {
        const titleEl = card.querySelector('.card-title');
        const textEl = card.querySelector('.card-text');
        const title = titleEl ? titleEl.textContent.trim() : '';
        const text = textEl ? textEl.textContent.trim() : '';
        if (title || text) {
            return {title, text};
        }
    }

    const title = (img.getAttribute('title') || '').trim();
    const alt = (img.getAttribute('alt') || '').trim();
    if (title || alt) {
        return {title: '', text: title || alt};
    }

    return null;
};

const ensureDialog = async() => {
    let dialog = document.querySelector(SELECTOR.dialog);
    if (dialog) {
        return dialog;
    }
    const {html} = await Templates.renderForPromise('local_imageblog/lightbox', {});
    document.body.insertAdjacentHTML('beforeend', html);
    dialog = document.querySelector(SELECTOR.dialog);

    dialog.addEventListener('click', (e) => {
        if (e.target.matches(SELECTOR.close) || e.target === dialog) {
            dialog.close();
        }
    });
    return dialog;
};

const openImage = async(src, alt, caption) => {
    const dialog = await ensureDialog();
    const img = dialog.querySelector(SELECTOR.img);
    img.src = src;
    img.alt = alt || '';

    const figcaption = dialog.querySelector(SELECTOR.caption);
    if (figcaption) {
        figcaption.textContent = '';
        if (caption && (caption.title || caption.text)) {
            if (caption.title) {
                const strong = document.createElement('strong');
                strong.textContent = caption.title;
                figcaption.appendChild(strong);
            }
            if (caption.text) {
                if (caption.title) {
                    figcaption.appendChild(document.createElement('br'));
                }
                figcaption.appendChild(document.createTextNode(caption.text));
            }
            figcaption.hidden = false;
        } else {
            figcaption.hidden = true;
        }
    }

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', 'open');
    }
};

export const init = () => {
    const body = document.querySelector(SELECTOR.body);
    if (!body) {
        return;
    }
    body.addEventListener('click', (e) => {
        const img = e.target.closest(SELECTOR.image);
        if (!img) {
            return;
        }
        e.preventDefault();
        openImage(img.dataset.href || img.src, img.alt, captionFor(img)).catch((err) => {
            Log.debug('local_imageblog/lightbox failed: ' + err);
        });
    });
};
