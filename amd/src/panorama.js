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
// is", without warranty of any kind, express or implied.
//
// The bundled Pannellum library under thirdparty/ retains its own MIT
// licence; see that directory and thirdpartylibs.xml for details.

/**
 * Lazy loader and initialiser for the bundled Pannellum 360° viewer.
 *
 * Loads thirdparty/pannellum/{pannellum.js,pannellum.css} on first use,
 * then attaches a viewer to each `[data-region="local-imageblog-panorama"]`
 * element using the URL stored on its `data-imgurl` attribute.
 *
 * @module     local_imageblog/panorama
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
