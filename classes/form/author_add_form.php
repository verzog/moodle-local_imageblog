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
 * Add a user to the blog-author role.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Single-field form for picking a user to promote to a blog author.
 */
class author_add_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $options = [
            'ajax'              => 'core_user/form_user_selector',
            'multiple'          => false,
            'noselectionstring' => get_string('author_add_placeholder', 'local_imageblog'),
            'valuehtmlcallback' => function ($userid) {
                global $DB, $OUTPUT;
                $namefields = implode(', ', \core_user\fields::get_name_fields());
                $user = $DB->get_record('user', ['id' => $userid], 'id, ' . $namefields);
                return $user ? fullname($user) : '';
            },
        ];

        $mform->addElement(
            'autocomplete',
            'userid',
            get_string('author_add', 'local_imageblog'),
            [],
            $options
        );
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('add'));
    }
}
