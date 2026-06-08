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
 * Unit tests for subscription.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
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

    public function test_interval_seconds_matches_frequency(): void {
        $this->assertSame(DAYSECS, subscription::interval_seconds(subscription::FREQ_DAILY));
        $this->assertSame(WEEKSECS, subscription::interval_seconds(subscription::FREQ_WEEKLY));
        $this->assertSame(DAYSECS * 30, subscription::interval_seconds(subscription::FREQ_MONTHLY));
        // Unknown values fall back to weekly.
        $this->assertSame(WEEKSECS, subscription::interval_seconds('whatever'));
    }

    public function test_get_due_subscribers_includes_never_sent_and_overdue(): void {
        $this->resetAfterTest();
        $now = 1700000000;
        $never  = $this->getDataGenerator()->create_user();
        $stale  = $this->getDataGenerator()->create_user();
        $recent = $this->getDataGenerator()->create_user();

        subscription::subscribe((int)$never->id, subscription::FREQ_DAILY);

        subscription::subscribe((int)$stale->id, subscription::FREQ_DAILY);
        $stalesub = subscription::get_for_user((int)$stale->id);
        subscription::mark_sent((int)$stalesub->id, $now - (DAYSECS * 2));

        subscription::subscribe((int)$recent->id, subscription::FREQ_DAILY);
        $recentsub = subscription::get_for_user((int)$recent->id);
        subscription::mark_sent((int)$recentsub->id, $now - 60);

        $rows = subscription::get_due_subscribers($now);
        $ids  = array_map(fn($r) => (int)$r->userid, $rows);
        sort($ids);
        $expected = [(int)$never->id, (int)$stale->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_get_due_subscribers_skips_suspended_and_deleted_users(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 1700000000;
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
}
