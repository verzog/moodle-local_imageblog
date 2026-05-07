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
 * Privacy provider tests.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_imageblog\post;

/**
 * @covers \local_imageblog\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Create a published post owned by the given user.
     */
    private function create_post_for(\stdClass $user, string $title = 'Test'): int {
        $this->setUser($user);
        return post::save((object)[
            'title'   => $title,
            'summary' => 'Summary',
            'status'  => post::STATUS_PUBLISHED,
        ], \context_system::instance());
    }

    public function test_get_metadata_describes_storage(): void {
        $collection = new \core_privacy\local\metadata\collection('local_imageblog');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    public function test_get_contexts_for_userid_returns_system_when_user_has_posts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_post_for($user);

        $contextlist = provider::get_contexts_for_userid((int)$user->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);
        $this->assertSame(CONTEXT_SYSTEM, (int)reset($contexts)->contextlevel);
    }

    public function test_get_users_in_context_lists_authors(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $this->create_post_for($alice);
        $this->create_post_for($bob);

        $context = \context_system::instance();
        $userlist = new userlist($context, 'local_imageblog');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [(int)$alice->id, (int)$bob->id];
        sort($expected);
        $this->assertSame($expected, $userids);
    }

    public function test_export_user_data_writes_post_record(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $postid = $this->create_post_for($user, 'My export');

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($user, 'local_imageblog', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([
            get_string('pluginname', 'local_imageblog'),
            'post-' . $postid,
        ]);
        $this->assertSame('My export', $data->title);
    }

    public function test_delete_data_for_user_removes_only_that_users_posts(): void {
        global $DB;
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $this->create_post_for($alice);
        $this->create_post_for($bob);

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($alice, 'local_imageblog', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('local_imageblog_posts', ['authorid' => $alice->id]));
        $this->assertSame(1, $DB->count_records('local_imageblog_posts', ['authorid' => $bob->id]));
    }

    public function test_delete_data_for_users_removes_listed_users(): void {
        global $DB;
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $this->create_post_for($alice);
        $this->create_post_for($bob);

        $context = \context_system::instance();
        $userlist = new approved_userlist($context, 'local_imageblog', [(int)$alice->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0, $DB->count_records('local_imageblog_posts', ['authorid' => $alice->id]));
        $this->assertSame(1, $DB->count_records('local_imageblog_posts', ['authorid' => $bob->id]));
    }

    public function test_delete_data_for_all_users_in_context_purges_table(): void {
        global $DB;
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $this->create_post_for($alice);
        $this->create_post_for($bob);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $DB->count_records('local_imageblog_posts'));
    }
}
