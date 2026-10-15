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
 * Adhoc task: award CPD hours for a revealed case.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\task;

use core\task\adhoc_task;
use local_imageblog\case_post;
use local_imageblog\evaluation;
use local_imageblog\post;
use local_imageblog\post_type;

/**
 * Awards participation CPD to everyone who took part in a revealed interactive
 * post — diagnosing a case or evaluating a post — off the request thread so the
 * reveal action returns promptly even for popular posts.
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
        $postid = (int)$data->postid;
        $p = post::get($postid);
        if (!$p) {
            return;
        }
        if (post_type::is_evaluate($p->posttype)) {
            evaluation::award_cpd_for_eval($postid);
        } else {
            case_post::award_cpd_for_case($postid);
        }
    }
}
