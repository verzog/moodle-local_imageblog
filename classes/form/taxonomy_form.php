<?php
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
 * Polymorphic create/edit form for any taxonomy type.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
