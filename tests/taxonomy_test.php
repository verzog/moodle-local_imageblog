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
 * Unit tests for the taxonomy CRUD helpers.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog;

/**
 * Unit tests for taxonomy CRUD operations.
 *
 * @covers \local_imageblog\taxonomy
 */
final class taxonomy_test extends \advanced_testcase {
    /**
     * Wipe seeded taxonomy rows so each test starts from an empty slate.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('local_imageblog_post_cats');
        $DB->delete_records('local_imageblog_post_tags');
        $DB->delete_records('local_imageblog_post_levels');
        $DB->delete_records('local_imageblog_subcategories');
        $DB->delete_records('local_imageblog_categories');
        $DB->delete_records('local_imageblog_tags');
        $DB->delete_records('local_imageblog_levels');
    }

    public function test_save_and_fetch_category(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'News', 'sortorder' => 5]);
        $this->assertGreaterThan(0, $id);

        $row = taxonomy::get(taxonomy::TYPE_CATEGORY, $id);
        $this->assertSame('News', $row->name);
        $this->assertSame(5, (int)$row->sortorder);

        $all = taxonomy::all(taxonomy::TYPE_CATEGORY);
        $this->assertCount(1, $all);
    }

    public function test_save_tag_generates_unique_slug(): void {
        $this->resetAfterTest();
        $id1 = taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => 'Hello World']);
        $id2 = taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => 'Hello World']);

        $row1 = taxonomy::get(taxonomy::TYPE_TAG, $id1);
        $row2 = taxonomy::get(taxonomy::TYPE_TAG, $id2);
        $this->assertSame('hello-world', $row1->slug);
        $this->assertSame('hello-world-2', $row2->slug);
    }

    public function test_save_level_rejects_invalid_colour(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_LEVEL, (object)[
            'name'      => 'Beginner',
            'colourkey' => 'rainbow',
        ]);
        $row = taxonomy::get(taxonomy::TYPE_LEVEL, $id);
        $this->assertSame('amber', $row->colourkey);
    }

    public function test_save_subcategory_requires_parent(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        taxonomy::save(taxonomy::TYPE_SUBCATEGORY, (object)['name' => 'Orphan']);
    }

    public function test_save_rejects_empty_name(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => '   ']);
    }

    public function test_delete_category_cascades(): void {
        global $DB;
        $this->resetAfterTest();

        $catid = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'Tutorial']);
        $subid = taxonomy::save(taxonomy::TYPE_SUBCATEGORY, (object)[
            'name'       => 'Dermoscopy',
            'categoryid' => $catid,
        ]);

        // Manually associate a fake post so we can verify the cascade.
        $postid = $DB->insert_record('local_imageblog_post_cats', (object)[
            'postid'        => 1,
            'categoryid'    => $catid,
            'subcategoryid' => $subid,
        ]);
        $this->assertTrue($DB->record_exists('local_imageblog_post_cats', ['id' => $postid]));

        taxonomy::delete(taxonomy::TYPE_CATEGORY, $catid);

        $this->assertNull(taxonomy::get(taxonomy::TYPE_CATEGORY, $catid));
        $this->assertNull(taxonomy::get(taxonomy::TYPE_SUBCATEGORY, $subid));
        $this->assertFalse($DB->record_exists('local_imageblog_post_cats', ['categoryid' => $catid]));
    }

    public function test_update_existing_row(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'Original']);
        taxonomy::save(taxonomy::TYPE_CATEGORY, (object)[
            'id'        => $id,
            'name'      => 'Renamed',
            'sortorder' => 9,
        ]);
        $row = taxonomy::get(taxonomy::TYPE_CATEGORY, $id);
        $this->assertSame('Renamed', $row->name);
        $this->assertSame(9, (int)$row->sortorder);
    }
}
