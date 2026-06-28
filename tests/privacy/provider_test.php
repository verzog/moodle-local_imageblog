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
 * Privacy provider tests.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_imageblog\post;

/**
 * Privacy provider tests for local_imageblog.
 *
 * @covers \local_imageblog\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a published post owned by the given user.
     *
     * @param \stdClass $user
     * @param string    $title
     * @return int Post id
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
