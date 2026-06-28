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
 * Listing filter behaviour: rebuilds the subcategory dropdown when the
 * category dropdown changes, using the JSON map embedded on the listing
 * region's data attribute.
 *
 * @module     local_imageblog/filter
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

import {get_string as getString} from 'core/str';

/**
 * Initialise the listing filter behaviour.
 */
export const init = () => {
    const region = document.querySelector('[data-region="local-imageblog-filters"]');
    if (!region) {
        return;
    }
    const categorySelect = region.querySelector('[data-action="category-change"]');
    const subSelect = region.querySelector('[data-region="subcategory"]');
    if (!categorySelect || !subSelect) {
        return;
    }

    let map = {};
    try {
        map = JSON.parse(region.dataset.subcats || '{}');
    } catch (e) {
        return;
    }

    const initialPlaceholder = subSelect.options.length
        ? subSelect.options[0].textContent
        : '';

    const rebuild = async() => {
        const categoryid = categorySelect.value;
        const placeholder = categoryid
            ? initialPlaceholder
            : await getString('selectsubcategory', 'local_imageblog');

        subSelect.innerHTML = '';
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = placeholder;
        subSelect.appendChild(blank);

        const list = (categoryid && map[categoryid]) ? map[categoryid] : [];
        list.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            subSelect.appendChild(option);
        });
    };

    categorySelect.addEventListener('change', rebuild);
};
