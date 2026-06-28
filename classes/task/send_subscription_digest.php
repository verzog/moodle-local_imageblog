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
 * Scheduled task: send subscription digest emails.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
