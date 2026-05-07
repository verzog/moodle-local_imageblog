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
 * Native image lightbox for the post view page.
 *
 * Uses the platform <dialog> element to show a full-size version of any
 * image clicked inside the post body, with no external dependencies.
 *
 * @module     local_imageblog/lightbox
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Log from 'core/log';

const SELECTOR = {
    body:   '.local-imageblog-post-body',
    image:  '.local-imageblog-post-body img',
    dialog: '.local-imageblog-lightbox',
    close:  '[data-action="close"]',
    img:    '.local-imageblog-lightbox img',
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

const openImage = async(src, alt) => {
    const dialog = await ensureDialog();
    const img = dialog.querySelector(SELECTOR.img);
    img.src = src;
    img.alt = alt || '';
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
        openImage(img.dataset.href || img.src, img.alt).catch((err) => {
            Log.debug('local_imageblog/lightbox failed: ' + err);
        });
    });
};
