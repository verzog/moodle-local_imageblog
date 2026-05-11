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
 * Subscription preference form.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\form;

use local_imageblog\subscription;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Lets the current user opt in to (and choose the frequency of) the digest.
 */
class subscription_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'subscribed',
            get_string('subscribe_optin', 'local_imageblog')
        );
        $mform->addHelpButton('subscribed', 'subscribe_optin', 'local_imageblog');

        $options = [];
        foreach (subscription::frequencies() as $f) {
            $options[$f] = get_string('frequency_' . $f, 'local_imageblog');
        }
        $mform->addElement(
            'select',
            'frequency',
            get_string('frequency', 'local_imageblog'),
            $options
        );
        $mform->setType('frequency', PARAM_ALPHA);
        $mform->setDefault('frequency', subscription::FREQ_WEEKLY);
        $mform->hideIf('frequency', 'subscribed', 'notchecked');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
