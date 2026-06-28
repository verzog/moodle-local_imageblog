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
 * Unit tests for subscription.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog;

/**
 * Unit tests for digest subscription preferences.
 *
 * @covers \local_imageblog\subscription
 */
final class subscription_test extends \advanced_testcase {
    public function test_subscribe_then_update_frequency_keeps_one_row(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        subscription::subscribe((int)$user->id, subscription::FREQ_WEEKLY);
        subscription::subscribe((int)$user->id, subscription::FREQ_DAILY);

        $rows = $DB->get_records('local_imageblog_subs', ['userid' => $user->id]);
        $this->assertCount(1, $rows);
        $this->assertSame(subscription::FREQ_DAILY, reset($rows)->frequency);
    }

    public function test_subscribe_normalises_unknown_frequency_to_weekly(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$user->id, 'fortnightly');
        $row = subscription::get_for_user((int)$user->id);
        $this->assertSame(subscription::FREQ_WEEKLY, $row->frequency);
    }

    public function test_unsubscribe_removes_the_row(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$user->id, subscription::FREQ_WEEKLY);
        subscription::unsubscribe((int)$user->id);

        $this->assertSame(0, $DB->count_records('local_imageblog_subs', ['userid' => $user->id]));
        $this->assertNull(subscription::get_for_user((int)$user->id));
    }

    public function test_mark_sent_updates_lastsent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$user->id, subscription::FREQ_WEEKLY);
        $sub = subscription::get_for_user((int)$user->id);

        subscription::mark_sent((int)$sub->id, 1700000000);
        $reloaded = subscription::get_for_user((int)$user->id);
        $this->assertSame(1700000000, (int)$reloaded->lastsent);
    }

    public function test_frequencies_offered_to_users(): void {
        $offered = subscription::frequencies();
        $this->assertContains(subscription::FREQ_IMMEDIATE, $offered);
        $this->assertContains(subscription::FREQ_DAILY, $offered);
        $this->assertContains(subscription::FREQ_WEEKLY, $offered);
        // Monthly is retired from the UI.
        $this->assertNotContains(subscription::FREQ_MONTHLY, $offered);
    }

    public function test_get_due_subscribers_fires_only_at_configured_hour(): void {
        $this->resetAfterTest();

        // Pin a Monday 08:00 + matching config.
        $now = strtotime('2024-01-08 08:00:00');
        set_config('digest_hour', 8, 'local_imageblog');
        set_config('digest_weekday', 1, 'local_imageblog');

        $daily = $this->getDataGenerator()->create_user();
        $weekly = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$daily->id, subscription::FREQ_DAILY);
        subscription::subscribe((int)$weekly->id, subscription::FREQ_WEEKLY);

        $ids = array_map(fn($r) => (int)$r->userid, subscription::get_due_subscribers($now));
        sort($ids);
        $expected = [(int)$daily->id, (int)$weekly->id];
        sort($expected);
        $this->assertSame($expected, $ids);

        // Outside the hour window — no one is due.
        $offwindow = strtotime('2024-01-08 09:00:00');
        $this->assertSame([], subscription::get_due_subscribers($offwindow));
    }

    public function test_get_due_subscribers_skips_already_sent_this_hour(): void {
        $this->resetAfterTest();
        $now = strtotime('2024-01-08 08:00:00');
        set_config('digest_hour', 8, 'local_imageblog');
        set_config('digest_weekday', 1, 'local_imageblog');

        $u = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$u->id, subscription::FREQ_DAILY);
        $sub = subscription::get_for_user((int)$u->id);
        subscription::mark_sent((int)$sub->id, $now);

        $this->assertSame([], subscription::get_due_subscribers($now));
    }

    public function test_get_due_subscribers_skips_suspended_and_deleted_users(): void {
        global $DB;
        $this->resetAfterTest();
        // Pin the hour so the daily window matches.
        $now = strtotime('2024-01-08 08:00:00');
        set_config('digest_hour', 8, 'local_imageblog');
        set_config('digest_weekday', 1, 'local_imageblog');

        $suspended = $this->getDataGenerator()->create_user();
        $deleted   = $this->getDataGenerator()->create_user();
        $emailstop = $this->getDataGenerator()->create_user();
        $ok        = $this->getDataGenerator()->create_user();

        foreach ([$suspended, $deleted, $emailstop, $ok] as $u) {
            subscription::subscribe((int)$u->id, subscription::FREQ_DAILY);
        }

        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        $DB->set_field('user', 'emailstop', 1, ['id' => $emailstop->id]);

        $rows = subscription::get_due_subscribers($now);
        $ids  = array_map(fn($r) => (int)$r->userid, $rows);
        $this->assertContains((int)$ok->id, $ids);
        $this->assertNotContains((int)$suspended->id, $ids);
        $this->assertNotContains((int)$deleted->id, $ids);
        $this->assertNotContains((int)$emailstop->id, $ids);
    }

    public function test_interval_seconds_window_per_frequency(): void {
        $this->assertSame(DAYSECS, subscription::interval_seconds(subscription::FREQ_DAILY));
        $this->assertSame(WEEKSECS, subscription::interval_seconds(subscription::FREQ_WEEKLY));
        // Unknown / immediate falls back to a daily window rather than fataling.
        $this->assertSame(DAYSECS, subscription::interval_seconds(subscription::FREQ_IMMEDIATE));
    }

    public function test_digest_task_processes_new_subscriber_without_error(): void {
        $this->resetAfterTest();
        set_config('subscriptions_enabled', 1, 'local_imageblog');
        // Match the daily window to the current hour so the subscriber is due
        // when the task reads the real clock inside execute().
        set_config('digest_hour', (int)date('G'), 'local_imageblog');

        $user = $this->getDataGenerator()->create_user();
        subscription::subscribe((int)$user->id, subscription::FREQ_DAILY);
        $sub = subscription::get_for_user((int)$user->id);
        // A brand-new subscriber has a null lastsent. That is the branch that
        // used to call the undefined subscription::interval_seconds() and
        // fatal — it runs whether or not any posts are due, so no post is
        // needed to exercise it.
        $this->assertNull($sub->lastsent);

        $task = new \local_imageblog\task\send_subscription_digest();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Reaching the end of execute() proves interval_seconds() resolves;
        // lastsent is now stamped for the subscriber.
        $reloaded = subscription::get_for_user((int)$user->id);
        $this->assertNotNull($reloaded->lastsent);
    }
}
