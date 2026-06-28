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
 * Adhoc task: award CPD hours for a revealed case.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog\task;

use core\task\adhoc_task;
use local_imageblog\case_post;

/**
 * Awards participation CPD to everyone who diagnosed a case, off the request
 * thread so the reveal action returns promptly even for popular cases.
 */
class award_case_cpd extends adhoc_task {
    /**
     * Task name shown in admin > tasks logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_award_cpd', 'local_imageblog');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data->postid)) {
            return;
        }
        case_post::award_cpd_for_case((int)$data->postid);
    }
}
