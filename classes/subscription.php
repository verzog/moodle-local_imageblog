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
 * Subscriber preferences for blog post digest emails.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Static helpers for managing per-user subscription preferences.
 */
class subscription {
    /** @var string Send one email per post, on publish. */
    const FREQ_IMMEDIATE = 'immediate';
    /** @var string Daily digest. */
    const FREQ_DAILY = 'daily';
    /** @var string Weekly digest. */
    const FREQ_WEEKLY = 'weekly';
    /** @var string Legacy monthly value kept for back-compat; migrated to weekly. */
    const FREQ_MONTHLY = 'monthly';

    /**
     * Frequency choices offered to users in the subscribe form.
     *
     * @return string[]
     */
    public static function frequencies(): array {
        return [self::FREQ_IMMEDIATE, self::FREQ_DAILY, self::FREQ_WEEKLY];
    }

    /**
     * Configured hour of the day (0–23) when daily and weekly digests fire.
     *
     * @return int
     */
    public static function digest_hour(): int {
        return max(0, min(23, (int)get_config('local_imageblog', 'digest_hour')));
    }

    /**
     * Configured day of the week (1=Mon … 7=Sun) the weekly digest fires.
     *
     * @return int
     */
    public static function digest_weekday(): int {
        $day = (int)get_config('local_imageblog', 'digest_weekday');
        return ($day >= 1 && $day <= 7) ? $day : 1;
    }

    /**
     * Fetch the subscription row for a user, or null if not subscribed.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_for_user(int $userid): ?\stdClass {
        global $DB;
        $r = $DB->get_record('local_imageblog_subs', ['userid' => $userid]);
        return $r ?: null;
    }

    /**
     * Subscribe (or update the frequency for) a user.
     *
     * @param int    $userid
     * @param string $frequency
     */
    public static function subscribe(int $userid, string $frequency): void {
        global $DB;
        if (!in_array($frequency, self::frequencies(), true)) {
            $frequency = self::FREQ_WEEKLY;
        }
        $now = time();
        $existing = self::get_for_user($userid);
        if ($existing) {
            $existing->frequency    = $frequency;
            $existing->timemodified = $now;
            $DB->update_record('local_imageblog_subs', $existing);
            return;
        }
        $record = (object)[
            'userid'       => $userid,
            'frequency'    => $frequency,
            'lastsent'     => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_imageblog_subs', $record);
    }

    /**
     * Remove a user's subscription.
     *
     * @param int $userid
     */
    public static function unsubscribe(int $userid): void {
        global $DB;
        $DB->delete_records('local_imageblog_subs', ['userid' => $userid]);
    }

    /**
     * Update the lastsent timestamp for a subscription row.
     *
     * @param int $subid
     * @param int $when
     */
    public static function mark_sent(int $subid, int $when): void {
        global $DB;
        $DB->update_record('local_imageblog_subs', (object)[
            'id'           => $subid,
            'lastsent'     => $when,
            'timemodified' => $when,
        ]);
    }

    /**
     * Daily and weekly subscribers due for a digest at the given time.
     *
     * Immediate subscribers are handled separately by
     * notify_immediate_subscribers() at publish time.
     *
     * Daily rows are due when the local hour matches digest_hour and the
     * row has not been sent today. Weekly rows are due when the local
     * weekday and hour both match the admin settings and the row has
     * not been sent in the past day.
     *
     * @param int $now Current timestamp.
     * @return \stdClass[] Rows joined with user fields.
     */
    public static function get_due_subscribers(int $now): array {
        global $DB;

        $hour = self::digest_hour();
        $weekday = self::digest_weekday();
        $nowhour = (int)date('G', $now);
        // PHP's date('N') is 1=Mon … 7=Sun, matching our digest_weekday.
        $nowweekday = (int)date('N', $now);

        $isdailywindow = ($nowhour === $hour);
        $isweeklywindow = ($nowweekday === $weekday && $nowhour === $hour);
        // Reset to start-of-current-hour so a per-hour gate is exact.
        $hourstart = strtotime(date('Y-m-d H:00:00', $now));

        $sql = "SELECT s.id AS subid, s.userid, s.frequency, s.lastsent,
                       u.id AS uid, u.email, u.firstname, u.lastname, u.mailformat,
                       u.auth, u.suspended, u.deleted, u.emailstop
                  FROM {local_imageblog_subs} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE u.deleted = 0 AND u.suspended = 0 AND u.emailstop = 0
                   AND u.confirmed = 1
                   AND s.frequency IN (:fdaily, :fweekly)
                   AND (s.lastsent IS NULL OR s.lastsent < :hourstart)";
        $rows = $DB->get_records_sql($sql, [
            'fdaily'    => self::FREQ_DAILY,
            'fweekly'   => self::FREQ_WEEKLY,
            'hourstart' => $hourstart,
        ]);

        $due = [];
        foreach ($rows as $row) {
            if ($row->frequency === self::FREQ_DAILY && $isdailywindow) {
                $due[] = $row;
            } else if ($row->frequency === self::FREQ_WEEKLY && $isweeklywindow) {
                $due[] = $row;
            }
        }
        return $due;
    }

    /**
     * Send an "as soon as published" notification to every immediate
     * subscriber. Called from post::set_status / post::save when a post
     * transitions to published.
     *
     * @param int $postid
     * @return int Number of emails sent.
     */
    public static function notify_immediate_subscribers(int $postid): int {
        global $DB, $PAGE;

        if (!get_config('local_imageblog', 'subscriptions_enabled')) {
            return 0;
        }
        $post = post::get($postid);
        if (!$post || $post->status !== post::STATUS_PUBLISHED) {
            return 0;
        }

        $sql = "SELECT s.id AS subid, s.userid,
                       u.id AS uid, u.email, u.firstname, u.lastname, u.mailformat,
                       u.suspended, u.deleted, u.emailstop, u.confirmed
                  FROM {local_imageblog_subs} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.frequency = :freq
                   AND u.deleted = 0 AND u.suspended = 0
                   AND u.emailstop = 0 AND u.confirmed = 1";
        $rows = $DB->get_records_sql($sql, ['freq' => self::FREQ_IMMEDIATE]);
        if (!$rows) {
            return 0;
        }

        $renderer = $PAGE->get_renderer('local_imageblog');
        $sender   = \core_user::get_noreply_user();
        $sent = 0;
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], '*', IGNORE_MISSING);
            if (!$user) {
                continue;
            }
            [$html, $text, $subject, $inlineimages] = $renderer->render_digest_email(
                [$post],
                $user,
                self::FREQ_IMMEDIATE
            );
            mailer::send_html_with_inline_images($user, $sender, $subject, $text, $html, $inlineimages);
            self::mark_sent((int)$row->subid, time());
            $sent++;
        }
        return $sent;
    }
}
