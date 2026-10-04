// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lazy loader and initialiser for the bundled Pannellum 360° viewer.
 *
 * Loads thirdparty/pannellum/{pannellum.js,pannellum.css} on first use,
 * then attaches a viewer to each `[data-region="local-imageblog-panorama"]`
 * element using the URL stored on its `data-imgurl` attribute.
 *
 * @module     local_imageblog/panorama
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let loadPromise = null;

const loadPannellum = (jsUrl, cssUrl) => {
    if (loadPromise) {
        return loadPromise;
    }
    loadPromise = new Promise((resolve, reject) => {
        if (window.pannellum) {
            resolve(window.pannellum);
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssUrl;
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = jsUrl;
        script.async = true;
        script.onload = () => {
            if (window.pannellum) {
                resolve(window.pannellum);
            } else {
                reject(new Error('Pannellum loaded but global is missing'));
            }
        };
        script.onerror = () => reject(new Error('Pannellum failed to load'));
        document.head.appendChild(script);
    });
    return loadPromise;
};

/**
 * Initialise all panorama regions on the page.
 *
 * @param {string} jsUrl Absolute URL to pannellum.js.
 * @param {string} cssUrl Absolute URL to pannellum.css.
 */
export const init = (jsUrl, cssUrl) => {
    const regions = document.querySelectorAll('[data-region="local-imageblog-panorama"]');
    if (!regions.length) {
        return;
    }
    loadPannellum(jsUrl, cssUrl).then(pannellum => {
        regions.forEach(region => {
            if (region.dataset.initialised === '1') {
                return;
            }
            const imgUrl = region.dataset.imgurl;
            if (!imgUrl) {
                return;
            }
            region.dataset.initialised = '1';
            pannellum.viewer(region, {
                type: 'equirectangular',
                panorama: imgUrl,
                autoLoad: true,
                showControls: true,
                hfov: 100,
            });
        });
        return regions;
    }).catch(() => {
        // Surface a fallback message but don't break the page.
        regions.forEach(region => {
            region.classList.add('local-imageblog-panorama-fallback');
        });
    });
};
