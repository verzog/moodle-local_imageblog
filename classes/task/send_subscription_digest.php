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
 * Scheduled task: send subscription digest emails.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\task;

use core\task\scheduled_task;
use local_imageblog\mailer;
use local_imageblog\post;
use local_imageblog\subscription;
use moodle_url;

/**
 * Iterates due subscribers and emails them a digest of recent posts.
 */
class send_subscription_digest extends scheduled_task {
    /**
     * Task display name shown in admin > scheduled tasks.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_digest', 'local_imageblog');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        global $DB, $PAGE;

        if (!get_config('local_imageblog', 'subscriptions_enabled')) {
            mtrace('local_imageblog: subscriptions disabled, skipping.');
            return;
        }

        $now = time();
        $due = subscription::get_due_subscribers($now);
        if (!$due) {
            mtrace('local_imageblog: no subscribers due.');
            return;
        }

        $renderer = $PAGE->get_renderer('local_imageblog');
        $sender   = \core_user::get_noreply_user();
        $sent     = 0;

        foreach ($due as $row) {
            $since = !empty($row->lastsent)
                ? (int)$row->lastsent
                : ($now - subscription::interval_seconds($row->frequency));
            $posts = self::posts_since($since);
            if (!$posts) {
                // No new posts; still bump lastsent so we don't re-check next run.
                subscription::mark_sent((int)$row->subid, $now);
                continue;
            }
            $user = $DB->get_record('user', ['id' => $row->userid], '*', IGNORE_MISSING);
            if (!$user) {
                continue;
            }
            [$html, $text, $subject, $inlineimages] = $renderer->render_digest_email(
                $posts,
                $user,
                $row->frequency
            );
            mailer::send_html_with_inline_images($user, $sender, $subject, $text, $html, $inlineimages);
            subscription::mark_sent((int)$row->subid, $now);
            $sent++;
        }

        mtrace("local_imageblog: sent {$sent} digest email(s).");
    }

    /**
     * Posts published strictly after the given timestamp, newest first.
     *
     * @param int $since
     * @return post[]
     */
    private static function posts_since(int $since): array {
        global $DB;
        $sql = "SELECT id
                  FROM {local_imageblog_posts}
                 WHERE status = :status
                   AND timepublished IS NOT NULL
                   AND timepublished > :since
              ORDER BY timepublished DESC";
        $ids = $DB->get_fieldset_sql($sql, [
            'status' => post::STATUS_PUBLISHED,
            'since'  => $since,
        ]);
        $out = [];
        foreach ($ids as $id) {
            $p = post::get((int)$id);
            if ($p) {
                $out[] = $p;
            }
        }
        return $out;
    }
}
