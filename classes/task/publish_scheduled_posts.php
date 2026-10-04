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
 * Scheduled task: flip scheduled posts to published when their time arrives.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\task;

use core\task\scheduled_task;
use local_imageblog\post;

/**
 * Auto-publishes posts whose timescheduled has passed.
 */
class publish_scheduled_posts extends scheduled_task {
    /**
     * Task name shown in admin > scheduled tasks.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_publish_scheduled', 'local_imageblog');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $due = $DB->get_fieldset_select(
            'local_imageblog_posts',
            'id',
            'status = :status AND timescheduled IS NOT NULL AND timescheduled <= :now',
            ['status' => post::STATUS_SCHEDULED, 'now' => $now]
        );
        if (!$due) {
            mtrace('local_imageblog: no scheduled posts due.');
            return;
        }
        foreach ($due as $postid) {
            post::set_status((int)$postid, post::STATUS_PUBLISHED);
        }
        mtrace('local_imageblog: published ' . count($due) . ' scheduled post(s).');
    }
}
