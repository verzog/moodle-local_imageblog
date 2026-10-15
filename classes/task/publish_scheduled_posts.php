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
 * Scheduled task: flip scheduled posts to published when their time arrives,
 * and auto-reveal diagnose-type cases once their reveal window has elapsed.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\task;

use core\task\scheduled_task;
use local_imageblog\case_post;
use local_imageblog\post;
use local_imageblog\post_type;

/**
 * Auto-publishes scheduled posts and auto-reveals due case outcomes.
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
        $now = time();
        $this->publish_due_posts($now);
        $this->reveal_due_cases($now);
    }

    /**
     * Flip scheduled posts to published when their time arrives.
     *
     * @param int $now
     */
    private function publish_due_posts(int $now): void {
        global $DB;

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

    /**
     * Reveal any published, not-yet-revealed diagnose-type case whose reveal
     * time has passed. The reveal time is the author-set caserevealat when
     * present, otherwise the publish date plus the site default reveal window
     * (default_reveal_days); a window of zero disables the automatic default.
     *
     * @param int $now
     */
    private function reveal_due_cases(int $now): void {
        global $DB;

        [$typesql, $typeparams] = $DB->get_in_or_equal(
            post_type::diagnose_types(),
            SQL_PARAMS_NAMED,
            'pt'
        );
        $params = $typeparams + ['status' => post::STATUS_PUBLISHED];
        $candidates = $DB->get_records_select(
            'local_imageblog_posts',
            "posttype $typesql AND status = :status AND (caserevealed = 0 OR caserevealed IS NULL)",
            $params,
            '',
            'id, timepublished, caserevealat, caseoutcome'
        );
        if (!$candidates) {
            mtrace('local_imageblog: no case outcomes due to reveal.');
            return;
        }

        $defaultdays = (int)get_config('local_imageblog', 'default_reveal_days');
        $window = $defaultdays > 0 ? $defaultdays * DAYSECS : 0;

        $revealed = 0;
        foreach ($candidates as $c) {
            $revealat = null;
            if (!empty($c->caserevealat)) {
                $revealat = (int)$c->caserevealat;
            } else if ($window > 0 && !empty($c->timepublished)) {
                $revealat = (int)$c->timepublished + $window;
            }
            if ($revealat !== null && $revealat <= $now) {
                // Never auto-reveal a case whose outcome has not been written:
                // the outcome editor is optional, and revealing an empty answer
                // would irreversibly close submissions and mislead readers. The
                // author can reveal manually, or the sweep retries once an
                // outcome exists.
                if (trim((string)$c->caseoutcome) === '') {
                    continue;
                }
                case_post::reveal((int)$c->id);
                $revealed++;
            }
        }
        mtrace('local_imageblog: auto-revealed ' . $revealed . ' case outcome(s).');
    }
}
