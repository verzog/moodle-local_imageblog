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
 * Listing filter behaviour: rebuilds the subcategory dropdown when the
 * category dropdown changes, using the JSON map embedded on the listing
 * region's data attribute.
 *
 * @module     local_imageblog/filter
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
