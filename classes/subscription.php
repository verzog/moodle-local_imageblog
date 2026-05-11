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
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Static helpers for managing per-user subscription preferences.
 */
class subscription {
    /** @var string Daily digest. */
    const FREQ_DAILY = 'daily';
    /** @var string Weekly digest. */
    const FREQ_WEEKLY = 'weekly';
    /** @var string Monthly digest. */
    const FREQ_MONTHLY = 'monthly';

    /**
     * Valid frequency choices.
     *
     * @return string[]
     */
    public static function frequencies(): array {
        return [self::FREQ_DAILY, self::FREQ_WEEKLY, self::FREQ_MONTHLY];
    }

    /**
     * Number of seconds between digests at the given frequency.
     *
     * @param string $frequency
     * @return int
     */
    public static function interval_seconds(string $frequency): int {
        switch ($frequency) {
            case self::FREQ_DAILY:
                return DAYSECS;
            case self::FREQ_MONTHLY:
                return DAYSECS * 30;
            case self::FREQ_WEEKLY:
            default:
                return WEEKSECS;
        }
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
     * All subscribers due for a digest at the given time.
     *
     * "Due" means: lastsent is null (never sent) OR lastsent + interval <= $now.
     *
     * @param int $now Current timestamp.
     * @return \stdClass[] Rows joined with user fields (id, email, firstname, lastname, mailformat).
     */
    public static function get_due_subscribers(int $now): array {
        global $DB;

        $sql = "SELECT s.id AS subid, s.userid, s.frequency, s.lastsent,
                       u.id AS uid, u.email, u.firstname, u.lastname, u.mailformat,
                       u.auth, u.suspended, u.deleted, u.emailstop
                  FROM {local_imageblog_subs} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE u.deleted = 0 AND u.suspended = 0 AND u.emailstop = 0";
        $rows = $DB->get_records_sql($sql);

        $due = [];
        foreach ($rows as $row) {
            $interval = self::interval_seconds($row->frequency);
            if (empty($row->lastsent) || ((int)$row->lastsent + $interval) <= $now) {
                $due[] = $row;
            }
        }
        return $due;
    }
}
