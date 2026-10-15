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
 * Create/edit form for a rubric template.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\form;

use local_imageblog\rubric;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit a rubric template (the template header only).
 */
class rubric_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition(): void {
        $mform = $this->_form;
        $record = $this->_customdata['record'] ?? null;

        $mform->addElement('hidden', 'id', $record->id ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $scale = [];
        for ($i = rubric::MIN_MAXSCORE; $i <= rubric::MAX_MAXSCORE; $i++) {
            $scale[$i] = (string)$i;
        }
        $mform->addElement('select', 'maxscore', get_string('rubric_maxscore', 'local_imageblog'), $scale);
        $mform->setType('maxscore', PARAM_INT);
        $mform->setDefault('maxscore', 5);
        $mform->addHelpButton('maxscore', 'rubric_maxscore', 'local_imageblog');

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_imageblog'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();

        if ($record) {
            $this->set_data($record);
        }
    }
}
