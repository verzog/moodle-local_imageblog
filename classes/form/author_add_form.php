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
 * Add a user to the blog-author role.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
