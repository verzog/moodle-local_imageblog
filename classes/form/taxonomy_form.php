<?php
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

/**
 * Polymorphic create/edit form for any taxonomy type.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog\form;

use local_imageblog\taxonomy;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit a single taxonomy row across all four types.
 */
class taxonomy_form extends moodleform {
    /**
     * Define form elements based on the requested taxonomy type.
     */
    public function definition(): void {
        $mform = $this->_form;
        $type = (string)$this->_customdata['type'];
        $record = $this->_customdata['record'] ?? null;

        $mform->addElement('hidden', 'id', $record->id ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'type', $type);
        $mform->setType('type', PARAM_ALPHA);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        if ($type === taxonomy::TYPE_SUBCATEGORY) {
            $categories = taxonomy::all(taxonomy::TYPE_CATEGORY);
            $options = ['' => get_string('selectcategory', 'local_imageblog')];
            foreach ($categories as $cat) {
                $options[$cat->id] = $cat->name;
            }
            $mform->addElement(
                'select',
                'categoryid',
                get_string('category', 'local_imageblog'),
                $options
            );
            $mform->setType('categoryid', PARAM_INT);
            $mform->addRule('categoryid', null, 'required', null, 'client');
        }

        if ($type === taxonomy::TYPE_TAG) {
            $mform->addElement('text', 'slug', get_string('slug', 'local_imageblog'), ['size' => 60]);
            $mform->setType('slug', PARAM_ALPHANUMEXT);
            $mform->addHelpButton('slug', 'slug', 'local_imageblog');
        }

        if ($type === taxonomy::TYPE_LEVEL) {
            $colours = [];
            foreach (taxonomy::LEVEL_COLOURS as $key) {
                $colours[$key] = get_string('colour_' . $key, 'local_imageblog');
            }
            $mform->addElement(
                'select',
                'colourkey',
                get_string('colour', 'local_imageblog'),
                $colours
            );
            $mform->setType('colourkey', PARAM_ALPHA);
        }

        if (in_array($type, [taxonomy::TYPE_CATEGORY, taxonomy::TYPE_SUBCATEGORY, taxonomy::TYPE_LEVEL], true)) {
            $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_imageblog'), ['size' => 6]);
            $mform->setType('sortorder', PARAM_INT);
            $mform->setDefault('sortorder', 0);
        }

        $this->add_action_buttons();

        if ($record) {
            $this->set_data($record);
        }
    }
}
