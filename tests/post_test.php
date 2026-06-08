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
 * Unit tests for the post model.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for the post model.
 *
 * @covers \local_imageblog\post
 */
final class post_test extends \advanced_testcase {
    /**
     * Grant the publish/create capabilities to the authenticated user role so
     * the test fixtures (which use plain users, not managers) can save posts
     * in any status.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $userrole = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/imageblog:createpost', CAP_ALLOW, $userrole, $syscontext->id, true);
        assign_capability('local/imageblog:publishpost', CAP_ALLOW, $userrole, $syscontext->id, true);
    }

    /**
     * Saving a new draft post creates a record owned by the current user.
     */
    public function test_save_creates_post_for_current_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = \context_system::instance();
        $data = (object)[
            'id'          => 0,
            'title'       => 'Hello',
            'summary'     => 'A summary',
            'status'      => post::STATUS_DRAFT,
            'lazyimages'  => 1,
        ];

        $postid = post::save($data, $context);
        $loaded = post::get($postid);

        $this->assertNotNull($loaded);
        $this->assertSame('Hello', $loaded->title);
        $this->assertSame((int)$user->id, $loaded->authorid);
        $this->assertSame(post::STATUS_DRAFT, $loaded->status);
        $this->assertNull($loaded->timepublished);
    }

    /**
     * Publishing a post sets timepublished, and re-saving keeps the original.
     */
    public function test_publishing_sets_timepublished_once(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $context = \context_system::instance();
        $postid = post::save((object)[
            'title'  => 'Draft',
            'status' => post::STATUS_DRAFT,
        ], $context);

        $published = post::save((object)[
            'id'     => $postid,
            'title'  => 'Published',
            'status' => post::STATUS_PUBLISHED,
        ], $context);

        $first = post::get($published);
        $this->assertNotNull($first->timepublished);
        $original = $first->timepublished;

        $resaved = post::save((object)[
            'id'     => $published,
            'title'  => 'Edited',
            'status' => post::STATUS_PUBLISHED,
        ], $context);

        $second = post::get($resaved);
        $this->assertSame($original, $second->timepublished);
    }

    /**
     * get_published filters by author, category and tag.
     */
    public function test_get_published_filters(): void {
        global $DB;
        $this->resetAfterTest();

        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $this->setUser($alice);
        $postalice = post::save((object)[
            'title'  => 'Alice published',
            'status' => post::STATUS_PUBLISHED,
        ], $context);

        $this->setUser($bob);
        post::save((object)[
            'title'  => 'Bob draft',
            'status' => post::STATUS_DRAFT,
        ], $context);
        $postbob = post::save((object)[
            'title'  => 'Bob published',
            'status' => post::STATUS_PUBLISHED,
        ], $context);

        $catid = $DB->insert_record('local_imageblog_categories', (object)[
            'name' => 'News', 'sortorder' => 0, 'timecreated' => time(),
        ]);
        $tagid = $DB->insert_record('local_imageblog_tags', (object)[
            'name' => 'test-feature', 'slug' => 'test-feature',
        ]);
        post::set_taxonomy($postalice, $catid, null, [$tagid], []);

        $all = post::get_published();
        $this->assertSame(2, $all['total']);

        $byauthor = post::get_published(['authorid' => $alice->id]);
        $this->assertSame(1, $byauthor['total']);
        $this->assertSame($postalice, $byauthor['posts'][0]->id);

        $bycat = post::get_published(['categoryid' => $catid]);
        $this->assertSame(1, $bycat['total']);

        $bytag = post::get_published(['tagid' => $tagid]);
        $this->assertSame(1, $bytag['total']);

        $bykeyword = post::get_published(['keyword' => 'bob']);
        $this->assertSame(1, $bykeyword['total']);
        $this->assertSame($postbob, $bykeyword['posts'][0]->id);

        // Keyword should also match tag names — alice's post has the
        // 'test-feature' tag so searching by 'feature' should find it.
        $bykeywordtag = post::get_published(['keyword' => 'feature']);
        $this->assertSame(1, $bykeywordtag['total']);
        $this->assertSame($postalice, $bykeywordtag['posts'][0]->id);
    }

    /**
     * Subcategory, level and date-range filters narrow results correctly.
     */
    public function test_get_published_subcategory_level_and_date_filters(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_system::instance();

        $now = time();
        $catid = $DB->insert_record('local_imageblog_categories', (object)[
            'name' => 'Tutorial', 'sortorder' => 0, 'timecreated' => $now,
        ]);
        $sub1 = $DB->insert_record('local_imageblog_subcategories', (object)[
            'name' => 'Dermoscopy', 'categoryid' => $catid, 'sortorder' => 0,
        ]);
        $sub2 = $DB->insert_record('local_imageblog_subcategories', (object)[
            'name' => 'Procedures', 'categoryid' => $catid, 'sortorder' => 1,
        ]);
        $level = $DB->insert_record('local_imageblog_levels', (object)[
            'name' => 'Beginner', 'colourkey' => 'teal', 'sortorder' => 0,
        ]);

        $postderm = post::save((object)[
            'title'  => 'Dermoscopy basics',
            'status' => post::STATUS_PUBLISHED,
        ], $context);
        post::set_taxonomy($postderm, $catid, $sub1, [], [$level]);

        $postproc = post::save((object)[
            'title'  => 'Cryotherapy procedure',
            'status' => post::STATUS_PUBLISHED,
        ], $context);
        post::set_taxonomy($postproc, $catid, $sub2, [], []);

        // Backdate the dermoscopy post to last month.
        $lastmonth = $now - (35 * DAYSECS);
        $DB->set_field('local_imageblog_posts', 'timepublished', $lastmonth, ['id' => $postderm]);

        $bysubcat = post::get_published(['subcategoryid' => $sub1]);
        $this->assertSame(1, $bysubcat['total']);
        $this->assertSame($postderm, $bysubcat['posts'][0]->id);

        $bylevel = post::get_published(['levelid' => $level]);
        $this->assertSame(1, $bylevel['total']);
        $this->assertSame($postderm, $bylevel['posts'][0]->id);

        $recent = post::get_published(['datefrom' => $now - DAYSECS]);
        $this->assertSame(1, $recent['total']);
        $this->assertSame($postproc, $recent['posts'][0]->id);

        $old = post::get_published(['dateto' => $now - DAYSECS]);
        $this->assertSame(1, $old['total']);
        $this->assertSame($postderm, $old['posts'][0]->id);
    }

    /**
     * Status + scope filter narrows to drafts, optionally limited to the viewer's own posts.
     */
    public function test_get_published_status_and_mineonly_filters(): void {
        $this->resetAfterTest();

        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $this->setUser($alice);
        $alicedraft = post::save((object)[
            'title' => 'Alice draft', 'status' => post::STATUS_DRAFT,
        ], $context);
        post::save((object)[
            'title' => 'Alice published', 'status' => post::STATUS_PUBLISHED,
        ], $context);

        $this->setUser($bob);
        $bobdraft = post::save((object)[
            'title' => 'Bob draft', 'status' => post::STATUS_DRAFT,
        ], $context);

        // Default behaviour unchanged: only published.
        $this->assertSame(1, post::get_published()['total']);

        // Manager view of all drafts: both authors' drafts visible.
        $alldrafts = post::get_published(['statuses' => [post::STATUS_DRAFT]]);
        $this->assertSame(2, $alldrafts['total']);

        // Author scope: alice asks for drafts and is restricted to her own.
        $minedrafts = post::get_published([
            'statuses' => [post::STATUS_DRAFT],
            'mineonly' => true,
            'viewerid' => $alice->id,
        ]);
        $this->assertSame(1, $minedrafts['total']);
        $this->assertSame($alicedraft, $minedrafts['posts'][0]->id);

        // Bob's own scope returns his draft only.
        $bobscope = post::get_published([
            'statuses' => [post::STATUS_DRAFT],
            'mineonly' => true,
            'viewerid' => $bob->id,
        ]);
        $this->assertSame(1, $bobscope['total']);
        $this->assertSame($bobdraft, $bobscope['posts'][0]->id);
    }

    /**
     * set_taxonomy replaces existing associations.
     */
    public function test_set_taxonomy_replaces_associations(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $context = \context_system::instance();
        $postid = post::save((object)[
            'title'  => 'Tagged',
            'status' => post::STATUS_DRAFT,
        ], $context);

        $tag1 = $DB->insert_record('local_imageblog_tags', (object)['name' => 't1', 'slug' => 't1']);
        $tag2 = $DB->insert_record('local_imageblog_tags', (object)['name' => 't2', 'slug' => 't2']);

        post::set_taxonomy($postid, null, null, [$tag1, $tag2], []);
        $loaded = post::get($postid);
        $this->assertEqualsCanonicalizing([$tag1, $tag2], $loaded->get_tag_ids());

        post::set_taxonomy($postid, null, null, [$tag2], []);
        $this->assertSame([$tag2], $loaded->get_tag_ids());
    }
}
