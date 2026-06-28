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
 * Subscription preference form.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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

        $options = ['none' => get_string('frequency_none', 'local_imageblog')];
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
        $mform->setDefault('frequency', 'none');
        $mform->addHelpButton('frequency', 'frequency', 'local_imageblog');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
